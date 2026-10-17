<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\TaskSubmission;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiEvaluationService
{
    public function __construct(
        private readonly StudentRepositoryInterface $studentRepository
    ) {}

    /**
     * Multi-Agent Orchestrator:
     * 1. Evaluator Agent (DeepSeek / GPT-4o): Algorithmic correctness, Big-O complexity, test cases.
     * 2. Security Auditor Agent (Gemini): SQL Injection, Memory Leaks, RCE, unsafe deserialization.
     */
    public function evaluateSubmission(string $submissionId): TaskSubmission
    {
        /** @var TaskSubmission $submission */
        $submission = TaskSubmission::query()
            ->with(['task', 'student'])
            ->findOrFail($submissionId);

        $submission->update(['status' => 'evaluating']);

        try {
            // Agent 1: Code Logic & Complexity Evaluation (DeepSeek / GPT-4o)
            $logicReport = $this->runCodeEvaluatorAgent(
                code: $submission->submitted_code,
                language: $submission->task->language,
                testCases: $submission->task->test_cases
            );

            // Agent 2: Cyber Security Audit (Google Gemini)
            $securityReport = $this->runGeminiSecurityAuditorAgent(
                code: $submission->submitted_code,
                language: $submission->task->language,
                securityRules: $submission->task->security_rules ?? []
            );

            $hasMemoryLeak = (bool) ($securityReport['has_memory_leak'] ?? false);
            $hasSqlInjection = (bool) ($securityReport['has_sql_injection'] ?? false);
            $logicScore = (int) ($logicReport['logic_score'] ?? 0);
            $securityScore = (int) ($securityReport['security_score'] ?? 0);

            $isSecurityRejected = $hasMemoryLeak || $hasSqlInjection || $securityScore < 70;
            $isPassed = ! $isSecurityRejected && $logicScore >= 75;

            $finalStatus = match (true) {
                $isSecurityRejected => 'security_rejected',
                $isPassed => 'passed',
                default => 'failed',
            };

            $awardedXp = $isPassed ? (int) $submission->task->reward_xp : 0;
            $awardedBonus = ($isPassed && $logicScore >= 95 && $securityScore >= 95)
                ? (float) $submission->task->reward_bonus
                : 0.00;

            DB::beginTransaction();

            try {
                /** @var TaskSubmission $lockedSubmission */
                $lockedSubmission = TaskSubmission::query()
                    ->where('id', $submission->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedSubmission->update([
                    'status' => $finalStatus,
                    'logic_score' => $logicScore,
                    'security_score' => $securityScore,
                    'has_memory_leak' => $hasMemoryLeak,
                    'has_sql_injection' => $hasSqlInjection,
                    'ai_feedback' => $logicReport,
                    'security_audit_report' => $securityReport,
                    'awarded_xp' => $awardedXp,
                    'evaluated_at' => now(),
                ]);

                DB::commit();
            } catch (Throwable $txException) {
                DB::rollBack();
                throw $txException;
            }

            if ($isPassed && ($awardedXp > 0 || $awardedBonus > 0.0)) {
                $this->studentRepository->creditRewardAtomic(
                    studentId: $submission->student_id,
                    xpDelta: $awardedXp,
                    bonusAmount: $awardedBonus,
                    referenceType: TaskSubmission::class,
                    referenceId: $submission->id,
                    description: "Task Passed: {$submission->task->title}"
                );
            }

            return $submission->refresh();
        } catch (Throwable $e) {
            Log::error('Multi-Agent Evaluation Error', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);

            $submission->update([
                'status' => 'failed',
                'ai_feedback' => ['error' => 'Evaluation pipeline error. Queued for retry.'],
            ]);

            throw new RuntimeException('Multi-Agent evaluation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Agent 1: DeepSeek / GPT-4o Code Evaluator
     *
     * @param  array<string, mixed>  $testCases
     * @return array{logic_score: int, time_complexity: string, space_complexity: string, feedback: string}
     */
    private function runCodeEvaluatorAgent(string $code, string $language, array $testCases): array
    {
        try {
            $endpoint = (string) config('services.deepseek.endpoint', 'https://api.deepseek.com/v1/chat/completions');
            $apiKey = (string) config('services.deepseek.key');

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->retry(2, 500)
                ->post($endpoint, [
                    'model' => config('services.deepseek.model', 'deepseek-coder'),
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a Senior Code Evaluator Agent. Analyze code correctness against test cases. Return STRICT JSON: {"logic_score": int(0-100), "time_complexity": string, "space_complexity": string, "feedback": string}.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'language' => $language,
                                'submitted_code' => $code,
                                'test_cases' => $testCases,
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Evaluator Agent HTTP status: ' . $response->status());
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '{}');
            /** @var array{logic_score?: int, time_complexity?: string, space_complexity?: string, feedback?: string} $parsed */
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return [
                'logic_score' => max(0, min(100, (int) ($parsed['logic_score'] ?? 0))),
                'time_complexity' => (string) ($parsed['time_complexity'] ?? 'O(N)'),
                'space_complexity' => (string) ($parsed['space_complexity'] ?? 'O(1)'),
                'feedback' => (string) ($parsed['feedback'] ?? 'Evaluated.'),
            ];
        } catch (Throwable $e) {
            Log::error('Evaluator Agent (DeepSeek/GPT-4o) Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Agent 2: Gemini Security Auditor (SQL Injection, Memory Leak, RCE detection)
     *
     * @param  array<string, mixed>  $securityRules
     * @return array{security_score: int, has_memory_leak: bool, has_sql_injection: bool, vulnerabilities: array<int, string>}
     */
    private function runGeminiSecurityAuditorAgent(string $code, string $language, array $securityRules): array
    {
        try {
            $apiKey = (string) config('services.gemini.key');
            $model = (string) config('services.gemini.model', 'gemini-1.5-pro');
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->post($url, [
                    'generationConfig' => [
                        'temperature' => 0.0,
                        'responseMimeType' => 'application/json',
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'instruction' => 'You are an Offensive & Defensive Cyber Security Auditor Agent. Inspect the code for SQL Injection, Memory Leaks, Unbounded Loops, XSS, and RCE. Return strict JSON: {"security_score": int(0-100), "has_memory_leak": bool, "has_sql_injection": bool, "vulnerabilities": [string]}',
                                        'language' => $language,
                                        'security_rules' => $securityRules,
                                        'code' => $code,
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Gemini Security Auditor HTTP status: ' . $response->status());
            }

            $rawJson = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '{}');
            /** @var array{security_score?: int, has_memory_leak?: bool, has_sql_injection?: bool, vulnerabilities?: array<int, string>} $parsed */
            $parsed = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);

            return [
                'security_score' => max(0, min(100, (int) ($parsed['security_score'] ?? 0))),
                'has_memory_leak' => (bool) ($parsed['has_memory_leak'] ?? false),
                'has_sql_injection' => (bool) ($parsed['has_sql_injection'] ?? false),
                'vulnerabilities' => (array) ($parsed['vulnerabilities'] ?? []),
            ];
        } catch (Throwable $e) {
            Log::error('Gemini Security Auditor Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
