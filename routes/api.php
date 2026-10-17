<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\TaskSubmissionController;
use App\Http\Middleware\EnsureRoleHierarchyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        // Student & Admin accessible routes
        Route::middleware([EnsureRoleHierarchyMiddleware::class . ':student,admin'])
            ->group(function (): void {
                Route::get('/student/profile', [TaskSubmissionController::class, 'profile'])
                    ->name('api.v1.student.profile');

                Route::post('/onboarding/evaluate', [OnboardingController::class, 'evaluate'])
                    ->name('api.v1.onboarding.evaluate');

                Route::post('/tasks/{task}/submit', [TaskSubmissionController::class, 'submit'])
                    ->name('api.v1.tasks.submit');
            });

        // AI-Mentor & Admin accessible audit routes
        Route::middleware([EnsureRoleHierarchyMiddleware::class . ':ai-mentor,admin'])
            ->prefix('mentor')
            ->group(function (): void {
                Route::get('/health', fn () => response()->json(['status' => 'AI-Mentor Active']))
                    ->name('api.v1.mentor.health');
            });
    });
