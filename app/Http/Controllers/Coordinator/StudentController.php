<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Level;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Support\StudentPhone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewStudentDirectory');

        $departmentIds = $this->departmentIds();
        $programs = Program::query()->whereIn('department_id', $departmentIds)->orderBy('name')->get();
        $levels = Level::query()->orderBy('sort_order')->get();
        $students = User::query()
            ->where('role', 'student')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->with(['program.department', 'level', 'classroom'])
            ->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->integer('program_id')))
            ->when($request->filled('level_id'), fn ($query) => $query->where('level_id', $request->integer('level_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('index_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('coordinator.students.index', [
            'students' => $students,
            'programs' => $programs,
            'levels' => $levels,
            'filters' => $request->only(['program_id', 'level_id', 'search']),
        ]);
    }

    public function legacyAssignClassRedirect(User $student): RedirectResponse
    {
        abort_unless($student->role === 'student', 404);
        $this->authorize('manageStudentInScope', $student);

        return redirect()->route('coordinator.students.edit', $student);
    }

    public function edit(User $student): View
    {
        abort_unless($student->role === 'student', 404);
        $this->authorize('manageStudentInScope', $student);

        $student->load(['program.department', 'level', 'classroom', 'university']);

        abort_if($student->university_id === null || (int) $student->university_id <= 0, 404);

        $departmentIds = $this->departmentIds();

        return view('coordinator.students.edit', [
            'student' => $student,
            'programs' => Program::query()
                ->whereIn('department_id', $departmentIds)
                ->where('university_id', $student->university_id)
                ->orderBy('name')
                ->get(),
            'levels' => Level::query()
                ->where('university_id', $student->university_id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'classes' => $this->scopedClasses(),
        ]);
    }

    public function update(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->role === 'student', 404);
        $this->authorize('manageStudentInScope', $student);

        abort_if($student->university_id === null || (int) $student->university_id <= 0, 404);

        $universityId = (int) $student->university_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'index_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique('users', 'index_number')
                    ->ignore($student->id)
                    ->where(fn ($query) => $query->where('university_id', $universityId)),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'generate_password' => ['nullable', 'boolean'],
        ]);

        $isActive = $request->boolean('is_active');

        $program = Program::query()->findOrFail((int) $validated['program_id']);
        abort_unless((int) $program->university_id === $universityId, 422);
        abort_unless($this->departmentIds()->contains((int) $program->department_id), 403);

        $level = Level::query()->findOrFail((int) $validated['level_id']);
        abort_unless((int) $level->university_id === $universityId, 422);

        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;
        if ($classId !== null && $classId <= 0) {
            $classId = null;
        }

        $phoneRaw = trim((string) ($validated['phone'] ?? ''));
        $normalizedPhone = null;
        if ($phoneRaw !== '') {
            $normalizedPhone = StudentPhone::normalize($phoneRaw);
            if ($normalizedPhone === null || ! StudentPhone::isGhanaMobile($normalizedPhone)) {
                return back()->withErrors(['phone' => __('Enter a valid Ghana mobile number or leave phone blank.')])->withInput();
            }
            if ($this->universityHasConflictingStudentPhone($universityId, $normalizedPhone, (int) $student->id)) {
                return back()->withErrors(['phone' => __('Another student in this institution already uses this phone number.')])->withInput();
            }
        }

        if ($isActive && $classId === null) {
            return back()->withErrors([
                'class_id' => __('Active students must be assigned to a class.'),
            ])->withInput();
        }

        if ($classId !== null) {
            $classroom = Classroom::query()->find($classId);
            abort_if($classroom === null, 404);
            $this->authorize('view', $classroom);
            abort_unless((int) $classroom->university_id === $universityId, 422);
            abort_unless((int) $classroom->program_id === (int) $program->id, 422);
            abort_unless((int) $classroom->level_id === (int) $level->id, 422);
        }

        $payload = [
            'name' => $validated['name'],
            'index_number' => trim((string) $validated['index_number']),
            'phone' => $normalizedPhone,
            'program_id' => (int) $program->id,
            'level_id' => (int) $level->id,
            'class_id' => $classId,
            'is_active' => $isActive,
            'email' => null,
        ];

        $generatedPassword = null;
        if ($request->boolean('generate_password')) {
            $generatedPassword = Str::upper(Str::random(10));
            $payload['password'] = $generatedPassword;
            $payload['last_student_password_reset_at'] = now();
        }

        $student->update($payload);

        $redirect = redirect()
            ->route('coordinator.students.edit', $student)
            ->with('status', __('Student saved.'));

        if ($generatedPassword !== null) {
            $redirect->with('generated_password', $generatedPassword);
        }

        return $redirect;
    }

    public function destroy(User $student): RedirectResponse
    {
        abort_unless($student->role === 'student', 404);
        $this->authorize('manageStudentInScope', $student);

        if ((int) ($student->class_id ?? 0) > 0) {
            return back()->withErrors([
                'student_delete' => __('Remove the student from class assignment before deleting.'),
            ]);
        }

        if ($this->studentHasAcademicData((int) $student->id)) {
            return back()->withErrors([
                'student_delete' => __('This student has quizzes, attempts, exam sessions, or results and cannot be deleted.'),
            ]);
        }

        $student->delete();

        return redirect()
            ->route('coordinator.students.index')
            ->with('status', __('Student removed.'));
    }

    public function exportJson(Request $request): StreamedResponse
    {
        $this->authorize('viewStudentDirectory');

        $departmentIds = $this->departmentIds();
        $students = User::query()
            ->where('role', 'student')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->with(['program', 'level', 'classroom'])
            ->orderBy('id')
            ->get();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'count' => $students->count(),
            'students' => $students->map(fn (User $student): array => [
                'name' => $student->name,
                'index_number' => $student->index_number,
                'phone' => $student->phone,
                'program_code' => $student->program?->code,
                'program_name' => $student->program?->name,
                'level_code' => $student->level?->code,
                'level_name' => $student->level?->name,
                'class_name' => $student->classroom?->name,
                'is_active' => (bool) $student->is_active,
            ])->values()->all(),
        ];

        $filename = 'students-export-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function importJsonForm(): View
    {
        $this->authorize('viewStudentDirectory');

        return view('coordinator.students.import-json');
    }

    public function importJson(Request $request): RedirectResponse
    {
        $this->authorize('viewStudentDirectory');

        $validated = $request->validate([
            'json_file' => ['required', 'file', 'max:4096'],
        ]);

        $contents = file_get_contents($validated['json_file']->getRealPath());
        if ($contents === false || trim($contents) === '') {
            return back()->withErrors(['json_file' => __('Uploaded JSON is empty.')])->withInput();
        }

        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            return back()->withErrors(['json_file' => __('Invalid JSON payload.')])->withInput();
        }

        $rows = $decoded['students'] ?? $decoded;
        if (! is_array($rows)) {
            return back()->withErrors(['json_file' => __('JSON must be an array of students or an object containing a students array.')])->withInput();
        }

        $departmentIds = $this->departmentIds();
        $programs = Program::query()
            ->whereIn('department_id', $departmentIds)
            ->get();

        $programByCode = $programs->keyBy(fn (Program $p) => Str::lower((string) $p->code));
        $programByName = $programs->keyBy(fn (Program $p) => Str::lower((string) $p->name));
        $levelByCode = Level::query()->get()->keyBy(fn (Level $l) => Str::lower((string) $l->code));
        $levelByName = Level::query()->get()->keyBy(fn (Level $l) => Str::lower((string) $l->name));
        $studentRoleId = (int) (Role::query()->where('slug', 'student')->value('id') ?? 0);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use (
            $rows,
            $programByCode,
            $programByName,
            $levelByCode,
            $levelByName,
            $studentRoleId,
            &$imported,
            &$updated,
            &$skipped,
            &$errors
        ): void {
            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    $skipped++;

                    continue;
                }

                $index = trim((string) ($row['index_number'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));

                if ($index === '' || $name === '') {
                    $skipped++;

                    continue;
                }

                $program = $programByCode->get(Str::lower(trim((string) ($row['program_code'] ?? ''))))
                    ?? $programByName->get(Str::lower(trim((string) ($row['program_name'] ?? ''))));
                $level = $levelByCode->get(Str::lower(trim((string) ($row['level_code'] ?? ''))))
                    ?? $levelByName->get(Str::lower(trim((string) ($row['level_name'] ?? ''))));

                if (! $program || ! $level) {
                    $skipped++;

                    continue;
                }

                $className = trim((string) ($row['class_name'] ?? ''));
                $classId = null;
                if ($className !== '') {
                    $classId = Classroom::query()
                        ->where('program_id', $program->id)
                        ->where('level_id', $level->id)
                        ->where('name', $className)
                        ->value('id');
                }

                $phoneRaw = trim((string) ($row['phone'] ?? ''));
                $phone = null;
                if ($phoneRaw !== '') {
                    $phone = StudentPhone::normalize($phoneRaw);
                    if ($phone === null || ! StudentPhone::isGhanaMobile($phone)) {
                        $errors[] = __('Row :row skipped: invalid phone.', ['row' => $i + 1]);
                        $skipped++;

                        continue;
                    }
                }

                $isActive = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL);
                if ($isActive && $classId === null) {
                    $isActive = false;
                }

                $existing = User::query()
                    ->where('role', 'student')
                    ->where('index_number', $index)
                    ->where('university_id', $program->university_id)
                    ->first();

                $payload = [
                    'university_id' => $program->university_id,
                    'program_id' => $program->id,
                    'level_id' => $level->id,
                    'class_id' => $classId,
                    'name' => $name,
                    'email' => null,
                    'phone' => $phone,
                    'index_number' => $index,
                    'role' => 'student',
                    'is_active' => $isActive,
                ];

                if ($existing) {
                    if ($this->studentHasAcademicData((int) $existing->id)) {
                        $errors[] = __('Row :row skipped: existing student has academic data and cannot be reassigned.', ['row' => $i + 1]);
                        $skipped++;

                        continue;
                    }
                    $existing->update($payload);
                    $updated++;

                    continue;
                }

                $created = User::create(array_merge($payload, [
                    'email_verified_at' => null,
                    'student_onboarded_at' => null,
                    'password' => Str::password(32),
                ]));
                if ($studentRoleId > 0) {
                    DB::table('role_user')->updateOrInsert(
                        ['role_id' => $studentRoleId, 'user_id' => $created->id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
                $imported++;
            }
        });

        $message = __('JSON import complete. :imported created, :updated updated, :skipped skipped.', [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        if ($errors !== []) {
            return redirect()->route('coordinator.students.import-json.form')
                ->with('status', $message)
                ->with('json_import_errors', array_slice($errors, 0, 10));
        }

        return redirect()->route('coordinator.students.index')->with('status', $message);
    }

    public function uploadForm(): View
    {
        $this->authorize('viewStudentDirectory');

        return view('coordinator.students.upload');
    }

    public function previewImport(Request $request): View
    {
        $this->authorize('viewStudentDirectory');

        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'map_index_number' => ['required', 'string', 'max:64'],
            'map_name' => ['nullable', 'string', 'max:64'],
            'map_phone' => ['nullable', 'string', 'max:64'],
            'map_program' => ['required', 'string', 'max:64'],
            'map_level' => ['required', 'string', 'max:64'],
            'map_class_name' => ['nullable', 'string', 'max:64'],
            'year' => ['nullable', 'digits:4'],
        ]);

        $mapIndex = Str::lower(trim($validated['map_index_number']));
        $mapName = Str::lower(trim((string) ($validated['map_name'] ?? '')));
        $mapPhone = Str::lower(trim((string) ($validated['map_phone'] ?? '')));
        $mapProgram = Str::lower(trim($validated['map_program']));
        $mapLevel = Str::lower(trim($validated['map_level']));
        $mapClassName = Str::lower(trim((string) ($validated['map_class_name'] ?? '')));

        $departmentIds = $this->departmentIds();
        $allowedPrograms = Program::query()->whereIn('department_id', $departmentIds)->get()->keyBy(fn (Program $program) => Str::lower($program->code ?? $program->name));
        $levels = Level::query()->get();
        $year = (int) ($validated['year'] ?? now()->year);

        $rows = $this->parseCsvFile($request->file('csv_file')->getRealPath());

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'The uploaded CSV appears to be empty.']);
        }

        $previewRows = [];
        $validRows = [];
        $seenIndexes = [];
        $seenPhones = [];

        foreach ($rows as $rowNumber => $row) {
            $name = $mapName !== '' ? trim((string) ($row[$mapName] ?? '')) : '';
            $phoneRaw = $mapPhone !== '' ? trim((string) ($row[$mapPhone] ?? '')) : '';
            $indexNumber = trim((string) ($row[$mapIndex] ?? ''));
            $programRaw = trim((string) ($row[$mapProgram] ?? ''));
            $levelRaw = trim((string) ($row[$mapLevel] ?? ''));
            $classNameRaw = $mapClassName !== '' ? trim((string) ($row[$mapClassName] ?? '')) : '';

            $errors = [];

            $programLookupKey = Str::lower($programRaw);
            $program = $allowedPrograms[$programLookupKey] ?? Program::query()
                ->whereIn('department_id', $departmentIds)
                ->where(function ($query) use ($programRaw) {
                    $query->where('name', $programRaw)->orWhere('code', $programRaw);
                })
                ->first();

            if (! $program) {
                $errors[] = 'Program not found within your departments.';
            }

            $level = $levels->first(function (Level $candidate) use ($levelRaw) {
                return Str::lower((string) $candidate->code) === Str::lower($levelRaw)
                    || Str::lower($candidate->name) === Str::lower($levelRaw)
                    || (string) $candidate->name === $levelRaw;
            });

            if (! $level) {
                $errors[] = 'Level not recognized.';
            }

            $universityId = $program ? (int) $program->university_id : 0;

            $normalizedPhone = $phoneRaw !== '' ? StudentPhone::normalize($phoneRaw) : null;
            if ($phoneRaw !== '' && $normalizedPhone === null) {
                $errors[] = 'Phone number could not be normalized.';
            }
            if ($normalizedPhone !== null && ! StudentPhone::isGhanaMobile($normalizedPhone)) {
                $errors[] = 'Phone must be a valid Ghana mobile number when provided.';
            }
            if ($normalizedPhone !== null) {
                $phoneKey = $universityId.'|'.$normalizedPhone;
                if (isset($seenPhones[$phoneKey])) {
                    $errors[] = 'Duplicate phone in file.';
                }
                $seenPhones[$phoneKey] = true;

                if ($universityId > 0 && $this->universityHasStudentPhone($universityId, $normalizedPhone)) {
                    $errors[] = 'Phone already exists for another student in this institution.';
                }
            }

            if ($indexNumber !== '') {
                $idxKey = $universityId.'|'.Str::upper($indexNumber);
                if (isset($seenIndexes[$idxKey])) {
                    $errors[] = 'Duplicate index number in file.';
                }
                $seenIndexes[$idxKey] = true;

                if ($universityId > 0 && User::query()->where('university_id', $universityId)->where('index_number', $indexNumber)->exists()) {
                    $errors[] = 'Index number already exists for this institution.';
                }
            }

            $classId = null;
            if ($program && $level) {
                $classQuery = Classroom::query()
                    ->where('program_id', $program->id)
                    ->where('level_id', $level->id)
                    ->where('is_active', true);
                if ($classNameRaw !== '') {
                    $classQuery->where('name', $classNameRaw);
                }
                $classroom = $classQuery->orderBy('name')->first();
                $classId = $classroom?->id;
                if ($classNameRaw !== '' && $classId === null) {
                    $errors[] = 'Class not found for the given program, level, and class name.';
                }
            }

            $previewRow = [
                'row_number' => $rowNumber + 2,
                'name' => $name,
                'phone' => $normalizedPhone,
                'index_number' => $indexNumber,
                'class_name' => $classNameRaw,
                'class_id' => $classId,
                'program' => $program?->name,
                'program_id' => $program?->id,
                'program_code' => $program?->code,
                'university_id' => $universityId > 0 ? $universityId : null,
                'level' => $level?->name,
                'level_id' => $level?->id,
                'errors' => $errors,
            ];

            $previewRows[] = $previewRow;

            if (empty($errors)) {
                $validRows[] = $previewRow;
            }
        }

        session([
            'student_csv_import' => [
                'year' => $year,
                'rows' => $validRows,
            ],
        ]);

        return view('coordinator.students.preview', [
            'previewRows' => $previewRows,
            'validCount' => count($validRows),
            'invalidCount' => count($previewRows) - count($validRows),
            'year' => $year,
            'lockedClassroom' => null,
            'backUrl' => route('coordinator.students.upload'),
        ]);
    }

    public function classScopedUploadForm(Classroom $classroom): View
    {
        $this->authorize('viewStudentDirectory');
        $this->authorize('view', $classroom);

        return view('coordinator.classes.upload-students', [
            'classroom' => $classroom->load(['program.department', 'level']),
        ]);
    }

    public function previewImportForClassroom(Request $request, Classroom $classroom): View
    {
        $this->authorize('viewStudentDirectory');
        $this->authorize('view', $classroom);

        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'map_index_number' => ['required', 'string', 'max:64'],
            'map_name' => ['nullable', 'string', 'max:64'],
            'map_phone' => ['nullable', 'string', 'max:64'],
            'year' => ['nullable', 'digits:4'],
        ]);

        $classroom->load(['program', 'level']);
        $program = $classroom->program;
        $level = $classroom->level;
        abort_if($program === null || $level === null, 404);

        $mapIndex = Str::lower(trim($validated['map_index_number']));
        $mapName = Str::lower(trim((string) ($validated['map_name'] ?? '')));
        $mapPhone = Str::lower(trim((string) ($validated['map_phone'] ?? '')));

        $departmentIds = $this->departmentIds();
        abort_unless($departmentIds->contains((int) $program->department_id), 403);

        $year = (int) ($validated['year'] ?? now()->year);
        $rows = $this->parseCsvFile($request->file('csv_file')->getRealPath());

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'The uploaded CSV appears to be empty.']);
        }

        $universityId = (int) $program->university_id;
        $previewRows = [];
        $validRows = [];
        $seenIndexes = [];
        $seenPhones = [];

        foreach ($rows as $rowNumber => $row) {
            $name = $mapName !== '' ? trim((string) ($row[$mapName] ?? '')) : '';
            $phoneRaw = $mapPhone !== '' ? trim((string) ($row[$mapPhone] ?? '')) : '';
            $indexNumber = trim((string) ($row[$mapIndex] ?? ''));

            $errors = [];

            $normalizedPhone = $phoneRaw !== '' ? StudentPhone::normalize($phoneRaw) : null;
            if ($phoneRaw !== '' && $normalizedPhone === null) {
                $errors[] = 'Phone number could not be normalized.';
            }
            if ($normalizedPhone !== null && ! StudentPhone::isGhanaMobile($normalizedPhone)) {
                $errors[] = 'Phone must be a valid Ghana mobile number when provided.';
            }
            if ($normalizedPhone !== null) {
                $phoneKey = $universityId.'|'.$normalizedPhone;
                if (isset($seenPhones[$phoneKey])) {
                    $errors[] = 'Duplicate phone in file.';
                }
                $seenPhones[$phoneKey] = true;

                if ($universityId > 0 && $this->universityHasStudentPhone($universityId, $normalizedPhone)) {
                    $errors[] = 'Phone already exists for another student in this institution.';
                }
            }

            if ($indexNumber !== '') {
                $idxKey = $universityId.'|'.Str::upper($indexNumber);
                if (isset($seenIndexes[$idxKey])) {
                    $errors[] = 'Duplicate index number in file.';
                }
                $seenIndexes[$idxKey] = true;

                if ($universityId > 0 && User::query()->where('university_id', $universityId)->where('index_number', $indexNumber)->exists()) {
                    $errors[] = 'Index number already exists for this institution.';
                }
            }

            $previewRow = [
                'row_number' => $rowNumber + 2,
                'name' => $name,
                'phone' => $normalizedPhone,
                'index_number' => $indexNumber,
                'class_name' => $classroom->name,
                'class_id' => $classroom->id,
                'program' => $program->name,
                'program_id' => $program->id,
                'program_code' => $program->code,
                'university_id' => $universityId,
                'level' => $level->name,
                'level_id' => $level->id,
                'errors' => $errors,
            ];

            $previewRows[] = $previewRow;

            if (empty($errors)) {
                $validRows[] = $previewRow;
            }
        }

        session([
            'student_csv_import' => [
                'year' => $year,
                'rows' => $validRows,
                'locked_classroom_id' => $classroom->id,
            ],
        ]);

        return view('coordinator.students.preview', [
            'previewRows' => $previewRows,
            'validCount' => count($validRows),
            'invalidCount' => count($previewRows) - count($validRows),
            'year' => $year,
            'lockedClassroom' => $classroom,
            'backUrl' => route('coordinator.classes.students.upload', $classroom),
        ]);
    }

    public function classScopedTemplate(Classroom $classroom): StreamedResponse
    {
        $this->authorize('viewStudentDirectory');
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

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('viewStudentDirectory');

        $payload = $request->session()->get('student_csv_import');

        if (! is_array($payload) || empty($payload['rows'])) {
            return redirect()->route('coordinator.students.upload')->withErrors([
                'csv_file' => 'No preview data found. Please upload and preview your CSV first.',
            ]);
        }

        $rows = $payload['rows'];
        $year = (int) ($payload['year'] ?? now()->year);
        $studentRoleId = Role::query()->where('slug', 'student')->value('id');
        $serialCache = [];
        $imported = 0;
        $unassigned = 0;

        DB::transaction(function () use ($rows, $year, $studentRoleId, &$serialCache, &$imported, &$unassigned): void {
            foreach ($rows as $row) {
                $classId = $row['class_id'] ?? null;
                if ($classId === null) {
                    $classId = Classroom::query()
                        ->where('program_id', $row['program_id'])
                        ->where('level_id', $row['level_id'])
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->value('id');
                }

                if (! $classId) {
                    $unassigned++;
                }

                $indexNumber = ($row['index_number'] ?? '') !== ''
                    ? $row['index_number']
                    : $this->nextIndexNumber($row['program_code'] ?: $row['program'], $year, $serialCache);

                $student = User::create([
                    'university_id' => $row['university_id'],
                    'program_id' => $row['program_id'],
                    'level_id' => $row['level_id'],
                    'class_id' => $classId,
                    'name' => ($row['name'] ?? '') !== '' ? $row['name'] : '',
                    'email' => null,
                    'phone' => $row['phone'] ?? null,
                    'index_number' => $indexNumber,
                    'role' => 'student',
                    'is_active' => $classId !== null,
                    'email_verified_at' => null,
                    'student_onboarded_at' => null,
                    'password' => Str::password(32),
                ]);

                if ($studentRoleId) {
                    DB::table('role_user')->updateOrInsert(
                        ['role_id' => $studentRoleId, 'user_id' => $student->id],
                        ['created_at' => now(), 'updated_at' => now()],
                    );
                }

                $imported++;
            }
        });

        $lockedClassroomId = isset($payload['locked_classroom_id']) ? (int) $payload['locked_classroom_id'] : null;

        $request->session()->forget('student_csv_import');

        $message = "Imported {$imported} student(s) successfully.";
        if ($unassigned > 0) {
            $message .= " {$unassigned} student(s) were left unassigned and set inactive.";
        }

        if ($lockedClassroomId > 0) {
            $targetClass = Classroom::query()->find($lockedClassroomId);
            if ($targetClass !== null) {
                $this->authorize('view', $targetClass);

                return redirect()->route('coordinator.classes.show', $targetClass)->with('status', $message);
            }
        }

        return redirect()->route('coordinator.students.index')->with('status', $message);
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $this->authorize('viewStudentDirectory');

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', 'in:activate,deactivate,delete'],
        ]);

        $departmentIds = $this->departmentIds();
        $action = $validated['action'];
        $isActive = $action === 'activate';

        $query = User::query()
            ->whereIn('id', $validated['student_ids'])
            ->where('role', 'student')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds));

        if ($action === 'delete') {
            $students = (clone $query)->get(['id', 'class_id']);
            $deleted = 0;
            $skipped = 0;

            foreach ($students as $student) {
                if ((int) ($student->class_id ?? 0) > 0 || $this->studentHasAcademicData((int) $student->id)) {
                    $skipped++;

                    continue;
                }

                User::query()->whereKey($student->id)->delete();
                $deleted++;
            }

            $message = __('Deleted :deleted student(s). :skipped skipped (assigned to class or has quiz/exam data).', [
                'deleted' => $deleted,
                'skipped' => $skipped,
            ]);

            return redirect()->route('coordinator.students.index')->with('status', $message);
        }

        if ($isActive && (clone $query)->whereNull('class_id')->exists()) {
            return redirect()->route('coordinator.students.index')->withErrors([
                'action' => 'Students without class assignment cannot be activated.',
            ]);
        }

        $query->update(['is_active' => $isActive]);

        return redirect()->route('coordinator.students.index')->with('status', 'Student statuses updated.');
    }

    public function template(): StreamedResponse
    {
        $this->authorize('viewStudentDirectory');

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');
            // Class rosters: Classes → group → Upload roster (index_number, name, phone only).
            fputcsv($handle, ['index_number', 'name', 'phone', 'program', 'level', 'class_name']);
            fputcsv($handle, ['BCS/2026/001', 'Akua Serwaa', '+233241112233', 'BCS', '100', '']);
            fputcsv($handle, ['', 'Yaw Boateng', '', 'BCS', '100', 'Group A']);
            fclose($handle);
        }, 'student-upload-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function parseCsvFile(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'rb');

        if (! $handle) {
            return $rows;
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return $rows;
        }

        $headers = array_map(fn ($header) => Str::lower(trim((string) $header)), $headers);

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($data[$index] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function nextIndexNumber(string $programCodeOrName, int $year, array &$cache): string
    {
        $normalizedProgramCode = Str::upper(Str::of($programCodeOrName)->replaceMatches('/[^A-Za-z0-9]/', '')->limit(6, ''));
        $normalizedProgramCode = $normalizedProgramCode !== '' ? $normalizedProgramCode : 'GEN';

        $key = "{$normalizedProgramCode}-{$year}";

        if (! isset($cache[$key])) {
            $prefix = "{$normalizedProgramCode}/{$year}/";
            $maxSerial = User::query()
                ->where('index_number', 'like', "{$prefix}%")
                ->pluck('index_number')
                ->map(function ($indexNumber) {
                    $parts = explode('/', (string) $indexNumber);

                    return (int) ($parts[2] ?? 0);
                })
                ->max();

            $cache[$key] = (int) $maxSerial;
        }

        $cache[$key]++;

        return sprintf('%s/%d/%03d', $normalizedProgramCode, $year, $cache[$key]);
    }

    private function universityHasStudentPhone(int $universityId, string $normalizedPhone): bool
    {
        return User::query()
            ->where('university_id', $universityId)
            ->where('role', 'student')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['phone'])
            ->contains(fn (User $u): bool => StudentPhone::normalize($u->phone) === $normalizedPhone);
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

    private function studentHasAcademicData(int $studentId): bool
    {
        return DB::table('exam_sessions')->where('student_id', $studentId)->exists()
            || DB::table('results')->where('user_id', $studentId)->exists()
            || DB::table('practice_quizzes')->where('student_id', $studentId)->exists()
            || DB::table('practice_attempts')->where('student_id', $studentId)->exists()
            || DB::table('practice_summaries')->where('student_id', $studentId)->exists();
    }

    private function departmentIds()
    {
        return auth()->user()->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id');
    }

    private function scopedClasses()
    {
        return Classroom::query()
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $this->departmentIds()))
            ->with(['program.department', 'level'])
            ->orderBy('name')
            ->get();
    }
}
