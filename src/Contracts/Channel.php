<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Contracts;

use BackedEnum;

/**
 * A delivery channel, as an enum case.
 *
 * Deliberately an interface and not an enum: an enum in the package would be closed, and no
 * application could add `push`, `sms` or a channel some third party ships next year. The package
 * contributes CoreChannel; an application contributes its own enum, and a third-party channel is
 * wired in by pointing `laravelChannel()` at that package's channel class.
 */
interface Channel extends BackedEnum
{
    /**
     * The bridge to actual delivery: a Laravel channel name ('mail', 'database') or the
     * class-string of a channel object. This is what ends up in a notification's via().
     */
    public function laravelChannel(): string;

    /**
     * Whether a recipient may switch this channel off. False for the inbox — a notification
     * store that can be switched off is not a store.
     */
    public function userConfigurable(): bool;

    /**
     * Whether the SuppressionPolicy (gate 4) applies. True for channels that interrupt out of
     * band — mail, push. False for `live`, where the open tab is the whole point.
     */
    public function isQuietable(): bool;

    /**
     * Whether this notifiable can receive on this channel at all.
     *
     * Laravel silently skips a channel when routeNotificationFor() returns nothing, which is
     * fine for delivery and wrong for a settings page — it would otherwise offer a push switch
     * to someone with no device registered.
     */
    public function isAvailableFor(object $notifiable): bool;

    /**
     * Column order in a settings UI. Lower sorts first.
     */
    public function sortOrder(): int;
}
