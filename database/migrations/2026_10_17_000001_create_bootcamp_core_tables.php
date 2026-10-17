<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->enum('tier', ['foundation', 'intermediate', 'advanced'])->index();
            $table->unsignedSmallInteger('max_capacity')->default(40);
            $table->unsignedSmallInteger('current_students_count')->default(0);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('group_id')->nullable()->constrained('groups')->nullOnDelete();
            
            // AES-256 Encrypted Personal Data (stored as text for ciphertext)
            $table->text('encrypted_phone');
            $table->text('encrypted_passport_serial');
            $table->text('encrypted_address')->nullable();
            $table->text('encrypted_github_token')->nullable();

            // Gamification & Financial Columns (Protected via SELECT ... FOR UPDATE)
            $table->unsignedBigInteger('xp_points')->default(0)->index();
            $table->unsignedSmallInteger('level')->default(1);
            $table->decimal('wallet_balance', 14, 2)->default(0.00);
            $table->unsignedSmallInteger('onboarding_score')->nullable();
            $table->enum('status', ['pending_onboarding', 'active', 'suspended', 'graduated'])
                ->default('pending_onboarding')
                ->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->text('description');
            $table->text('starter_code')->nullable();
            $table->string('language', 40)->default('php');
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'expert'])->index();
            $table->unsignedInteger('reward_xp')->default(100);
            $table->decimal('reward_bonus', 10, 2)->default(0.00);
            $table->unsignedSmallInteger('day_number')->index(); // 1 to 90 (3-month bootcamp)
            $table->jsonb('test_cases');
            $table->jsonb('security_rules')->nullable();
            $table->timestamps();
        });

        Schema::create('task_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->longText('submitted_code');
            $table->enum('status', [
                'queued',
                'evaluating',
                'passed',
                'failed',
                'security_rejected',
            ])->default('queued')->index();
            
            // Multi-Agent Evaluation Scores
            $table->unsignedTinyInteger('logic_score')->nullable();      // DeepSeek / GPT-4o (0-100)
            $table->unsignedTinyInteger('security_score')->nullable();   // Gemini Auditor (0-100)
            $table->boolean('has_memory_leak')->default(false);
            $table->boolean('has_sql_injection')->default(false);
            $table->jsonb('ai_feedback')->nullable();
            $table->jsonb('security_audit_report')->nullable();
            $table->unsignedInteger('awarded_xp')->default(0);
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'task_id', 'status']);
        });

        Schema::create('ai_interviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('task_submission_id')->nullable()->constrained('task_submissions')->nullOnDelete();
            $table->enum('status', ['generating', 'ready', 'in_progress', 'completed', 'expired'])
                ->default('generating')
                ->index();
            $table->jsonb('questions'); // 10 dynamic AI-generated questions
            $table->jsonb('student_answers')->nullable();
            $table->jsonb('ai_evaluation_summary')->nullable();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->unsignedInteger('awarded_xp')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('type', ['xp_credit', 'xp_debit', 'wallet_credit', 'wallet_debit'])->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference_type', 80);
            $table->uuid('reference_id');
            $table->string('description', 255);
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ai_interviews');
        Schema::dropIfExists('task_submissions');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('students');
        Schema::dropIfExists('groups');
    }
};
