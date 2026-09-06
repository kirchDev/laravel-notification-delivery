<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Channels;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Notification;
use KirchDev\NotificationDelivery\Events\NotificationBroadcasted;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\NotificationDelivery;
use KirchDev\NotificationDelivery\Notifications\TypedNotification;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;

/**
 * The inbox: writes the stored notification and fires the broadcast.
 *
 * This is the one channel that must never be skipped, which is why CoreChannel::Inbox is not
 * user-configurable and is expected to sit in a type's `locked` list. Everything else is a copy
 * of what lives here.
 *
 * Its collaborators are resolved per send rather than injected: Laravel's ChannelManager is a
 * singleton and caches the channel object it built for the first delivery, so a constructor
 * argument here outlives every scope reset. On Octane and in a queue worker that would pin the
 * scoped DeliveryResolver — and the preference cache behind it — from the first request for the
 * lifetime of the process, and answer a later recipient with an earlier one's preferences.
 */
class InboxChannel
{
    public function __construct(private readonly Container $container) {}

    public function send(object $notifiable, Notification $notification): ?DeliveredNotification
    {
        if (! $notification instanceof TypedNotification) {
            // Laravel hands every notification to whichever channels its own via() named. One
            // that is not a TypedNotification has no type and no payload, so there is nothing to
            // store — and inventing a row from its class name is exactly the liability the type
            // key exists to avoid.
            return null;
        }

        $key = NotificationDelivery::morphKeyFor($notifiable);

        if ($key === null) {
            // An on-demand route or an unsaved model: deliverable by mail, not storable.
            return null;
        }

        $payload = $notification->payload($notifiable);
        $model = NotificationDelivery::notificationModel();

        $row = new $model;
        $row->fill([
            NotificationDelivery::MORPH_TYPE => NotificationDelivery::morphTypeFor($notifiable),
            NotificationDelivery::morphKey() => $key,
            'type' => NotificationDelivery::typeKey($notification->type()),
            'payload' => $payload->toStoredArray(),
        ]);
        $row->save();

        if ($notification->type()->definition()->broadcast) {
            $this->events()->dispatch(new NotificationBroadcasted(
                $notifiable,
                $payload->forDelivery(
                    $row->publicId(),
                    $this->resolver()->decide($notifiable, $notification)->announces(),
                ),
            ));
        }

        return $row;
    }

    private function resolver(): DeliveryResolver
    {
        /** @var DeliveryResolver $resolver */
        $resolver = $this->container->make(DeliveryResolver::class);

        return $resolver;
    }

    private function events(): Dispatcher
    {
        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        return $events;
    }
}
