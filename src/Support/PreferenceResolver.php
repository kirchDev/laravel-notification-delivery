<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * Gate 3, and the three-tier lookup behind it.
 *
 *   1. a row for (notifiable, type, channel)      wins
 *   2. a row for (notifiable, group:…, channel)   otherwise
 *   3. no row at all                              → null, "undecided"
 *
 * Undecided is not off. The caller falls back to the type's own default, which is what makes a
 * newly added type take effect immediately for every existing recipient with no backfill.
 *
 * Bound scoped, and every lookup for one notifiable is served from a single query: a settings
 * page resolves dozens of type/channel pairs, and a send resolves several in a row.
 */
final class PreferenceResolver
{
    /**
     * @var array<string, array<string, bool>>
     */
    private array $cache = [];

    /**
     * Whether the recipient switched this channel on or off, or null when they never said.
     */
    public function resolve(object $notifiable, NotificationType $type, Channel $channel): ?bool
    {
        $rows = $this->rowsFor($notifiable);
        $channelValue = (string) $channel->value;

        $typeRow = $rows[NotificationDelivery::typeKey($type).'|'.$channelValue] ?? null;

        if ($typeRow !== null) {
            return $typeRow;
        }

        $group = $type::group();

        if ($group === null) {
            return null;
        }

        return $rows[NotificationDelivery::groupKey($group).'|'.$channelValue] ?? null;
    }

    /**
     * Which tier answered — 'type', 'group', or 'default' when nothing was stored.
     *
     * A settings page needs this, not just the value: a switch showing "on" because the recipient
     * said so and one showing "on" because nobody has said anything look identical, and only the
     * second silently follows the type's default when that changes.
     *
     * @return 'type'|'group'|'default'
     */
    public function source(object $notifiable, NotificationType $type, Channel $channel): string
    {
        $rows = $this->rowsFor($notifiable);
        $channelValue = (string) $channel->value;

        if (isset($rows[NotificationDelivery::typeKey($type).'|'.$channelValue])) {
            return 'type';
        }

        $group = $type::group();

        if ($group !== null && isset($rows[NotificationDelivery::groupKey($group).'|'.$channelValue])) {
            return 'group';
        }

        return 'default';
    }

    /**
     * Drop the cached rows for one notifiable. Called after a preference is written, so a
     * settings page that saves and re-renders in one request shows what it just stored.
     */
    public function forget(object $notifiable): void
    {
        unset($this->cache[NotificationDelivery::identityFor($notifiable)]);
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * @return array<string, bool>
     */
    private function rowsFor(object $notifiable): array
    {
        $identity = NotificationDelivery::identityFor($notifiable);

        if (isset($this->cache[$identity])) {
            return $this->cache[$identity];
        }

        $key = NotificationDelivery::morphKeyFor($notifiable);

        if ($key === null) {
            // Nothing was ever stored against an unsaved or non-model notifiable, so there is
            // nothing to load — and no query to waste on finding that out again.
            return $this->cache[$identity] = [];
        }

        $model = NotificationDelivery::preferenceModel();

        $rows = [];

        $records = $model::query()
            ->where(NotificationDelivery::MORPH_TYPE, NotificationDelivery::morphTypeFor($notifiable))
            ->where(NotificationDelivery::morphKey(), $key)
            ->get(['type', 'channel', 'enabled']);

        foreach ($records as $record) {
            $rows[$record->getAttribute('type').'|'.$record->getAttribute('channel')] = (bool) $record->getAttribute('enabled');
        }

        return $this->cache[$identity] = $rows;
    }
}
