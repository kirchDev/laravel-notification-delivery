# Laravel Notification Delivery

`kirchdev/laravel-notification-delivery` adds the layer Laravel's notifications leave out: stored
notifications with read state, per-recipient channel preferences, and a gate chain that decides
delivery before `via()` runs. Everything is configured in `config/notification-delivery.php`.

Every notification stays an `Illuminate\Notifications\Notification`, every channel stays a Laravel
channel, and `Notification::send()` stays the entry point. Only the decision changes hands.

## Setup order (do not reorder)
- Publish the config first: `{{ $assist->artisanCommand('vendor:publish --tag=notification-delivery-config') }}`.
- Set `notification-delivery.keys.*` (`id` / `uuid` / `ulid`) and `table_names.*` **before**
  migrating — the migrations read the config at run time, and `keys.notifiable_morph_key_type` must
  match the key type of the models you notify or the morph key will not line up.
- The package ships migrations but never loads them. Publish them once, then migrate:
  `{{ $assist->artisanCommand('vendor:publish --tag=notification-delivery-migrations') }}` and
  `{{ $assist->artisanCommand('migrate') }}`.
- **Replace `Notifiable` on the recipient**, do not add to it. `Illuminate\Notifications\Notifiable`
  is `RoutesNotifications` **plus** `HasDatabaseNotifications`, and the latter defines
  `notifications()` against Laravel's own table, which this package never writes. Left in place it
  is either a trait method collision or a relation that silently always comes back empty. Use
  `Illuminate\Notifications\RoutesNotifications` together with
  `KirchDev\NotificationDelivery\Concerns\HasNotificationDelivery`.
- Declare the application's notification type enums in `notification-delivery.discovery.types` and
  any own channel enums in `notification-delivery.channels.enums`. Sending needs neither list; a
  settings page cannot be rendered without them.

## Declaring a notification
- A **type is an enum case with a stable string key**, never a class name. Laravel's database
  channel stores the fully-qualified class, and a class that moves leaves historical rows pointing
  nowhere.
- The enum implements `KirchDev\NotificationDelivery\Contracts\NotificationType`: a **static**
  `group()` (the group belongs to the enum, not the case) and a per-case `definition()`. Write
  `definition()` as one `match ($this)` so the parts cannot drift and a missing case throws
  `UnhandledMatchError` instead of quietly yielding no channels.
- The notification itself extends
  `KirchDev\NotificationDelivery\Notifications\TypedNotification` and implements `type()` and
  `payload()`. Do not override `via()` — that is the gate chain's method.
- `payload()` returns a translation key and its arguments, never a rendered string: the inbox
  translates in the frontend so a language switch takes effect without a reload, while mail
  translates server-side in the recipient's locale.

## The gate chain
Per notification and channel, in order; each gate can stop the chain.

1. Does the type know this channel, and can this notifiable resolve it?
2. Is the channel locked? Then send, skipping gates 3 and 4.
3. Has the recipient switched it off? **No row means undecided, not off** — it falls back to the
   type's default, which is what lets a new type ship without a backfill.
4. Does the `SuppressionPolicy` say this is a bad moment? Only channels whose `isQuietable()` is
   true are asked.

`via()` cannot wait: it is evaluated once, synchronously. A policy that defers therefore drops the
channel from `via()` and schedules `DeliverDeferredNotification` instead, which re-checks when the
delay expires and either delivers or discards.

## Managing the inbox
- The package ships **no routes and no controllers**. Every operation is a plain action under
  `KirchDev\NotificationDelivery\Actions`, all with `execute(...)`: `ListNotifications`,
  `CountUnreadNotifications`, `MarkNotificationAsRead`, `MarkAllNotificationsAsRead`,
  `ListNotificationPreferences`, `UpdateNotificationPreference`.
- Every action takes the recipient first and scopes to their rows, so an id coming from a request
  cannot reach somebody else's notification.
- `UpdateNotificationPreference` takes `null` to **clear** a preference, which is not the same as
  `false`. False is off; null puts the recipient back on the type's default.
- Stored notifications are removed by `notification-delivery:prune`, which ships **unscheduled**
  and, by default, **never deletes anything** — both retention windows start at `null`.

## Extending it
- Resolve the models through `config('notification-delivery.models.*')`. Never `new`, `::query()`
  or `::find()` on `KirchDev\NotificationDelivery\Models\*`: an application without a morph map
  stores the class it resolved, so a row written through the packaged class names a different class
  than every row beside it and quietly stops matching.
- **Add a channel with your own enum**, never by editing `CoreChannel` — that is what the `Channel`
  interface is for. Point `laravelChannel()` at the third-party channel class and let
  `isAvailableFor()` answer whether this recipient has a device, a phone number or an address.
- **This package does not build push.** `laravel-notification-channels/webpush` already ships the
  channel, the subscription table, the trait, VAPID key generation and the endpoint cleanup;
  `…/fcm` and `…/apn` cover native push. Wire one in with an enum.
- Bind your own `KirchDev\NotificationDelivery\Contracts\SuppressionPolicy` for gate 4. The default
  is `NeverSuppress`, upgraded to the presence-aware `DeviceSessionSuppression` when
  `kirchdev/laravel-device-sessions` is installed. Do not extend the shipped classes.
- Listen for `KirchDev\NotificationDelivery\Events\NotificationBroadcasted` rather than reaching
  into the channel. One event carries both the data sync and, via `announce`, the interruption.
