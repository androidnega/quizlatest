<?php

namespace Tests\Feature\Coordinator;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoordinatorClassesFilterTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    private function seedClassNamed(string $name): Classroom
    {
        $coord = $this->coordinator();
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');
        $year = AcademicYear::activeForUniversity((int) $coord->university_id);

        return Classroom::query()->create([
            'university_id' => $coord->university_id,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => $name,
            'section' => null,
            'academic_year' => $year?->name,
            'academic_year_id' => $year?->id,
            'is_active' => true,
        ]);
    }

    public function test_classes_index_live_search_finds_class(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $this->seedClassNamed('Quantum Algorithms Lab');

        $this->actingAs($coord)
            ->get(route('coordinator.classes.index', ['q' => 'Quantum']))
            ->assertOk()
            ->assertSee('Quantum Algorithms Lab', false);
    }

    public function test_classes_index_program_filter(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $this->seedClassNamed('Filtered Class Alpha');
        $bcsId = (int) DB::table('programs')->where('code', 'BCS')->value('id');

        $this->actingAs($coord)
            ->get(route('coordinator.classes.index', ['program_id' => $bcsId]))
            ->assertOk()
            ->assertSee('Filtered Class Alpha', false);
    }
}
