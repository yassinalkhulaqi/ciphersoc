<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->string('incident_id')->unique();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('severity')->default('medium');
            $t->string('priority')->default('p3');
            $t->string('status')->default('open');
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('team')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->jsonb('tags')->nullable();
            $t->jsonb('mitre_techniques')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['severity']);
            $t->index(['status']);
            $t->index(['assignee_id']);
            $t->index(['created_at']);
        });
        Schema::create('incident_alerts', function (Blueprint $t) {
            $t->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $t->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $t->primary(['incident_id', 'alert_id']);
        });
        Schema::create('incident_events', function (Blueprint $t) {
            $t->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->primary(['incident_id', 'event_id']);
        });
        Schema::create('incident_iocs', function (Blueprint $t) {
            $t->unsignedBigInteger('incident_id');
            $t->unsignedBigInteger('ioc_id');
            $t->primary(['incident_id', 'ioc_id']);
        });
        Schema::create('incident_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('incident_timeline', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $t->string('entry_type')->default('note');
            $t->string('title');
            $t->text('detail')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->jsonb('metadata')->nullable();
            $t->timestamps();
            $t->index(['incident_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_timeline');
        Schema::dropIfExists('incident_comments');
        Schema::dropIfExists('incident_iocs');
        Schema::dropIfExists('incident_events');
        Schema::dropIfExists('incident_alerts');
        Schema::dropIfExists('incidents');
    }
};
