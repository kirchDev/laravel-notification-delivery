<?php

declare(strict_types=1);

namespace KirchDev\NotificationDelivery\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One actionable button on a notification: what it does, with what, and what it is called.
 *
 * A plain value object rather than a spatie/laravel-data object — a package that pulled a DTO
 * library in would push that choice onto every consumer, and this carries three fields.
 *
 * @implements Arrayable<string, mixed>
 */
final class ActionData implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $name  The action key the frontend dispatches on.
     * @param  array<string, mixed>  $payload  Arguments for it.
     * @param  string  $label  A translation key, not a rendered string — the inbox translates in
     *                         the frontend so a language switch takes effect without a reload.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $payload = [],
        public readonly string $label = '',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($attributes['payload'] ?? null) ? $attributes['payload'] : [];

        return new self(
            is_string($attributes['name'] ?? null) ? $attributes['name'] : '',
            $payload,
            is_string($attributes['label'] ?? null) ? $attributes['label'] : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'payload' => $this->payload,
            'label' => $this->label,
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
