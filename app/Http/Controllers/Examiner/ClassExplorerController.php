<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\User;
use App\Support\StudentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassExplorerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quiz::class);

        $user = $request->user();
        $manageableCourseIds = $this->manageableCourseIds($request);

        if ($manageableCourseIds === []) {
            return view('examiner.classes.index', [
                'classrooms' => collect(),
                'courseCountByClass' => collect(),
            ]);
        }

        $classIds = DB::table('class_course')
            ->whereIn('course_id', $manageableCourseIds)
            ->distinct()
            ->pluck('class_id');

        $classrooms = Classroom::query()
            ->whereIn('id', $classIds)
            ->where('university_id', (int) $user->university_id)
            ->with(['level:id,name,code'])
            ->withCount('students')
            ->orderBy('name')
            ->get(['id', 'name', 'section', 'level_id']);

        $courseCountByClass = DB::table('class_course')
            ->whereIn('class_id', $classrooms->pluck('id'))
            ->whereIn('course_id', $manageableCourseIds)
            ->selectRaw('class_id, COUNT(DISTINCT course_id) as c')
            ->groupBy('class_id')
            ->pluck('c', 'class_id');

        return view('examiner.classes.index', [
            'classrooms' => $classrooms,
            'courseCountByClass' => $courseCountByClass,
        ]);
    }

    public function show(Request $request, Classroom $classroom): View
    {
        $this->authorize('viewAny', Quiz::class);
        $this->authorize('view', $classroom);

        $user = $request->user();

        $allowedCourseIds = $this->allowedCourseIdsForTeachingClass($request, $classroom);

        $courses = Course::query()
            ->whereIn('id', $allowedCourseIds)
            ->with(['department:id,name'])
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'department_id']);

        $quizzesByCourse = Quiz::query()
            ->where('created_by', $user->id)
            ->whereIn('course_id', $allowedCourseIds)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'status', 'course_id', 'updated_at'])
            ->groupBy('course_id');

        $allQuizzes = $quizzesByCourse->flatten()->sortByDesc('updated_at')->values();

        $examinersByCourseId = ExaminerCourseAssignment::query()
            ->whereIn('course_id', $allowedCourseIds)
            ->where('is_active', true)
            ->with(['examinerUser:id,name'])
            ->get()
            ->groupBy('course_id')
            ->map(function ($rows) {
                return $rows
                    ->map(fn ($row) => $row->examinerUser?->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            });

        $classroom->loadCount('students');

        return view('examiner.classes.show', [
            'classroom' => $classroom->loadMissing('level:id,name,code'),
            'courses' => $courses,
            'quizzesByCourse' => $quizzesByCourse,
            'allQuizzes' => $allQuizzes,
            'examinersByCourseId' => $examinersByCourseId,
        ]);
    }

    public function studentsIndex(Request $request, Classroom $classroom): View
    {
        $this->authorize('viewAny', Quiz::class);
        $this->authorize('view', $classroom);

        $allowedCourseIds = $this->allowedCourseIdsForTeachingClass($request, $classroom);
        $classroom->loadCount('students');

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'activity' => ['nullable', 'in:all,active,quiet'],
            'sort' => ['nullable', 'in:name_asc,name_desc,index_asc,index_desc,sessions_asc,sessions_desc'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $activity = $validated['activity'] ?? 'all';
        $sort = $validated['sort'] ?? 'name_asc';

        $scopedExamSessions = function ($relation) use ($classroom, $allowedCourseIds): void {
            $relation->where('class_id', $classroom->id);
            if ($allowedCourseIds !== []) {
                $relation->whereHas('exam', fn ($eq) => $eq->whereIn('course_id', $allowedCourseIds));
            } else {
                $relation->whereRaw('0 = 1');
            }
        };

        $query = $classroom->students()
            ->withCount([
                'examSessions as roster_session_count' => $scopedExamSessions,
            ]);

        if ($search !== '') {
            $safe = addcslashes($search, '%_\\');
            $like = '%'.$safe.'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('name', 'like', $like)
                    ->orWhere('index_number', 'like', $like);
            });
        }

        if ($activity === 'active') {
            $query->whereHas('examSessions', $scopedExamSessions);
        } elseif ($activity === 'quiet') {
            $query->whereDoesntHave('examSessions', $scopedExamSessions);
        }

        match ($sort) {
            'name_desc' => $query->orderByDesc('name'),
            'index_asc' => $query->orderBy('index_number'),
            'index_desc' => $query->orderByDesc('index_number'),
            'sessions_asc' => $query->orderBy('roster_session_count')->orderBy('name'),
            'sessions_desc' => $query->orderByDesc('roster_session_count')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $students = $query->paginate(25)->withQueryString();

        return view('examiner.classes.students', [
            'classroom' => $classroom->loadMissing('level:id,name,code'),
            'students' => $students,
            'filters' => [
                'q' => $search,
                'activity' => $activity,
                'sort' => $sort,
            ],
            'allowedCourseIds' => $allowedCourseIds,
        ]);
    }

    public function showStudent(Request $request, Classroom $classroom, User $student): View
    {
        $this->authorize('viewAny', Quiz::class);
        $this->authorize('view', $classroom);
        $this->abortUnlessTeachingStudent($classroom, $student);

        $allowedCourseIds = $this->allowedCourseIdsForTeachingClass($request, $classroom);

        $sessions = ExamSession::query()
            ->where('student_id', $student->id)
            ->where('class_id', $classroom->id)
            ->whereHas('exam', fn ($q) => $q->whereIn('course_id', $allowedCourseIds))
            ->with([
                'exam:id,title,course_id',
                'exam.course:id,title,code',
            ])
            ->orderByDesc('start_time')
            ->limit(100)
            ->get();

        return view('examiner.classes.student', [
            'classroom' => $classroom->loadMissing('level:id,name,code'),
            'student' => $student,
            'sessions' => $sessions,
            'allowedCourseIds' => $allowedCourseIds,
        ]);
    }

    public function storeStudent(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('viewAny', Quiz::class);
        $this->authorize('addStudent', $classroom);

        $classroom->loadMissing(['program:id', 'level:id']);

        $universityId = (int) $classroom->university_id;
        abort_if($universityId <= 0, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'index_number' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $indexNormalized = trim((string) $validated['index_number']);

        $existing = User::query()
            ->where('university_id', $universityId)
            ->where('index_number', $indexNormalized)
            ->where('role', 'student')
            ->first();

        if ($existing !== null) {
            if ((int) ($existing->class_id ?? 0) === (int) $classroom->id) {
                return back()
                    ->withInput()
                    ->withErrors(['index_number' => __('This student index is already on this class roster.')]);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'index_number' => __('This index is already registered at your institution (another class). Contact your program office — roster edits beyond adding new learners here require coordination.'),
                ]);
        }

        $phoneRaw = trim((string) ($validated['phone'] ?? ''));
        $normalizedPhone = null;
        if ($phoneRaw !== '') {
            $normalizedPhone = StudentPhone::normalize($phoneRaw);
            if ($normalizedPhone === null || ! StudentPhone::isGhanaMobile($normalizedPhone)) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => __('Enter a valid Ghana mobile number or leave phone blank.')]);
            }
            if ($this->universityHasConflictingStudentPhone($universityId, $normalizedPhone, 0)) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => __('Another student in this institution already uses this phone number.')]);
            }
        }

        $studentRoleId = (int) (Role::query()->where('slug', 'student')->value('id') ?? 0);

        DB::transaction(function () use (
            $classroom,
            $validated,
            $indexNormalized,
            $normalizedPhone,
            $universityId,
            $studentRoleId,
        ): void {
            $student = User::create([
                'university_id' => $universityId,
                'program_id' => (int) $classroom->program_id,
                'level_id' => (int) $classroom->level_id,
                'class_id' => (int) $classroom->id,
                'name' => $validated['name'],
                'email' => null,
                'phone' => $normalizedPhone,
                'index_number' => $indexNormalized,
                'role' => 'student',
                'is_active' => true,
                'email_verified_at' => null,
                'student_onboarded_at' => null,
                'password' => Str::password(32),
            ]);

            if ($studentRoleId > 0) {
                DB::table('role_user')->updateOrInsert(
                    ['role_id' => $studentRoleId, 'user_id' => $student->id],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        });

        $indexQuery = [];
        if ($request->filled('filter_q')) {
            $indexQuery['q'] = (string) $request->input('filter_q');
        }
        if ($request->filled('filter_activity')) {
            $indexQuery['activity'] = (string) $request->input('filter_activity');
        }
        if ($request->filled('filter_sort')) {
            $indexQuery['sort'] = (string) $request->input('filter_sort');
        }

        return redirect()
            ->route('examiner.teaching-classes.students.index', array_merge(['classroom' => $classroom], $indexQuery))
            ->with('status', __('Student added to this class roster.'));
    }

    /**
     * CSV export of learners currently assigned to this class (same columns as roster uploads).
     */
    public function downloadClassRoster(Classroom $classroom): StreamedResponse
    {
        $this->authorize('viewAny', Quiz::class);
        $this->authorize('view', $classroom);

        $filename = Str::slug($classroom->name).'-class-roster.csv';

        return response()->streamDownload(function () use ($classroom): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['index_number', 'name', 'phone']);

            User::query()
                ->where('role', 'student')
                ->where('class_id', $classroom->id)
                ->orderBy('index_number')
                ->chunkById(200, function ($chunk) use ($handle): void {
                    foreach ($chunk as $student) {
                        /** @var User $student */
                        fputcsv($handle, [
                            $student->index_number,
                            $student->name,
                            $student->phone ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<int, int>
     */
    private function allowedCourseIdsForTeachingClass(Request $request, Classroom $classroom): array
    {
        $manageableCourseIds = collect($this->manageableCourseIds($request));
        $classCourseIds = DB::table('class_course')
            ->where('class_id', $classroom->id)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id);

        return $manageableCourseIds->intersect($classCourseIds)->values()->all();
    }

    private function abortUnlessTeachingStudent(Classroom $classroom, User $student): void
    {
        abort_unless($student->role === 'student', 404);
        abort_unless((int) ($student->class_id ?? 0) === (int) $classroom->id, 404);
        abort_unless((int) ($student->university_id ?? 0) === (int) $classroom->university_id, 404);
    }

    private function universityHasConflictingStudentPhone(int $universityId, string $normalizedPhone, int $ignoreUserId): bool
    {
        return User::query()
            ->where('university_id', $universityId)
            ->where('role', 'student')
            ->whereKeyNot($ignoreUserId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['phone'])
            ->contains(fn (User $u): bool => StudentPhone::normalize($u->phone) === $normalizedPhone);
    }

    /**
     * Blank CSV roster template for coordinators (same columns as coordinator upload); examiners may download to align with class intake.
     */
    public function downloadStudentTemplate(Classroom $classroom): StreamedResponse
    {
        $this->authorize('viewAny', Quiz::class);
        $this->authorize('view', $classroom);

        $classroom->load('program');
        $programCode = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', (string) ($classroom->program?->code ?? 'CLS')), 6));
        $programCode = $programCode !== '' ? $programCode : 'CLS';

        $filename = Str::slug($classroom->name).'-students-template.csv';

        return response()->streamDownload(function () use ($programCode): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['index_number', 'name', 'phone']);
            fputcsv($handle, [$programCode.'/'.now()->year.'/001', 'Sample Student', '+233241112233']);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<int, int>
     */
    private function manageableCourseIds(Request $request): array
    {
        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $request->user()->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
