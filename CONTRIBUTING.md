# Contributing to laravel-notification-delivery

Thanks for taking the time to contribute! 🔔 This document covers what you need to get a PR landed.

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to uphold it.

## Reporting issues

- **Questions & ideas, or something that might be a bug**: start in the [Discord forum](https://discord.kirch.dev/) — that's where the low-friction, unconfirmed stuff lives.
- **Confirmed bugs**: open a [Bug report](https://github.com/kirchDev/laravel-notification-delivery/issues/new?template=bug_report.yml) with a minimal failing Pest test if at all possible.
- **Feature requests**: open a [Feature request](https://github.com/kirchDev/laravel-notification-delivery/issues/new?template=feature_request.yml).
- **Security vulnerabilities**: **do not** open a public issue. Follow [SECURITY.md](SECURITY.md).

## Development setup

Requirements:

- PHP **8.4+**
- Composer 2
- Node **24+** and **pnpm 12** (for dev tooling — husky, lint-staged, oxlint, oxfmt)

Clone and install:

```bash
git clone https://github.com/kirchDev/laravel-notification-delivery.git
cd laravel-notification-delivery
composer install
pnpm install   # wires husky hooks
```

## Running the suite

| Command             | What it does                                 |
| :------------------ | :------------------------------------------- |
| `composer test`     | Pest 5 + Testbench (in-memory SQLite).       |
| `composer pint`     | Laravel Pint in test mode.                   |
| `composer pint:fix` | Auto-fix style.                              |
| `composer larastan` | Larastan / PHPStan static analysis.          |
| `pnpm check`        | oxlint + oxfmt across JS / JSON / YAML / MD. |
| `pnpm check:fix`    | Auto-fix the above.                          |

The same commands run in CI — keep them green before you push.

## Branching & PRs

1. **Branch off `dev`, and open the PR against `dev`.** `main` is the release branch: `dev` is rolled up into it by the promotion PR, and release-please cuts the release from there.
2. **Conventional Commits required.** Commitlint enforces this on every commit. Examples:
   - `feat: add ulid key type support`
   - `fix(gates): treat a missing preference row as undecided`
   - `docs(readme): clarify the inbox channel's broadcast`
   - `chore(deps): bump testbench to 11.5`
   - Breaking changes: `feat!: ...` or include `BREAKING CHANGE:` in the body.
3. **One concern per PR.** Smaller PRs land faster.
4. **Tests are required for code changes.** Bug fixes need a regression test, features need coverage of the happy path and at least one edge case.
5. **Update relevant docs.** README, config inline docs, the Boost guideline and skill, and the migration if you change a default.

## Style & quality gates

Husky runs the following on `git commit`:

- **PHP files** → `pint` + `phpstan` (Larastan)
- **JS / JSON / YAML / MD** → `oxlint` + `oxfmt`

If a hook fails, fix the issue and commit again. **Don't `--no-verify`** unless I explicitly ask.

> [!TIP]
> Run `pnpm check:fix` and `composer pint:fix` before opening a PR — saves a CI cycle.

## Releases

Releases are automated via [release-please](https://github.com/googleapis/release-please). When your `feat:`/`fix:` commits reach `main` through the promotion PR, release-please opens a PR with the next version bump and CHANGELOG entry. Merging it tags the release; Packagist picks it up via the GitHub webhook.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
