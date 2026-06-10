<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Tests\TestCase;

/**
 * The exam-taking surface is locked to desktop browsers until the
 * mobile UI ships. This guards the contract:
 *   - phone-class User-Agents always get 423 (Locked) on a taking
 *     route, regardless of auth state, before any controller code
 *     runs;
 *   - AJAX requests get JSON-shaped 423 so the runtime client can
 *     handle the lock without trying to parse HTML;
 *   - desktop-class User-Agents are NOT short-circuited by this
 *     middleware (they may still redirect/4xx for other reasons —
 *     auth, missing session — but never with code 423);
 *   - the 'desktop' middleware is wired onto the routes the user
 *     would actually hit to take a quiz.
 */
class DesktopOnlyExamGateTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const ANDROID_PHONE_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';

    private const DESKTOP_CHROME_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function test_iphone_user_agent_is_locked_out_of_take_route(): void
    {
        $this->withHeader('User-Agent', self::IPHONE_UA)
            ->get('/student/exam/non-existent-session-id')
            ->assertStatus(423)
            ->assertSeeText(__('Quizzes are desktop-only for now'));
    }

    public function test_android_phone_user_agent_is_locked_out(): void
    {
        $this->withHeader('User-Agent', self::ANDROID_PHONE_UA)
            ->get('/student/exam/non-existent-session-id')
            ->assertStatus(423);
    }

    public function test_ajax_request_from_mobile_returns_json_423(): void
    {
        $response = $this->withHeader('User-Agent', self::IPHONE_UA)
            ->getJson('/exam-sessions/non-existent/state');

        $response->assertStatus(423);
        $this->assertSame('desktop_required', $response->json('error'));
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_desktop_user_agent_passes_the_desktop_gate(): void
    {
        // We're not authenticated so we expect to be redirected to login
        // (or hit a 4xx for other reasons) — anything BUT 423 proves
        // the desktop middleware itself didn't short-circuit the request.
        $response = $this->withHeader('User-Agent', self::DESKTOP_CHROME_UA)
            ->get('/student/exam/non-existent-session-id');

        $this->assertNotSame(423, $response->getStatusCode(), 'Desktop UA must not be blocked by the desktop-only gate.');
    }

    public function test_desktop_middleware_is_attached_to_the_critical_taking_routes(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $expected = [
            'student.exam.take',
            'student.exam.instructions',
            'student.exam.prepare',
            'exam-sessions.start',
            'exam-sessions.state',
            'exam-sessions.answers',
            'exam-sessions.answers.save',
            'exam-sessions.submit',
            'exam-sessions.heartbeat',
            'exam-sessions.proctoring-events.store',
            'student.practice.quizzes.take',
            'student.practice.quizzes.submit',
            'proctoring.uploads.path',
            'proctoring.uploads.file',
        ];

        foreach ($expected as $name) {
            $route = $router->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} should exist.");
            $this->assertContains(
                'desktop',
                $route->gatherMiddleware(),
                "Route {$name} must have the 'desktop' middleware to enforce desktop-only quiz taking."
            );
        }
    }

    public function test_submitted_and_results_pages_are_not_locked_so_students_can_review_on_phones(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        foreach (['student.exam.submitted', 'student.practice.quizzes.result'] as $name) {
            $route = $router->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} should exist.");
            $this->assertNotContains(
                'desktop',
                $route->gatherMiddleware(),
                "Route {$name} should NOT have the 'desktop' middleware — it's read-only and useful on mobile."
            );
        }
    }
}
