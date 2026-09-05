<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Channels;

use Illuminate\Notifications\Notification;

/**
 * The `live` channel delivers nothing, and that is not an oversight.
 *
 * There is exactly one WebSocket event per notification, fired by InboxChannel, and `live` only
 * decides whether `announce` sits on its payload. Splitting that into a second event would mean
 * a recipient who switched the toast off also stopped receiving the counter update — the bell
 * would go stale until they reloaded.
 *
 * The class exists so `live` has a laravelChannel() like every other channel and needs no special
 * case anywhere in the chain. Laravel calls send(), and send() has nothing left to do.
 */
class LiveChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        // Intentionally empty — see the class docblock.
    }
}
