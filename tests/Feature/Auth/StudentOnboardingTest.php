<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_page_loads_for_student_session(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->withSession([
            'student_onboarding_user_id' => $user->id,
            'student_onboarding_verified_at' => now()->timestamp,
        ])
            ->get('/student/onboarding')
            ->assertOk()
            ->assertSee('Finish enrolling your account')
            ->assertSee('ob-start')
            ->assertSee('ob-capture')
            ->assertSee('ob-retry');
    }

    public function test_onboarding_rejects_missing_face_payload(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->withSession([
            'student_onboarding_user_id' => $user->id,
            'student_onboarding_verified_at' => now()->timestamp,
        ])
            ->post('/student/onboarding', [
                'name' => 'Student Name',
                'password' => 'NewSecurePass9!',
                'password_confirmation' => 'NewSecurePass9!',
            ])
            ->assertSessionHasErrors(['face_embedding_json', 'face_liveness_embedding_json']);
    }

    public function test_student_can_complete_onboarding_and_reach_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/099',
            'phone' => '233241112233',
            'name' => '',
            'email' => null,
            'is_active' => true,
            'student_onboarded_at' => null,
            'email_verified_at' => null,
        ]);

        $embeddingA = [];
        $embeddingB = [];
        for ($i = 0; $i < 18; $i++) {
            $embeddingA[] = sin($i) * 0.5 + 0.1;
        }
        foreach ($embeddingA as $i => $v) {
            $embeddingB[] = $v + cos($i) * 0.05;
        }

        $response = $this->withSession([
            'student_onboarding_user_id' => $user->id,
            'student_onboarding_verified_at' => now()->timestamp,
        ])
            ->post('/student/onboarding', [
                'name' => 'Updated Name',
                'password' => 'NewSecurePass9!',
                'password_confirmation' => 'NewSecurePass9!',
                'face_embedding_json' => json_encode($embeddingA),
                'face_liveness_embedding_json' => json_encode($embeddingB),
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user->refresh();
        $this->assertNotNull($user->student_onboarded_at);
        $this->assertSame('Updated Name', $user->name);
        $this->assertIsArray($user->face_embedding);
    }
}
