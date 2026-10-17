<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Group;
use App\Models\LedgerTransaction;
use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class StudentRepository implements StudentRepositoryInterface
{
    public function findByIdForUpdate(string $studentId): Student
    {
        /** @var Student|null $student */
        $student = Student::query()
            ->where('id', $studentId)
            ->lockForUpdate()
            ->first();

        if (! $student) {
            throw new RuntimeException("Student not found for atomic lock: {$studentId}");
        }

        return $student;
    }

    public function findAvailableGroupForUpdate(string $tier): Group
    {
        /** @var Group|null $group */
        $group = Group::query()
            ->where('tier', $tier)
            ->where('is_active', true)
            ->whereColumn('current_students_count', '<', 'max_capacity')
            ->orderBy('current_students_count', 'asc')
            ->lockForUpdate()
            ->first();

        if (! $group) {
            throw new RuntimeException("No active group with available capacity (max 40) for tier: {$tier}");
        }

        return $group;
    }

    public function assignStudentToGroup(Student $student, Group $group, int $onboardingScore): Student
    {
        $student->update([
            'group_id' => $group->id,
            'onboarding_score' => $onboardingScore,
            'status' => 'active',
        ]);

        $group->increment('current_students_count');

        return $student->refresh();
    }

    /**
     * Race-condition safe XP & Financial Balance update using SELECT ... FOR UPDATE
     */
    public function creditRewardAtomic(
        string $studentId,
        int $xpDelta,
        float $bonusAmount,
        string $referenceType,
        string $referenceId,
        string $description
    ): Student {
        DB::beginTransaction();

        try {
            $student = $this->findByIdForUpdate($studentId);

            if ($xpDelta > 0) {
                $xpBefore = (float) $student->xp_points;
                $xpAfter = $xpBefore + $xpDelta;

                $student->xp_points = (int) $xpAfter;
                $student->level = max(1, (int) floor(sqrt($student->xp_points / 100)) + 1);

                LedgerTransaction::query()->create([
                    'student_id' => $student->id,
                    'type' => 'xp_credit',
                    'amount' => $xpDelta,
                    'balance_before' => $xpBefore,
                    'balance_after' => $xpAfter,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'description' => $description,
                ]);
            }

            if ($bonusAmount > 0.0) {
                $walletBefore = (float) $student->wallet_balance;
                $walletAfter = round($walletBefore + $bonusAmount, 2);

                $student->wallet_balance = $walletAfter;

                LedgerTransaction::query()->create([
                    'student_id' => $student->id,
                    'type' => 'wallet_credit',
                    'amount' => $bonusAmount,
                    'balance_before' => $walletBefore,
                    'balance_after' => $walletAfter,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'description' => "{$description} (Monetary Bonus)",
                ]);
            }

            $student->save();

            DB::commit();

            return $student->refresh();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
