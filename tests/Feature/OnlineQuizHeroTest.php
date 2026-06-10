<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnlineQuizHeroTest extends TestCase
{
    public function test_homepage_renders_desktop_hero_and_clean_mobile_hero(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Desktop / tablet hero: illustrated component is still wired up
        // and lives inside the md+ grid.
        $this->assertStringContainsString('data-online-quiz-hero', $html);
        $this->assertStringContainsString((string) __('QuizSnap promotional illustration: a student on a laptop in a teal chair beside a phone showing secure digital quizzes and exams for schools.'), $html);
        $this->assertStringContainsString('images/home/quizsnap-homepage-hero-desktop-student-laptop.jpg', $html);
        $this->assertStringContainsString('md:hidden', $html);
        $this->assertStringContainsString('md:grid md:grid-cols-2', $html);

        // Mobile hero: deliberately a clean typographic layout — no
        // promotional banner image any more. Verify the new copy +
        // primary CTA are present, and the body uses a true white
        // background for the small-screen experience.
        $this->assertStringContainsString((string) __('Verified students. Smart assessments. Trusted results.'), $html);
        $this->assertStringContainsString((string) __('Built for schools'), $html);
        $this->assertStringContainsString((string) __('Student login'), $html);
        $this->assertStringContainsString('bg-white', $html);

        // Old mobile banner asset is gone for good.
        $this->assertStringNotContainsString('quizsnap-homepage-hero-mobile-assessments-banner.jpg', $html);
        $this->assertStringNotContainsString('data-home-hero-mobile', $html);

        // Old quiz-hero animation copy that lived earlier should still be absent.
        $this->assertStringNotContainsString((string) __('Click illustration to pause animation'), $html);
        $this->assertStringNotContainsString((string) __('Student Taking an Online Quiz'), $html);
    }

    public function test_quiz_hero_demo_route_is_removed(): void
    {
        $this->get('/quiz-hero-demo')->assertNotFound();
    }
}
