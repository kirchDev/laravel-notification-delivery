<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;
use KirchDev\NotificationDelivery\Support\SuppressionDecision;

/**
 * A gate 4 that always holds quietable channels back — what an application binds when it wants
 * "mail only if they didn't read it in the app".
 */
final class DeferMail implements SuppressionPolicy
{
    public function decide(object $notifiable, NotificationType $type, Channel $channel): SuppressionDecision
    {
        return SuppressionDecision::defer(120);
    }
}
