<?php

namespace Tests\Unit;

use App\Services\AnswerPayloadValidator;
use PHPUnit\Framework\TestCase;

class AnswerPayloadValidatorMcqRemapTest extends TestCase
{
    public function test_remaps_display_indices_to_original_option_indices(): void
    {
        // Display order: original option 2, then 0, then 1
        $displayToOriginal = [2, 0, 1];

        $out = AnswerPayloadValidator::remapMcqPayloadToOriginalIndices([
            'type' => 'mcq',
            'selected' => [0, 2],
        ], $displayToOriginal);

        $this->assertSame('mcq', $out['type']);
        // Display 0 → original 2, display 2 → original 1; result sorted uniquely.
        $this->assertSame([1, 2], $out['selected']);
    }
}
