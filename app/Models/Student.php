<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'students';

    protected $fillable = [
        'user_id',
        'group_id',
        'encrypted_phone',
        'encrypted_passport_serial',
        'encrypted_address',
        'encrypted_github_token',
        'xp_points',
        'level',
        'wallet_balance',
        'onboarding_score',
        'status',
    ];

    protected $hidden = [
        'encrypted_passport_serial',
        'encrypted_github_token',
    ];

    /**
     * AES-256 Encryption via Laravel 11 Model Casting
     */
    protected function casts(): array
    {
        return [
            'encrypted_phone' => 'encrypted',
            'encrypted_passport_serial' => 'encrypted',
            'encrypted_address' => 'encrypted',
            'encrypted_github_token' => 'encrypted',
            'xp_points' => 'integer',
            'level' => 'integer',
            'wallet_balance' => 'decimal:2',
            'onboarding_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(AiInterview::class);
    }

    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }
}
