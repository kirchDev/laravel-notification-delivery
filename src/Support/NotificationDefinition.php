<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Enums\ChannelPreference;

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
     * @param  list<Channel>|null  $available  Which channels this type offers beyond its locked
     *                                         and always ones. Null means "the defaults" — the
     *                                         usual case. Name it explicitly to offer a channel
     *                                         that is off by default but switchable on. Locked
     *                                         channels are always known, listed here or not: a
     *                                         list that could omit one would drop the
     *                                         notification it exists to guarantee.
     * @param  bool  $broadcast  False switches off the NotificationBroadcasted fan-out for a
     *                           bulk send that must not produce ten thousand WebSocket events.
     *                           A property of the type, never of a recipient's preference.
     * @param  list<Channel>  $always  On, and past the SuppressionPolicy, unless the recipient
     *                                 says otherwise — ChannelPreference::Always as the default
     *                                 for a security alert that should not wait for the recipient
     *                                 to be away. Unlike `locked`, a stored type or group row
     *                                 still wins. Known whether or not `available` repeats it. On
     *                                 a channel that is never quiet it is plain `default`.
     */
    public function __construct(
        public readonly array $default = [],
        public readonly array $locked = [],
        public readonly ?array $available = null,
        public readonly bool $broadcast = true,
        public readonly array $always = [],
    ) {}

    /**
     * Every channel this type knows, in UI order.
     *
     * @return list<Channel>
     */
    public function channels(): array
    {
        $channels = [...$this->locked, ...$this->always, ...($this->available ?? $this->default)];

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
     * Whether the channel is on for a recipient who has expressed no preference. A locked or an
     * always channel is on by definition, whether or not it is also listed as a default.
     */
    public function isDefault(Channel $channel): bool
    {
        return $this->isLocked($channel)
            || $this->contains($this->always, $channel)
            || $this->contains($this->default, $channel);
    }

    /**
     * What a recipient who has expressed no preference gets on this channel.
     *
     * A locked channel skips gate 4 as well as gate 3, so on a quietable channel it reads as
     * Always.
     */
    public function defaultPreference(Channel $channel): ChannelPreference
    {
        if (! $this->isDefault($channel)) {
            return ChannelPreference::Off;
        }

        if (! $channel->isQuietable()) {
            return ChannelPreference::On;
        }

        return $this->isLocked($channel) || $this->contains($this->always, $channel)
            ? ChannelPreference::Always
            : ChannelPreference::WhenAway;
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
