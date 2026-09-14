<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Enums;

use KirchDev\NotificationDelivery\Contracts\Channel;

/**
 * What a recipient can say about one channel — the four answers a settings page offers.
 *
 * Which of them a channel accepts follows from whether gate 4 applies to it. A quietable channel
 * has three: off, "when I'm away" (the SuppressionPolicy decides) and "always" (the policy is
 * skipped). A channel that is never quiet has nothing for a policy to decide, so it has two: off
 * and on.
 *
 * Stored as two columns rather than one, because the row that answers `enabled` has to go on
 * answering it for every reader that predates the bypass: `enabled` is off-or-not, and
 * `bypass_suppression` is true only for Always and null otherwise.
 */
enum ChannelPreference: string
{
    case Off = 'off';

    /** A channel that is never quiet, switched on. */
    case On = 'on';

    /** A quietable channel, delivered subject to the SuppressionPolicy. */
    case WhenAway = 'when_away';

    /** A quietable channel, delivered without asking the SuppressionPolicy at all. */
    case Always = 'always';

    /**
     * Read a stored row back as the preference it expresses on this channel.
     */
    public static function fromStored(bool $enabled, ?bool $bypassSuppression, Channel $channel): self
    {
        if (! $enabled) {
            return self::Off;
        }

        if (! $channel->isQuietable()) {
            return self::On;
        }

        return $bypassSuppression === true ? self::Always : self::WhenAway;
    }

    /**
     * The preferences this channel can express.
     *
     * @return list<self>
     */
    public static function acceptedBy(Channel $channel): array
    {
        return $channel->isQuietable()
            ? [self::Off, self::WhenAway, self::Always]
            : [self::Off, self::On];
    }

    public function isAcceptedBy(Channel $channel): bool
    {
        return in_array($this, self::acceptedBy($channel), true);
    }

    public function delivers(): bool
    {
        return $this !== self::Off;
    }

    public function bypassesSuppression(): bool
    {
        return $this === self::Always;
    }
}
