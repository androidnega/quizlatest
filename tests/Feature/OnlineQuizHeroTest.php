<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnlineQuizHeroTest extends TestCase
{
    public function test_homepage_renders_desktop_and_mobile_hero_assets(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-online-quiz-hero', $html);
        $this->assertStringContainsString('data-home-hero-mobile', $html);
        $this->assertStringContainsString((string) __('QuizSnap promotional illustration: a student on a laptop in a teal chair beside a phone showing secure digital quizzes and exams for schools.'), $html);
        $this->assertStringContainsString((string) __('Secure school assessments promotional banner with quizzes, exams, and results.'), $html);
        $this->assertStringContainsString('images/home/quizsnap-homepage-hero-desktop-student-laptop.jpg', $html);
        $this->assertStringContainsString('images/home/quizsnap-homepage-hero-mobile-assessments-banner.jpg', $html);
        $this->assertStringContainsString('md:hidden', $html);
        $this->assertStringContainsString('md:grid md:grid-cols-2', $html);
        $this->assertStringNotContainsString((string) __('Click illustration to pause animation'), $html);
        $this->assertStringNotContainsString((string) __('Student Taking an Online Quiz'), $html);
    }

    public function test_quiz_hero_demo_route_is_removed(): void
    {
        $this->get('/quiz-hero-demo')->assertNotFound();
    }
}
