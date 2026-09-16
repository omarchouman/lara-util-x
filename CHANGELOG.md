# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.5] - 2026-09-17

A follow-up to 1.5.4 closing gaps that release left behind, including one
regression it introduced.

### Security

- `current_password` was not redacted from the access log. It is the field Laravel Breeze's password-update form submits, so a password-change route still wrote the user's existing password into `access_logs` in plaintext. `current_password`, `new_password`, `new_password_confirmation`, `api_key`, and `client_secret` are now excluded by default.
- Access log redaction now applies at any nesting depth. `Request::except()` only strips top-level keys, so a nested `user[password]` survived unless the exclusion list happened to name `user.password`. Dot-notation entries still work for targeting one specific nested key.
- Query-string parameter names are now matched case-insensitively, so `?Token=` is redacted alongside `?token=`.

### Fixed

- Unique-rule rewriting on update discarded trailing where clauses, a regression introduced in 1.5.4. `required|unique:users,email,NULL,id,tenant_id,7` became `required|unique:users,email,5`, silently turning per-tenant uniqueness into global uniqueness. Only the ignore-id slot is replaced now.
- `ConfigUtil::forgetSetting()` ignored dot notation. It used `unset()`, which cannot reach a nested key, so `forgetSetting('mail.from')` silently did nothing. It now uses `Arr::forget()`.
- Documented the correct pruning schedule for access logs. Without `--model`, `php artisan model:prune` only scans `app/Models`, so a model living in `vendor/` is never discovered and the 1.5.4 documentation's claim was false.

### Changed

- Package classes are no longer publishable. A published copy kept its `LaraUtilX` namespace while landing in `app/`, where Composer's PSR-4 mapping expects `App\`, so the copy was never autoloaded and every call still resolved to the package. Removing the tags changes no runtime behaviour. `lara-util-x-config`, `lara-util-x-feature-toggles`, `lara-util-x-migrations`, and `lara-util-x-stubs` are unaffected.
- `SchedulerUtil::hasOverdueTasks()` renamed to `hasDueTasks()`. `Event::isDue()` asks whether a cron expression matches the current minute, so the method reported "due now", not "overdue", and an `everyMinute()` task made it true almost constantly. The old name still works and delegates to the new one, but is deprecated.
- Documented that `SchedulerUtil` only works in a console context. Laravel registers schedules through `Artisan::starting()` and `afterResolving(ConsoleKernel::class)`, neither of which fires during a web request, so calling it from an HTTP route returns an empty schedule rather than an error. This has always been true on every supported Laravel version.

### Added

- GitHub Actions workflow running the suite against Laravel 10, 11, 12, and 13 on every push and pull request. Laravel 10 runs on PHP 8.1 so the declared PHP floor is exercised rather than assumed, and each row runs the suite in both declaration and random order.
- Test coverage grew from 234 to 245.

## [1.5.4] - 2026-09-16

A correctness and security release. Several utilities were unusable in a real
application, and three components wrote credentials or crashed on first contact
with production data.

### Security

- `AccessLogMiddleware` no longer stores request bodies verbatim. It recorded `json_encode($request->all())`, so placing it on a login route wrote plaintext passwords into `access_logs`. Credentials are now redacted using the same exclusion list as the audit trail, configurable via `lara-util-x.access_log.excluded_attributes`.
- `AccessLogMiddleware` redacts credentials in the query string as well. `fullUrl()` persisted tokens passed as query parameters.
- `AccessLog` is now prunable. The table previously grew without bound; `php artisan model:prune` removes rows older than `lara-util-x.access_log.retention_days` (30 by default, null to disable).
- `FileProcessingTrait::uploadFile()` no longer reuses the client-supplied filename. Only the extension is kept, and the stored name is random.

### Fixed

- `SchedulerUtil` threw on any application with a scheduled task. `getNextRunDate()` and `isRunning()` do not exist on Laravel's `Event`; it now uses `isDue($app)` and inspects the overlapping mutex. It also dumped every event through `print_r` into the log on each call, which exhausted memory once real events were registered.
- `SchedulerUtil::hasOverdueTasks()` could never return true, because it compared `nextRunDate()` (always in the future) against now.
- `LoggingUtil` threw a `TypeError` whenever a channel was passed. `getLogger()` declared a `Monolog\Logger` return type, but `Log::channel()` returns `Illuminate\Log\Logger`; the return type is now the PSR interface.
- `ConfigUtil` was non-functional. `getSetting()` always returned null, and `setSetting()` passed an absolute `storage_path()` to `Storage::put()`, so settings were written somewhere they could never be read back from. Settings now round-trip through a configurable disk and path, with dot-notation support.
- `FeatureToggleUtil` copied a config file into the host application's `config/` at runtime, which fails on read-only deploys and is ignored once the config is cached. Defaults now come from the service provider.
- `FeatureToggleUtil::isEnabled()` threw when a feature was declared as an array holding user or environment overrides, because it returned the array from a `bool` method. Overrides now resolve most-specific-first: user, then environment, then `enabled`.
- `FilteringUtil` `ends_with` was wrong for repeated substrings. `"Smith Smith"` did not match `"Smith"`, because the first occurrence's offset was compared rather than the end of the string. `starts_with` was rewritten alongside it.
- `CachingUtil` wrote tagged entries but read and forgot them untagged, so on a taggable store with `default_tags` configured every read missed.
- `CrudController` accepted `?per_page` straight from the request, so `?per_page=1000000` returned the whole table. It is now clamped by `$maxPerPage` (100 by default), and generated controllers gained a `--max-per-page` option.
- `CrudController` unique-rule rewriting on update broke when `unique:` was not the last rule or did not name its column. The rule segment is now rebuilt rather than having the id appended to the whole string.
- The OpenAI provider, which is the default, failed with a bare "class not found" on a fresh install. `openai-php/client` has never been a dependency; it is now listed under `suggest`, and the provider raises an actionable error naming the package and the alternatives.
- The Claude provider's default model was `claude-3-5-sonnet-20241022`, retired in October 2025, so every call with default configuration failed. It now defaults to `claude-sonnet-5`.
- The Claude provider passed OpenAI-style messages through unchanged, but Anthropic takes `system` as a top-level parameter rather than a message role, so the unified interface broke as soon as a system prompt was sent. System messages are now lifted out of the conversation.
- Publishing was broken in three ways: `__DIR__ . '\Models'` used a backslash and failed on Linux, the validation rule pointed at `RejectCommonPasswords_App.php`, which does not exist, and the service provider published itself into `app/Providers`, risking double registration. The provider is no longer publishable.
- `XHelper::strSlugify()` returned an empty string for any non-Latin input, including Arabic. It now uses `Str::slug()`, which transliterates, and accepts a custom separator.
- `FileProcessingTrait::getFile()` returned the literal string `"File not found"`, making a missing file indistinguishable from a file containing that text. It returns null.
- Removed implicit-nullable parameters in `CachingUtil` and `ConfigUtil`, which raised deprecation notices on PHP 8.4.

### Added

- `access_log` and `config` configuration blocks.
- `CrudController::$maxPerPage` and the `--max-per-page` option on `make:crud`.
- `ConfigUtil::forgetSetting()`.
- `SchedulerUtil::isDue()` and `isRunning()` as public methods.
- Test coverage grew from 179 to 234. `XHelper`, `AccessLogMiddleware`, and the Claude provider had no tests at all; the `CachingUtil`, `ConfigUtil`, and `SchedulerUtil` suites were rewritten to exercise real behaviour rather than mocks that could not disagree with the implementation.

## [1.5.3] - 2026-08-24

### Security

- Controllers generated by `make:crud` now restrict `?sort_by` to a `$sortable` allow-list written into the generated `index()` method. Previously the generated controller passed the value straight to `orderBy()`, so any column on the table could be used to order results and its values inferred from the row order. This closes the gap left by 1.5.2, which fixed the same defect only in the abstract `CrudController`.

### Added

- `--sortable=` option on `make:crud` for setting the allow-list explicitly. When omitted it defaults to `id`, the declared `--fields`, and `created_at`, with credential-looking columns such as `password`, `api_token`, and `remember_token` excluded.

### Changed

- Generated controllers normalise `?sort_direction` to `asc` or `desc` rather than passing the raw value to `orderBy()`.

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

[1.5.5]: https://github.com/omarchouman/lara-util-x/compare/1.5.4...1.5.5
[1.5.4]: https://github.com/omarchouman/lara-util-x/compare/1.5.3...1.5.4
[1.5.3]: https://github.com/omarchouman/lara-util-x/compare/1.5.2...1.5.3
[1.5.2]: https://github.com/omarchouman/lara-util-x/compare/1.5.1...1.5.2
[1.5.1]: https://github.com/omarchouman/lara-util-x/compare/1.4.0...1.5.1
[1.5.0]: https://github.com/omarchouman/lara-util-x/commit/1c65bcd
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
