# SETUP

Working brief for `kirchdev/laravel-notification-delivery`. It carries two things: **what** the
package does, and **how** it must be built so it is indistinguishable in shape from its two
siblings.

Delete this file once the package is released and its README, guideline and skill carry the same
information.

## The two references, and how binding they are

Both sibling packages are checked out next to this one:

```
/root/projects/comGithub/kirchDev/laravel-device-sessions
/root/projects/comGithub/kirchDev/laravel-pbac
```

**Read them rather than this file wherever the two disagree with each other or with what follows.**
Every convention below was lifted from them, not invented here. Where they agree, that agreement is
binding — file layout, `composer.json` shape, the `spatie/laravel-package-tools` provider, config
file structure, migration naming, Boost resources, test layout, tooling config. Where they differ,
this file names the difference and picks one; those are the only decisions taken here.

---

# Part 1 — What the package does

Laravel already delivers notifications. It does not remember them, it has no notion of a user
preferring one channel over another, and it decides nothing before `via()` runs. This package adds
that layer, and nothing else: every notification stays an
`Illuminate\Notifications\Notification`, every channel stays a Laravel channel, `Notification::send()`
stays the entry point.

Three things it owns:

1. **Persistence** — a stored notification with read/unread state, so an offline recipient still
   finds it later.
2. **Preferences** — per notification type and channel, held by the recipient.
3. **The delivery decision** — a gate chain that runs before `via()` and decides which channels
   survive.

## Notification types are enums, not class names

Laravel's `database` channel stores the fully-qualified class name. That is a liability: classes
move, and moved classes leave historical rows pointing nowhere. A type is a stable string key
instead, declared by an enum that implements the package's contract — the same shape the consuming
application already uses for permissions.

```php
enum OrganisationNotification: string implements NotificationType
{
    case MemberInvited = 'organisation.member.invited';
    case MemberRemoved = 'organisation.member.removed';

    // Static: the group belongs to the enum, not to the case.
    public static function group(): NotificationGroup
    {
        return NotificationGroup::Organisation;
    }

    // Per case. One match so the parts cannot drift apart, and a missing case
    // throws UnhandledMatchError instead of silently yielding no channels.
    public function definition(): NotificationDefinition
    {
        return match ($this) {
            self::MemberInvited => new NotificationDefinition(
                default: [CoreChannel::Inbox, CoreChannel::Mail],
                locked:  [CoreChannel::Inbox],
            ),
            self::MemberRemoved => new NotificationDefinition(
                default: [CoreChannel::Inbox],
                locked:  [CoreChannel::Inbox],
            ),
        };
    }
}
```

An application that wants to carry more per type returns a subclass of `NotificationDefinition` —
PHP's covariant return types make that work without a second interface.

## Channels are an interface, not an enum

An enum in the package would be closed: no application could ever add a channel. So `Channel` is an
interface that enums implement — the package ships `CoreChannel`, an application adds its own, and
a third-party channel package is wired in by pointing at its class.

```php
interface Channel extends BackedEnum
{
    public function laravelChannel(): string;              // the bridge to delivery
    public function userConfigurable(): bool;              // inbox: false
    public function isQuietable(): bool;                   // does gate 4 apply?
    public function isAvailableFor(object $notifiable): bool;
    public function sortOrder(): int;                      // column order in the UI
}
```

| Channel | Does                                                               | User-configurable | Ships in   |
| :------ | :----------------------------------------------------------------- | :---------------- | :--------- |
| `inbox` | Writes the row **and** fires `NotificationBroadcasted`. The truth. | never             | 0.1        |
| `live`  | The toast in an open tab — sets `announce` on the payload.         | per type          | 0.1        |
| `mail`  | Laravel's `mail` channel.                                          | per type          | 0.1        |
| `push`  | Third-party: `laravel-notification-channels/webpush`.              | per type          | never ours |
| `sms`   | Third-party, e.g. Twilio.                                          | per type          | never ours |

`inbox` is not configurable, or it is not an inbox.

**`live` is where the distinction gets subtle.** The broadcast carries two things: the **data
sync** — counter and list, which must always run or the bell shows stale numbers until the next
reload — and the **interruption**, the toast that jumps into view unasked. Only the second is a
delivery somebody would want to switch off ("put it in my inbox, but don't interrupt me").

So it is **one event with a flag**: `InboxChannel` fires `NotificationBroadcasted` unconditionally,
and the gate chain for `live` decides only whether `announce: true` sits on the payload. The frontend
shows the toast exactly then. `live` is therefore **not quietable** — the tab being open is the whole
point — while push, its sibling that interrupts through the OS, is.

Separately, `NotificationDefinition` carries `broadcast: false` for a bulk send that must not fan out
ten thousand WebSocket events. That switches off the sync itself and is a property of the _type_, not
a user's choice.

**This package does not build push.** `laravel-notification-channels/webpush` already ships the
channel, the subscription table, a `HasPushSubscriptions` trait, VAPID key generation
(`php artisan webpush:vapid`) and the cleanup of expired endpoints; `…/fcm` and `…/apn` cover native
push in the same ecosystem. An application wires one in with an enum pointing at their channel class
— which is precisely why `Channel` is an interface:

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
}
```

Push and the broadcast overlap exactly while the app is open, so push is quietable under the same
`if_unread` rule as mail — but on a much shorter fuse, seconds rather than minutes, because a push
notification two minutes late is pointless.

`isAvailableFor()` earns its place on the interface rather than being a push special case. Laravel
silently skips a channel when `routeNotificationFor()` returns nothing — fine for delivery, wrong for
a settings page, which would otherwise show a Telegram switch to someone who never stored a Telegram
id. The same holds for push without a device.

## Preference granularity: group, type, or both

Resolution is three-tier, and because the table is sparse anyway, the finer tier costs nothing while
nobody uses it:

```
1. row for (notifiable, type, channel)      → wins
2. row for (notifiable, group:…, channel)   → otherwise
3. definition()->default                    → otherwise
```

The `type` column carries either a type key or a `group:`-prefixed one — one prefix, not a second
schema. **The consuming application decides which tier its UI offers**, not the package. Group-level
is enough to start with; thirty individual switches are not a settings page anyone reads. Refining
later costs a UI row and no migration.

## Optional dependency: device-sessions

The package ships a `DeviceSessionSuppression` that is only bound when
`kirchdev/laravel-device-sessions` is installed — the same pattern device-sessions itself uses to
attach its Fortify bridge:

```json
"suggest": {
    "kirchdev/laravel-device-sessions": "Enables the presence-aware suppression policy."
}
```

```php
private function registerDeviceSessionPolicy(): void
{
    if (! class_exists(\KirchDev\DeviceSessions\Models\UserDevice::class)) {
        return;
    }

    $this->app->scoped(SuppressionPolicy::class, DeviceSessionSuppression::class);
}
```

It is a default, not a constraint — the contract stands and an application rebinds it freely. Add
the package to `require-dev` so the suite can exercise both paths.

**It must not read `users.last_seen_at`.** That column belongs to the application — gildstone writes
it from a hand-built listener on `DeviceTouched`, another project may not have it at all. The only
dependable source is what device-sessions maintains itself: `user_devices.last_seen_at`.

It will rarely be needed, though. "Inbox always, live when online, otherwise mail" **falls out of the
three rules** without any escalation logic: `inbox` is locked and always runs, `live` sets `announce`
and the open tab decides whether a toast appears, and `mail` under `if_unread` drops as soon as
`read_at` is set. Whoever saw the toast has read it; whoever was away has not. The "was online" is
already inside `if_unread` — which is why it works without device-sessions at all.

## The gate chain

Per notification and channel, in order. Each gate can stop the chain.

| #   | Gate                                                                 | Owner           |
| :-- | :------------------------------------------------------------------- | :-------------- |
| 1   | Does the type know this channel, and can this notifiable resolve it? | package         |
| 2   | Is the channel locked? Then send, skipping gates 3 and 4.            | package         |
| 3   | Has the recipient switched it off? No row means undecided, not off.  | package         |
| 4   | Does the `SuppressionPolicy` say this is a bad moment?               | **application** |

Gate 4 is a contract with a `NeverSuppress` default, because deciding what "quiet" means would
force the package to know about device sessions and presence. The consuming application binds its
own — the same pattern as `IpMasker` and `DeviceNameResolver` in device-sessions, or
`OrganisationResolver` in pbac.

**`via()` cannot wait.** It is evaluated once, synchronously, and returns channels; a delay cannot be
expressed in it. A policy that defers therefore drops the channel from `via()` and schedules a job
instead, which re-checks on execution and either delivers or discards. Hence the resolver method
that feeds `via()` is named `immediate()`.

## The recipient is a morph

`user_id` would be the naive column. pbac shows the house answer: `model_has_roles` carries
`model_type` plus a configurable morph key with a configurable key type. Laravel's own notifications
do the same.

That device-sessions is _not_ polymorphic is not a counter-example but the test of the rule: a device
hangs off something that signs in. A notification does not — an invitation sent to an address that
has no account yet needs a row, and `user_id NOT NULL` has none for it.

(Push subscriptions would be non-polymorphic for exactly that reason — but they are not ours to
model.)

## Data model

| Table                      | Holds                                                                  |
| :------------------------- | :--------------------------------------------------------------------- |
| `delivered_notifications`  | `notifiable_type`, morph key, `type`, `payload`, `read_at`, timestamps |
| `notification_preferences` | notifiable morph, `type`, `channel`, `enabled`                         |

Both names configurable through `table_names`, all key types through `keys`. There is **no
subscription table** — that belongs to whichever push package an application chooses.

Preferences are **sparse** — only deviations from the type's default are stored. A new type then
takes effect immediately with a sensible default, with no backfill for existing recipients.

There is deliberately **no tenant column**. Filtering an inbox by the active tenant produces a bell
that hides things depending on where the reader happens to be standing, which is a bell nobody can
trust. The tenant belongs in `payload`, where the message text needs it anyway, and the package then
needs no notion of organisations at all. An application that disagrees adds the column in its own
migration — published migrations are consumer-owned.

## Retention

Default is `null`: **never delete**. A device revoked half a year ago interests nobody; a record of
who was invited when can be the reason somebody still has access years later. Two separate windows,
because read notifications go stale faster than unread ones.

```php
'prune' => [
    'retention_days'      => null,
    'retention_days_read' => null,
],
```

The command ships **unscheduled**, exactly like `device-sessions:prune`.

## What stays with the consumer

The package ships **no routes** — same as device-sessions. The application writes its own controller
and its own response DTO, and owns the entire frontend. It also owns the concrete notification
classes, its own type enums, mail templates, and the i18n keys behind them.

Two facts a consumer has to be told, and both belong in the guideline:

- **`Notifiable` must be replaced by `RoutesNotifications` + this package's trait.** Laravel's
  `Notifiable` is `RoutesNotifications` **plus** `HasDatabaseNotifications`, and the latter defines
  `notifications()` against Laravel's own table — which this package never writes. Left in place it
  is either a trait method collision or, worse, a relation that silently always returns empty.
- **Translation runs on two tracks**, unavoidably: the inbox translates in the frontend so a language
  switch takes effect without a reload, mail translates server-side in the recipient's locale. Same
  key on both sides, and a test that asserts both sides have an entry for every type — without it,
  drift is certain and only the recipient notices.

---

# Part 2 — How it is built

Everything in this part is copied from the two siblings. When in doubt, open the corresponding file
there.

## `composer.json`

Same key order, same blocks. From the siblings verbatim:

- `$schema`, `name`, `description`, `type: library`, `license: MIT`, `keywords`, `authors`,
  `homepage`, `support`
- `require`: `php: ^8.4`, the `illuminate/*` components actually used (`^13.0`), and
  `spatie/laravel-package-tools: ^1.93.1`
- `require-dev`: `larastan/larastan ^3.9`, `laravel/pint ^1.29`, `orchestra/testbench ^11.0`,
  `pestphp/pest ^5.0`, `pestphp/pest-plugin-laravel ^5.0`
- `autoload`: `KirchDev\NotificationDelivery\` → `src/`
- `autoload-dev`: `KirchDev\NotificationDelivery\Tests\` → `tests/`
- `scripts`: `test`, `test:fast`, `test:coverage`, `pint`, `pint:fix`, `larastan` — copy the bodies
  unchanged, including `Composer\Config::disableProcessTimeout` in `test:coverage`
- `extra.branch-alias.dev-main: 0.x-dev` and `extra.laravel.providers` naming the provider
- `config`: `sort-packages: true`, `allow-plugins.pestphp/pest-plugin: true`
- `minimum-stability: stable`, `prefer-stable: true`

Only depend on the `illuminate/*` components actually used. Both siblings list them individually
rather than pulling `laravel/framework`.

## Directory layout

```
config/notification-delivery.php
database/migrations/0001_01_01_00000N_create_*_table.php
resources/boost/guidelines/core.blade.php
resources/boost/skills/notification-delivery-development/SKILL.md
src/
  Actions/
  Channels/
  Concerns/
  Console/
  Contracts/
  Enums/
  Events/
  Models/
  Notifications/
  Support/
  NotificationDeliveryServiceProvider.php
tests/
```

Naming rules taken from the siblings:

- **Contracts** are bare interface names in `src/Contracts/` — `IpMasker`, `DeviceNameResolver`,
  `OrganisationResolver`. Here: `Channel`, `NotificationType`, `SuppressionPolicy`.
- **Default implementations** live in `src/Support/` with a `Default` prefix where they implement a
  contract of the same subject (`DefaultIpMasker`, `DefaultOsFamilyDetector`). A policy that does
  nothing is named for its behaviour instead — `NeverSuppress`.
- **Actions** are verb-first, one public `execute(...)` — `ListUserDevices`, `RevokeUserDevice`.
  Here: `ListNotifications`, `MarkNotificationAsRead`, `MarkAllNotificationsAsRead`.
- **Console commands** carry the `Command` suffix — `PruneRevokedUserDevicesCommand`. Here:
  `PruneDeliveredNotificationsCommand`.
- **Models** are bare — `UserDevice`, `Role`, `RolePermission`.

Copy `src/Concerns/HasConfigurableKey.php` from device-sessions rather than reimplementing it; it is
what makes `keys.*` work across `id` / `uuid` / `ulid`.

## Service provider

Extends `Spatie\LaravelPackageTools\PackageServiceProvider`. Class name is
`NotificationDeliveryServiceProvider`, in the package root namespace.

```php
public function configurePackage(Package $package): void
{
    $package
        ->name('laravel-notification-delivery')
        ->hasConfigFile()
        ->hasCommands(PruneDeliveredNotificationsCommand::class)
        ->discoversMigrations();
}
```

**Copy `bootPackageMigrations()` verbatim from either sibling, docblock included.** It guards the
upstream migration-name computation behind `runningInConsole()`, because upstream globs the
consumer's `database/migrations` on every boot otherwise. It is not an optimisation anyone should
re-derive.

`packageRegistered()` binds contracts to defaults; `packageBooted()` wires events and integrations.

**Bind `scoped()`, not `singleton()`**, for anything holding per-request state — the consuming
application runs Octane, where workers outlive requests. pbac binds everything scoped for exactly
this reason and lets caching services implement its `Resettable` contract. device-sessions uses
`singleton()` only for stateless resolvers. Follow pbac here: the preference resolver and the
delivery resolver both cache within a request.

## Config file

`config/notification-delivery.php`. Same block structure as both siblings: `declare(strict_types=1)`,
imports at the top, one banner comment per section explaining _why_ the knob exists, not what it is.

Sections, in the siblings' order: `models`, `table_names`, `column_names`, `keys`, then
package-specific ones (`channels`, `discovery`, `suppression`, `prune`).

Two rules the siblings state explicitly and this package inherits:

- `keys.*` must be set **before** migrating — the migrations read config at run time and bake the
  types into the schema. Changing them later needs an application-owned migration.
- Every model is resolved through `config('notification-delivery.models.*')`. Never `new`,
  `::query()` or `::find()` on a packaged model class: an application without a morph map stores the
  class it resolved, so a row written through the packaged class names a different class than every
  row beside it and quietly stops matching.

## Migrations

Named with Laravel's sentinel date, numbered in dependency order:

```
0001_01_01_000001_create_delivered_notifications_table.php
0001_01_01_000002_create_notification_preferences_table.php
```

- Discovered via `discoversMigrations()`, never listed in the provider.
- `runsMigrations()` stays **off**. Consumers publish
  (`vendor:publish --tag=notification-delivery-migrations`) and own the copies.
- Each migration reads `table_names`, `column_names` and `keys` from config at run time, and uses a
  private `addKeyColumn()` helper matching on `uuid` / `ulid` / default — copy the shape from
  `0001_01_01_000004_create_model_has_roles_table.php` in pbac.

## Boost resources

Both siblings ship these, and both have a test asserting they exist. Same here:

- `resources/boost/guidelines/core.blade.php` — starts with an H1 naming the package, then a
  **"Setup order (do not reorder)"** section, then usage, then an **"Extending it"** section.
  Artisan commands are written as `{{ $assist->artisanCommand('...') }}`, never hard-coded.
- `resources/boost/skills/notification-delivery-development/SKILL.md` — YAML front matter with
  `name` (matching the directory) and a `description` starting
  `'Work with kirchdev/laravel-notification-delivery: ...'`.

A consumer opts in by listing the package in its `boost.json` `packages[]`.

## Tests

Pest, `tests/` at the root, `autoload-dev` namespace `...\Tests\`.

- `tests/TestCase.php` extends Testbench and registers the provider; `tests/Pest.php` binds it.
- `tests/Fixtures/` holds the models the suite authenticates and notifies —
  including `UuidUser` / `UlidUser` variants, as device-sessions does.
- Feature tests in `tests/Feature/`, one file per behaviour.
- **Key-type suites**: separate `tests/UuidKeys/` and `tests/UlidKeys/` directories with their own
  `TestCase`, each running a smoke test against that key type. Copy the shape from device-sessions.

Two tests are mandatory because both siblings carry them and they guard package-level invariants:

- **`MigrationPublishingTest`** — asserts publishing maps onto already-published files instead of
  dropping duplicates beside them. This is the behaviour that broke consumers before
  `spatie/laravel-package-tools` 1.93.1.
- **`BoostResourcesTest`** — asserts the guideline and skill files exist and are discoverable.

## Tooling

Copy these three files unchanged apart from the suite name:

- `phpstan.neon` — larastan extension, `level: 7`, paths `src`, `database/migrations`,
  `tests/Fixtures`, `parallel.maximumNumberOfProcesses: 1`, `treatPhpDocTypesAsCertain: false`
- `pint.json` — `{"preset": "laravel"}`
- `phpunit.xml.dist` — testsuite named after the package, `<source>` including `src`

## The JS side

Already scaffolded, but `package.json` still identifies as `scaffold`. Align it with the siblings:

- `name`: `@kirchdev/laravel-notification-delivery`, `private: true`
- `description`: `Dev tooling for kirchdev/laravel-notification-delivery (Composer package).`
- `homepage`, `bugs.url`, `repository.url` pointing at this repo
- Drop `typecheck`, `check:policy` and the TypeScript dev dependencies unless the scaffold's policy
  check is meant to stay — neither sibling has them; their `check` is `pnpm lint && pnpm format`.
- Keep `version` starting at `0.1.0` and let release-please move it.

`.tituskirch-skills.json` already carries `docs.preset: "package"` and the GitHub tracker, matching
the siblings' setup. Its `verify` is `pnpm check`, which covers only the JS side — extend it to run
the PHP gates too, since those are where the package's real checks live.

---

# Part 3 — Where the two references disagree

These are the only points not settled by copying. Each names the choice made here and why.

| Question        | device-sessions                  | pbac                         | Here                                                                     |
| :-------------- | :------------------------------- | :--------------------------- | :----------------------------------------------------------------------- |
| Trait directory | `src/Concerns/`                  | `src/Traits/`                | **`src/Concerns/`** — Laravel's own word, and pbac is the outlier        |
| Manager class   | `src/Support/DeviceSessions.php` | `src/PbacManager.php` (root) | **root**, as `NotificationDelivery.php` — it is the package's front door |
| Facade          | none                             | `src/Facades/Pbac.php`       | **none for now** — add one only if call sites turn awkward               |
| Bindings        | `singleton()`                    | `scoped()`                   | **`scoped()`** — Octane, and this package caches per request             |
| Enums           | `src/Enums/`                     | none                         | **`src/Enums/`** — `CoreChannel` lives there                             |

---

# Part 4 — Order of work

1. `composer.json`, provider skeleton, `config/notification-delivery.php`, tooling files. Nothing
   works yet; the shape is right.
2. Migrations plus models, with `HasConfigurableKey` copied over. `MigrationPublishingTest` green.
3. The inbox channel and the stored notification: `TypedNotification`, `InboxChannel`,
   read/unread actions. This is the smallest useful package.
4. `Channel` and `NotificationType` contracts, `CoreChannel`, the registries and discovery.
5. Preferences, the gate chain, `SuppressionPolicy` with `NeverSuppress`.
6. `PruneDeliveredNotificationsCommand`.
7. Boost guideline and skill, `BoostResourcesTest`.
8. README in the kirchDev house style.

Steps 1–3 are a releasable `0.1.0` on their own — persistence, read state, `inbox`, `live` and `mail`. Steps 4–5
are what turn a store into a delivery layer and can follow as `0.2.0`. The `Channel` interface
belongs in `0.1.0` regardless, because it defines the seam the later work attaches to.

# Part 5 — The first consumer

`kirchDev/gildstone` is the reason this package exists, tracked as **ENG-162**. That issue covers the
application half and depends on this package being released; the split is:

| Here                                                           | In gildstone                                                             |
| :------------------------------------------------------------- | :----------------------------------------------------------------------- |
| Tables, models, `InboxChannel`, `TypedNotification`            | The concrete type enums under `app/Enums/Notification/`                  |
| `Channel` / `NotificationType` / `SuppressionPolicy` contracts | The `SuppressionPolicy` implementation reading `laravel-device-sessions` |
| `CoreChannel`, registries, discovery                           | `Api\V1\Me\NotificationController` + response DTO                        |
| Gates 1–4, deferred delivery job                               | The Nuxt bell, list, settings page                                       |
| `notification-delivery:prune`, the trait                       | A `DeviceTouched` listener as the first real producer                    |

gildstone consumes it as a normal versioned Composer dependency and publishes the migrations, exactly
as it does with `laravel-pbac` and `laravel-device-sessions`. It does **not** vendor the code.

Two consequences worth stating, because they will otherwise surprise whoever implements ENG-162:

- **`PayloadData` and `ActionData` move here.** They live in gildstone today
  (`app/Data/Notification/`) but are domain-free, and `PayloadData` gains two fields in the process:
  `publicId` (so the frontend can merge a live message with the loaded list) and `announce` (the
  result of the chain for `CoreChannel::Live`). `NotificationBroadcasted` moves with them.
- **gildstone's `User` has to drop `Notifiable`** in favour of `RoutesNotifications` plus this
  package's trait. See the note in Part 1.

# Part 6 — Still open

- **Table names.** `delivered_notifications` and `notification_preferences` are proposals.
  `notifications` is unavailable — Laravel's own database channel claims it, and leaving that name
  free avoids a collision for consumers that still use it.
- **Whether a `NotificationGroup` contract belongs in the package** or grouping is left entirely to
  the consuming application's enums.
