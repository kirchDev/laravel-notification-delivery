<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use KirchDev\NotificationDelivery\Events\NotificationRead;
use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * Mark everything unread as read, and report how many rows moved.
 *
 * One UPDATE rather than a loop of saves: "mark all read" is pressed on an inbox with hundreds of
 * rows, and model events on a bulk read carry nothing anybody listens for.
 *
 * Fires one NotificationRead with no public id when at least one row moved, and nothing otherwise.
 */
final class MarkAllNotificationsAsRead
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly CountUnreadNotifications $unread,
    ) {}

    public function execute(object $notifiable): int
    {
        if (NotificationDelivery::morphKeyFor($notifiable) === null) {
            return 0;
        }

        $moved = NotificationDelivery::notificationModel()::query()
            ->forNotifiable($notifiable)
            ->unread()
            ->update(['read_at' => now()]);

        if ($moved > 0) {
            $this->events->dispatch(new NotificationRead(
                $notifiable,
                null,
                $this->unread->execute($notifiable),
            ));
        }

        return $moved;
    }
}
