# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.2] - 2026-08-23

### Security

- `Auditable` no longer writes sensitive attributes into the audit trail. Passwords, tokens, and secrets are excluded by default, and the list is configurable via `lara-util-x.audit.excluded_attributes`. Previously a model using the trait recorded every attribute verbatim, so applying it to `User` stored password hashes and tokens in `model_audits`.
- `CrudController` now restricts `sort_by` to columns declared in `$sortableFields`. Previously any column could be used to order results, letting a caller infer values from columns they could not otherwise read.

### Added

- `CrudController::$sortableFields` for declaring which columns may be sorted on.
- `Auditable::auditExcludedAttributes()` for adding per-model exclusions on top of the configured defaults.
- `audit` configuration block with `table` and `excluded_attributes` keys. The audit table name is no longer hardcoded.
- Test coverage for `CrudController` and the `Auditable` trait.
- `CHANGELOG.md`.

### Fixed

- `composer.json` declared no dependencies at all. It now requires `php ^8.1`, the `illuminate/*` components the package actually uses, `nesbot/carbon`, and `guzzlehttp/guzzle`, so Composer can no longer install the package into an incompatible project.
- Removed `minimum-stability: dev` from `composer.json`, which pushed dev-stability resolution onto anyone installing the package.
- `CrudController::deleteRecord()` returned a JSON body with a `204 No Content` status, which is not a valid combination. It now returns `200` with the message body.
- Fixed an order-dependent failure in `MakeCrudCommandTest`. Generated files leaked between tests, so a later test could assert against stale content.

### Changed

- Declared support for Laravel 10, 11, 12, and 13, and PHP 8.1+. The README previously claimed Laravel 8.0+ and PHP 8.0+, neither of which the code supported.
- Documented the `make:crud` generator and the `XHelper` class in the README, and removed the entry for `ValidationHelperTrait`, which does not exist in the package.

## [1.5.1] - 2026-03-17

### Fixed

- Synced the version declared in `composer.json`.

## [1.5.0] - 2026-03-16

### Added

- `make:crud` API CRUD generator, producing a model, controller, and migration from a field definition, with support for relationships, soft deletes, searchable fields, and route registration.

## [1.4.0] - 2025-12-29

### Added

- `XHelper` class collecting array, string, date, and UUID helpers.

## [1.3.1] - 2025-12-23

### Fixed

- Resolved test suite issues and removed the `CachingUtil` feature test.

## [1.3.0] - 2025-10-23

### Added

- Test suite covering the utilities, traits, enums, and validation rules.

## [1.2.0] - 2025-10-13

### Added

- `RejectCommonPasswords` validation rule.

## [1.1.8] - 2025-10-10

### Fixed

- `CachingUtil` behavior.

## [1.1.7] - 2025-09-27

### Added

- Claude LLM provider.

## [1.1.6] - 2025-09-12

### Added

- Gemini LLM provider.

## [1.1.5] - 2025-08-19

### Fixed

- `RateLimiterUtil` behavior.

## [1.1.4] - 2025-07-24

### Added

- Enums, and a new method on `PaginationUtil`.

## [1.1.3] - 2025-07-12

### Changed

- Enhanced `ApiResponseTrait`.

## [1.1.2] - 2025-06-30

### Removed

- Committed vendor directory.

[1.5.2]: https://github.com/omarchouman/lara-util-x/compare/1.5.1...1.5.2
[1.5.1]: https://github.com/omarchouman/lara-util-x/compare/1.5.0...1.5.1
[1.5.0]: https://github.com/omarchouman/lara-util-x/compare/1.4.0...1.5.0
[1.4.0]: https://github.com/omarchouman/lara-util-x/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/omarchouman/lara-util-x/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/omarchouman/lara-util-x/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/omarchouman/lara-util-x/compare/1.1.8...1.2.0
[1.1.8]: https://github.com/omarchouman/lara-util-x/compare/1.1.7...1.1.8
[1.1.7]: https://github.com/omarchouman/lara-util-x/compare/1.1.6...1.1.7
[1.1.6]: https://github.com/omarchouman/lara-util-x/compare/1.1.5...1.1.6
[1.1.5]: https://github.com/omarchouman/lara-util-x/compare/1.1.4...1.1.5
[1.1.4]: https://github.com/omarchouman/lara-util-x/compare/1.1.3...1.1.4
[1.1.3]: https://github.com/omarchouman/lara-util-x/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/omarchouman/lara-util-x/releases/tag/1.1.2
