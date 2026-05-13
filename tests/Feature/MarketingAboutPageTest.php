<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingAboutPageTest extends TestCase
{
    public function test_about_page_is_reachable_and_on_brand(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('QuizSnap', false)
            ->assertDontSee('Laravel', false)
            ->assertSee('Yeboah D. Augustine', false)
            ->assertSee('/images/team/', false);
    }

    public function test_about_route_name_resolves(): void
    {
        $this->assertSame(url('/about'), route('about'));
    }
}
