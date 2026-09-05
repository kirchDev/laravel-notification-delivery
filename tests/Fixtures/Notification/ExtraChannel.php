<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Tests\Fixtures\Notification;

use Illuminate\Database\Eloquent\Model;
use KirchDev\NotificationDelivery\Contracts\Channel;

/**
 * What an application's own channel enum looks like — the seam the Channel interface exists for.
 * `isAvailableFor()` stands in for "has this recipient registered a device / a phone number".
 */
enum ExtraChannel: string implements Channel
{
    case Push = 'push';

    public function laravelChannel(): string
    {
        return 'push';
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
        return $notifiable instanceof Model && filled($notifiable->getAttribute('name'));
    }

    public function sortOrder(): int
    {
        return 30;
    }
}
