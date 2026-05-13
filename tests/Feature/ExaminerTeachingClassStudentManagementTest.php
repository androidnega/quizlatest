<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExaminerTeachingClassStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, classroom: Classroom, courseId: int}
     */
    private function seedTeachingClassContext(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');
        $admin = User::query()->where('email', 'admin')->firstOrFail();

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner-tcs-'.Str::lower(Str::random(8)).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'TCS401',
            'title' => 'Teaching Class Students',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $courseId,
            'examiner_user_id' => $examiner->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classroom = Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'TCS-CLASS',
            'section' => null,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        DB::table('class_course')->insert([
            'class_id' => $classroom->id,
            'course_id' => $courseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['examiner' => $examiner, 'classroom' => $classroom, 'courseId' => $courseId];
    }

    public function test_examiner_can_add_student_to_class_roster_and_view_profile(): void
    {
        $ctx = $this->seedTeachingClassContext();
        $examiner = $ctx['examiner'];
        $classroom = $ctx['classroom'];
        $courseId = $ctx['courseId'];

        $index = 'BCS/2099/777';

        $this->actingAs($examiner)
            ->post(route('examiner.teaching-classes.students.store', $classroom), [
                'name' => 'Roster Test Student',
                'index_number' => $index,
                'phone' => '',
            ])
            ->assertRedirect(route('examiner.teaching-classes.students.index', $classroom));

        $this->assertDatabaseHas('users', [
            'name' => 'Roster Test Student',
            'index_number' => $index,
            'class_id' => $classroom->id,
            'role' => 'student',
        ]);

        $student = User::query()->where('index_number', $index)->firstOrFail();

        $quiz = Quiz::query()->create([
            'university_id' => (int) $classroom->university_id,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Linked quiz',
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
        ]);

        DB::table('exam_sessions')->insert([
            'student_id' => $student->id,
            'class_id' => $classroom->id,
            'exam_id' => $quiz->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now(),
            'end_time' => now(),
            'violation_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($examiner)
            ->get(route('examiner.teaching-classes.students.show', [$classroom, $student]))
            ->assertOk()
            ->assertSee('Roster Test Student', false)
            ->assertSee($index, false)
            ->assertSee('Linked quiz', false)
            ->assertSee('Teaching Class Students', false);
    }

    public function test_examiner_cannot_add_duplicate_index_on_same_class(): void
    {
        $ctx = $this->seedTeachingClassContext();
        $examiner = $ctx['examiner'];
        $classroom = $ctx['classroom'];

        $payload = [
            'name' => 'First',
            'index_number' => 'BCS/2099/888',
            'phone' => '',
        ];

        $this->actingAs($examiner)
            ->post(route('examiner.teaching-classes.students.store', $classroom), $payload)
            ->assertRedirect(route('examiner.teaching-classes.students.index', $classroom));

        $this->actingAs($examiner)
            ->from(route('examiner.teaching-classes.show', $classroom))
            ->post(route('examiner.teaching-classes.students.store', $classroom), [
                'name' => 'Second',
                'index_number' => 'BCS/2099/888',
                'phone' => '',
            ])
            ->assertRedirect(route('examiner.teaching-classes.show', $classroom))
            ->assertSessionHasErrors('index_number');
    }

    public function test_examiner_cannot_reuse_existing_institution_index_from_another_record(): void
    {
        $ctx = $this->seedTeachingClassContext();
        $examiner = $ctx['examiner'];
        $classroom = $ctx['classroom'];

        $existing = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($examiner)
            ->from(route('examiner.teaching-classes.show', $classroom))
            ->post(route('examiner.teaching-classes.students.store', $classroom), [
                'name' => 'Clone Attempt',
                'index_number' => $existing->index_number,
                'phone' => '',
            ])
            ->assertRedirect(route('examiner.teaching-classes.show', $classroom))
            ->assertSessionHasErrors('index_number');
    }

    public function test_examiner_cannot_open_student_not_in_this_class(): void
    {
        $ctx = $this->seedTeachingClassContext();
        $examiner = $ctx['examiner'];
        $classroom = $ctx['classroom'];

        $otherClass = Classroom::query()->create([
            'university_id' => $classroom->university_id,
            'program_id' => $classroom->program_id,
            'level_id' => $classroom->level_id,
            'name' => 'OTHER-CLASS',
            'section' => null,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $studentElsewhere = User::factory()->create([
            'role' => 'student',
            'university_id' => $classroom->university_id,
            'program_id' => $classroom->program_id,
            'level_id' => $classroom->level_id,
            'class_id' => $otherClass->id,
            'index_number' => 'BCS/2099/666',
            'is_active' => true,
        ]);

        $this->actingAs($examiner)
            ->get(route('examiner.teaching-classes.students.show', [$classroom, $studentElsewhere]))
            ->assertNotFound();
    }

    public function test_manage_students_page_renders_roster(): void
    {
        $ctx = $this->seedTeachingClassContext();
        $examiner = $ctx['examiner'];
        $classroom = $ctx['classroom'];

        $this->actingAs($examiner)
            ->post(route('examiner.teaching-classes.students.store', $classroom), [
                'name' => 'Listing Check',
                'index_number' => 'BCS/2099/555',
                'phone' => '',
            ])
            ->assertRedirect(route('examiner.teaching-classes.students.index', $classroom));

        $this->actingAs($examiner)
            ->get(route('examiner.teaching-classes.students.index', $classroom))
            ->assertOk()
            ->assertSee('Listing Check', false)
            ->assertSee('BCS/2099/555', false);
    }

    public function test_roster_csv_contains_only_this_class_indices(): void
    {
        $ctx = $this->seedTeachingClassContext();
        $examiner = $ctx['examiner'];
        $classroom = $ctx['classroom'];

        $this->actingAs($examiner)
            ->post(route('examiner.teaching-classes.students.store', $classroom), [
                'name' => 'CSV Row',
                'index_number' => 'BCS/2099/444',
                'phone' => '',
            ]);

        $response = $this->actingAs($examiner)
            ->get(route('examiner.teaching-classes.students.roster', $classroom));

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('index_number', $body);
        $this->assertStringContainsString('BCS/2099/444', $body);
        $this->assertStringContainsString('CSV Row', $body);
    }
}
