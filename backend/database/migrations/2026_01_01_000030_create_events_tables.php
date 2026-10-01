<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->timestamp('event_timestamp');
            $t->timestamp('ingested_at')->useCurrent();
            $t->string('source')->nullable();
            $t->string('source_type')->default('generic');
            $t->string('parser')->default('generic_json');
            $t->foreignId('host_id')->nullable()->constrained('hosts')->nullOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $t->string('event_type')->default('generic');
            $t->string('severity')->default('info');
            $t->text('message')->nullable();
            $t->string('username')->nullable();
            $t->string('source_ip', 45)->nullable();
            $t->string('destination_ip', 45)->nullable();
            $t->integer('source_port')->nullable();
            $t->integer('destination_port')->nullable();
            $t->string('protocol')->nullable();
            $t->string('process_name')->nullable();
            $t->integer('process_id')->nullable();
            $t->string('parent_process')->nullable();
            $t->text('file_path')->nullable();
            $t->text('command_line')->nullable();
            $t->string('hostname')->nullable();
            $t->string('domain')->nullable();
            $t->text('url')->nullable();
            $t->string('hash')->nullable();
            $t->string('hash_type')->nullable();
            $t->string('action')->nullable();
            $t->string('status')->nullable();
            $t->text('raw_log')->nullable();
            $t->jsonb('normalized')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->string('processing_status')->default('stored');
            $t->timestamps();
            $t->index(['event_timestamp']);
            $t->index(['host_id']);
            $t->index(['agent_id']);
            $t->index(['source_ip']);
            $t->index(['destination_ip']);
            $t->index(['event_type']);
            $t->index(['severity']);
            $t->index(['created_at']);
            $t->index(['username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
