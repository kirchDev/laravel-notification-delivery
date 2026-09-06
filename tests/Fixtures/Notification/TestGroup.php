<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures\Notification;

use KirchDev\NotificationDelivery\Contracts\NotificationGroup;

enum TestGroup: string implements NotificationGroup
{
    case Organisation = 'organisation';
}
