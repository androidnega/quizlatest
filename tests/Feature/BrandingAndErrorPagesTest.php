<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_does_not_contain_laravel_branding(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Laravel', false);
    }

    public function test_login_page_does_not_contain_laravel_branding(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Laravel', false);
    }

    public function test_login_page_does_not_show_staff_login_link(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString((string) route('staff.login', [], false), $html);
    }

    public function test_staff_login_page_is_reachable_without_laravel_branding(): void
    {
        $this->get('/admin_login')
            ->assertOk()
            ->assertDontSee('Laravel', false);
    }

    public function test_guest_layout_includes_quizsnap_favicon_not_laravel_default(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('favicon.svg', $html);
        $this->assertStringNotContainsString('laravel.svg', $html);
    }

    public function test_404_page_renders_quizsnap_branding(): void
    {
        $this->get('/this-route-should-not-exist-zz-12345')
            ->assertNotFound()
            ->assertSee('QuizSnap', false)
            ->assertSee('Page not found', false);
    }

    public function test_403_page_renders_quizsnap_branding_for_student_on_admin(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->get('/dashboard/universities')
            ->assertForbidden()
            ->assertSee('QuizSnap', false);
    }

    public function test_error_500_view_does_not_echo_exception_details(): void
    {
        $html = view('errors.500', ['exception' => new \RuntimeException('INTERNAL_SECRET_XYZ')])->render();
        $this->assertStringNotContainsString('INTERNAL_SECRET_XYZ', $html);
        $this->assertStringContainsString('Something went wrong', $html);
    }
}
