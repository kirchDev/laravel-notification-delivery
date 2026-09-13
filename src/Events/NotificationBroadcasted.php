<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KirchDev\NotificationDelivery\Concerns\BroadcastsToNotifiable;
use KirchDev\NotificationDelivery\Support\PayloadData;

/**
 * One notification reached one recipient's inbox.
 *
 * Fired unconditionally by InboxChannel (unless the type sets `broadcast: false`), because it
 * carries the data sync as well as the interruption. `announce` on the payload is what separates
 * the two: false updates the bell, true also shows the toast.
 */
class NotificationBroadcasted implements ShouldBroadcast
{
    use BroadcastsToNotifiable;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly object $notifiable,
        public readonly PayloadData $notification,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->notification->toArray();
    }

    /**
     * An explicit name keeps Laravel from prefixing the namespace, so the frontend listens for
     * `NotificationBroadcasted` rather than for where this class happens to live.
     */
    public function broadcastAs(): string
    {
        return 'NotificationBroadcasted';
    }
}
