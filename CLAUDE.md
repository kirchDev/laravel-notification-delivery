# CLAUDE.md

This file provides guidance to AI coding agents — Claude Code (claude.ai/code) and vendor-neutral tools such as Codex, OpenCode, Cursor, and Copilot — when working with code in this repository.

## Agent instruction files

`CLAUDE.md` and `AGENTS.md` are kept **byte-identical**. `CLAUDE.md` is what Claude Code reads; `AGENTS.md` is what vendor-neutral agent tools read — Codex, OpenCode, Cursor, Copilot, and whatever follows them. Two real files, deliberately not a symlink: not every tool resolves one.

**After editing either file, copy it over the other — don't repeat the edit by hand:**

```bash
cp CLAUDE.md AGENTS.md   # or the reverse, whichever you just edited
```

Retyping a change is exactly how the two drift; one reflowed line or reworded clause is enough. `diff CLAUDE.md AGENTS.md` must print nothing. If it ever does, treat it as a defect and fix it by letting one file win wholesale — never by merging them.

## What this is

`kirchdev/laravel-notification-delivery` is a standalone Composer **library** (not an application) that adds three things to Laravel 13's notifications: persistence with read state, per-recipient channel preferences, and a gate chain that decides delivery before `via()` runs. PHP 8.4+, ships its own service provider auto-discovered via `extra.laravel.providers`.

It adds a layer and replaces nothing: every notification stays an `Illuminate\Notifications\Notification`, every channel stays a Laravel channel, `Notification::send()` stays the entry point.

The library has no host app — tests run against `orchestra/testbench` with in-memory SQLite.

## Commands

PHP (Composer scripts):

- `composer test` — Pest 5 suite via Testbench.
- `composer test -- --filter=SomeTest` — run a single test / pattern.
- `composer test:coverage` — coverage report; CI gates at 90%.
- `composer pint` — Laravel Pint in **test** mode (no writes). `composer pint:fix` to auto-fix.
- `composer larastan` — Larastan/PHPStan at `--memory-limit=512M`.

Node tooling (lint/format only, no app code):

- `pnpm check` / `pnpm check:fix` — oxlint + oxfmt over JS / JSON / YAML / MD.
- Husky runs Pint + Larastan + oxlint + oxfmt on commit via lint-staged. Don't `--no-verify` unless explicitly asked.

Commits **must** follow Conventional Commits (commitlint enforced). Branch off `dev` and PR into `dev`; `main` is the release branch and release-please cuts from there.

## Architecture

**Types are enums, channels are an interface.** `Contracts/NotificationType` is implemented by an application's own enum: a **static** `group()` (the group belongs to the enum, not the case) and a per-case `definition()`. `Contracts/Channel` is likewise an enum interface — the package ships `Enums/CoreChannel` (`inbox`, `live`, `mail`) and an application adds its own. An enum in the package would be closed, and nobody could ever add push.

**`Support/DeliveryResolver` is the gate chain**, and the centre of the package. Four gates per channel, in order: availability, locked, the recipient's preference, then `Contracts/SuppressionPolicy`. `TypedNotification::via()` calls it and hands back the survivors' `laravelChannel()` values.

The decision is memoised per `(notification, notifiable)` in a **`WeakMap`, not an array keyed by `spl_object_id`** — an object id is reused once its object is collected, and a worker sending thousands of notifications would eventually answer one notification's question with another's decision. It is read twice: by `via()`, and by `InboxChannel` asking whether `live` survived.

**`Channels/InboxChannel` writes the row and fires the one broadcast.** `Channels/LiveChannel` delivers nothing on purpose — `live` only decides whether `announce: true` sits on the payload `InboxChannel` broadcasts. The class exists so `live` has a `laravelChannel()` like every other channel and needs no special case in the chain.

**`Support/PreferenceResolver` is gate 3**, three tiers deep (type row → `group:` row → the type's default), serving every lookup for one recipient from a single query. `null` means undecided, **not off** — that is what lets a new type ship with a sensible default and no backfill.

**`Jobs/DeliverDeferredNotification` is the answer to "`via()` cannot wait".** A policy that defers drops the channel from `via()` and schedules this instead; it re-checks on execution and either delivers or discards. It sends through `Notification::sendNow()` with an **explicit channel list** rather than re-sending the notification — re-running `via()` would write a second inbox row.

Everything host-facing is a contract in `src/Contracts/` (`Channel`, `NotificationType`, `NotificationGroup`, `SuppressionPolicy`). Models (`DeliveredNotification`, `NotificationPreference`) are swappable via `config('notification-delivery.models.*')` and key-type agnostic via `HasConfigurableKey` (id / uuid / ulid from config). `HasNotificationDelivery` goes on the recipient.

## Things that are easy to get wrong

- **The recipient replaces `Notifiable`, it does not add to it.** Laravel's `Notifiable` is `RoutesNotifications` **plus** `HasDatabaseNotifications`, and the latter defines `notifications()` against Laravel's own table, which this package never writes. Left in place it is either a trait method collision or a relation that silently always comes back empty. `HasNotificationDeliveryTraitTest` pins that the fixture does not use it.
- **Bindings are `scoped()`, never `singleton()`.** The consuming application runs Octane, where a worker outlives the request, and all four bound services cache per-request state. A singleton here is a cache that eventually answers for the wrong recipient.
- **Config must be read at resolve time, not at register time.** The provider registers before the application's own config is in place — under Testbench it registers before `defineEnvironment()` runs at all. `SuppressionPolicy` is therefore bound to a closure; a class name decided in `packageRegistered()` would ignore whatever the application configured.
- **`mergeConfigFrom()` merges only the top level.** Setting one nested key in a test's `defineEnvironment()` replaces that whole block, dropping every sibling default. `tests/TestCase.php` sets `keys` and `suppression` as whole arrays for exactly this reason.
- **`laravel-device-sessions` is a `require-dev` dependency**, so the auto-binding hands every test the presence-aware policy unless it is pinned. `tests/TestCase.php` pins `NeverSuppress`; `DeviceSessionSuppressionTest` creates the `user_devices` table itself and names the policy explicitly.
- `DeviceSessionSuppression` must read **`user_devices.last_seen_at`, never `users.last_seen_at`**. That column belongs to the application — gildstone writes it from a hand-built listener, another project has no such column — and a policy depending on it would silently never suppress there.
- **The default morph key column has to sit in `$fillable` literally.** `fillableFromArray()` filters on `getFillable()` before `isFillable()` is ever consulted, so the `isFillable()` override only covers a *renamed* column.
- `notification-delivery.keys.*` and `table_names.*` must be set **before** running the published migrations — the migration files read config at run time. `notifiable_morph_key_type` must match the key type of the models being notified.
- Migrations are **publish-only** — `configurePackage()` leaves `runsMigrations()` off, so the provider never calls `loadMigrationsFrom()` and `vendor:publish --tag=notification-delivery-migrations` is the only route into a host app. `discoversMigrations()` maps each file individually and stamps the target with the publish time, one second per position — an already published copy keeps its filename, so re-publishing never duplicates a migration. The test suite loads the package path itself in `tests/TestCase.php`.
- Source migrations are named **`0001_01_01_<sequence>_<migration>`**. The date is Laravel's own sentinel, not a claim about a day: `laravel-package-tools` strips exactly `/^\d{4}_\d{2}_\d{2}_\d{6}_/` before stamping its own. A new migration takes the next free sequence number, and that order is the only thing carrying dependency order — there is no list of migrations anywhere. `MigrationPublishingTest` asserts the source names, the prefix shape and the resulting publish order.
- `laravel-package-tools` builds published paths as `migrations/` + `dirname($name)` + `/`, and `dirname()` of a bare filename is `.` — so every published path contains a literal `/./`. The copy resolves it; assertions must normalise it (`normalisePath()` in `MigrationPublishingTest`).
- `bootPackageMigrations()` is **overridden** to return early outside the console: upstream computes each published name — globbing the consumer's `database/migrations` — before its own `runningInConsole()` check, so a request-time boot would otherwise pay a directory scan per migration. The guard stands down if `runsMigrations()` is ever switched on.
- The provider extends `Spatie\LaravelPackageTools\PackageServiceProvider`. Wiring goes in `packageRegistered()`; `configurePackage()` carries only the config file, the command and the migrations. Booting the provider by hand in a test needs `->register()->boot()` — `$this->package` is built in `register()`.
- **The prune command refuses a negative window** rather than clamping it to zero. Clamping would read as "delete everything older than right now", which is the one outcome a typo in a retention setting must never produce. Both windows default to `null`: never delete.
- `resources/boost/` is **consumer-facing**, unlike `CLAUDE.md` / `AGENTS.md`. Laravel Boost discovers it purely from the filesystem — it reads the consumer's root `composer.json` and looks for `vendor/kirchdev/laravel-notification-delivery/resources/boost/{guidelines,skills}` — so there is no dependency on `laravel/boost` here and nothing to register. The paths are the whole contract; renaming a directory removes the package from `boost:install` without an error.
- Third-party guidelines get **no version resolution and no fallback**: everything under `guidelines/` is always loaded, and a guideline that throws while rendering is silently replaced by an empty string. Keep `$assist` calls inside the documented `GuidelineAssist` surface (`BoostResourcesTest` asserts it against an allowlist and renders every guideline through Boost's own placeholder pipeline). A `SKILL.md` missing `name` or `description` frontmatter is discarded just as silently; keep that frontmatter flat `key: value`, since Boost parses it with `symfony/yaml` and the suite — which does not depend on it — parses it by hand.
- Tests use Testbench; there is no `bootstrap/app.php`. Add new setup to `tests/TestCase.php` / `tests/Pest.php`. A test that calls `migrate:fresh` must call `TestCase::restoreBaselineSchema()` afterwards, not the package path alone — `migrate:fresh` drops the host `users` table too.
