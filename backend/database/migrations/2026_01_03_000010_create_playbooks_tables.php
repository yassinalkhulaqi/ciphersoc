<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playbooks', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('enabled')->default(true);
            // trigger: {severity:[...], rule_ids:[...], min_risk:int}
            $t->jsonb('trigger')->nullable();
            // actions: [{type:assign|status|comment|create_incident, ...params}]
            $t->jsonb('actions');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('playbook_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('playbook_id')->constrained('playbooks')->cascadeOnDelete();
            $t->jsonb('alert_ids');
            $t->jsonb('result')->nullable();
            $t->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playbook_runs');
        Schema::dropIfExists('playbooks');
    }
};
