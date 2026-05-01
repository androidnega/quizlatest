<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProctoringRiskUpdateEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $sessionId,
        public int $examId,
        public int $studentId,
        public string $riskState,
        public int $violationScore,
        public string $previousRiskState,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('exam-session.'.$this->sessionId)];
    }

    public function broadcastAs(): string
    {
        return 'proctoring.risk-update';
    }
}
