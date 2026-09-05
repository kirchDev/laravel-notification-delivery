<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use InvalidArgumentException;
use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\NotificationDelivery;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;

/**
 * Store, or clear, one recipient's decision about one channel.
 *
 * The target is either a type (the fine tier) or a group (the coarse one) — the consuming
 * application decides which of them its settings page offers, and refining later costs a UI row
 * and no migration.
 *
 * Passing null for `$enabled` deletes the row, which is not the same as passing false: false is
 * "off", null is "I have no opinion, use the type's default". That distinction is what keeps the
 * table sparse and lets a changed default reach the people who never touched it.
 */
final class UpdateNotificationPreference
{
    public function __construct(private readonly PreferenceResolver $preferences) {}

    public function execute(
        object $notifiable,
        NotificationType|NotificationGroup $target,
        Channel $channel,
        ?bool $enabled,
    ): void {
        if (! $channel->userConfigurable()) {
            throw new InvalidArgumentException(
                sprintf('The [%s] channel is not user-configurable.', $channel->value),
            );
        }

        $key = NotificationDelivery::morphKeyFor($notifiable);

        if ($key === null) {
            throw new InvalidArgumentException(
                'Preferences can only be stored for a persisted notifiable model.',
            );
        }

        $model = NotificationDelivery::preferenceModel();

        $query = $model::query()
            ->forNotifiable($notifiable)
            ->where('type', self::targetKey($target))
            ->where('channel', (string) $channel->value);

        if ($enabled === null) {
            $query->delete();
            $this->preferences->forget($notifiable);

            return;
        }

        $existing = $query->first();

        if ($existing !== null) {
            $existing->forceFill(['enabled' => $enabled])->save();
            $this->preferences->forget($notifiable);

            return;
        }

        $preference = new $model;
        $preference->fill([
            NotificationDelivery::MORPH_TYPE => NotificationDelivery::morphTypeFor($notifiable),
            NotificationDelivery::morphKey() => $key,
            'type' => self::targetKey($target),
            'channel' => (string) $channel->value,
            'enabled' => $enabled,
        ]);
        $preference->save();

        $this->preferences->forget($notifiable);
    }

    private static function targetKey(NotificationType|NotificationGroup $target): string
    {
        return $target instanceof NotificationType
            ? NotificationDelivery::typeKey($target)
            : NotificationDelivery::groupKey($target);
    }
}
