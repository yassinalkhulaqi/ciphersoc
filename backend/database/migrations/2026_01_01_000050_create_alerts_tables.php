<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->uuid('alert_id')->unique();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('severity')->default('medium');
            $t->string('status')->default('new');
            $t->string('source')->default('detection-engine');
            $t->foreignId('detection_rule_id')->nullable()->constrained('detection_rules')->nullOnDelete();
            $t->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->integer('occurrence_count')->default(1);
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->integer('confidence')->default(70);
            $t->integer('risk_score')->default(50);
            $t->jsonb('risk_factors')->nullable();
            $t->jsonb('mitre')->nullable();
            $t->jsonb('tags')->nullable();
            $t->string('dedup_key')->nullable();
            $t->jsonb('context')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['severity']);
            $t->index(['status']);
            $t->index(['detection_rule_id']);
            $t->index(['host_id']);
            $t->index(['assignee_id']);
            $t->index(['created_at']);
            $t->index(['dedup_key']);
        });
        Schema::create('alert_events', function (Blueprint $t) {
            $t->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->primary(['alert_id', 'event_id']);
        });
        Schema::create('alert_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('alert_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_status_history');
        Schema::dropIfExists('alert_comments');
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alerts');
    }
};
