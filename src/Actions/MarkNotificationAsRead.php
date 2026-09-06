<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * Mark one notification read.
 *
 * Scoped to the notifiable, so an id arriving from a request cannot reach another recipient's
 * row. Returns false for an unknown id and for one that was already read — the caller cannot tell
 * the two apart, and does not need to.
 */
final class MarkNotificationAsRead
{
    public function execute(object $notifiable, int|string $id): bool
    {
        if (NotificationDelivery::morphKeyFor($notifiable) === null) {
            return false;
        }

        $notification = NotificationDelivery::notificationModel()::query()
            ->forNotifiable($notifiable)
            ->whereKey($id)
            ->first();

        return $notification !== null && $notification->markAsRead();
    }
}
