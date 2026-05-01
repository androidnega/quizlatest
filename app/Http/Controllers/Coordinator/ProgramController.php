<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Program::class);

        $departmentIds = $this->departmentIds();

        return view('coordinator.programs.index', [
            'programs' => Program::query()
                ->whereIn('department_id', $departmentIds)
                ->with('department')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Program::class);

        $departmentIds = $this->departmentIds();
        $selectedDepartmentId = (int) $request->integer('department_id', $departmentIds[0] ?? 0);

        $this->authorize('update', Program::make(['department_id' => $selectedDepartmentId]));

        $department = auth()->user()->coordinatorAssignments()
            ->where('department_id', $selectedDepartmentId)
            ->with('department')
            ->first()?->department;

        return view('coordinator.programs.create', [
            'department' => $department,
            'departmentId' => $selectedDepartmentId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Program::class);

        $departmentIds = $this->departmentIds();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20'],
            'department_id' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $departmentId = (int) $validated['department_id'];
        $this->authorize('update', Program::make(['department_id' => $departmentId]));

        $request->validate([
            'code' => [
                Rule::unique('programs', 'code')->where(fn ($query) => $query->where('department_id', $departmentId)),
            ],
            'name' => [
                Rule::unique('programs', 'name')->where(fn ($query) => $query->where('department_id', $departmentId)),
            ],
        ]);

        Program::create([
            'university_id' => auth()->user()->university_id,
            'department_id' => $departmentId,
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('coordinator.programs.index')->with('status', 'Program created successfully.');
    }

    public function edit(Program $program): View
    {
        $this->authorize('update', $program);

        return view('coordinator.programs.edit', [
            'program' => $program->load('department'),
        ]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $request->validate([
            'code' => [
                Rule::unique('programs', 'code')
                    ->where(fn ($query) => $query->where('department_id', $program->department_id))
                    ->ignore($program->id),
            ],
            'name' => [
                Rule::unique('programs', 'name')
                    ->where(fn ($query) => $query->where('department_id', $program->department_id))
                    ->ignore($program->id),
            ],
        ]);

        $program->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('coordinator.programs.index')->with('status', 'Program updated successfully.');
    }

    public function toggleStatus(Program $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $program->update(['is_active' => ! $program->is_active]);

        return redirect()->route('coordinator.programs.index')->with('status', 'Program status updated.');
    }

    private function departmentIds(): array
    {
        return auth()->user()
            ->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
