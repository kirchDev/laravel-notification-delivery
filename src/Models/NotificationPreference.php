<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use KirchDev\NotificationDelivery\Concerns\HasConfigurableKey;
use KirchDev\NotificationDelivery\NotificationDelivery;

/**
 * One recipient's decision about one channel, for one type or one whole group.
 *
 * The table is sparse on purpose: only deviations from the type's default are stored. A type
 * added next month therefore takes effect immediately, with its own default, for every recipient
 * who never said anything about it — and no backfill exists to forget to run.
 *
 * `type` carries either a type key ('organisation.member.invited') or a group key
 * ('group:organisation'). One prefix, not a second schema.
 *
 * @property string $type
 * @property string $channel
 * @property bool $enabled
 */
class NotificationPreference extends Model
{
    use HasConfigurableKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'notifiable_type',
        'notifiable_id',
        'type',
        'channel',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        $table = config('notification-delivery.table_names.preferences', 'notification_preferences');

        return is_string($table) && $table !== '' ? $table : 'notification_preferences';
    }

    /**
     * The morph key column is configurable, so a renamed one has to be let through by name
     * rather than by the literal in $fillable.
     */
    public function isFillable($key): bool
    {
        if ($key === NotificationDelivery::morphKey()) {
            return true;
        }

        return parent::isFillable($key);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo(
            'notifiable',
            NotificationDelivery::MORPH_TYPE,
            NotificationDelivery::morphKey(),
        );
    }

    /**
     * @param  Builder<NotificationPreference>  $query
     * @return Builder<NotificationPreference>
     */
    public function scopeForNotifiable(Builder $query, object $notifiable): Builder
    {
        return $query
            ->where(NotificationDelivery::MORPH_TYPE, NotificationDelivery::morphTypeFor($notifiable))
            ->where(NotificationDelivery::morphKey(), NotificationDelivery::morphKeyFor($notifiable));
    }
}
