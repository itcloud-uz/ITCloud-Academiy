<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSubmission extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'student_id',
        'task_id',
        'submitted_code',
        'status',
        'logic_score',
        'security_score',
        'has_memory_leak',
        'has_sql_injection',
        'ai_feedback',
        'security_audit_report',
        'awarded_xp',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'logic_score' => 'integer',
            'security_score' => 'integer',
            'has_memory_leak' => 'boolean',
            'has_sql_injection' => 'boolean',
            'ai_feedback' => 'array',
            'security_audit_report' => 'array',
            'awarded_xp' => 'integer',
            'evaluated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
