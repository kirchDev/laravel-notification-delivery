<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

/**
 * A recipient that names its own broadcast channel, the way Laravel lets any notifiable do.
 */
class BroadcastRoutingUser extends User
{
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'inbox.'.$this->getKey();
    }
}
