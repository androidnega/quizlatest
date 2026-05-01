<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\AnswerPayloadValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnswerPayloadValidatorTest extends TestCase
{
    #[Test]
    public function it_normalizes_mcq_single_index(): void
    {
        $q = new Question(['type' => 'mcq']);
        $out = AnswerPayloadValidator::validate($q, [
            'type' => 'mcq',
            'selected' => 2,
        ]);
        $this->assertSame([2], $out['selected']);
        $this->assertSame('mcq', $out['type']);
    }

    #[Test]
    public function it_rejects_type_mismatch(): void
    {
        $this->expectException(ValidationException::class);
        $q = new Question(['type' => 'essay']);
        AnswerPayloadValidator::validate($q, [
            'type' => 'mcq',
            'selected' => [0],
        ]);
    }
}
