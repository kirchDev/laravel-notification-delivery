<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The body of a notification: a translation key, its arguments, and its actions.
 *
 * Two of its fields are not stored with the row, because they are facts about one delivery
 * rather than about the notification:
 *
 * - `publicId` is the stored row's key, so a frontend can merge a live message with the list it
 *   already loaded instead of showing the same notification twice.
 * - `announce` is the gate chain's verdict for CoreChannel::Live — whether this one should
 *   interrupt with a toast, or only update the bell.
 *
 * @implements Arrayable<string, mixed>
 */
final class PayloadData implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $name  A translation key. Both the inbox (client-side) and mail
     *                        (server-side, in the recipient's locale) resolve it.
     * @param  array<string, mixed>  $payload  Its arguments.
     * @param  list<ActionData>  $actions
     */
    public function __construct(
        public readonly string $name,
        public readonly array $payload = [],
        public readonly array $actions = [],
        public readonly ?string $publicId = null,
        public readonly bool $announce = false,
    ) {}

    /**
     * The same payload as one concrete delivery: identified, and told whether to interrupt.
     */
    public function forDelivery(string $publicId, bool $announce): self
    {
        return new self($this->name, $this->payload, $this->actions, $publicId, $announce);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($attributes['payload'] ?? null) ? $attributes['payload'] : [];

        $actions = [];

        if (is_array($attributes['actions'] ?? null)) {
            foreach ($attributes['actions'] as $action) {
                if ($action instanceof ActionData) {
                    $actions[] = $action;
                } elseif (is_array($action)) {
                    $actions[] = ActionData::fromArray($action);
                }
            }
        }

        return new self(
            is_string($attributes['name'] ?? null) ? $attributes['name'] : '',
            $payload,
            $actions,
            is_string($attributes['publicId'] ?? null) ? $attributes['publicId'] : null,
            (bool) ($attributes['announce'] ?? false),
        );
    }

    /**
     * What the row stores: the notification itself, without the two per-delivery fields. The
     * public id is the row's own key, and `announce` describes a moment that has passed by the
     * time anyone reads the row back.
     *
     * @return array<string, mixed>
     */
    public function toStoredArray(): array
    {
        return [
            'name' => $this->name,
            'payload' => $this->payload,
            'actions' => array_map(static fn (ActionData $action): array => $action->toArray(), $this->actions),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->toStoredArray(),
            'publicId' => $this->publicId,
            'announce' => $this->announce,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
