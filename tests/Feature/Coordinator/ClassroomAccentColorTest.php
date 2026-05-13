<?php

namespace Tests\Feature\Coordinator;

use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassroomAccentColorTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    public function test_store_class_persists_accent_color(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $this->actingAs($coord)->post(route('coordinator.classes.store'), [
            'name' => 'Color Lab Section',
            'program_id' => $programId,
            'level_id' => $levelId,
            'is_active' => '1',
            'accent_color' => '#c026d3',
        ])->assertRedirect(route('coordinator.classes.index'));

        $this->assertSame('#C026D3', Classroom::query()->where('name', 'Color Lab Section')->value('accent_color'));
    }

    public function test_null_accent_falls_back_to_default_hex(): void
    {
        $classroom = new Classroom([
            'accent_color' => null,
        ]);

        $this->assertSame(Classroom::DEFAULT_ACCENT_COLOR, $classroom->accentHex());
    }
}
