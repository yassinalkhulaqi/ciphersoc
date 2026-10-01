<?php

use App\Models\DetectionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $t) {
            $t->timestamp('last_notified_at')->nullable()->after('last_seen_at');
            $t->foreignId('superseded_by')->nullable()->after('dedup_key')
                ->constrained('alerts')->nullOnDelete();
            $t->jsonb('matched_iocs')->nullable()->after('mitre');
        });

        // Race-safe dedup: one open alert per dedup_key (PostgreSQL partial index).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS alerts_dedup_open_uidx ON alerts (dedup_key) '.
                "WHERE status NOT IN ('resolved','closed','false_positive') AND deleted_at IS NULL"
            );
        }

        // Upgrade RL-SSH002 to a true sequence rule on existing databases
        // (fresh seeds get it from DetectionRuleSeeder).
        $ssh002 = DetectionRule::where('rule_id', 'RL-SSH002')->first();
        if ($ssh002 && ! isset($ssh002->conditions['steps'])) {
            $ssh002->update(['conditions' => [
                'logic' => 'SEQUENCE', 'within_minutes' => 15, 'group_by' => 'source_ip',
                'steps' => [
                    ['field' => 'event_type', 'op' => 'equals', 'value' => 'authentication_failure'],
                    ['field' => 'event_type', 'op' => 'equals', 'value' => 'authentication_success'],
                ],
            ]]);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS alerts_dedup_open_uidx');
        }
        Schema::table('alerts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('superseded_by');
            $t->dropColumn(['last_notified_at', 'matched_iocs']);
        });
    }
};
