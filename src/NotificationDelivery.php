<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery;

use Illuminate\Database\Eloquent\Model;
use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;

/**
 * The package's front door: every configurable model, column and key, resolved in one place.
 *
 * Resolving models through here rather than naming the packaged class is not a style preference.
 * An application without a morph map stores the class it resolved, so a row written through
 * KirchDev\NotificationDelivery\Models\DeliveredNotification names a different class than every
 * row written through the application's own subclass — and quietly stops matching.
 */
final class NotificationDelivery
{
    /**
     * The column holding the notifiable's class name. Fixed, unlike its key column: a morph type
     * is a class string on every key type, so there is nothing to configure.
     */
    public const MORPH_TYPE = 'notifiable_type';

    /**
     * The prefix that turns a group into a preference key. One prefix, not a second schema —
     * the `type` column carries either a type key or a prefixed group key.
     */
    public const GROUP_PREFIX = 'group:';

    /**
     * @return class-string<DeliveredNotification>
     */
    public static function notificationModel(): string
    {
        $model = config('notification-delivery.models.notification', DeliveredNotification::class);

        /** @var class-string<DeliveredNotification> $resolved */
        $resolved = is_string($model) && $model !== '' ? $model : DeliveredNotification::class;

        return $resolved;
    }

    /**
     * @return class-string<NotificationPreference>
     */
    public static function preferenceModel(): string
    {
        $model = config('notification-delivery.models.preference', NotificationPreference::class);

        /** @var class-string<NotificationPreference> $resolved */
        $resolved = is_string($model) && $model !== '' ? $model : NotificationPreference::class;

        return $resolved;
    }

    public static function morphKey(): string
    {
        $key = config('notification-delivery.column_names.notifiable_morph_key', 'notifiable_id');

        return is_string($key) && $key !== '' ? $key : 'notifiable_id';
    }

    public static function keyType(): string
    {
        $type = config('notification-delivery.keys.primary_key_type', 'id');

        return is_string($type) ? $type : 'id';
    }

    /**
     * The preference key for a group — the middle resolution tier.
     */
    public static function groupKey(NotificationGroup $group): string
    {
        return self::GROUP_PREFIX.$group->value;
    }

    /**
     * The morph type a notifiable is stored under. Runs through the morph map, so an application
     * that aliases its models keeps its alias here too.
     */
    public static function morphTypeFor(object $notifiable): string
    {
        return $notifiable instanceof Model ? $notifiable->getMorphClass() : $notifiable::class;
    }

    /**
     * The morph key a notifiable is stored under, or null when it has none — an unsaved model,
     * or a notifiable that is not a model at all (an on-demand mail route, say). A null means
     * "nothing to persist against", not "key zero".
     */
    public static function morphKeyFor(object $notifiable): int|string|null
    {
        if (! $notifiable instanceof Model) {
            return null;
        }

        $key = $notifiable->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }

    /**
     * A stable cache identity for a notifiable. Falls back to the object's own identity when
     * there is no key, so two unsaved notifiables never share a cache entry.
     */
    public static function identityFor(object $notifiable): string
    {
        $key = self::morphKeyFor($notifiable);

        return self::morphTypeFor($notifiable).'#'.($key ?? 'object:'.spl_object_id($notifiable));
    }

    /**
     * The stored `type` value for a notification type.
     */
    public static function typeKey(NotificationType $type): string
    {
        return (string) $type->value;
    }
}
