<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iocs', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->text('value');
            $t->string('normalized_value');
            $t->integer('confidence')->default(50);
            $t->string('severity')->default('medium');
            $t->string('source')->default('manual');
            $t->string('status')->default('active');
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->jsonb('tags')->nullable();
            $t->text('notes')->nullable();
            $t->integer('threat_score')->default(0);
            $t->string('reputation')->default('unknown');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['type', 'normalized_value']);
            $t->index(['type']);
            $t->index(['status']);
            $t->index(['reputation']);
        });
        Schema::create('ioc_enrichments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ioc_id')->constrained('iocs')->cascadeOnDelete();
            $t->string('provider');
            $t->jsonb('result')->nullable();
            $t->string('verdict')->default('unknown');
            $t->integer('malicious_count')->default(0);
            $t->integer('suspicious_count')->default(0);
            $t->integer('harmless_count')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('checked_at')->useCurrent();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['ioc_id', 'provider']);
        });
        Schema::create('threat_intel_providers', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('slug')->unique();
            $t->string('status')->default('not_configured');
            $t->boolean('enabled')->default(true);
            $t->boolean('mock_mode')->default(true);
            $t->jsonb('config')->nullable();
            $t->timestamp('last_check_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('threat_intel_results', function (Blueprint $t) {
            $t->id();
            $t->string('indicator_type');
            $t->string('indicator_value');
            $t->string('provider');
            $t->jsonb('raw_result')->nullable();
            $t->string('verdict')->default('unknown');
            $t->integer('score')->default(0);
            $t->timestamp('checked_at')->useCurrent();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['indicator_type', 'indicator_value']);
            $t->index(['provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threat_intel_results');
        Schema::dropIfExists('threat_intel_providers');
        Schema::dropIfExists('ioc_enrichments');
        Schema::dropIfExists('iocs');
    }
};
