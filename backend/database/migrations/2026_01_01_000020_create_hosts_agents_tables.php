<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosts', function (Blueprint $t) {
            $t->id();
            $t->string('host_id')->unique();
            $t->string('hostname');
            $t->string('os')->nullable();
            $t->string('os_version')->nullable();
            $t->string('arch')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('mac_address')->nullable();
            $t->string('environment')->default('production');
            $t->string('status')->default('never_connected');
            $t->integer('criticality')->default(50);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('first_seen_at')->nullable();
            $t->jsonb('tags')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['hostname']);
            $t->index(['status']);
            $t->index(['last_seen_at']);
        });
        Schema::create('agents', function (Blueprint $t) {
            $t->id();
            $t->string('agent_id')->unique();
            $t->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $t->string('hostname');
            $t->string('os')->nullable();
            $t->string('agent_version')->nullable();
            $t->string('status')->default('offline');
            $t->string('enrollment_token_hash')->nullable();
            $t->string('api_token_hash')->nullable();
            $t->timestamp('last_heartbeat_at')->nullable();
            $t->timestamp('enrolled_at')->nullable();
            $t->jsonb('capabilities')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->timestamps();
            $t->index(['status']);
            $t->index(['hostname']);
            $t->index(['last_heartbeat_at']);
        });
        Schema::create('agent_heartbeats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $t->string('status')->default('online');
            $t->jsonb('payload')->nullable();
            $t->timestamps();
            $t->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_heartbeats');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('hosts');
    }
};
