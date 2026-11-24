# Contributing to laravel-notification-delivery

This project follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting issues

- **Questions, ideas, possible bugs**: the [Discord forum](https://discord.kirch.dev/).
- **Confirmed bugs**: a [bug report](https://github.com/kirchDev/laravel-notification-delivery/issues/new?template=bug_report.yml), ideally with a failing Pest test.
- **Feature requests**: a [feature request](https://github.com/kirchDev/laravel-notification-delivery/issues/new?template=feature_request.yml).
- **Security vulnerabilities**: never a public issue — see [SECURITY.md](SECURITY.md).

## Setup

PHP 8.4+, Composer 2, Node 24+ and pnpm 12.

```bash
git clone https://github.com/kirchDev/laravel-notification-delivery.git
cd laravel-notification-delivery
composer install
pnpm install   # wires the husky hooks
```

`composer test`, `composer larastan`, `composer pint` and `pnpm check` must pass — CI runs the same. `composer pint:fix` and `pnpm check:fix` auto-fix.

## Pull requests

1. Branch off `dev` and open the PR against `dev`. `main` is the release branch.
2. [Conventional Commits](https://www.conventionalcommits.org/), enforced by commitlint. Mark breaking changes with `!` or a `BREAKING CHANGE:` footer.
3. One concern per PR.
4. Code changes need tests: a regression test for a fix, the happy path plus an edge case for a feature.
5. Update what the change affects: README, `docs/`, config docblocks, the Boost guideline and skill in `resources/boost/`, and the migrations when a default changes.

The commit hook runs Pint and Larastan on PHP, oxlint on JS and oxfmt on JS / JSON / YAML / Markdown. Fix what it reports instead of skipping it with `--no-verify`.

## Releases

[release-please](https://github.com/googleapis/release-please) versions from the `feat:`/`fix:` commits that reach `main` through the promotion PR; merging its release PR tags the release and Packagist picks it up.

## Coding agents

Agent instructions live in [AGENTS.md](AGENTS.md). Claude Code loads it only while no `CLAUDE.md` or `CLAUDE.local.md` sits in the working directory or above it, so don't add one.

## License

Contributions are licensed under the [MIT License](LICENSE).
