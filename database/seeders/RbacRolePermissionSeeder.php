<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'tasks.submit',
            'tasks.evaluate',
            'interviews.participate',
            'interviews.audit',
            'students.view_own',
            'students.view_all',
            'students.manage_finance',
            'groups.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'sanctum');
        }

        $studentRole = Role::findOrCreate('student', 'sanctum');
        $studentRole->syncPermissions([
            'tasks.submit',
            'interviews.participate',
            'students.view_own',
        ]);

        $aiMentorRole = Role::findOrCreate('ai-mentor', 'sanctum');
        $aiMentorRole->syncPermissions([
            'tasks.evaluate',
            'interviews.audit',
            'students.view_all',
        ]);

        $adminRole = Role::findOrCreate('admin', 'sanctum');
        $adminRole->syncPermissions($permissions);
    }
}
