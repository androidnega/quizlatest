<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExaminerCourseAssignment;
use App\Models\Level;
use App\Models\Program;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Classroom::class);

        $programIds = $this->scopedProgramIds();

        $query = Classroom::query()
            ->whereIn('program_id', $programIds)
            ->with(['program.department', 'level'])
            ->withCount('students');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $needle = '%'.$escaped.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('name', 'like', $needle)
                    ->orWhere('section', 'like', $needle)
                    ->orWhereHas('program', function ($pq) use ($needle): void {
                        $pq->where('name', 'like', $needle)->orWhere('code', 'like', $needle);
                    });
            });
        }

        if ($request->filled('program_id')) {
            $pid = $request->integer('program_id');
            if (in_array($pid, $programIds, true)) {
                $query->where('program_id', $pid);
            }
        }

        if ($request->filled('level_id')) {
            $lid = $request->integer('level_id');
            $allowedLevels = $this->scopedLevelIds();
            if (in_array($lid, $allowedLevels, true)) {
                $query->where('level_id', $lid);
            }
        }

        $status = (string) $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = (string) $request->input('sort', 'name');
        $dir = strtolower((string) $request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'students') {
            $query->orderBy('students_count', $dir)->orderBy('name');
        } elseif ($sort === 'recent') {
            $query->orderByDesc('updated_at')->orderBy('name');
        } else {
            $query->orderBy('name', $dir);
        }

        $classes = $query->paginate(24)->withQueryString();

        $filtersActive = collect($request->only(['q', 'program_id', 'level_id', 'status', 'sort', 'dir']))
            ->filter(function ($v, string $k): bool {
                if ($k === 'status' && ($v === null || $v === '' || $v === 'all')) {
                    return false;
                }
                if ($k === 'sort' && ($v === null || $v === '' || $v === 'name')) {
                    return false;
                }
                if ($k === 'dir' && ($v === null || $v === '' || $v === 'asc')) {
                    return false;
                }

                return $v !== null && $v !== '';
            })->isNotEmpty();

        return view('coordinator.classes.index', [
            'classes' => $classes,
            'programs' => $this->scopedPrograms(),
            'levels' => $this->scopedLevels(),
            'filters' => [
                'q' => $request->input('q', ''),
                'program_id' => $request->input('program_id'),
                'level_id' => $request->input('level_id'),
                'status' => $status,
                'sort' => $sort,
                'dir' => $dir,
            ],
            'filtersActive' => $filtersActive,
        ]);
    }

    public function show(Classroom $classroom): View
    {
        $this->authorize('view', $classroom);

        $classroom->load([
            'program.department',
            'level',
            'courses' => fn ($query) => $query->orderBy('code'),
        ]);
        $classroom->loadCount('students');

        $students = $classroom->students()
            ->with(['program', 'level'])
            ->orderByDesc('created_at')
            ->paginate(12);

        $courseIds = $classroom->courses->modelKeys();
        $coursesById = $classroom->courses->keyBy('id');

        $classExaminers = collect();

        if ($courseIds !== []) {
            $assignments = ExaminerCourseAssignment::query()
                ->whereIn('course_id', $courseIds)
                ->where('is_active', true)
                ->with(['examinerUser'])
                ->get();

            $classExaminers = $assignments
                ->groupBy('examiner_user_id')
                ->map(function ($items) use ($coursesById) {
                    $examiner = $items->first()->examinerUser;
                    $courses = $items
                        ->map(fn (ExaminerCourseAssignment $assignment) => $coursesById->get($assignment->course_id))
                        ->filter()
                        ->unique(fn ($course) => $course->id)
                        ->sortBy(fn ($course) => mb_strtolower((string) $course->code))
                        ->values();

                    return ['examiner' => $examiner, 'courses' => $courses];
                })
                ->filter(fn (array $row): bool => $row['examiner'] !== null)
                ->sortBy(fn (array $row): string => mb_strtolower((string) ($row['examiner']->name ?? '')))
                ->values();
        }

        return view('coordinator.classes.show', [
            'classroom' => $classroom,
            'students' => $students,
            'classExaminers' => $classExaminers,
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
            'accent_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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

        $accentHex = array_key_exists('accent_color', $validated) && ($validated['accent_color'] ?? '') !== ''
            ? '#'.strtoupper(substr((string) $validated['accent_color'], 1))
            : null;

        Classroom::create([
            'university_id' => auth()->user()->university_id,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => $validated['name'],
            'section' => null,
            'academic_year' => $activeYear?->name,
            'academic_year_id' => $activeYear?->id,
            'is_active' => $request->boolean('is_active', true),
            'accent_color' => $accentHex,
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
            'accent_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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

        $updates = [
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
        ];

        if (array_key_exists('accent_color', $validated)) {
            $updates['accent_color'] = ($validated['accent_color'] ?? '') !== ''
                ? '#'.strtoupper(substr((string) $validated['accent_color'], 1))
                : null;
        }

        $classroom->update($updates);

        return redirect()->route('coordinator.classes.index')->with('status', 'Class updated successfully.');
    }

    public function toggleStatus(Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);

        $classroom->update(['is_active' => ! $classroom->is_active]);

        return redirect()
            ->back()
            ->with('status', __('Class status updated.'));
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
