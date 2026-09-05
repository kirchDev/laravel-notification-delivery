<div align="center">

# 🔔 laravel-notification-delivery

**The layer Laravel's notifications leave out — stored notifications with read state, per-recipient channel preferences, and a gate chain that decides delivery before `via()` runs.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kirchdev/laravel-notification-delivery.svg?style=flat-square&color=4f46e5)](https://packagist.org/packages/kirchdev/laravel-notification-delivery)
[![Total Downloads](https://img.shields.io/packagist/dt/kirchdev/laravel-notification-delivery.svg?style=flat-square&color=4f46e5)](https://packagist.org/packages/kirchdev/laravel-notification-delivery)
[![Tests](https://img.shields.io/github/actions/workflow/status/kirchDev/laravel-notification-delivery/ci.yml?branch=main&style=flat-square&label=tests)](https://github.com/kirchDev/laravel-notification-delivery/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/packagist/dependency-v/kirchdev/laravel-notification-delivery/php?style=flat-square&color=8993be)](https://packagist.org/packages/kirchdev/laravel-notification-delivery)
[![Laravel Version](https://img.shields.io/packagist/dependency-v/kirchdev/laravel-notification-delivery/illuminate%2Fsupport?style=flat-square&label=laravel&color=ff2d20)](https://packagist.org/packages/kirchdev/laravel-notification-delivery)
[![License: MIT](https://img.shields.io/packagist/l/kirchdev/laravel-notification-delivery.svg?style=flat-square&color=10b981)](LICENSE)

</div>

---

```php
Notification::send($user, new MemberInvitedNotification($organisation));

$user->unreadNotificationCount(); // 1 — from a store Laravel never kept
```

That's it. The notification is stored, the bell updates, the toast fires if the recipient wants it, and the mail goes out unless they said otherwise — all decided before `via()` ever runs.

## 🤔 Why

Laravel already delivers notifications. It does not remember them, it has no notion of a recipient preferring one channel over another, and it decides nothing before `via()` runs — so every application grows its own half of that layer, usually twice.

This package is that layer and nothing else. Every notification stays an `Illuminate\Notifications\Notification`, every channel stays a Laravel channel, `Notification::send()` stays the entry point. Only the decision changes hands.

## 📦 Install & run

```bash
composer require kirchdev/laravel-notification-delivery
php artisan vendor:publish --tag=notification-delivery-migrations
php artisan migrate
```

> [!IMPORTANT]
> Publish the config first (`--tag=notification-delivery-config`) and set `notification-delivery.keys.*` + `table_names.*` **before** migrating — the migrations read config at run time, and `keys.notifiable_morph_key_type` must match the key type of the models you notify.

The publish step is **required, once**: the package does not auto-load its migrations, so nothing schema-related runs until the two files sit in your own `database/migrations`. Your DDL stays reviewable, in your repository, and on your deploy pipeline's terms.

> [!WARNING]
> **Replace `Notifiable` on your recipient — don't add to it.** Laravel's `Notifiable` is `RoutesNotifications` **plus** `HasDatabaseNotifications`, and the latter defines `notifications()` against Laravel's own table, which this package never writes. Left in place it is either a trait method collision or a relation that silently always comes back empty.

```php
use Illuminate\Notifications\RoutesNotifications;
use KirchDev\NotificationDelivery\Concerns\HasNotificationDelivery;

class User extends Authenticatable
{
    use HasNotificationDelivery;
    use RoutesNotifications;
}
```

Declare a type, and send it:

```php
enum OrganisationNotification: string implements NotificationType
{
    case MemberInvited = 'organisation.member.invited';

    public static function group(): NotificationGroup
    {
        return Group::Organisation;
    }

    public function definition(): NotificationDefinition
    {
        return match ($this) {
            self::MemberInvited => new NotificationDefinition(
                default: [CoreChannel::Live, CoreChannel::Mail],
                locked:  [CoreChannel::Inbox],
            ),
        };
    }
}
```

## ✨ Features

- **📥 A real inbox** — stored notifications with read/unread state, so an offline recipient still finds it later. Keyed by a **stable type string**, not a class name: classes move, and moved classes leave historical rows pointing nowhere.
- **🎚️ Preferences that resolve three ways** — per type, per group, or the type's own default. Sparse storage means a new type ships with a sensible default and no backfill.
- **🚪 A gate chain before `via()`** — availability, locked channels, the recipient's choice, then your own suppression policy. Four gates, in order, each able to stop the chain.
- **🧩 Channels are an interface, not an enum** — the package ships `inbox`, `live` and `mail`; you add push, SMS or anything else with your own enum. Nothing here is closed.
- **⏳ Deferred delivery** — `via()` cannot wait, so a held-back channel leaves it and a job re-checks later, delivering only if the notification is still unread.
- **📡 One broadcast, two jobs** — the same event carries the data sync **and** the interruption, so switching the toast off never leaves the bell stale.
- **🧰 Config-driven schema** — models, table names, morph key column and key types (`id` / `uuid` / `ulid`) all overridable.
- **🧪 Library-grade** — Pest 5 + Testbench, no host app needed.

## 🚦 The gate chain

Per notification and channel, in order. Each gate can stop the chain.

| #   | Gate                                                                | Owner   |
| :-- | :------------------------------------------------------------------ | :------ |
| 1   | Does the type know this channel, and can this recipient resolve it? | package |
| 2   | Is the channel locked? Then send, skipping gates 3 and 4.           | package |
| 3   | Has the recipient switched it off? **No row means undecided, not off.** | package |
| 4   | Does the `SuppressionPolicy` say this is a bad moment?              | **you** |

Gate 4 defaults to `NeverSuppress`, because deciding what "quiet" means would force the package to know about presence, working hours and time zones. Installing [`kirchdev/laravel-device-sessions`](https://github.com/kirchDev/laravel-device-sessions) upgrades it to a presence-aware policy; binding the contract yourself replaces both.

> [!TIP]
> **"Inbox always, live when online, otherwise mail" needs no escalation logic at all.** The inbox is locked and always runs, `live` sets `announce` and the open tab decides whether a toast appears, and a deferred mail is discarded as soon as `read_at` is set. Whoever saw the toast has read it; whoever was away has not.

## 📬 The three channels

| Channel | Does                                                          | User-configurable |
| :------ | :------------------------------------------------------------ | :---------------- |
| `inbox` | Writes the row **and** fires `NotificationBroadcasted`. The truth. | never             |
| `live`  | The toast in an open tab — sets `announce` on the payload.    | per type          |
| `mail`  | Laravel's own mail channel.                                   | per type          |

`live` is the one that surprises people: it is **not quietable**, because the tab being open is the whole point of it. Push, its sibling that interrupts through the OS, is — and that difference is a method on the channel, not a special case in the chain.

## 📋 Managing the inbox

The package ships **no routes** — every operation is a plain action you call from your own controllers, so the response shape stays yours:

```php
use KirchDev\NotificationDelivery\Actions\{
    ListNotifications, CountUnreadNotifications, MarkNotificationAsRead,
    MarkAllNotificationsAsRead, ListNotificationPreferences, UpdateNotificationPreference
};

$inbox  = app(ListNotifications::class)->execute($user, limit: 20);
$unread = app(CountUnreadNotifications::class)->execute($user);

app(MarkNotificationAsRead::class)->execute($user, $id);   // scoped to $user — an id from a request cannot reach another inbox
app(MarkAllNotificationsAsRead::class)->execute($user);

$grid = app(ListNotificationPreferences::class)->execute($user);  // the settings page, with where each value came from
app(UpdateNotificationPreference::class)->execute($user, $type, CoreChannel::Mail, false);
```

> [!TIP]
> Passing `null` for the last argument **clears** a preference — which is not the same as `false`. False is off; null puts the recipient back on the type's default, and is what a "reset" button wants.

## 🎚️ How a preference resolves

```
1. row for (recipient, type, channel)      → wins
2. row for (recipient, group:…, channel)   → otherwise
3. definition()->default                   → otherwise
```

The `type` column carries either a type key or a `group:`-prefixed one — one prefix, not a second schema. **You decide which tier your UI offers.** Group-level is enough to start with; thirty individual switches are not a settings page anyone reads, and refining later costs a UI row and no migration.

Only deviations are stored, so a type added next month takes effect immediately, with its own default, for every recipient who never said anything about it.

## 📡 Events & broadcasting

`InboxChannel` fires one event per stored notification:

```php
Event::listen(function (NotificationBroadcasted $event) {
    $event->notification->publicId;  // the stored row's key
    $event->notification->announce;  // whether this one should interrupt
});
```

It broadcasts on Laravel's own private channel convention (`App.Models.User.1`, or whatever the recipient answers from `receivesBroadcastNotificationsOn()`), so an application already broadcasting notifications keeps its channel authorisation.

<details>
<summary>Why <code>live</code> is one flag on one event, and not a second broadcast</summary>

The broadcast carries two things: the **data sync** — counter and list, which must always run or the bell shows stale numbers until the next reload — and the **interruption**, the toast that jumps into view unasked. Only the second is a delivery somebody would want to switch off ("put it in my inbox, but don't interrupt me").

So `InboxChannel` fires `NotificationBroadcasted` unconditionally, and the gate chain for `live` decides only whether `announce: true` sits on the payload. Splitting them into two events would mean a recipient who switched the toast off also stopped receiving counter updates.

Separately, `NotificationDefinition` carries `broadcast: false` for a bulk send that must not fan out ten thousand WebSocket events. That switches off the sync itself, and is a property of the **type**, never of a recipient's choice.

</details>

## 🧩 Extending it

Everything host-facing is a contract — rebind it, never extend the shipped class:

| Contract            | Default                            | Controls                                          |
| :------------------ | :--------------------------------- | :------------------------------------------------ |
| `SuppressionPolicy` | `NeverSuppress` / device-sessions  | Gate 4 — when a channel is a bad idea right now   |
| `Channel`           | `CoreChannel`                      | Which channels exist, and how each reaches Laravel |
| `NotificationType`  | yours                              | The type key, its group, its channel definition    |
| `NotificationGroup` | yours                              | The bucket the middle preference tier stores against |

<details>
<summary>Adding push, SMS or a third-party channel</summary>

`Channel` is an interface precisely so the package's list is not the end of it:

```php
enum PushChannel: string implements Channel
{
    case Web = 'web_push';

    public function laravelChannel(): string
    {
        return \NotificationChannels\WebPush\WebPushChannel::class;
    }

    public function isAvailableFor(object $notifiable): bool
    {
        return $notifiable->pushSubscriptions()->exists();
    }

    // userConfigurable(), isQuietable(), sortOrder() …
}
```

Then list it in `notification-delivery.channels.enums`.

**This package does not build push, deliberately.** `laravel-notification-channels/webpush` already ships the channel, the subscription table, a `HasPushSubscriptions` trait, VAPID key generation and the cleanup of expired endpoints; `…/fcm` and `…/apn` cover native push. There is no subscription table here — that belongs to whichever push package you choose.

`isAvailableFor()` earns its place on the interface rather than being a push special case: Laravel silently skips a channel when `routeNotificationFor()` returns nothing, which is fine for delivery and wrong for a settings page — it would otherwise offer a Telegram switch to somebody who never stored a Telegram id.

</details>

> [!IMPORTANT]
> Resolve models through `config('notification-delivery.models.*')` — never `new`, `::query()` or `::find()` on `KirchDev\NotificationDelivery\Models\*`. An application without a morph map stores the class it resolved, so a row written through the packaged class names a different class than every row beside it and quietly stops matching.

## 🗄️ Data model

| Table                      | Holds                                                                  |
| :------------------------- | :--------------------------------------------------------------------- |
| `delivered_notifications`  | `notifiable_type`, morph key, `type`, `payload`, `read_at`, timestamps |
| `notification_preferences` | notifiable morph, `type`, `channel`, `enabled`                         |

<details>
<summary>Three schema decisions worth knowing before you extend it</summary>

- **The recipient is a morph, not a `user_id`.** An invitation sent to an address with no account yet still needs a row, and a `NOT NULL user_id` has none for it.
- **There is no tenant column.** Filtering an inbox by the active tenant produces a bell that hides things depending on where the reader happens to be standing. The tenant belongs in `payload`, where the message text needs it anyway — and the package then needs no notion of organisations at all. Disagree? Published migrations are yours; add the column.
- **The table is not called `notifications`.** Laravel's own database channel claims that name, and leaving it free is what lets you keep using that channel alongside this package.

</details>

## 🧹 Pruning

```bash
php artisan notification-delivery:prune                        # windows from config — both null by default
php artisan notification-delivery:prune --days=365 --read-days=90
```

Two windows, because read notifications go stale faster than unread ones. Both default to `null`: **never delete**. A record of who was invited when can be the reason somebody still has access years later, and that call is yours, not a package default's. The command ships **unscheduled** — wire it into your scheduler with `Schedule::command('notification-delivery:prune')->dailyAt('03:20')`.

## ⚙️ Configuration

Four keys are the ones you actually set:

```php
'keys'        => ['primary_key_type' => 'id', 'notifiable_morph_key_type' => 'id'],
'channels'    => ['enums' => [CoreChannel::class, App\Enums\PushChannel::class]],
'discovery'   => ['types' => [App\Enums\Notification\OrganisationNotification::class]],
'suppression' => ['policy' => null, 'presence_window' => 300, 'defer' => 120],
```

`channels.enums` and `discovery.types` are what a settings page enumerates — **sending needs neither**. `suppression.policy` of `null` means auto: never suppress, or the presence-aware policy when device-sessions is installed.

The rest — models, table names, the morph key column and the two retention windows — is documented inline in [`config/notification-delivery.php`](config/notification-delivery.php).

## 🌍 Translation runs on two tracks

Unavoidably: the inbox translates in the **frontend**, so a language switch takes effect without a reload; mail translates **server-side**, in the recipient's locale. `payload()` therefore returns a translation key and its arguments, never a rendered string.

Same key on both sides — and write the test that asserts every type has an entry on both. Without it, drift is certain and only the recipient notices.

## 🧪 Testing

```bash
composer install
composer test       # Pest 5
composer pint       # Laravel Pint (test mode)
composer larastan   # Larastan / PHPStan
```

The test suite runs via Testbench + in-memory SQLite — no host app required.

## 🤝 Contributing

PRs welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Conventional Commits required (enforced via commitlint). Husky runs Pint + Larastan + oxlint + oxfmt on `git commit`, so you can mostly forget about style.

> [!TIP]
> Run `pnpm check:fix` (Node tooling) and `composer pint:fix` (PHP) before pushing — CI will catch what husky missed.

## 🛣️ Versioning

[Semantic Versioning](https://semver.org/) via [release-please](https://github.com/googleapis/release-please) — see [CHANGELOG.md](CHANGELOG.md).

## 📄 License

[MIT](LICENSE) © [Titus Kirch](https://github.com/TitusKirch/) / [IT-Dienstleistungen Titus Kirch](https://kirch.dev)
