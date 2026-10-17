<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Services\AI\AiOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly AiOnboardingService $onboardingService
    ) {}

    public function evaluate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'answers' => ['required', 'array', 'min:1', 'max:10'],
            'answers.*.question' => ['required', 'string', 'max:1000'],
            'answers.*.code_answer' => ['required', 'string', 'max:15000'],
        ]);

        try {
            $result = $this->onboardingService->evaluateAndAssignGroup(
                studentId: (string) $validated['student_id'],
                answers: $validated['answers']
            );

            return response()->json([
                'message' => 'Onboarding assessment completed and group assigned.',
                'tier' => $result['tier'],
                'score' => $result['score'],
                'rationale' => $result['rationale'],
                'student' => new StudentResource($result['student']),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Onboarding evaluation failed.',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
