<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\Channel;

/**
 * Every channel the application exposes, from the enums named in
 * config('notification-delivery.channels.enums').
 *
 * A registry exists because a settings page has to render columns for channels no notification
 * has been sent on yet — that list cannot be derived from stored rows, only declared.
 */
final class ChannelRegistry
{
    /**
     * @var list<Channel>|null
     */
    private ?array $channels = null;

    /**
     * Every registered channel, in UI order.
     *
     * @return list<Channel>
     */
    public function all(): array
    {
        if ($this->channels !== null) {
            return $this->channels;
        }

        $channels = [];

        foreach (self::configuredEnums() as $enum) {
            foreach ($enum::cases() as $case) {
                $channels[] = $case;
            }
        }

        usort($channels, static fn (Channel $a, Channel $b): int => $a->sortOrder() <=> $b->sortOrder());

        return $this->channels = $channels;
    }

    /**
     * The registered channel with this backed value, or null.
     *
     * Values are expected to be unique across the registered enums; where two collide, the
     * first registered wins, so an application overriding a core channel lists its own enum first.
     */
    public function find(string $value): ?Channel
    {
        foreach ($this->all() as $channel) {
            if ((string) $channel->value === $value) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * @return list<class-string<Channel>>
     */
    private static function configuredEnums(): array
    {
        $configured = config('notification-delivery.channels.enums', []);

        if (! is_array($configured)) {
            return [];
        }

        $enums = [];

        foreach ($configured as $enum) {
            if (is_string($enum) && is_a($enum, Channel::class, true)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }
}
