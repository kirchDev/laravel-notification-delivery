<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Enums;

use KirchDev\NotificationDelivery\Channels\InboxChannel;
use KirchDev\NotificationDelivery\Channels\LiveChannel;
use KirchDev\NotificationDelivery\Contracts\Channel;

/**
 * The three channels the package itself ships.
 *
 * Push and SMS are deliberately absent. laravel-notification-channels/webpush already ships the
 * channel, the subscription table, a HasPushSubscriptions trait, VAPID key generation and the
 * cleanup of expired endpoints — writing a second one here would be worse and permanent. An
 * application wires one in with its own enum, which is precisely why Channel is an interface.
 */
enum CoreChannel: string implements Channel
{
    /** Writes the row and fires NotificationBroadcasted. The truth. */
    case Inbox = 'inbox';

    /** The toast in an open tab — sets `announce` on the broadcast payload. */
    case Live = 'live';

    /** Laravel's own mail channel. */
    case Mail = 'mail';

    public function laravelChannel(): string
    {
        return match ($this) {
            self::Inbox => InboxChannel::class,
            self::Live => LiveChannel::class,
            self::Mail => 'mail',
        };
    }

    /**
     * The inbox is never configurable — a store a recipient can switch off is not a store, and
     * the notification they turned it off for is the one they come looking for later.
     */
    public function userConfigurable(): bool
    {
        return $this !== self::Inbox;
    }

    /**
     * `live` is not quietable, and that is the subtle one. The broadcast carries a data sync
     * (counter and list, which must always run or the bell shows stale numbers until the next
     * reload) and an interruption (the toast). Only the second is a delivery anyone would want
     * to hold back, and holding it back for a tab that is open right now means nothing — the
     * point of it is that they are already looking. Push, its sibling that interrupts through
     * the OS, is quietable for exactly the reason `live` is not.
     */
    public function isQuietable(): bool
    {
        return $this === self::Mail;
    }

    public function isAvailableFor(object $notifiable): bool
    {
        if ($this !== self::Mail) {
            return true;
        }

        // Laravel resolves a mail address through RoutesNotifications. A notifiable without it —
        // an application's own on-demand route object, say — has no address to offer, and a
        // settings page that showed a mail switch for it would show a switch that does nothing.
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return false;
        }

        return filled($notifiable->routeNotificationFor('mail'));
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Inbox => 0,
            self::Live => 10,
            self::Mail => 20,
        };
    }
}
