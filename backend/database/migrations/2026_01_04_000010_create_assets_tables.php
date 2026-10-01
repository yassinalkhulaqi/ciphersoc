<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('hostname')->unique();
            $t->string('ip_address')->nullable();
            $t->string('os')->nullable();
            $t->string('criticality')->default('medium');
            $t->string('status')->default('online');
            $t->timestamp('last_seen_at')->nullable();
            $t->jsonb('tags')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_vulnerabilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $t->string('cve')->nullable();
            $t->string('title');
            $t->string('severity')->default('medium');
            $t->float('cvss')->nullable();
            $t->string('status')->default('open');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_vulnerabilities');
        Schema::dropIfExists('assets');
    }
};
