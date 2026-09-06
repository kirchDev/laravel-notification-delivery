<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;
use KirchDev\NotificationDelivery\Support\SuppressionDecision;

/**
 * A gate 4 that sends everything and counts how often it was asked — the visible end of "how
 * often did the chain actually run".
 */
final class CountingSuppression implements SuppressionPolicy
{
    public int $calls = 0;

    public function decide(object $notifiable, NotificationType $type, Channel $channel): SuppressionDecision
    {
        $this->calls++;

        return SuppressionDecision::send();
    }
}
