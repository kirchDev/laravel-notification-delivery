<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use KirchDev\NotificationDelivery\Events\NotificationRead;
use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * Mark one notification read.
 *
 * Scoped to the notifiable, so an id arriving from a request cannot reach another recipient's
 * row. Returns false for an unknown id and for one that was already read — the caller cannot tell
 * the two apart, and does not need to.
 *
 * Fires NotificationRead only when the row actually moved, so a repeated click broadcasts nothing.
 */
final class MarkNotificationAsRead
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly CountUnreadNotifications $unread,
    ) {}

    public function execute(object $notifiable, int|string $id): bool
    {
        if (NotificationDelivery::morphKeyFor($notifiable) === null) {
            return false;
        }

        $notification = NotificationDelivery::notificationModel()::query()
            ->forNotifiable($notifiable)
            ->whereKey($id)
            ->first();

        if ($notification === null || ! $notification->markAsRead()) {
            return false;
        }

        $this->events->dispatch(new NotificationRead(
            $notifiable,
            $notification->publicId(),
            $this->unread->execute($notifiable),
        ));

        return true;
    }
}
