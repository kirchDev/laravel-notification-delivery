<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\Channel;

/**
 * What a notification type says about its channels.
 *
 * Applications that need more per type (an icon, a mail template, a retention override) extend
 * this class and narrow NotificationType::definition()'s return type — PHP's covariant returns
 * make that work without a second interface.
 */
class NotificationDefinition
{
    /**
     * @param  list<Channel>  $default  On unless the recipient says otherwise.
     * @param  list<Channel>  $locked  Always on; gates 3 and 4 are skipped for these.
     * @param  list<Channel>|null  $available  Every channel this type knows. Null means
     *                                         "default plus locked" — the usual case. Name it
     *                                         explicitly to offer a channel that is off by
     *                                         default but switchable on.
     * @param  bool  $broadcast  False switches off the NotificationBroadcasted fan-out for a
     *                           bulk send that must not produce ten thousand WebSocket events.
     *                           A property of the type, never of a recipient's preference.
     */
    public function __construct(
        public readonly array $default = [],
        public readonly array $locked = [],
        public readonly ?array $available = null,
        public readonly bool $broadcast = true,
    ) {}

    /**
     * Every channel this type knows, in UI order.
     *
     * @return list<Channel>
     */
    public function channels(): array
    {
        $channels = $this->available ?? [...$this->locked, ...$this->default];

        $unique = [];

        foreach ($channels as $channel) {
            $unique[$channel::class.'::'.$channel->value] = $channel;
        }

        $channels = array_values($unique);

        usort($channels, static fn (Channel $a, Channel $b): int => $a->sortOrder() <=> $b->sortOrder());

        return $channels;
    }

    public function knows(Channel $channel): bool
    {
        return $this->contains($this->channels(), $channel);
    }

    public function isLocked(Channel $channel): bool
    {
        return $this->contains($this->locked, $channel);
    }

    /**
     * Whether the channel is on for a recipient who has expressed no preference. A locked
     * channel is on by definition, whether or not it is also listed as a default.
     */
    public function isDefault(Channel $channel): bool
    {
        return $this->isLocked($channel) || $this->contains($this->default, $channel);
    }

    /**
     * @param  list<Channel>  $haystack
     */
    private function contains(array $haystack, Channel $needle): bool
    {
        foreach ($haystack as $channel) {
            if ($channel === $needle) {
                return true;
            }
        }

        return false;
    }
}
