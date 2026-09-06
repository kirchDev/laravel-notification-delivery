<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use Illuminate\Database\Eloquent\Collection;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * A recipient's inbox, newest first. The host decides how to present it (DTO, grouping, infinite
 * scroll) and owns the controller — the package ships no routes.
 */
final class ListNotifications
{
    /**
     * @return Collection<int, DeliveredNotification>
     */
    public function execute(object $notifiable, int $limit = 20, bool $unreadOnly = false): Collection
    {
        if (NotificationDelivery::morphKeyFor($notifiable) === null) {
            /** @var Collection<int, DeliveredNotification> $empty */
            $empty = new Collection;

            return $empty;
        }

        $query = NotificationDelivery::notificationModel()::query()
            ->forNotifiable($notifiable);

        if ($unreadOnly) {
            $query->unread();
        }

        /** @var Collection<int, DeliveredNotification> $notifications */
        $notifications = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();

        return $notifications;
    }
}
