<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
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
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly object $notifiable,
        public readonly PayloadData $notification,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channelName())];
    }

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

    /**
     * Laravel's own convention, so an application already broadcasting notifications keeps its
     * channel authorisation: `App.Models.User.1`, or whatever the notifiable answers from
     * receivesBroadcastNotificationsOn().
     */
    private function channelName(): string
    {
        $notifiable = $this->notifiable;

        if (method_exists($notifiable, 'receivesBroadcastNotificationsOn')) {
            return (string) $notifiable->receivesBroadcastNotificationsOn();
        }

        $key = $notifiable instanceof Model ? $notifiable->getKey() : null;

        return str_replace('\\', '.', $notifiable::class).'.'.(is_scalar($key) ? $key : '');
    }
}
