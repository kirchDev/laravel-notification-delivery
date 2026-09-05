<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures\Notification;

use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Support\NotificationDefinition;

enum TestNotificationType: string implements NotificationType
{
    /** Inbox locked, live and mail on by default. */
    case Invited = 'test.member.invited';

    /** Inbox only. */
    case Removed = 'test.member.removed';

    /** A bulk send: stored, but no WebSocket fan-out. */
    case Digest = 'test.digest';

    /** Mail is known but off until the recipient switches it on. */
    case OptIn = 'test.opt.in';

    public static function group(): NotificationGroup
    {
        return TestGroup::Organisation;
    }

    public function definition(): NotificationDefinition
    {
        return match ($this) {
            self::Invited => new NotificationDefinition(
                default: [CoreChannel::Live, CoreChannel::Mail],
                locked: [CoreChannel::Inbox],
            ),
            self::Removed => new NotificationDefinition(
                locked: [CoreChannel::Inbox],
            ),
            self::Digest => new NotificationDefinition(
                default: [CoreChannel::Mail],
                locked: [CoreChannel::Inbox],
                broadcast: false,
            ),
            self::OptIn => new NotificationDefinition(
                locked: [CoreChannel::Inbox],
                available: [CoreChannel::Inbox, CoreChannel::Mail],
            ),
        };
    }
}
