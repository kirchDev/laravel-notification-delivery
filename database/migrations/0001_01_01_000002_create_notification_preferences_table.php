<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('notification-delivery.table_names');
        $morphKey = config('notification-delivery.column_names.notifiable_morph_key', 'notifiable_id');
        $primaryKeyType = config('notification-delivery.keys.primary_key_type', 'id');
        $morphKeyType = config('notification-delivery.keys.notifiable_morph_key_type', 'id');

        Schema::create($tableNames['preferences'], function (Blueprint $table) use ($morphKey, $morphKeyType, $primaryKeyType) {
            $this->addPrimaryKey($table, 'id', $primaryKeyType);

            $table->string('notifiable_type');
            $this->addKeyColumn($table, $morphKey, $morphKeyType);

            // Either a type key ('organisation.member.invited') or a group key
            // ('group:organisation'). One prefix, not a second schema.
            $table->string('type');
            $table->string('channel');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(
                ['notifiable_type', $morphKey, 'type', 'channel'],
                'notification_preferences_unique',
            );
            $table->index(['notifiable_type', $morphKey], 'notification_preferences_notifiable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('notification-delivery.table_names.preferences', 'notification_preferences'));
    }

    private function addPrimaryKey(Blueprint $table, string $column, string $type): void
    {
        match ($type) {
            'uuid' => $table->uuid($column)->primary(),
            'ulid' => $table->ulid($column)->primary(),
            default => $table->id($column),
        };
    }

    private function addKeyColumn(Blueprint $table, string $column, string $type, bool $nullable = false): void
    {
        $definition = match ($type) {
            'uuid' => $table->uuid($column),
            'ulid' => $table->ulid($column),
            default => $table->unsignedBigInteger($column),
        };

        if ($nullable) {
            $definition->nullable();
        }
    }
};
