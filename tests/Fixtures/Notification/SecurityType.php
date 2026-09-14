<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures\Notification;

use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Support\NotificationDefinition;

enum SecurityType: string implements NotificationType
{
    /** Mail goes out even while the recipient is around, unless they say otherwise. */
    case Alert = 'test.security.alert';

    public static function group(): NotificationGroup
    {
        return TestGroup::Organisation;
    }

    public function definition(): NotificationDefinition
    {
        return new NotificationDefinition(
            default: [CoreChannel::Live],
            locked: [CoreChannel::Inbox],
            always: [CoreChannel::Mail],
        );
    }
}
