<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoordinatorController extends Controller
{
    public function index(): View
    {
        $this->authorize('manageCoordinatorDirectory');

        $coordinators = User::query()
            ->where('role', 'coordinator')
            ->with(['coordinatorAssignments.department.faculty'])
            ->latest()
            ->paginate(10);

        return view('admin.coordinators.index', [
            'coordinators' => $coordinators,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manageCoordinatorDirectory');

        return view('admin.coordinators.create', [
            'faculties' => $this->facultiesWithDepartments(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageCoordinatorDirectory');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $coordinator = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'index_number' => null,
                'role' => 'coordinator',
                'is_active' => $request->boolean('is_active', true),
                'email_verified_at' => now(),
            ]);

            $this->syncCoordinatorRole($coordinator->id);
            $this->syncAssignments($coordinator, $validated['department_ids'], $request->boolean('is_active', true));
        });

        return redirect()->route('admin.coordinators.index')->with('status', 'Coordinator account created successfully.');
    }

    public function edit(User $coordinator): View
    {
        $this->authorize('manageCoordinatorAccount', $coordinator);

        return view('admin.coordinators.edit', [
            'coordinator' => $coordinator->load('coordinatorAssignments'),
            'faculties' => $this->facultiesWithDepartments(),
            'selectedDepartmentIds' => $coordinator->coordinatorAssignments()->pluck('department_id')->all(),
        ]);
    }

    public function update(Request $request, User $coordinator): RedirectResponse
    {
        $this->authorize('manageCoordinatorAccount', $coordinator);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', Rule::unique('users', 'email')->ignore($coordinator->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $request, $coordinator): void {
            $payload = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $request->boolean('is_active'),
            ];

            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }

            $coordinator->update($payload);
            $this->syncCoordinatorRole($coordinator->id);
            $this->syncAssignments($coordinator, $validated['department_ids'], $request->boolean('is_active'));
        });

        return redirect()->route('admin.coordinators.index')->with('status', 'Coordinator updated successfully.');
    }

    private function facultiesWithDepartments()
    {
        return Faculty::query()
            ->with(['departments' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    private function syncAssignments(User $coordinator, array $departmentIds, bool $isActive): void
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

        if (! empty($rows)) {
            DB::table('coordinator_assignments')->insert($rows);
        }
    }

    private function syncCoordinatorRole(int $userId): void
    {
        $coordinatorRoleId = Role::query()->where('slug', 'coordinator')->value('id');

        if ($coordinatorRoleId) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $coordinatorRoleId, 'user_id' => $userId],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
