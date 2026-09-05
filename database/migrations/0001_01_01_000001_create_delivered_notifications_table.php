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

        Schema::create($tableNames['notifications'], function (Blueprint $table) use ($morphKey, $morphKeyType, $primaryKeyType) {
            $this->addPrimaryKey($table, 'id', $primaryKeyType);

            // No foreign key on (notifiable_type, notifiable_id): that side is polymorphic, and
            // it is deliberately not a plain user_id — an invitation sent to an address with no
            // account yet still needs a row, and a NOT NULL user_id has none for it.
            $table->string('notifiable_type');
            $this->addKeyColumn($table, $morphKey, $morphKeyType);

            $table->string('type')->index();
            $table->json('payload');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell: unread count and the list, both scoped to one recipient. Ordering by the
            // key rather than created_at keeps the second index usable on every key type.
            $table->index(['notifiable_type', $morphKey, 'read_at'], 'delivered_notifications_inbox_index');
            $table->index(['notifiable_type', $morphKey, 'created_at'], 'delivered_notifications_recent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('notification-delivery.table_names.notifications', 'delivered_notifications'));
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
