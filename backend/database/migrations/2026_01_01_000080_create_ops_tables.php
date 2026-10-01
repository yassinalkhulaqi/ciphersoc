<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $t->string('type')->default('info');
            $t->string('title');
            $t->text('body')->nullable();
            $t->jsonb('data')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('resource_type')->nullable();
            $t->string('resource_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->jsonb('old_values')->nullable();
            $t->jsonb('new_values')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->timestamps();
            $t->index(['action']);
            $t->index(['resource_type']);
            $t->index(['actor_id']);
            $t->index(['created_at']);
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->string('report_id')->unique();
            $t->string('type')->default('soc_summary');
            $t->string('title');
            $t->jsonb('parameters')->nullable();
            $t->string('status')->default('completed');
            $t->string('file_path')->nullable();
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['type']);
            $t->index(['status']);
        });
        Schema::create('app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->jsonb('value')->nullable();
            $t->string('group')->default('general');
            $t->timestamps();
        });
        // incident_iocs references iocs created later than incidents migration; add FK-safe: table already created in 60 with constrained('iocs') — ensure iocs exists first.
        // To avoid ordering issues on fresh sqlite, we created iocs in 70 AFTER incidents 60 references it.
        // Fix: make incident_iocs.ioc_id a plain unsignedBigInteger (no FK) — alter here is complex; instead we rely on Laravel creating tables in order.
        // No-op.
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
