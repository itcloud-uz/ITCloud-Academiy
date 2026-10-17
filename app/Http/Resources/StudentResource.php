<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole('admin');
        $isSelf = $user && (string) optional($user->student)->id === (string) $this->id;

        return [
            'id' => $this->id,
            'full_name' => $this->user?->name,
            'status' => $this->status,
            'xp_points' => $this->xp_points,
            'level' => $this->level,
            'wallet_balance' => (float) $this->wallet_balance,
            'group' => [
                'id' => $this->group?->id,
                'name' => $this->group?->name,
                'tier' => $this->group?->tier,
            ],
            // Encrypted PII fields are decrypted only for Admin or the Student owner; never exposed to AI-Mentor
            'phone' => $this->when($isAdmin || $isSelf, fn () => $this->encrypted_phone),
            'address' => $this->when($isAdmin || $isSelf, fn () => $this->encrypted_address),
            'passport_serial' => $this->when($isAdmin, fn () => $this->encrypted_passport_serial),
        ];
    }
}
