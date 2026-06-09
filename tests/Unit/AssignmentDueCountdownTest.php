<?php

namespace Tests\Unit;

use App\Models\Quiz;
use App\Support\AssignmentDueCountdown;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssignmentDueCountdownTest extends TestCase
{
    public function test_shows_countdown_within_five_days_before_due(): void
    {
        Carbon::setTestNow('2026-05-19 12:00:00');

        $exam = new Quiz([
            'assessment_type' => 'assignment',
            'due_at' => Carbon::parse('2026-05-22 18:00:00'),
            'proctoring_settings' => [],
            'status' => 'published',
        ]);

        $countdown = AssignmentDueCountdown::resolve($exam);

        $this->assertNotNull($countdown);
        $this->assertSame(__('Due in'), $countdown['prefix']);
        $this->assertSame($exam->due_at->toIso8601String(), $countdown['ends_at']);
    }

    public function test_hides_countdown_more_than_five_days_before_due(): void
    {
        Carbon::setTestNow('2026-05-19 12:00:00');

        $exam = new Quiz([
            'assessment_type' => 'assignment',
            'due_at' => Carbon::parse('2026-05-26 18:00:00'),
            'proctoring_settings' => [],
            'status' => 'published',
        ]);

        $this->assertNull(AssignmentDueCountdown::resolve($exam));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
