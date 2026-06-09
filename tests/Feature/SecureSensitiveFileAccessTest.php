<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Department;
use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\Quiz;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecureSensitiveFileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_examiner_can_stream_verification_evidence_from_private_disk(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        Storage::fake('public');

        $rel = 'proctoring/user_'.$ctx['session']->student_id.'/session_'.$ctx['session']->id.'/verification.jpg';
        Storage::disk('local')->put($rel, '%PNG fake');
        $ctx['session']->update(['verification_image_path' => $rel]);

        $this->actingAs($ctx['examiner']);
        $this->get(route('examiner.exam-sessions.evidence.verification', $ctx['session']))
            ->assertOk();
    }

    public function test_examiner_can_stream_legacy_public_verification_image(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        Storage::fake('public');

        $rel = 'proctoring/user_'.$ctx['session']->student_id.'/session_'.$ctx['session']->id.'/verification.jpg';
        Storage::disk('public')->put($rel, '%PNG legacy');
        $ctx['session']->update(['verification_image_path' => $rel]);

        $this->actingAs($ctx['examiner']);
        $this->get(route('examiner.exam-sessions.evidence.verification', $ctx['session']))
            ->assertOk();
    }

    public function test_coordinator_without_course_access_cannot_stream_verification_evidence(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => null,
            'code' => 'ORPHAN',
            'title' => 'No dept',
            'credit_hours' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'X',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $coord->id,
            'title' => 'Other',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'questions_per_student' => 1,
            'randomize_questions' => false,
            'randomize_options' => false,
            'proctoring_settings' => json_encode(new \stdClass),
            'published_at' => null,
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = ExamSession::query()->create([
            'student_id' => User::query()->where('role', 'student')->value('id'),
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
            'verification_image_path' => 'proctoring/x.jpg',
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('proctoring/x.jpg', 'x');

        $this->actingAs($coord);
        $this->get(route('examiner.exam-sessions.evidence.verification', $session))
            ->assertForbidden();
    }

    public function test_examiner_can_stream_proctoring_snapshot_for_matching_event(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        Storage::fake('public');

        $snap = 'proctoring/user_'.$ctx['session']->student_id.'/session_'.$ctx['session']->session_id.'/snap.jpg';
        Storage::disk('local')->put($snap, '%PNG snap');

        $event = ProctoringEvent::query()->create([
            'user_id' => $ctx['session']->student_id,
            'quiz_id' => $ctx['session']->exam_id,
            'event_type' => 'face_missing',
            'severity' => 1,
            'flagged' => false,
            'action_taken' => null,
            'metadata' => [
                'file_path' => $snap,
                'session_id' => $ctx['session']->session_id,
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($ctx['examiner']);
        $this->get(route('examiner.exam-sessions.evidence.event', [$ctx['session'], $event]))
            ->assertOk();
    }

    public function test_snapshot_route_returns_404_when_event_session_id_mismatches(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        $snap = 'proctoring/user_'.$ctx['session']->student_id.'/session_bad/snap.jpg';
        Storage::disk('local')->put($snap, '%PNG snap');

        $event = ProctoringEvent::query()->create([
            'user_id' => $ctx['session']->student_id,
            'quiz_id' => $ctx['session']->exam_id,
            'event_type' => 'face_missing',
            'severity' => 1,
            'flagged' => false,
            'action_taken' => null,
            'metadata' => [
                'file_path' => $snap,
                'session_id' => 'wrong-session',
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($ctx['examiner']);
        $this->get(route('examiner.exam-sessions.evidence.event', [$ctx['session'], $event]))
            ->assertNotFound();
    }

    public function test_admin_can_stream_verification_evidence(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        $rel = 'proctoring/user_'.$ctx['session']->student_id.'/session_'.$ctx['session']->id.'/verification.jpg';
        Storage::disk('local')->put($rel, '%PNG fake');
        $ctx['session']->update(['verification_image_path' => $rel]);

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $this->actingAs($admin);
        $this->get(route('admin.exam-sessions.evidence.verification', $ctx['session']))
            ->assertOk();
    }

    public function test_session_review_page_does_not_embed_raw_storage_paths(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        $rel = 'proctoring/user_'.$ctx['session']->student_id.'/session_'.$ctx['session']->id.'/verification.jpg';
        Storage::disk('local')->put($rel, '%PNG fake');
        $ctx['session']->update(['verification_image_path' => $rel]);

        $this->actingAs($ctx['examiner']);
        $this->get(route('examiner.exam-sessions.show', [
            'exam' => $ctx['session']->exam,
            'examSession' => $ctx['session'],
        ]))
            ->assertOk()
            ->assertDontSee($rel, false);
    }

    public function test_student_can_download_visible_course_material(): void
    {
        $ctx = $this->seedPracticeMaterialContext();
        $this->actingAs($ctx['student']);

        $this->get(route('student.practice.materials.download', $ctx['material']))
            ->assertOk();
    }

    public function test_student_cannot_download_material_scoped_to_another_class(): void
    {
        $ctx = $this->seedPracticeMaterialContext(restrictMaterialToAltClass: true);
        $this->actingAs($ctx['student']);

        $this->get(route('student.practice.materials.download', $ctx['material']))
            ->assertNotFound();
    }

    public function test_examiner_can_download_course_material_they_manage(): void
    {
        $ctx = $this->seedPracticeMaterialContext();
        $this->actingAs($ctx['examiner']);

        $this->get(route('examiner.courses.materials.download', [$ctx['course'], $ctx['material']]))
            ->assertOk();
    }

    public function test_authenticated_user_can_stream_own_face_portrait(): void
    {
        $this->seed(InitialSetupSeeder::class);
        Storage::fake('local');
        Storage::fake('public');

        $student = User::query()->where('role', 'student')->firstOrFail();
        $rel = 'proctoring/face-templates/test-face.jpg';
        Storage::disk('local')->put($rel, '%PNG face');
        $student->update(['face_image_path' => $rel]);

        $this->actingAs($student);
        $this->get(route('profile.face-image'))
            ->assertOk();
    }

    public function test_review_timeline_forbids_unauthorized_student(): void
    {
        $ctx = $this->seedScopedExamContext();
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->getJson(route('exam-sessions.review-timeline', $ctx['session']))
            ->assertForbidden();
    }

    public function test_review_timeline_json_contains_no_raw_metadata_or_paths(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        Storage::fake('public');

        $secretPath = 'proctoring/user_'.$ctx['session']->student_id.'/SECRET_PATH_MARKER_abc/snap.jpg';
        ProctoringEvent::query()->create([
            'user_id' => $ctx['session']->student_id,
            'quiz_id' => $ctx['session']->exam_id,
            'event_type' => 'tab_switch',
            'severity' => 1,
            'flagged' => true,
            'action_taken' => 'warn',
            'metadata' => [
                'file_path' => $secretPath,
                'session_id' => $ctx['session']->session_id,
                'payload' => ['file_path' => $secretPath, 'upload_token' => 'tok-secret'],
                'face_embedding' => [0.1, 0.2, 0.3],
            ],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($ctx['examiner'])
            ->getJson(route('exam-sessions.review-timeline', $ctx['session']));

        $response->assertOk();
        $this->assertStringNotContainsString('SECRET_PATH_MARKER_abc', $response->getContent());
        $this->assertStringNotContainsString('tok-secret', $response->getContent());
        $this->assertStringNotContainsString('face_embedding', $response->getContent());

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('captured_images', $data);
        $this->assertArrayHasKey('events', $data);
        foreach ($data['events'] as $ev) {
            $this->assertArrayNotHasKey('metadata', $ev);
            $this->assertArrayNotHasKey('file_path', $ev);
            $this->assertArrayHasKey('has_evidence', $ev);
            $this->assertFalse($ev['has_evidence']);
            $this->assertArrayNotHasKey('evidence_url', $ev);
        }
    }

    public function test_review_timeline_includes_evidence_url_when_file_exists_on_public_disk(): void
    {
        $ctx = $this->seedScopedExamContext();
        Storage::fake('local');
        Storage::fake('public');

        $rel = 'proctoring/user_'.$ctx['session']->student_id.'/legacy_pub.jpg';
        Storage::disk('public')->put($rel, '%PNG x');

        $event = ProctoringEvent::query()->create([
            'user_id' => $ctx['session']->student_id,
            'quiz_id' => $ctx['session']->exam_id,
            'event_type' => 'face_missing',
            'severity' => 2,
            'flagged' => false,
            'action_taken' => null,
            'metadata' => [
                'file_path' => $rel,
                'session_id' => $ctx['session']->session_id,
            ],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($ctx['examiner'])
            ->getJson(route('exam-sessions.review-timeline', $ctx['session']));

        $response->assertOk();
        $this->assertStringNotContainsString($rel, $response->getContent());

        $events = $response->json('events');
        $this->assertIsArray($events);
        $match = collect($events)->firstWhere('id', $event->id);
        $this->assertNotNull($match);
        $this->assertTrue($match['has_evidence']);
        $this->assertArrayHasKey('evidence_url', $match);
        $this->assertStringContainsString('/evidence/events/'.$event->id, $match['evidence_url']);
    }

    /**
     * @return array{examiner: User, exam: Quiz, session: ExamSession}
     */
    private function seedScopedExamContext(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.secure.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS101',
            'title' => 'Intro CS',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $courseId,
            'examiner_user_id' => $examiner->id,
            'assigned_by' => null,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'A',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Midterm',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'questions_per_student' => 1,
            'randomize_questions' => false,
            'randomize_options' => false,
            'proctoring_settings' => json_encode(new \stdClass),
            'published_at' => null,
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::query()->where('role', 'student')->firstOrFail();

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
        ]);

        $exam = Quiz::query()->findOrFail($quizId);

        return ['examiner' => $examiner->fresh(), 'exam' => $exam, 'session' => $session];
    }

    /**
     * @return array{course: Course, material: CourseMaterial, student: User, examiner: User}
     */
    private function seedPracticeMaterialContext(bool $restrictMaterialToAltClass = false): array
    {
        $this->seed(InitialSetupSeeder::class);
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $system = app(SystemSettingsService::class);
        $system->set('enable_student_practice_quizzes', '1', $admin);
        $system->set('enable_course_material_uploads', '1', $admin);

        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $coord->university_id,
            'email' => 'examiner.matsec.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $dept = Department::query()->where('code', 'CS')->firstOrFail();

        $course = Course::query()->create([
            'university_id' => $coord->university_id,
            'department_id' => $dept->id,
            'code' => 'MATSEC101',
            'title' => 'Material security course',
            'credit_hours' => 3,
            'is_active' => true,
        ]);

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $course->id,
            'examiner_user_id' => $examiner->id,
            'assigned_by' => null,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uniId = (int) $coord->university_id;
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $classA = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'SecA',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classB = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'SecB',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('class_course')->insert([
            [
                'class_id' => $classA,
                'course_id' => $course->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'class_id' => $classB,
                'course_id' => $course->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $student = User::query()->where('role', 'student')->firstOrFail();
        $student->update(['class_id' => $classA]);

        Storage::fake('local');
        $rel = 'course_materials/900/readme.txt';
        Storage::disk('local')->put($rel, 'hello materials');

        $material = CourseMaterial::query()->create([
            'course_id' => $course->id,
            'class_id' => $restrictMaterialToAltClass ? $classB : null,
            'uploaded_by' => $examiner->id,
            'title' => 'Lecture notes',
            'material_kind' => CourseMaterial::KIND_SUPPLEMENTARY,
            'file_path' => $rel,
            'file_type' => 'txt',
            'extracted_text_path' => null,
            'status' => CourseMaterial::STATUS_READY,
            'extraction_error' => null,
        ]);

        return [
            'course' => $course,
            'material' => $material,
            'student' => $student,
            'examiner' => $examiner->fresh(),
        ];
    }
}
