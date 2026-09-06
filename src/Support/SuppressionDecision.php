<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

/**
 * What gate 4 decided for one channel: deliver now, drop it, or hold it back.
 *
 * "Hold it back" cannot be expressed inside via(), which is evaluated once and synchronously and
 * returns channels. A deferred channel is therefore dropped from via() and a job scheduled in its
 * place, which re-checks on execution and either delivers or discards.
 */
final class SuppressionDecision
{
    private const SEND = 'send';

    private const DROP = 'drop';

    private const DEFER = 'defer';

    private function __construct(
        private readonly string $verdict,
        private readonly int $delaySeconds,
    ) {}

    public static function send(): self
    {
        return new self(self::SEND, 0);
    }

    public static function drop(): self
    {
        return new self(self::DROP, 0);
    }

    /**
     * A delay of zero or less is a send: holding something back for no time at all only buys a
     * queue round-trip and a second chance to lose the notification.
     */
    public static function defer(int $delaySeconds): self
    {
        return $delaySeconds > 0 ? new self(self::DEFER, $delaySeconds) : self::send();
    }

    public function isSend(): bool
    {
        return $this->verdict === self::SEND;
    }

    public function isDrop(): bool
    {
        return $this->verdict === self::DROP;
    }

    public function isDefer(): bool
    {
        return $this->verdict === self::DEFER;
    }

    public function delaySeconds(): int
    {
        return $this->delaySeconds;
    }
}
