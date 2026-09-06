<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;

/**
 * The default gate 4: there is no bad moment.
 *
 * Named for its behaviour rather than prefixed `Default`, because "do nothing" is a policy an
 * application may well want to keep — a package that suppressed anything by default would be
 * deciding, on the consumer's behalf, that some notification is not worth delivering.
 */
final class NeverSuppress implements SuppressionPolicy
{
    public function decide(object $notifiable, NotificationType $type, Channel $channel): SuppressionDecision
    {
        return SuppressionDecision::send();
    }
}
