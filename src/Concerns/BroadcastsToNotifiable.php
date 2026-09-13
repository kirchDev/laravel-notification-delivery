<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Concerns;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;

/**
 * The one private channel every package event goes out on, shared so a delivery and a read can
 * never end up on two different channels for the same recipient.
 *
 * The using class carries the recipient as a public `$notifiable` property.
 */
trait BroadcastsToNotifiable
{
    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channelName())];
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
