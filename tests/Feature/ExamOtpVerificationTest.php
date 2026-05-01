<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_otp_requires_authentication(): void
    {
        $this->postJson('/exam-sessions/verify-otp', [
            'exam_id' => 1,
            'otp_code' => '123456',
        ])->assertUnauthorized();
    }

    public function test_start_requires_authentication(): void
    {
        $this->postJson('/exam-sessions/start', [
            'exam_id' => 1,
        ])->assertUnauthorized();
    }
}
