<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            // Null follows the SuppressionPolicy, true skips it. Nullable so every row stored
            // before this column existed keeps meaning exactly what it meant — no backfill.
            $table->boolean('bypass_suppression')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropColumn('bypass_suppression');
        });
    }

    private function table(): string
    {
        $table = config('notification-delivery.table_names.preferences', 'notification_preferences');

        return is_string($table) && $table !== '' ? $table : 'notification_preferences';
    }
};
