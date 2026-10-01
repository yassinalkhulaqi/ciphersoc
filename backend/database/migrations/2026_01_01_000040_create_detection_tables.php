<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitre_tactics', function (Blueprint $t) {
            $t->id();
            $t->string('tactic_id')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('mitre_techniques', function (Blueprint $t) {
            $t->id();
            $t->string('technique_id')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->foreignId('tactic_id')->nullable()->constrained('mitre_tactics')->nullOnDelete();
            $t->string('tactic')->nullable();
            $t->boolean('is_subtechnique')->default(false);
            $t->string('parent_id')->nullable();
            $t->jsonb('platforms')->nullable();
            $t->timestamps();
            $t->index(['tactic']);
        });
        Schema::create('detection_rules', function (Blueprint $t) {
            $t->id();
            $t->string('rule_id')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('severity')->default('medium');
            $t->boolean('enabled')->default(true);
            $t->string('status')->default('active');
            $t->string('event_type')->nullable();
            $t->string('rule_type')->default('threshold');
            $t->jsonb('conditions');
            $t->integer('threshold')->default(1);
            $t->integer('time_window_minutes')->default(5);
            $t->string('group_by')->nullable();
            $t->integer('cooldown_minutes')->default(15);
            $t->boolean('suppression_enabled')->default(true);
            $t->string('mitre_technique_id')->nullable();
            $t->string('mitre_tactic')->nullable();
            $t->jsonb('tags')->nullable();
            $t->integer('priority')->default(50);
            $t->integer('version')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['enabled']);
            $t->index(['severity']);
            $t->index(['event_type']);
        });
        Schema::create('detection_rule_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('detection_rule_id')->constrained('detection_rules')->cascadeOnDelete();
            $t->integer('version');
            $t->jsonb('snapshot');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('change_note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_rule_versions');
        Schema::dropIfExists('detection_rules');
        Schema::dropIfExists('mitre_techniques');
        Schema::dropIfExists('mitre_tactics');
    }
};
