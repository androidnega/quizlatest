<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Level;
use App\Models\Program;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassroomController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Classroom::class);

        return view('coordinator.classes.index', [
            'classes' => Classroom::query()
                ->whereIn('program_id', $this->scopedProgramIds())
                ->with(['program.department', 'level'])
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Classroom::class);

        return view('coordinator.classes.create', [
            'programs' => $this->scopedPrograms(),
            'levels' => $this->scopedLevels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Classroom::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'program_id' => ['required', 'integer'],
            'level_id' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $programId = (int) $validated['program_id'];
        $levelId = (int) $validated['level_id'];
        $program = Program::query()->find($programId);
        $level = Level::query()->find($levelId);
        abort_if($program === null || $level === null, 404);
        $this->authorize('view', $program);
        $this->authorize('view', $level);

        $request->validate([
            'name' => [
                Rule::unique('classes', 'name')
                    ->where(fn ($query) => $query
                        ->where('program_id', $programId)
                        ->where('level_id', $levelId)),
            ],
        ]);

        $activeYear = AcademicYear::activeForUniversity((int) auth()->user()->university_id);

        Classroom::create([
            'university_id' => auth()->user()->university_id,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => $validated['name'],
            'section' => null,
            'academic_year' => $activeYear?->name,
            'academic_year_id' => $activeYear?->id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('coordinator.classes.index')->with('status', 'Class created successfully.');
    }

    public function edit(Classroom $classroom): View
    {
        $this->authorize('update', $classroom);

        return view('coordinator.classes.edit', [
            'classroom' => $classroom->load(['program.department', 'level']),
            'programs' => $this->scopedPrograms(),
            'levels' => $this->scopedLevels(),
        ]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'program_id' => ['required', 'integer'],
            'level_id' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $programId = (int) $validated['program_id'];
        $levelId = (int) $validated['level_id'];
        $program = Program::query()->find($programId);
        $level = Level::query()->find($levelId);
        abort_if($program === null || $level === null, 404);
        $this->authorize('view', $program);
        $this->authorize('view', $level);

        $request->validate([
            'name' => [
                Rule::unique('classes', 'name')
                    ->where(fn ($query) => $query
                        ->where('program_id', $programId)
                        ->where('level_id', $levelId))
                    ->ignore($classroom->id),
            ],
        ]);

        $classroom->update([
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('coordinator.classes.index')->with('status', 'Class updated successfully.');
    }

    public function toggleStatus(Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);

        $classroom->update(['is_active' => ! $classroom->is_active]);

        return redirect()->route('coordinator.classes.index')->with('status', 'Class status updated.');
    }

    private function scopedPrograms()
    {
        return Program::query()
            ->whereIn('department_id', $this->departmentIds())
            ->with('department')
            ->orderBy('name')
            ->get();
    }

    private function scopedLevels()
    {
        return Level::query()
            ->where('university_id', auth()->user()->university_id)
            ->orderBy('sort_order')
            ->get();
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

    private function scopedProgramIds(): array
    {
        return Program::query()
            ->whereIn('department_id', $this->departmentIds())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function scopedLevelIds(): array
    {
        return Level::query()
            ->where('university_id', auth()->user()->university_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
