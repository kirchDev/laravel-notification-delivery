---
name: notification-delivery-development
description: 'Work with kirchdev/laravel-notification-delivery: declare notification types and channels, build the bell and the settings page, bind a suppression policy, and test the gate chain.'
---

# Notification delivery development

Use this when building a notification bell, an inbox list or a notification settings page, adding a
channel, deciding when a mail should be held back, or debugging why a notification did not reach
somebody in an application that uses `kirchdev/laravel-notification-delivery`.

## Declaring a type

A type is a stable string key on an enum, not a class name — classes move, and moved classes leave
historical rows pointing nowhere.

```php
enum OrganisationNotification: string implements NotificationType
{
    case MemberInvited = 'organisation.member.invited';
    case MemberRemoved = 'organisation.member.removed';

    // Static: the group belongs to the enum, not to the case.
    public static function group(): ?NotificationGroup
    {
        return NotificationGroup::Organisation;
    }

    // Per case. One match so the parts cannot drift apart, and a missing case
    // throws UnhandledMatchError instead of silently yielding no channels.
    public function definition(): NotificationDefinition
    {
        return match ($this) {
            self::MemberInvited => new NotificationDefinition(
                default: [CoreChannel::Live, CoreChannel::Mail],
                locked:  [CoreChannel::Inbox],
            ),
            self::MemberRemoved => new NotificationDefinition(
                locked: [CoreChannel::Inbox],
            ),
        };
    }
}
```

- `locked` is the inbox, and effectively only the inbox: it skips gates 3 and 4 entirely. It is
  always known, whether or not an explicit `available` list repeats it.
- `default` is on unless the recipient says otherwise. `available` widens the set beyond that —
  a channel a recipient can switch **on** that ships off.
- `broadcast: false` switches off the WebSocket fan-out for a bulk send that must not produce ten
  thousand events. A property of the type, never of a recipient's choice.
- Carrying more per type? Return a subclass of `NotificationDefinition` — covariant return types
  make that work without a second interface.

The notification extends `TypedNotification` and implements `type()` and `payload()`. **Do not
override `via()`** — the gate chain owns it. `toMail()` and friends stay exactly where they were.

## The channels, and the one that surprises people

| Channel | Does                                                               | User-configurable |
| :------ | :----------------------------------------------------------------- | :---------------- |
| `inbox` | Writes the row **and** fires `NotificationBroadcasted`. The truth. | never             |
| `live`  | The toast in an open tab — sets `announce` on the payload.         | per type          |
| `mail`  | Laravel's own mail channel.                                        | per type          |

`live` is **not** a second broadcast. The one event carries both the data sync (counter and list,
which must always run or the bell shows stale numbers until the next reload) and the interruption;
only the second is something anybody would want to switch off. So `InboxChannel` fires the event
unconditionally and `live` decides only whether `announce: true` sits on the payload — the frontend
shows the toast exactly then.

For the same reason `live` is **not quietable**: the tab being open is the whole point. Push, its
sibling that interrupts through the OS, is.

## Adding a channel

Never edit `CoreChannel` — that is what the interface is for:

```php
enum PushChannel: string implements Channel
{
    case Web = 'web_push';

    public function laravelChannel(): string
    {
        return \NotificationChannels\WebPush\WebPushChannel::class;
    }

    public function isAvailableFor(object $n): bool
    {
        return $n->pushSubscriptions()->exists();
    }
    // userConfigurable(), isQuietable(), sortOrder() …
}
```

Then list it in `notification-delivery.channels.enums`.

`isAvailableFor()` earns its place on the interface rather than being a push special case: Laravel
silently skips a channel when `routeNotificationFor()` returns nothing, which is fine for delivery
and wrong for a settings page — it would otherwise show a Telegram switch to somebody who never
stored a Telegram id.

**This package does not build push.** `laravel-notification-channels/webpush` already ships the
channel, the subscription table, a `HasPushSubscriptions` trait, VAPID key generation
(`php artisan webpush:vapid`) and the cleanup of expired endpoints; `…/fcm` and `…/apn` cover native
push. There is deliberately no subscription table here — that belongs to whichever push package you
choose.

## Preferences: three tiers, sparse storage

```
1. row for (notifiable, type, channel)      → wins
2. row for (notifiable, group:…, channel)   → otherwise
3. definition()->default                    → otherwise
```

**The application decides which tier its UI offers.** Group-level is enough to start with — thirty
individual switches are not a settings page anyone reads — and refining later costs a UI row and no
migration, because the `type` column already carries either key.

Only deviations are stored. `UpdateNotificationPreference` takes `null` to delete the row, and that
is the operation a "reset to default" button wants; `false` means off and keeps the recipient there
when the type's default changes.

## Gate 4: when to hold a mail back

Bind your own `SuppressionPolicy`. The default is `NeverSuppress`; installing
`kirchdev/laravel-device-sessions` upgrades it to `DeviceSessionSuppression`, which holds a
quietable channel back while a device was seen recently.

You will rarely need more. **"Inbox always, live when online, otherwise mail" falls out of the three
rules with no escalation logic at all**: the inbox is locked and always runs, `live` sets `announce`
and the open tab decides whether a toast appears, and a deferred mail is discarded as soon as
`read_at` is set. Whoever saw the toast has read it; whoever was away has not. The "was online" is
already inside "if unread" — which is why it works with no presence tracking whatsoever.

A policy that defers returns `SuppressionDecision::defer($seconds)`. The channel then leaves `via()`
and `DeliverDeferredNotification` re-checks when the delay expires, delivering through `sendNow()`
with an explicit channel list so no second inbox row appears. It defers **once**: a second deferral
is a discard, or a policy that always defers would hold a notification forever.

## Recipients and translation

- The recipient is a **morph**, not a `user_id` — an invitation sent to an address with no account
  yet still needs a row.
- There is deliberately **no tenant column**. Filtering an inbox by the active tenant produces a
  bell that hides things depending on where the reader is standing. Put the tenant in the payload,
  where the message text needs it anyway.
- **Translation runs on two tracks**, unavoidably: the inbox translates in the frontend so a
  language switch takes effect without a reload, mail translates server-side in the recipient's
  locale. Same key both sides — and write the test that asserts every type has an entry on both,
  because without it drift is certain and only the recipient notices.

## Testing

- Drive `Notification::send()`, not the channel: what a test of `InboxChannel::send()` alone proves
  is that the class works, not that the gate chain named it.
- Assert `announce` on the broadcast rather than on the presence of the event — a recipient who
  switched `live` off still gets the event, and a test that asserts otherwise pins the wrong
  behaviour.
- Give the test recipient `RoutesNotifications` + `HasNotificationDelivery`, never `Notifiable`.
- Pin the `SuppressionPolicy` in your test environment. With device-sessions installed the default
  binding reads `user_devices`, and a suite that did not create that table fails somewhere far from
  the cause.
- `keys.notifiable_morph_key_type` has to match the recipients in the test schema too; a mismatch
  shows up at migrate time, not at assert time.
