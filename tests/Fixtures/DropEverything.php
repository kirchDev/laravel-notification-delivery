<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;
use KirchDev\NotificationDelivery\Support\SuppressionDecision;

final class DropEverything implements SuppressionPolicy
{
    public function decide(object $notifiable, NotificationType $type, Channel $channel): SuppressionDecision
    {
        return SuppressionDecision::drop();
    }
}
