<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\TaskEvaluatedEvent;
use App\Models\TaskSubmission;
use App\Services\AI\AiEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckStudentTaskJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 300;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public readonly string $submissionId
    ) {
        $this->onConnection('redis');
        $this->onQueue('ai-evaluations');
    }

    public function uniqueId(): string
    {
        return $this->submissionId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->submissionId))->releaseAfter(95)->expireAfter(180),
        ];
    }

    public function handle(AiEvaluationService $evaluationService): void
    {
        try {
            $submission = $evaluationService->evaluateSubmission($this->submissionId);

            // Real-time WebSocket broadcast to student's private channel
            broadcast(new TaskEvaluatedEvent($submission));

            // Automatically trigger 10 AI interview questions if the student passed
            if ($submission->status === 'passed') {
                GenerateInterviewQuestionsJob::dispatch(
                    studentId: $submission->student_id,
                    submissionId: $submission->id
                );
            }
        } catch (Throwable $e) {
            Log::error('CheckStudentTaskJob execution failed', [
                'submission_id' => $this->submissionId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('CheckStudentTaskJob permanently failed after retries', [
            'submission_id' => $this->submissionId,
            'error' => $exception->getMessage(),
        ]);

        TaskSubmission::query()
            ->where('id', $this->submissionId)
            ->update([
                'status' => 'failed',
                'ai_feedback' => [
                    'feedback' => 'AI Evaluation service timed out. System administrator notified.',
                ],
            ]);
    }
}
