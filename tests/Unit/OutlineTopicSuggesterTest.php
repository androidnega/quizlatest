<?php

namespace Tests\Unit;

use App\Services\OutlineTopicSuggester;
use Tests\TestCase;

class OutlineTopicSuggesterTest extends TestCase
{
    public function test_extracts_line_based_topics(): void
    {
        $text = "Introduction to calculus\nDerivatives in practice\n\nShort\n".str_repeat('x', 300);

        $topics = app(OutlineTopicSuggester::class)->suggestFromPlainText($text, max: 10);

        $this->assertContains('Introduction to calculus', $topics);
        $this->assertContains('Derivatives in practice', $topics);
        $this->assertNotContains('Short', $topics);
    }
}
