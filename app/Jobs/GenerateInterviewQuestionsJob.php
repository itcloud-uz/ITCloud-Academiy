<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiInterview;
use App\Models\TaskSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GenerateInterviewQuestionsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 45, 90];
    }

    public function __construct(
        public readonly string $studentId,
        public readonly string $submissionId
    ) {
        $this->onConnection('redis');
        $this->onQueue('ai-interviews');
    }

    public function uniqueId(): string
    {
        return "{$this->studentId}:{$this->submissionId}";
    }

    public function handle(): void
    {
        try {
            /** @var TaskSubmission $submission */
            $submission = TaskSubmission::query()
                ->with('task')
                ->findOrFail($this->submissionId);

            $interview = DB::transaction(function () use ($submission): AiInterview {
                $existing = AiInterview::query()
                    ->where('student_id', $this->studentId)
                    ->where('task_submission_id', $submission->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                return AiInterview::query()->create([
                    'student_id' => $this->studentId,
                    'task_submission_id' => $submission->id,
                    'status' => 'generating',
                    'questions' => [],
                ]);
            });

            if ($interview->status === 'ready') {
                return;
            }

            $questions = $this->requestTenTechnicalQuestions(
                taskTitle: $submission->task->title,
                language: $submission->task->language,
                submittedCode: $submission->submitted_code
            );

            DB::beginTransaction();

            try {
                /** @var AiInterview $lockedInterview */
                $lockedInterview = AiInterview::query()
                    ->where('id', $interview->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedInterview->update([
                    'status' => 'ready',
                    'questions' => $questions,
                ]);

                DB::commit();
            } catch (Throwable $txError) {
                DB::rollBack();
                throw $txError;
            }
        } catch (Throwable $e) {
            Log::error('GenerateInterviewQuestionsJob failed', [
                'student_id' => $this->studentId,
                'submission_id' => $this->submissionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generates exactly 10 deep technical interview questions tailored to the student's code.
     *
     * @return array<int, array{id: int, question: string, category: string, expected_key_concepts: array<int, string>}>
     */
    private function requestTenTechnicalQuestions(string $taskTitle, string $language, string $submittedCode): array
    {
        $endpoint = (string) config('services.deepseek.endpoint', 'https://api.deepseek.com/v1/chat/completions');
        $apiKey = (string) config('services.deepseek.key');

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->retry(2, 500)
            ->post($endpoint, [
                'model' => config('services.deepseek.model', 'deepseek-coder'),
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a Senior Technical Interviewer. Generate EXACTLY 10 technical defense questions based on the student\'s submitted code to verify authorship, memory management, security, and algorithmic complexity. Return STRICT JSON: {"questions": [{"id": 1..10, "question": string, "category": "architecture|security|complexity|edge_case", "expected_key_concepts": [string]}]}',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'task_title' => $taskTitle,
                            'language' => $language,
                            'submitted_code' => $submittedCode,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI Interview Generator HTTP status: ' . $response->status());
        }

        $rawContent = (string) data_get($response->json(), 'choices.0.message.content', '{}');
        /** @var array{questions?: array<int, array{id: int, question: string, category: string, expected_key_concepts: array<int, string>}>} $decoded */
        $decoded = json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);

        $questions = $decoded['questions'] ?? [];

        if (count($questions) !== 10) {
            throw new RuntimeException('AI Interview Generator did not return exactly 10 questions.');
        }

        return $questions;
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('GenerateInterviewQuestionsJob permanently failed', [
            'student_id' => $this->studentId,
            'submission_id' => $this->submissionId,
            'error' => $exception->getMessage(),
        ]);

        AiInterview::query()
            ->where('student_id', $this->studentId)
            ->where('task_submission_id', $this->submissionId)
            ->update(['status' => 'expired']);
    }
}
