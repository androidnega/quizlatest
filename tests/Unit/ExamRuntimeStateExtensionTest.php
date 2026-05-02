<?php

namespace Tests\Unit;

use App\Models\ExamSession;
use App\Support\ExamRuntimeStateExtension;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamRuntimeStateExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_payload_includes_exam_end_at_when_exam_missing(): void
    {
        $session = new ExamSession;
        $session->exam_id = 999_999;

        $payload = ExamRuntimeStateExtension::forSession($session);

        $this->assertArrayHasKey('exam_end_at', $payload);
        $this->assertNull($payload['exam_end_at']);
        $this->assertArrayHasKey('time_remaining_seconds', $payload);
    }
}
