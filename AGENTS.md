# kirchdev/laravel-notification-delivery

A Composer **library** for Laravel 13 / PHP 8.4+: stored notifications with read state, per-recipient channel preferences, and a gate chain that decides delivery before `via()` runs. It adds a layer and replaces nothing — notifications, channels and `Notification::send()` stay Laravel's own. No host app: tests run on `orchestra/testbench` with in-memory SQLite (CI adds PostgreSQL).

## Commands

- `composer test` (`-- --filter=X` for one test), `composer test:fast` (parallel), `composer test:coverage` — CI gates at 90%.
- `composer pint` (check) / `composer pint:fix`, `composer larastan`.
- `pnpm check` / `pnpm check:fix` — oxlint + oxfmt.
- Husky + lint-staged run these on commit; never `--no-verify` unless asked.

Conventional Commits (commitlint). Branch off `dev`, PR into `dev`; release-please cuts from `main`.

## Architecture

- **Types are enums, channels an interface.** An application's enum implements `Contracts/NotificationType` — **static** `group()`, per-case `definition()`. `Contracts/Channel` is an enum interface too; the package ships `Enums/CoreChannel` (`inbox`, `live`, `mail`) and applications add their own.
- **`Support/DeliveryResolver` is the gate chain**: availability → locked → preference → `Contracts/SuppressionPolicy`. `TypedNotification::via()` returns the survivors' `laravelChannel()`.
- The decision is memoised per `(notifiable, notification)` and read by `via()` and by `InboxChannel` (did `live` survive?). Key the notifiable with a **`WeakMap`, never `spl_object_id`** (ids are reused). Never key by the notification object — `NotificationSender` clones it per send and per channel; `TypedNotification::deliveryToken()` identifies it across clones.
- **`Channels/InboxChannel`** writes the row and fires the one broadcast. It resolves its collaborators **per send**, never via the constructor — `ChannelManager` caches the channel instance for the worker's lifetime. `Channels/LiveChannel` delivers nothing; `live` only sets `announce: true` on the inbox broadcast and needs no special case in the chain.
- **`Support/PreferenceResolver`** (gate 3): type row → `group:` row → type default, one query per recipient. `null` means undecided, **not off**. Resolution is row-wise: the row answering `enabled` also answers `bypass_suppression`, read as `Enums/ChannelPreference` (`Off`/`On`/`WhenAway`/`Always`). `Always` (stored or from the type's `always:` list) skips gate 4; a type row never inherits its group's bypass.
- **`Jobs/DeliverDeferredNotification`**: a deferring policy drops the channel from `via()` and schedules this job, which re-checks and delivers via `Notification::sendNow()` with an **explicit channel list** — re-running `via()` would write a second inbox row.
- **A `locked` channel is always known**: `NotificationDefinition::channels()` prepends locked channels even when an explicit `available` list omits them.
- Host-facing contracts live in `src/Contracts/`. Models are swappable via `config('notification-delivery.models.*')` and key-type agnostic via `HasConfigurableKey`. `HasNotificationDelivery` goes on the recipient.

## Things that are easy to get wrong

- **`HasNotificationDelivery` replaces `Notifiable`**, never sits beside it — `HasDatabaseNotifications` collides on `notifications()`. `HasNotificationDeliveryTraitTest` pins this.
- **Bindings are `scoped()`, never `singleton()`** — the consumer runs Octane.
- **Read config at resolve time, not register time** — hence `SuppressionPolicy` is bound to a closure.
- **`mergeConfigFrom()` merges only the top level**: in `defineEnvironment()`, set whole blocks (as `tests/TestCase.php` does for `keys` and `suppression`).
- `laravel-device-sessions` is `require-dev`, so auto-binding would give every test the presence-aware policy. `tests/TestCase.php` pins `NeverSuppress`; `DeviceSessionSuppressionTest` creates `user_devices` and names its policy.
- `DeviceSessionSuppression` reads **`user_devices.last_seen_at`, never `users.last_seen_at`** — that column belongs to the application and may not exist.
- **A renamed morph key column goes into `getFillable()`**, not `isFillable()` — `fillableFromArray()` drops it before `isFillable()` is asked.
- Set `notification-delivery.keys.*` and `table_names.*` **before** running the published migrations; `notifiable_morph_key_type` must match the notified models' key type.
- Migrations are **publish-only** (`runsMigrations()` stays off; `vendor:publish --tag=notification-delivery-migrations`). The test suite loads the package path in `tests/TestCase.php`.
- Source migrations are named **`0001_01_01_<sequence>_<migration>`**; a new one takes the next free sequence — that order is the only dependency order. `MigrationPublishingTest` asserts names and publish order.
- Published paths contain a literal `/./`; assertions must normalise it (`normalisePath()` in `MigrationPublishingTest`).
- `bootPackageMigrations()` is overridden to return early outside the console; keep the guard standing down if `runsMigrations()` is switched on.
- The provider extends Spatie's `PackageServiceProvider`: wiring in `packageRegistered()`; `configurePackage()` holds only config, command and migrations. Booting it by hand in a test needs `->register()->boot()`.
- **The prune command refuses a negative window**, never clamps it. Both windows default to `null` (never delete).
- `resources/boost/` is **consumer-facing**; Boost finds `resources/boost/{guidelines,skills}` by path alone (no `laravel/boost` dependency, nothing to register) — renaming a directory silently drops the package from `boost:install`.
- Boost loads every guideline and silently blanks one that throws: keep `$assist` calls inside the `GuidelineAssist` allowlist `BoostResourcesTest` asserts. A `SKILL.md` without `name`/`description` frontmatter is silently discarded; keep that frontmatter flat `key: value`.
- No `bootstrap/app.php`: test setup goes in `tests/TestCase.php` / `tests/Pest.php`. After `migrate:fresh`, call `TestCase::restoreBaselineSchema()`, not just the package path — `migrate:fresh` also drops the host `users` table.
