<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserAccountController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manageGlobalUserAccounts');

        $query = User::query()
            ->with('university')
            ->where('role', '!=', 'student')
            ->orderByDesc('id');

        $role = $request->string('role')->toString();
        if ($role !== '' && in_array($role, ['admin', 'coordinator', 'examiner'], true)) {
            $query->where('role', $role);
        }

        $search = trim($request->string('q')->toString());
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('index_number', 'like', $like);
            });
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roleFilter' => $role,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manageGlobalUserAccounts');

        $universities = University::query()->orderBy('name')->get();
        $defaultUniversityId = (int) (auth()->user()?->university_id ?? 0);
        if ($defaultUniversityId <= 0 || ! $universities->contains('id', $defaultUniversityId)) {
            $defaultUniversityId = (int) ($universities->first()?->id ?? 0);
        }

        return view('admin.users.create', [
            'universities' => $universities,
            'defaultUniversityId' => $defaultUniversityId > 0 ? $defaultUniversityId : null,
            'faculties' => Faculty::query()
                ->with(['departments' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageGlobalUserAccounts');

        $role = $request->string('role')->toString();

        $rules = [
            'role' => ['required', Rule::in(['admin', 'coordinator', 'examiner'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($role === 'coordinator') {
            $rules['department_ids'] = ['required', 'array', 'min:1'];
            $rules['department_ids.*'] = ['integer', 'exists:departments,id'];
        } else {
            $rules['university_id'] = ['required', 'integer', 'exists:universities,id'];
        }

        $validated = $request->validate($rules);

        $generatedPassword = Str::upper(Str::random(10));

        $user = DB::transaction(function () use ($request, $validated, $generatedPassword): User {
            $role = $validated['role'];
            $isActive = $request->boolean('is_active', true);

            if ($role === 'coordinator') {
                $departmentIds = array_values(array_unique(array_map('intval', $validated['department_ids'])));
                $firstDepartment = Department::query()->with('faculty')->findOrFail($departmentIds[0]);
                $universityId = (int) $firstDepartment->faculty->university_id;

                $created = User::create([
                    'university_id' => $universityId,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => $generatedPassword,
                    'index_number' => null,
                    'role' => 'coordinator',
                    'is_active' => $isActive,
                    'email_verified_at' => now(),
                ]);

                $this->syncCoordinatorRoleForUser($created->id);
                $this->syncCoordinatorAssignments($created, $departmentIds, $isActive);

                return $created;
            }

            $created = User::create([
                'university_id' => (int) $validated['university_id'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $generatedPassword,
                'index_number' => null,
                'role' => $role,
                'is_active' => $isActive,
                'email_verified_at' => now(),
            ]);

            if ($role === 'admin') {
                $this->syncAdminRoleForUser($created->id);
            }

            return $created;
        });

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', __('Account created.'))
            ->with('generated_password', $generatedPassword);
    }

    public function edit(User $user): View
    {
        $this->authorize('manageUserAsAdmin', $user);

        return view('admin.users.edit', [
            'account' => $user->load('university'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manageUserAsAdmin', $user);

        if ($user->id === $request->user()->id && ! $request->boolean('is_active')) {
            return back()->withErrors([
                'is_active' => __('You cannot deactivate your own account.'),
            ])->withInput();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['nullable', 'boolean'],
            'generate_password' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'] !== null && $validated['email'] !== '' ? $validated['email'] : null,
            'is_active' => $request->boolean('is_active'),
        ];

        $generatedPassword = null;
        if ($request->boolean('generate_password')) {
            $generatedPassword = Str::upper(Str::random(10));
            $payload['password'] = $generatedPassword;
            $payload['last_student_password_reset_at'] = now();
        }

        $user->update($payload);

        $redirect = redirect()
            ->route('admin.users.edit', $user)
            ->with('status', __('Account updated.'));

        if ($generatedPassword !== null) {
            $redirect->with('generated_password', $generatedPassword);
        }

        return $redirect;
    }

    private function syncAdminRoleForUser(int $userId): void
    {
        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        if ($adminRoleId) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => (int) $adminRoleId, 'user_id' => $userId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function syncCoordinatorRoleForUser(int $userId): void
    {
        $coordinatorRoleId = Role::query()->where('slug', 'coordinator')->value('id');
        if ($coordinatorRoleId) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => (int) $coordinatorRoleId, 'user_id' => $userId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function syncCoordinatorAssignments(User $coordinator, array $departmentIds, bool $isActive): void
    {
        $departments = Department::query()
            ->whereIn('id', array_unique($departmentIds))
            ->get(['id', 'faculty_id']);

        $coordinator->coordinatorAssignments()->delete();

        $rows = $departments->map(fn (Department $department) => [
            'user_id' => $coordinator->id,
            'faculty_id' => $department->faculty_id,
            'department_id' => $department->id,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows !== []) {
            DB::table('coordinator_assignments')->insert($rows);
        }
    }
}
