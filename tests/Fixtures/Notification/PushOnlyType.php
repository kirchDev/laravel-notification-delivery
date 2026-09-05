<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures\Notification;

use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Support\NotificationDefinition;

/**
 * A type reaching for an application-owned channel, so gate 1 has something to turn down.
 */
enum PushOnlyType: string implements NotificationType
{
    case Alert = 'test.alert';

    public static function group(): NotificationGroup
    {
        return TestGroup::Organisation;
    }

    public function definition(): NotificationDefinition
    {
        return new NotificationDefinition(
            default: [ExtraChannel::Push],
            locked: [CoreChannel::Inbox],
        );
    }
}
