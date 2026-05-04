<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnlineQuizHeroTest extends TestCase
{
    public function test_homepage_renders_online_quiz_hero_component(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-online-quiz-hero', $html);
        $this->assertStringContainsString((string) __('Illustration of a student taking an online quiz at a computer with notes and keyboard.'), $html);
        $this->assertStringNotContainsString((string) __('Click illustration to pause animation'), $html);
        $this->assertStringNotContainsString((string) __('Student Taking an Online Quiz'), $html);
    }

    public function test_quiz_hero_demo_route_renders_component(): void
    {
        $html = $this->get('/quiz-hero-demo')->assertOk()->getContent();

        $this->assertStringContainsString('data-online-quiz-hero', $html);
    }
}
