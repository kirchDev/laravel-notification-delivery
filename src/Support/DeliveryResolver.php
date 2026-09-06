<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Contracts\SuppressionPolicy;
use KirchDev\NotificationDelivery\Notifications\TypedNotification;
use WeakMap;

/**
 * The gate chain, run per notification and channel, in order. Each gate can stop the chain.
 *
 *   1. Does the type know this channel, and can this notifiable resolve it?   (package)
 *   2. Is the channel locked? Then send, skipping gates 3 and 4.             (package)
 *   3. Has the recipient switched it off? No row means undecided, not off.   (package)
 *   4. Does the SuppressionPolicy say this is a bad moment?                  (application)
 *
 * Bound scoped, and the result memoised per (notifiable, notification) pair: via() asks for the
 * immediate channels and InboxChannel then asks whether `live` survived. Running the chain twice
 * would be wasteful, and — with a policy that reads the clock — could disagree with itself
 * between the two calls.
 *
 * The notifiable is a WeakMap key rather than an object id, because an object id is reused once
 * its object is collected, and a long-running worker sending thousands of notifications would
 * eventually answer one recipient's question with another's decision. The notification cannot be
 * a key at all: NotificationSender clones it for the send and again for every channel, so the two
 * call sites never hold the same object. Its own token identifies it across those clones.
 */
final class DeliveryResolver
{
    /**
     * @var WeakMap<object, array<string, DeliveryDecision>>
     */
    private WeakMap $cache;

    public function __construct(
        private readonly PreferenceResolver $preferences,
        private readonly SuppressionPolicy $suppression,
    ) {
        $this->cache = new WeakMap;
    }

    public function decide(object $notifiable, TypedNotification $notification): DeliveryDecision
    {
        $token = $notification->deliveryToken();

        /** @var array<string, DeliveryDecision> $decisions */
        $decisions = $this->cache[$notifiable] ?? [];

        if (! isset($decisions[$token])) {
            $decisions[$token] = $this->run($notifiable, $notification);

            $this->cache[$notifiable] = $decisions;
        }

        return $decisions[$token];
    }

    private function run(object $notifiable, TypedNotification $notification): DeliveryDecision
    {
        $type = $notification->type();
        $definition = $type->definition();

        $immediate = [];
        $deferred = [];
        $dropped = [];

        foreach ($definition->channels() as $channel) {
            // Gate 1 — the type knows the channel by construction here; what is still open is
            // whether this particular recipient can receive on it at all.
            if (! $channel->isAvailableFor($notifiable)) {
                $dropped[] = $channel;

                continue;
            }

            // Gate 2 — locked wins over everything after it. This is the inbox: a notification
            // nobody can switch off, because the alternative is losing it.
            if ($definition->isLocked($channel)) {
                $immediate[] = $channel;

                continue;
            }

            // Gate 3 — an explicit false is off; null is undecided and falls back to the type's
            // own default, which is what lets a new type ship without a backfill.
            $preference = $this->preferences->resolve($notifiable, $type, $channel);

            if ($preference === false || ($preference === null && ! $definition->isDefault($channel))) {
                $dropped[] = $channel;

                continue;
            }

            // Gate 4 — only channels that interrupt out of band get asked. `live` never does:
            // the tab being open is the whole point of it.
            if (! $channel->isQuietable()) {
                $immediate[] = $channel;

                continue;
            }

            $decision = $this->suppression->decide($notifiable, $type, $channel);

            if ($decision->isDrop()) {
                $dropped[] = $channel;

                continue;
            }

            if ($decision->isDefer()) {
                $deferred[] = ['channel' => $channel, 'delaySeconds' => $decision->delaySeconds()];

                continue;
            }

            $immediate[] = $channel;
        }

        return new DeliveryDecision($immediate, $deferred, $dropped);
    }

    /**
     * Run the chain for a single channel without the memo — what the deferred job re-checks with
     * when its delay expires.
     */
    public function decideChannel(object $notifiable, NotificationType $type, Channel $channel): SuppressionDecision
    {
        $definition = $type->definition();

        if (! $definition->knows($channel) || ! $channel->isAvailableFor($notifiable)) {
            return SuppressionDecision::drop();
        }

        if ($definition->isLocked($channel)) {
            return SuppressionDecision::send();
        }

        $preference = $this->preferences->resolve($notifiable, $type, $channel);

        if ($preference === false || ($preference === null && ! $definition->isDefault($channel))) {
            return SuppressionDecision::drop();
        }

        if (! $channel->isQuietable()) {
            return SuppressionDecision::send();
        }

        return $this->suppression->decide($notifiable, $type, $channel);
    }
}
