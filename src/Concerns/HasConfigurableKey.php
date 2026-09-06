<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Drives the primary key type from config('notification-delivery.keys.primary_key_type').
 *
 * Unlike a hardcoded HasUuids, this lets a host run the package on integer,
 * UUID, or ULID keys by flipping one config value before migrating. UUID/ULID
 * values are generated on create when the key is still empty.
 */
trait HasConfigurableKey
{
    public static function bootHasConfigurableKey(): void
    {
        static::creating(function (Model $model): void {
            $type = self::notificationDeliveryKeyType();

            if ($type === 'id') {
                return;
            }

            $keyName = $model->getKeyName();

            if (filled($model->getAttribute($keyName))) {
                return;
            }

            $model->setAttribute(
                $keyName,
                $type === 'ulid' ? (string) Str::ulid() : (string) Str::orderedUuid(),
            );
        });
    }

    public function getKeyType(): string
    {
        return self::notificationDeliveryKeyType() === 'id' ? 'int' : 'string';
    }

    public function getIncrementing(): bool
    {
        return self::notificationDeliveryKeyType() === 'id';
    }

    public static function notificationDeliveryKeyType(): string
    {
        $type = config('notification-delivery.keys.primary_key_type', 'id');

        return is_string($type) ? $type : 'id';
    }
}
