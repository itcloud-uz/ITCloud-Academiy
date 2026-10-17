<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Http\Resources\TaskSubmissionResource;
use App\Jobs\CheckStudentTaskJob;
use App\Models\Task;
use App\Models\TaskSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TaskSubmissionController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $student = $request->user()?->student()->with('group')->firstOrFail();

        return response()->json([
            'data' => new StudentResource($student),
        ], Response::HTTP_OK);
    }

    public function submit(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'submitted_code' => ['required', 'string', 'min:10', 'max:65000'],
            'language' => ['required', 'string', 'in:php,javascript,python,sql'],
        ]);

        try {
            $student = $request->user()?->student;

            if (! $student) {
                return response()->json([
                    'message' => 'Student profile not found.',
                ], Response::HTTP_FORBIDDEN);
            }

            $submission = TaskSubmission::query()->create([
                'student_id' => $student->id,
                'task_id' => $task->id,
                'submitted_code' => $validated['submitted_code'],
                'status' => 'queued',
            ]);

            CheckStudentTaskJob::dispatch($submission->id);

            return response()->json([
                'message' => 'Code queued for Multi-Agent AI evaluation.',
                'data' => new TaskSubmissionResource($submission),
            ], Response::HTTP_ACCEPTED);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to queue task submission.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
