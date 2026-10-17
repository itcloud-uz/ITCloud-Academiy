<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'starter_code',
        'language',
        'difficulty',
        'reward_xp',
        'reward_bonus',
        'day_number',
        'test_cases',
        'security_rules',
    ];

    protected function casts(): array
    {
        return [
            'reward_xp' => 'integer',
            'reward_bonus' => 'decimal:2',
            'day_number' => 'integer',
            'test_cases' => 'array',
            'security_rules' => 'array',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }
}
