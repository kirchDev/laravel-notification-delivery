<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures\Notification;

use KirchDev\NotificationDelivery\Contracts\Channel;

/**
 * An application's replacement for the core `mail` channel: same backed value, its own Laravel
 * channel, and a sort order that places it after every core channel.
 */
enum OverridingMailChannel: string implements Channel
{
    case Mail = 'mail';

    public function laravelChannel(): string
    {
        return 'transactional-mail';
    }

    public function userConfigurable(): bool
    {
        return true;
    }

    public function isQuietable(): bool
    {
        return true;
    }

    public function isAvailableFor(object $notifiable): bool
    {
        return true;
    }

    public function sortOrder(): int
    {
        return 99;
    }
}
