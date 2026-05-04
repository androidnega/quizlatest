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
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewStudentDirectory');

        $departmentIds = $this->departmentIds();
        $programs = Program::query()->whereIn('department_id', $departmentIds)->orderBy('name')->get();
        $levels = Level::query()->orderBy('sort_order')->get();
        $classes = Classroom::query()
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->with(['program', 'level'])
            ->orderBy('name')
            ->get();

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
                        ->orWhere('index_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('coordinator.students.index', [
            'students' => $students,
            'programs' => $programs,
            'levels' => $levels,
            'classes' => $classes,
            'filters' => $request->only(['program_id', 'level_id', 'search']),
        ]);
    }

    public function editClass(User $student): View
    {
        $this->authorize('manageStudentInScope', $student);

        return view('coordinator.students.assign-class', [
            'student' => $student->load(['program.department', 'level', 'classroom']),
            'classes' => $this->scopedClasses(),
        ]);
    }

    public function updateClass(Request $request, User $student): RedirectResponse
    {
        $this->authorize('manageStudentInScope', $student);

        $validated = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        $classId = $validated['class_id'] ?? null;
        if ($classId === null && $student->is_active) {
            return redirect()->back()->withErrors([
                'class_id' => 'Active students must have a class assignment.',
            ]);
        }

        if ($classId !== null) {
            $classroom = Classroom::query()->find((int) $classId);
            abort_if($classroom === null, 404);
            $this->authorize('view', $classroom);

            abort_unless((int) $student->program_id === (int) $classroom->program_id, 422);
        }

        $student->update(['class_id' => $classId]);

        return redirect()->route('coordinator.students.index')->with('status', 'Student class assignment updated.');
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
            'map_email' => ['nullable', 'string', 'max:64'],
            'map_phone' => ['nullable', 'string', 'max:64'],
            'map_program' => ['required', 'string', 'max:64'],
            'map_level' => ['required', 'string', 'max:64'],
            'map_class_name' => ['nullable', 'string', 'max:64'],
            'year' => ['nullable', 'digits:4'],
        ]);

        $mapIndex = Str::lower(trim($validated['map_index_number']));
        $mapName = Str::lower(trim((string) ($validated['map_name'] ?? '')));
        $mapEmail = Str::lower(trim((string) ($validated['map_email'] ?? '')));
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
            $email = $mapEmail !== '' ? trim((string) ($row[$mapEmail] ?? '')) : '';
            $phoneRaw = $mapPhone !== '' ? trim((string) ($row[$mapPhone] ?? '')) : '';
            $indexNumber = trim((string) ($row[$mapIndex] ?? ''));
            $programRaw = trim((string) ($row[$mapProgram] ?? ''));
            $levelRaw = trim((string) ($row[$mapLevel] ?? ''));
            $classNameRaw = $mapClassName !== '' ? trim((string) ($row[$mapClassName] ?? '')) : '';

            $errors = [];

            if ($email !== '' && User::query()->where('email', $email)->exists()) {
                $errors[] = 'Email already exists.';
            }

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
                'email' => $email !== '' ? $email : null,
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
        ]);
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
                    'email' => $row['email'] ?? null,
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

        $request->session()->forget('student_csv_import');

        $message = "Imported {$imported} student(s) successfully.";
        if ($unassigned > 0) {
            $message .= " {$unassigned} student(s) were left unassigned and set inactive.";
        }

        return redirect()->route('coordinator.students.index')->with('status', $message);
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $this->authorize('viewStudentDirectory');

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', 'in:activate,deactivate'],
        ]);

        $departmentIds = $this->departmentIds();
        $isActive = $validated['action'] === 'activate';

        $query = User::query()
            ->whereIn('id', $validated['student_ids'])
            ->where('role', 'student')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds));

        if ($isActive && (clone $query)->whereNull('class_id')->exists()) {
            return redirect()->route('coordinator.students.index')->withErrors([
                'action' => 'Students without class assignment cannot be activated.',
            ]);
        }

        $query->update(['is_active' => $isActive]);

        return redirect()->route('coordinator.students.index')->with('status', 'Student statuses updated.');
    }

    public function bulkAssignClass(Request $request): RedirectResponse
    {
        $this->authorize('viewStudentDirectory');

        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
        ]);

        $departmentIds = $this->departmentIds();

        $program = Program::query()->find((int) $validated['program_id']);
        abort_if($program === null, 404);
        $this->authorize('view', $program);

        $classroom = Classroom::query()
            ->where('id', $validated['class_id'])
            ->where('program_id', $validated['program_id'])
            ->where('level_id', $validated['level_id'])
            ->first();
        abort_if($classroom === null, 404);
        $this->authorize('view', $classroom);

        $updated = User::query()
            ->where('role', 'student')
            ->where('program_id', $validated['program_id'])
            ->where('level_id', $validated['level_id'])
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->update([
                'class_id' => $classroom->id,
                'is_active' => true,
            ]);

        return redirect()->route('coordinator.students.index')->with('status', "Assigned class to {$updated} student(s).");
    }

    public function template(): StreamedResponse
    {
        $this->authorize('viewStudentDirectory');

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['index_number', 'name', 'phone', 'program', 'level', 'class_name']);
            fputcsv($handle, ['BCS/2026/001', 'Akua Serwaa', '+233241112233', 'BCS', '100', '']);
            fputcsv($handle, ['', 'Yaw Boateng', '', 'BCS', '100', 'A']);
            fclose($handle);
        }, 'student-upload-template.csv', ['Content-Type' => 'text/csv']);
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
