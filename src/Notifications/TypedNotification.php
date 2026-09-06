<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Jobs\DeliverDeferredNotification;
use KirchDev\NotificationDelivery\Support\DeliveryDecision;
use KirchDev\NotificationDelivery\Support\DeliveryResolver;
use KirchDev\NotificationDelivery\Support\PayloadData;

/**
 * A notification that carries a type key and a payload, and lets the gate chain answer via().
 *
 * It is still an ordinary Illuminate notification — Notification::send() stays the entry point,
 * every channel stays a Laravel channel, toMail() and friends stay where they are. All this adds
 * is who decides which of them run.
 */
abstract class TypedNotification extends Notification
{
    /**
     * This notification's identity for the gate chain's memo, assigned on first use.
     *
     * Not Laravel's own $id: that one is stamped per notifiable, after via() has already run, and
     * a notification sent to a hundred recipients carries a hundred of them.
     */
    private ?string $deliveryToken = null;

    /**
     * The stable key this notification is stored and configured under.
     */
    abstract public function type(): NotificationType;

    /**
     * What the inbox stores and the frontend renders: a translation key, its arguments, and any
     * actions. Not a rendered string — the inbox translates client-side so a language switch
     * takes effect without a reload, while mail translates server-side in the recipient's locale.
     */
    abstract public function payload(object $notifiable): PayloadData;

    /**
     * The channels that survived the gate chain.
     *
     * A deferred channel is not in this list: via() is evaluated once and synchronously and has
     * no way to express "in two minutes", so the channel is dropped here and a job scheduled
     * instead — which is why the method feeding via() is called immediate().
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $decision = $this->deliveryDecision($notifiable);

        $this->scheduleDeferred($notifiable, $decision);

        return $decision->laravelChannels();
    }

    public function deliveryDecision(object $notifiable): DeliveryDecision
    {
        return app(DeliveryResolver::class)->decide($notifiable, $this);
    }

    /**
     * What the gate chain memoises its verdict under.
     *
     * It has to be a value rather than the object's own identity, because Laravel never hands the
     * same object to both call sites: NotificationSender clones the notification once for the
     * send and again for every channel, so via() and InboxChannel see three different objects of
     * one notification. A property survives all of that — clone copies it — while spl_object_id
     * and a WeakMap key do not.
     */
    public function deliveryToken(): string
    {
        return $this->deliveryToken ??= (string) Str::uuid();
    }

    private function scheduleDeferred(object $notifiable, DeliveryDecision $decision): void
    {
        foreach ($decision->deferred() as $deferred) {
            /** @var Channel $channel */
            $channel = $deferred['channel'];

            DeliverDeferredNotification::dispatch($notifiable, $this, $channel)
                ->delay($deferred['delaySeconds']);
        }
    }
}
