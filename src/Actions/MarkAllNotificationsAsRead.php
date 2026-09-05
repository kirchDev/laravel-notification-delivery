<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * Mark everything unread as read, and report how many rows moved.
 *
 * One UPDATE rather than a loop of saves: "mark all read" is pressed on an inbox with hundreds of
 * rows, and model events on a bulk read carry nothing anybody listens for.
 */
final class MarkAllNotificationsAsRead
{
    public function execute(object $notifiable): int
    {
        if (NotificationDelivery::morphKeyFor($notifiable) === null) {
            return 0;
        }

        return NotificationDelivery::notificationModel()::query()
            ->forNotifiable($notifiable)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
