<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures\Notification;

use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Support\NotificationDefinition;

/**
 * Grouping is optional: this one resolves on two tiers rather than three.
 */
enum UngroupedType: string implements NotificationType
{
    case Plain = 'test.plain';

    public static function group(): ?NotificationGroup
    {
        return null;
    }

    public function definition(): NotificationDefinition
    {
        return new NotificationDefinition(
            default: [CoreChannel::Mail],
            locked: [CoreChannel::Inbox],
        );
    }
}
