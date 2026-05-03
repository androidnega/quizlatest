<?php

namespace Tests\Feature;

use App\Models\AcademicResetSnapshot;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    private function departmentId(): int
    {
        return (int) DB::table('departments')->where('code', 'CS')->value('id');
    }

    private function programId(): int
    {
        return (int) DB::table('programs')->where('code', 'BCS')->value('id');
    }

    private function level100Id(): int
    {
        return (int) DB::table('levels')->where('code', '100')->value('id');
    }

    private function level200Id(): int
    {
        return (int) DB::table('levels')->where('code', '200')->value('id');
    }

    private function activeAcademicYearId(int $universityId): int
    {
        $id = AcademicYear::activeForUniversity($universityId)?->id;

        return (int) $id;
    }

    public function test_student_cannot_access_academic_reset(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)->get(route('coordinator.academic-reset.index'))->assertForbidden();
    }

    public function test_complete_reset_deactivates_classes_and_clears_student_assignments_without_deleting_logs(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $uniId = (int) $coord->university_id;

        $ayId = $this->activeAcademicYearId($uniId);
        $this->assertGreaterThan(0, $ayId);

        $classId = Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $this->programId(),
            'level_id' => $this->level100Id(),
            'name' => 'ResetTestClass',
            'section' => null,
            'academic_year' => '2026',
            'academic_year_id' => $ayId,
            'is_active' => true,
        ])->id;

        $student = User::query()->where('role', 'student')->firstOrFail();
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        $logsBefore = ActivityLog::query()->count();

        $this->actingAs($coord);
        $response = $this->post(route('coordinator.academic-reset.preview'), [
            'department_id' => $this->departmentId(),
            'academic_year_id' => $ayId,
            'reset_type' => 'complete',
        ]);
        $response->assertRedirect();
        $snapshot = AcademicResetSnapshot::query()->latest('id')->first();
        $this->assertNotNull($snapshot);
        $this->assertNull($snapshot->applied_at);

        $this->post(route('coordinator.academic-reset.apply', $snapshot), [
            'confirmation_phrase' => 'RESET',
            'confirm_understood' => '1',
        ])->assertRedirect(route('coordinator.academic-reset.index'));

        $snapshot->refresh();
        $this->assertNotNull($snapshot->applied_at);

        $this->assertFalse((bool) Classroom::query()->find($classId)?->is_active);
        $this->assertNull(User::query()->find($student->id)?->class_id);

        $this->assertGreaterThan($logsBefore, ActivityLog::query()->count());
        $this->assertTrue(
            ActivityLog::query()->where('event_type', 'academic_reset_applied')->exists(),
        );
    }

    public function test_apply_snapshot_twice_is_rejected(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();

        $this->actingAs($coord)->post(route('coordinator.academic-reset.preview'), [
            'department_id' => $this->departmentId(),
            'academic_year_id' => $this->activeAcademicYearId((int) $coord->university_id),
            'reset_type' => 'peace',
        ])->assertRedirect();

        $snapshot = AcademicResetSnapshot::query()->latest('id')->firstOrFail();

        $this->post(route('coordinator.academic-reset.apply', $snapshot), [
            'confirmation_phrase' => 'RESET',
            'confirm_understood' => '1',
        ])->assertRedirect();

        $this->post(route('coordinator.academic-reset.apply', $snapshot->fresh()), [
            'confirmation_phrase' => 'RESET',
            'confirm_understood' => '1',
        ])->assertStatus(422);
    }

    public function test_review_rejects_non_initiating_coordinator(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();

        $this->actingAs($coord)->post(route('coordinator.academic-reset.preview'), [
            'department_id' => $this->departmentId(),
            'academic_year_id' => $this->activeAcademicYearId((int) $coord->university_id),
            'reset_type' => 'peace',
        ])->assertRedirect();

        $snapshot = AcademicResetSnapshot::query()->latest('id')->firstOrFail();
        $student = User::query()->where('role', 'student')->firstOrFail();
        $snapshot->update(['initiated_by' => $student->id]);

        $this->actingAs($coord)
            ->get(route('coordinator.academic-reset.review', $snapshot))
            ->assertForbidden();
    }

    public function test_continual_reset_promotes_students_to_next_level(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();

        $this->actingAs($coord)->post(route('coordinator.academic-reset.preview'), [
            'department_id' => $this->departmentId(),
            'academic_year_id' => $this->activeAcademicYearId((int) $coord->university_id),
            'reset_type' => 'continual',
            'program_ids' => [$this->programId()],
            'level_ids' => [$this->level100Id()],
        ])->assertRedirect();

        $snapshot = AcademicResetSnapshot::query()->latest('id')->firstOrFail();

        $this->post(route('coordinator.academic-reset.apply', $snapshot), [
            'confirmation_phrase' => 'RESET',
            'confirm_understood' => '1',
        ])->assertRedirect();

        $this->assertSame(
            0,
            User::query()->where('role', 'student')->where('level_id', $this->level100Id())->count(),
        );
        $this->assertGreaterThanOrEqual(
            1,
            User::query()->where('role', 'student')->where('level_id', $this->level200Id())->count(),
        );
    }

    public function test_admin_can_view_snapshots_index(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $uniId = (int) $admin->university_id;

        AcademicResetSnapshot::query()->create([
            'department_id' => $this->departmentId(),
            'academic_year_id' => $this->activeAcademicYearId($uniId),
            'initiated_by' => $this->coordinator()->id,
            'reset_type' => 'complete',
            'payload' => [
                'reset_type' => 'complete',
                'frozen_class_ids' => [],
                'frozen_student_ids' => [],
                'university_id' => $uniId,
            ],
            'summary' => ['class_count' => 0],
            'applied_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.academic-reset-snapshots.index'))
            ->assertOk();
    }
}
