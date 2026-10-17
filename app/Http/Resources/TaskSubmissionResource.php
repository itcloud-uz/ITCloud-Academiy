<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'task_id' => $this->task_id,
            'status' => $this->status,
            'logic_score' => $this->logic_score,
            'security_score' => $this->security_score,
            'has_memory_leak' => $this->has_memory_leak,
            'has_sql_injection' => $this->has_sql_injection,
            'ai_feedback' => $this->ai_feedback,
            'security_audit_report' => $this->security_audit_report,
            'awarded_xp' => $this->awarded_xp,
            'evaluated_at' => $this->evaluated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
