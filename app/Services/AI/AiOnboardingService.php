<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiOnboardingService
{
    public function __construct(
        private readonly StudentRepositoryInterface $studentRepository
    ) {}

    /**
     * Evaluates registration mini-test answers via Claude 3.5 Sonnet and assigns student to a group
     * using strict DB::beginTransaction() and lockForUpdate().
     *
     * @param  string  $studentId
     * @param  array<int, array{question: string, code_answer: string}>  $answers
     * @return array{student: Student, tier: string, score: int, rationale: string}
     */
    public function evaluateAndAssignGroup(string $studentId, array $answers): array
    {
        $evaluation = $this->requestClaudeAssessment($answers);

        $score = (int) ($evaluation['score'] ?? 0);
        $tier = match (true) {
            $score >= 80 => 'advanced',
            $score >= 45 => 'intermediate',
            default => 'foundation',
        };

        DB::beginTransaction();

        try {
            $student = $this->studentRepository->findByIdForUpdate($studentId);
            $group = $this->studentRepository->findAvailableGroupForUpdate($tier);

            $updatedStudent = $this->studentRepository->assignStudentToGroup(
                student: $student,
                group: $group,
                onboardingScore: $score
            );

            DB::commit();

            // Credit initial onboarding XP atomically
            $updatedStudent = $this->studentRepository->creditRewardAtomic(
                studentId: $updatedStudent->id,
                xpDelta: 50,
                bonusAmount: 0.00,
                referenceType: 'onboarding_assessment',
                referenceId: $updatedStudent->id,
                description: "Onboarding Placement Completed ({$tier})"
            );

            return [
                'student' => $updatedStudent,
                'tier' => $tier,
                'score' => $score,
                'rationale' => (string) ($evaluation['rationale'] ?? 'Assessed by Claude 3.5 Sonnet.'),
            ];
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AI Onboarding group assignment failed', [
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Onboarding placement failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<int, array{question: string, code_answer: string}>  $answers
     * @return array{score: int, rationale: string}
     */
    private function requestClaudeAssessment(array $answers): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(25)
                ->retry(2, 500)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'max_tokens' => 512,
                    'temperature' => 0.1,
                    'system' => 'You are an Enterprise Coding Bootcamp Placement Assessor. Return STRICT JSON ONLY with keys: {"score": int (0-100), "rationale": string}.',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => json_encode(['diagnostic_submissions' => $answers], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Claude API HTTP status: ' . $response->status());
            }

            $rawText = (string) data_get($response->json(), 'content.0.text', '{}');
            /** @var array{score?: int, rationale?: string} $decoded */
            $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);

            return [
                'score' => max(0, min(100, (int) ($decoded['score'] ?? 0))),
                'rationale' => (string) ($decoded['rationale'] ?? 'Diagnostic evaluated.'),
            ];
        } catch (Throwable $e) {
            Log::critical('Claude Onboarding API Error', ['exception' => $e->getMessage()]);
            throw new RuntimeException('Unable to evaluate onboarding test via Claude AI.', 0, $e);
        }
    }
}
