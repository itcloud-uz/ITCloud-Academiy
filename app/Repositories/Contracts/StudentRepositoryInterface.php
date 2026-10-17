<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Group;
use App\Models\Student;

interface StudentRepositoryInterface
{
    public function findByIdForUpdate(string $studentId): Student;

    public function findAvailableGroupForUpdate(string $tier): Group;

    public function assignStudentToGroup(Student $student, Group $group, int $onboardingScore): Student;

    public function creditRewardAtomic(
        string $studentId,
        int $xpDelta,
        float $bonusAmount,
        string $referenceType,
        string $referenceId,
        string $description
    ): Student;
}
