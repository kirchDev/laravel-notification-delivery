<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Actions;

use InvalidArgumentException;
use KirchDev\NotificationDelivery\Contracts\Channel;
use KirchDev\NotificationDelivery\Contracts\NotificationGroup;
use KirchDev\NotificationDelivery\Contracts\NotificationType;
use KirchDev\NotificationDelivery\Enums\ChannelPreference;
use KirchDev\NotificationDelivery\NotificationDelivery;
use KirchDev\NotificationDelivery\Support\PreferenceResolver;

/**
 * Store, or clear, one recipient's decision about one channel.
 *
 * The target is either a type (the fine tier) or a group (the coarse one) — the consuming
 * application decides which of them its settings page offers, and refining later costs a UI row
 * and no migration.
 *
 * Passing null deletes the row, which is not the same as passing Off: Off is "off", null is "I
 * have no opinion, use the type's default". That distinction is what keeps the table sparse and
 * lets a changed default reach the people who never touched it.
 *
 * A preference the channel cannot express is refused rather than coerced: On for a quietable
 * channel would silently mean one of WhenAway or Always, and WhenAway or Always for a channel gate 4
 * never asks about would store a distinction that does nothing.
 */
final class UpdateNotificationPreference
{
    public function __construct(private readonly PreferenceResolver $preferences) {}

    public function execute(
        object $notifiable,
        NotificationType|NotificationGroup $target,
        Channel $channel,
        ?ChannelPreference $preference,
    ): void {
        if (! $channel->userConfigurable()) {
            throw new InvalidArgumentException(
                sprintf('The [%s] channel is not user-configurable.', $channel->value),
            );
        }

        if ($preference !== null && ! $preference->isAcceptedBy($channel)) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] channel accepts only [%s], not [%s].',
                $channel->value,
                implode(', ', array_map(
                    static fn (ChannelPreference $accepted): string => $accepted->value,
                    ChannelPreference::acceptedBy($channel),
                )),
                $preference->value,
            ));
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

        if ($preference === null) {
            $query->delete();
            $this->preferences->forget($notifiable);

            return;
        }

        // Both columns on every write: the row is one answer, so stepping back from Always has to
        // clear the bypass rather than leave it behind.
        $columns = [
            'enabled' => $preference->delivers(),
            'bypass_suppression' => $preference->bypassesSuppression() ? true : null,
        ];

        $existing = $query->first();

        if ($existing !== null) {
            $existing->forceFill($columns)->save();
            $this->preferences->forget($notifiable);

            return;
        }

        $row = new $model;
        $row->fill([
            NotificationDelivery::MORPH_TYPE => NotificationDelivery::morphTypeFor($notifiable),
            NotificationDelivery::morphKey() => $key,
            'type' => self::targetKey($target),
            'channel' => (string) $channel->value,
            ...$columns,
        ]);
        $row->save();

        $this->preferences->forget($notifiable);
    }

    private static function targetKey(NotificationType|NotificationGroup $target): string
    {
        return $target instanceof NotificationType
            ? NotificationDelivery::typeKey($target)
            : NotificationDelivery::groupKey($target);
    }
}
