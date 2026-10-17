<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TaskSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskEvaluatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TaskSubmission $submission
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("student.{$this->submission->student_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.evaluated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $student = $this->submission->student()->first();

        return [
            'submission_id' => $this->submission->id,
            'task_id' => $this->submission->task_id,
            'status' => $this->submission->status,
            'logic_score' => $this->submission->logic_score,
            'security_score' => $this->submission->security_score,
            'has_memory_leak' => $this->submission->has_memory_leak,
            'has_sql_injection' => $this->submission->has_sql_injection,
            'awarded_xp' => $this->submission->awarded_xp,
            'feedback' => $this->submission->ai_feedback['feedback'] ?? null,
            'student_state' => [
                'xp_points' => $student?->xp_points ?? 0,
                'level' => $student?->level ?? 1,
                'wallet_balance' => (float) ($student?->wallet_balance ?? 0.00),
            ],
            'evaluated_at' => $this->submission->evaluated_at?->toIso8601String(),
        ];
    }
}
