<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KirchDev\NotificationDelivery\Concerns\BroadcastsToNotifiable;

/**
 * A recipient read one notification, or all of them at once.
 *
 * Fired by MarkNotificationAsRead and MarkAllNotificationsAsRead, and only when a row actually
 * moved — never by DeliveredNotification::markAsRead(), which stays raw persistence. It is what
 * keeps a second open tab's badge honest.
 *
 * A type's `broadcast: false` does not silence it: the unread count covers rows of every type, and
 * a mark-all spans types anyway.
 *
 * Queued like NotificationBroadcasted. The count is absolute and taken at dispatch, so a newer
 * delivery broadcast can overtake it; a listener then shows one too few until the next event.
 */
class NotificationRead implements ShouldBroadcast
{
    use BroadcastsToNotifiable;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string|null  $publicId  The row that was read; null when everything was marked at once.
     * @param  int  $unreadCount  The recipient's unread count after the read, so a listener need not ask.
     */
    public function __construct(
        public readonly object $notifiable,
        public readonly ?string $publicId,
        public readonly int $unreadCount,
    ) {}

    /**
     * @return array{publicId: string|null, unreadCount: int}
     */
    public function broadcastWith(): array
    {
        return [
            'publicId' => $this->publicId,
            'unreadCount' => $this->unreadCount,
        ];
    }

    /**
     * An explicit name keeps Laravel from prefixing the namespace, so the frontend listens for
     * `NotificationRead` rather than for where this class happens to live.
     */
    public function broadcastAs(): string
    {
        return 'NotificationRead';
    }
}
