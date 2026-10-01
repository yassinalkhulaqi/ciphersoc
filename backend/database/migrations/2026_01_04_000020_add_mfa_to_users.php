<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('mfa_secret')->nullable()->after('remember_token');
            $t->boolean('mfa_enabled')->default(false)->after('mfa_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['mfa_secret', 'mfa_enabled']);
        });
    }
};
