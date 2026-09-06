<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use KirchDev\NotificationDelivery\Concerns\HasConfigurableKey;
use KirchDev\NotificationDelivery\NotificationDelivery;
use KirchDev\NotificationDelivery\Support\PayloadData;

/**
 * A notification that was delivered to the inbox, with its read state.
 *
 * The table is not called `notifications`: Laravel's own database channel claims that name, and
 * leaving it free is what lets a consumer keep using that channel alongside this package.
 *
 * @property string $type
 * @property array<string, mixed> $payload
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeliveredNotification extends Model
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
        'payload',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        $table = config('notification-delivery.table_names.notifications', 'delivered_notifications');

        return is_string($table) && $table !== '' ? $table : 'delivered_notifications';
    }

    /**
     * The morph key column is configurable, so a renamed one has to be added to the fillable list
     * itself — not merely waved through by isFillable().
     *
     * fill() runs fillableFromArray() first, which intersects the incoming attributes with
     * getFillable(); a renamed key is dropped there and isFillable() is never consulted for it.
     * An override on that method would therefore only ever answer for the default column, which
     * already sits in $fillable literally.
     *
     * @return list<string>
     */
    public function getFillable(): array
    {
        /** @var list<string> $fillable */
        $fillable = parent::getFillable();
        $morphKey = NotificationDelivery::morphKey();

        return in_array($morphKey, $fillable, true) ? $fillable : [...$fillable, $morphKey];
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
     * The id a frontend uses to merge a live message with the list it already loaded.
     */
    public function publicId(): string
    {
        return (string) $this->getKey();
    }

    public function payloadData(): PayloadData
    {
        return PayloadData::fromArray($this->payload ?? []);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Stamp the row as read. Idempotent: re-reading something does not move the timestamp, which
     * matters because the deferred-delivery rule keys off "was it read", not "when".
     */
    public function markAsRead(): bool
    {
        if ($this->read_at !== null) {
            return false;
        }

        $this->forceFill(['read_at' => now()])->save();

        return true;
    }

    /**
     * @param  Builder<DeliveredNotification>  $query
     * @return Builder<DeliveredNotification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @param  Builder<DeliveredNotification>  $query
     * @return Builder<DeliveredNotification>
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * @param  Builder<DeliveredNotification>  $query
     * @return Builder<DeliveredNotification>
     */
    public function scopeForNotifiable(Builder $query, object $notifiable): Builder
    {
        return $query
            ->where(NotificationDelivery::MORPH_TYPE, NotificationDelivery::morphTypeFor($notifiable))
            ->where(NotificationDelivery::morphKey(), NotificationDelivery::morphKeyFor($notifiable));
    }
}
