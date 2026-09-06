<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use KirchDev\NotificationDelivery\NotificationDelivery;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;
use KirchDev\NotificationDelivery\Support\TypeRegistry;

/**
 * The settings page's grid: every registered type against every channel it knows, with what is
 * effective today and where that came from.
 *
 * Channels the recipient cannot receive on at all are left out rather than shown switched off —
 * a Telegram row offered to somebody who never stored a Telegram id is a switch that does nothing.
 */
final class ListNotificationPreferences
{
    public function __construct(
        private readonly TypeRegistry $types,
        private readonly PreferenceResolver $preferences,
    ) {}

    /**
     * @return list<array{type: string, group: string|null, channel: string, enabled: bool, locked: bool, configurable: bool, source: string}>
     */
    public function execute(object $notifiable): array
    {
        $rows = [];

        foreach ($this->types->all() as $type) {
            $definition = $type->definition();
            $group = $type::group();

            foreach ($definition->channels() as $channel) {
                if (! $channel->isAvailableFor($notifiable)) {
                    continue;
                }

                $locked = $definition->isLocked($channel);
                $stored = $locked ? null : $this->preferences->resolve($notifiable, $type, $channel);

                $rows[] = [
                    'type' => NotificationDelivery::typeKey($type),
                    'group' => $group === null ? null : (string) $group->value,
                    'channel' => (string) $channel->value,
                    'enabled' => $locked || ($stored ?? $definition->isDefault($channel)),
                    'locked' => $locked,
                    'configurable' => $locked ? false : $channel->userConfigurable(),
                    'source' => $locked ? 'default' : $this->preferences->source($notifiable, $type, $channel),
                ];
            }
        }

        return $rows;
    }
}
