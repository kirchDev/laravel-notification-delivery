<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * The number on the bell. Its own action because it is asked for on nearly every page, and
 * loading the whole list to count it is the mistake this exists to prevent.
 */
final class CountUnreadNotifications
{
    public function execute(object $notifiable): int
    {
        if (NotificationDelivery::morphKeyFor($notifiable) === null) {
            return 0;
        }

        return NotificationDelivery::notificationModel()::query()
            ->forNotifiable($notifiable)
            ->unread()
            ->count();
    }
}
