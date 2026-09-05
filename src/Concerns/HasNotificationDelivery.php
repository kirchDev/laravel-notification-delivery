<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;
use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * What a recipient gains: their inbox, their unread count, and their preferences.
 *
 * This replaces the half of Laravel's Notifiable that this package supersedes. A model keeps
 * `Illuminate\Notifications\RoutesNotifications` and adds this trait, instead of `Notifiable` —
 * which is RoutesNotifications *plus* HasDatabaseNotifications, and the latter defines
 * notifications() against Laravel's own `notifications` table, which this package never writes.
 * Left in place it is either a trait method collision or, worse, a relation that silently always
 * comes back empty.
 *
 *     use Illuminate\Notifications\RoutesNotifications;
 *     use KirchDev\NotificationDelivery\Concerns\HasNotificationDelivery;
 *
 *     class User extends Authenticatable
 *     {
 *         use HasNotificationDelivery;
 *         use RoutesNotifications;
 *     }
 */
trait HasNotificationDelivery
{
    /**
     * @return MorphMany<DeliveredNotification, $this>
     */
    public function deliveredNotifications(): MorphMany
    {
        return $this->morphMany(
            NotificationDelivery::notificationModel(),
            'notifiable',
            NotificationDelivery::MORPH_TYPE,
            NotificationDelivery::morphKey(),
        )->latest('created_at');
    }

    /**
     * @return MorphMany<DeliveredNotification, $this>
     */
    public function unreadNotifications(): MorphMany
    {
        return $this->deliveredNotifications()->whereNull('read_at');
    }

    /**
     * @return MorphMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): MorphMany
    {
        return $this->morphMany(
            NotificationDelivery::preferenceModel(),
            'notifiable',
            NotificationDelivery::MORPH_TYPE,
            NotificationDelivery::morphKey(),
        );
    }

    /**
     * The number on the bell.
     */
    public function unreadNotificationCount(): int
    {
        return $this->unreadNotifications()->count();
    }
}
