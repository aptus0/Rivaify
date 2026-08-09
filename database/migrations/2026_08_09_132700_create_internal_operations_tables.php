<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->json('permissions');
            $table->timestamps();
        });

        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('staff_role_id')->constrained('staff_roles')->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index(['staff_role_id', 'status']);
        });

        Schema::create('staff_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->string('session_id_hash')->unique();
            $table->string('device_label')->nullable();
            $table->string('ip_hash')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->index(['staff_user_id', 'revoked_at']);
        });

        Schema::create('operation_cases', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('case_number')->unique();
            $table->string('type');
            $table->string('priority')->default('NORMAL');
            $table->string('status')->default('OPEN');
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'opened_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['store_id', 'status']);
            $table->index(['resource_type', 'resource_id']);
        });

        Schema::create('operation_case_notes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('operation_case_id')->constrained('operation_cases')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('visibility')->default('internal');
            $table->text('body');
            $table->timestamps();

            $table->index(['operation_case_id', 'created_at']);
        });

        Schema::create('store_capabilities', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('capability');
            $table->boolean('enabled')->default(true);
            $table->string('source')->default('system');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'capability']);
            $table->index(['capability', 'enabled']);
        });

        Schema::create('store_restrictions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('active');
            $table->text('reason');
            $table->text('internal_note')->nullable();
            $table->text('merchant_message')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('severity')->default('INFO');
            $table->string('type');
            $table->string('title');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['severity', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('request_id')->nullable();
            $table->string('session_id_hash')->nullable();
            $table->text('reason')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_hash')->nullable();
            $table->timestamp('created_at');

            $table->index(['action', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('staff_user_id');
        });

        $this->seedRoles();
        $this->seedExistingAdmins();
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('store_restrictions');
        Schema::dropIfExists('store_capabilities');
        Schema::dropIfExists('operation_case_notes');
        Schema::dropIfExists('operation_cases');
        Schema::dropIfExists('staff_sessions');
        Schema::dropIfExists('staff_users');
        Schema::dropIfExists('staff_roles');
    }

    private function seedRoles(): void
    {
        $allPermissions = [
            'stores.view', 'stores.restrict', 'stores.restore',
            'verification.view', 'verification.assign', 'verification.review', 'verification.approve', 'verification.reject', 'verification.request_information',
            'documents.view', 'documents.download',
            'users.view', 'users.sessions.revoke', 'users.impersonate',
            'payments.view', 'refunds.view', 'refunds.retry', 'shipments.view', 'returns.view',
            'finance.view', 'risk.view', 'risk.manage', 'support.view', 'support.respond',
            'integrations.view', 'system.view', 'staff.manage', 'audit.view',
        ];

        $roles = [
            'super_admin' => ['Super Admin', $allPermissions],
            'operations_admin' => ['Operations Admin', array_values(array_diff($allPermissions, ['staff.manage']))],
            'verification_analyst' => ['Verification Analyst', ['verification.view', 'verification.assign', 'verification.review', 'verification.approve', 'verification.reject', 'verification.request_information', 'documents.view', 'stores.view', 'audit.view']],
            'risk_analyst' => ['Risk Analyst', ['risk.view', 'risk.manage', 'verification.view', 'stores.view', 'documents.view', 'audit.view']],
            'support_agent' => ['Support Agent', ['support.view', 'support.respond', 'stores.view', 'users.view', 'orders.view']],
            'finance_analyst' => ['Finance Analyst', ['finance.view', 'payments.view', 'refunds.view', 'audit.view']],
            'technical_operations' => ['Technical Operations', ['system.view', 'integrations.view', 'audit.view', 'stores.view']],
        ];

        foreach ($roles as $key => [$name, $permissions]) {
            DB::table('staff_roles')->insert([
                'ulid' => (string) Str::ulid(),
                'key' => $key,
                'name' => $name,
                'permissions' => json_encode(array_values(array_unique($permissions))),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedExistingAdmins(): void
    {
        $roleId = DB::table('staff_roles')->where('key', 'super_admin')->value('id');

        DB::table('users')
            ->where('is_rivaify_admin', true)
            ->orderBy('id')
            ->get(['name', 'email', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'last_login_at'])
            ->each(function (object $user) use ($roleId): void {
                DB::table('staff_users')->insert([
                    'ulid' => (string) Str::ulid(),
                    'staff_role_id' => $roleId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'status' => 'active',
                    'two_factor_secret' => $user->two_factor_secret,
                    'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
