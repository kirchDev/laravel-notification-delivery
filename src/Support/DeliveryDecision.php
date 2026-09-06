<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Enums\CoreChannel;

/**
 * What the gate chain decided for one notification to one recipient: which channels deliver now,
 * which are held back and for how long, and which were dropped.
 *
 * Computed once per (notification, notifiable) and read twice — via() takes the immediate list,
 * and InboxChannel asks whether `live` survived so it can set `announce` on the payload it
 * broadcasts.
 */
final class DeliveryDecision
{
    /**
     * @param  list<Channel>  $immediate
     * @param  array<int, array{channel: Channel, delaySeconds: int}>  $deferred
     * @param  list<Channel>  $dropped
     */
    public function __construct(
        private readonly array $immediate = [],
        private readonly array $deferred = [],
        private readonly array $dropped = [],
    ) {}

    /**
     * @return list<Channel>
     */
    public function immediate(): array
    {
        return $this->immediate;
    }

    /**
     * @return array<int, array{channel: Channel, delaySeconds: int}>
     */
    public function deferred(): array
    {
        return $this->deferred;
    }

    /**
     * @return list<Channel>
     */
    public function dropped(): array
    {
        return $this->dropped;
    }

    /**
     * The Laravel channel names via() hands back.
     *
     * @return list<string>
     */
    public function laravelChannels(): array
    {
        return array_values(array_map(
            static fn (Channel $channel): string => $channel->laravelChannel(),
            $this->immediate,
        ));
    }

    public function delivers(Channel $channel): bool
    {
        foreach ($this->immediate as $immediate) {
            if ($immediate === $channel) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this delivery should interrupt: the toast in an open tab, as opposed to the bell
     * counter quietly moving. The data sync happens either way — only the interruption is a
     * delivery anybody would want to switch off.
     */
    public function announces(): bool
    {
        return $this->delivers(CoreChannel::Live);
    }
}
