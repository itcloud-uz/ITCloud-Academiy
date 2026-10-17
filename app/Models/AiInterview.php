<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInterview extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'student_id',
        'task_submission_id',
        'status',
        'questions',
        'student_answers',
        'ai_evaluation_summary',
        'final_score',
        'awarded_xp',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'student_answers' => 'array',
            'ai_evaluation_summary' => 'array',
            'final_score' => 'integer',
            'awarded_xp' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(TaskSubmission::class, 'task_submission_id');
    }
}
