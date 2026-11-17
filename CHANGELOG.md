# Changelog

All notable changes to `filament-blackbox` will be documented in this file.

## v0.0.12 - 2026-11-18

### Security

* Exclusion-aware display, Revert and Recover: audit values are filtered through the auditable model's CURRENT owen-it rules (`getAuditExclude()`, `getAuditInclude()`, `getAuditStrict()`, `getAuditTimestamps()`, `getHidden()`, `getVisible()`) before they are rendered in the page and slide-over, before Revert/Recover visibility is decided, and before the transition. Rows written before a field was excluded (password, `remember_token`, hidden fields) are never shown or restored
* In strict mode with a `$visible` allowlist, an attribute is auditable only if it is IN the allowlist — including audit keys that are absent from the hydrated model (dropped column, `select()` subset, legacy key)
* Revert and Recover apply the audit through owen-it's own `transitionTo()` with a filtered, unsaved copy of the audit, so owen-it's checks and `AuditableTransitionException` still apply; a refused transition is now reported in a notification instead of failing the request
* After a transition, every dirty attribute is re-checked: a setter on an allowed field that touched an excluded one aborts the transition (`ProtectedAttributeTransitionException`), resets the model and never saves or previews the value
* Revert and Recover are hidden when no auditable attribute is left to apply (Revert/Recover were already limited to `updated` audits)

### Added

* User label: `Filament::getUserName()` (the `HasName` contract — `getFilamentName()`, else the plain `name` attribute) with a bare `#id` only when that yields nothing; "System" is shown only when the audit has no user. No `users` table or `name` column is assumed, and the audit user is resolved through owen-it's `user` morph (`audit.user.morph_prefix`)
* Avatars come from `Filament::getUserAvatarUrl()` (the `HasAvatar` contract — `getFilamentAvatarUrl()`, else `avatar_url`, else the panel's default avatar provider) instead of a hardcoded external avatar URL; a system audit (no user) shows no avatar
* Server-side searchable user filter (no more `User::all()`), backed by the new `blackbox.users.resolver` config (class-string implementing `Blackbox\FilamentBlackbox\Contracts\UserResolver`) and `blackbox.users.search_columns` (default `['name', 'email']`, missing columns are skipped — they must be real DB columns, because `getFilamentName()` is PHP-only). The default resolver offers the user model of EVERY guard in `audit.user.guards` (`audit.user.guards` → `auth.guards.<guard>.provider` → `auth.providers.<name>.model`), and keys each option by the audit's own user morph (`<user_type>:<user_id>` as owen-it stores it), so users of different models with the same id stay distinguishable in the filter options and in the filtered audit list
* Any Eloquent model implementing owen-it's `Audit` contract is accepted as `config('audit.implementation')` (page, slide-over and transitions) — not only a subclass of the default `Audit` model
* `blackbox.authorize_without_policy` config (default `true`, the previous behaviour): what `blackboxCheck()` returns for a model that has no policy
* Relation timelines fall back to an `auditRelations()` method on the model when `blackbox.resources.<Model>.relations` is absent, and accept indexed entries (`['rel']`) as well as associative ones (`['rel' => 'nested']`)
* `restored` in the event filter
* Pest tests with real owen-it audits for exclusion filtering (display, Revert/Recover, visible allowlist keys missing from the model, excluded legacy values under newer encrypted casts, non scalar values with `audit.allowed_array_values`, mutators that dirty excluded fields, non-strict `$auditExclude` protection, `$auditInclude` allowlists, memo cache hits), user labels and search (including non-`users` providers, case-insensitive search asserted in the generated SQL, escaped wildcards, key ordering, one query per model in `labels()`), relation timelines (including a non-callable `auditRelations()`), the event and date filters (including fractional-second timestamps, renamed audit timestamp columns, day-granular values and unparsable filter values) and the policy fallback

### Changed

* Non-strict models follow owen-it exactly: `$hidden` counts only in strict mode (as owen-it's own `resolveAuditExclusions()` does) — Blackbox deliberately adds no stricter `$hidden` rule. Protect secrets on non-strict models with `$auditExclude` / `audit.exclude` instead; documented in the README, with a test proving a non-strict model that excludes `password` never shows or restores it from a legacy row

### Fixed

* Removed the leftover `tooltip('saya tooltip')` from `BlackboxAction`
* `created_until` now includes the whole end day, including fractional-second timestamps such as 23:59:59.500
* Excluded legacy values are filtered out BEFORE owen-it's `getModified()` formats them, so a plain value stored under a newer `encrypted` cast (or any incompatible accessor) can no longer break the timeline
* The exclusion mirror now also covers owen-it's non scalar current-value rule, including `audit.allowed_array_values`
* A Revert/Recover that would touch protected fields reports a generic refusal ("This change can't be reverted because it would modify protected fields."); the protected attribute names and values stay on the `ProtectedAttributeTransitionException` for logs and never reach the preview or the notification
* Filtered audit copies carry the audit model's real timestamp columns (`getCreatedAtColumn()`/`getUpdatedAtColumn()`, null-safe when a model reports none), not hardcoded `created_at`/`updated_at`, so audit implementations with renamed timestamp columns keep working
* Every remaining hardcoded `created_at` is gone: the page header, the slide-over header, the audit item timestamp, the `created_from`/`created_until` filters and both timelines' ordering all use the audit model's own created-at column via one null-safe helper, falling back to the primary key when a model reports none
* An `AuditableTransitionException` from owen-it's `transitionTo()` now reports a generic "This audit can't be applied to the current record." in the preview and notification; the real message (which can name attributes) and the incompatible keys go to the log. The same generic-plus-log treatment applies to the protected-attribute refusal
* A `$auditInclude` model hides and never restores keys outside its include list, on old rows too
* The `auditRelations()` fallback requires a PUBLIC `auditRelations()` method, so an inaccessible (e.g. protected) one is treated as absent instead of fataling through `Model::__call`
* The default user search is case-insensitive on every database driver (lowered on BOTH sides of the comparison — a plain `like` is case-sensitive on PostgreSQL), and with no searchable columns only a key-compatible term is looked up by key
* The user filter's `labels()` resolves the selected users with one query per user model, not one per id
* The date filters parse defensively: an unparsable (e.g. tampered) `created_from`/`created_until` value is ignored instead of throwing a 500, and the filters are documented as day-granular (a time component is not applied)
* The default user search escapes `LIKE` wildcards (a `%` or `_` in the term matches literally), orders results by key and lists the user table's columns once per model per search instead of one `hasColumn` probe per configured column
* The owen-it exclusion rules are memoised per auditable model instance (a fingerprint of the model's attributes and rule inputs), so a timeline no longer recomputes them per row; a `transitionTo()` invalidates the memo

## v0.0.11 - 2026-11-17

### Changed

* Support Filament v5.11 (`filament/filament` constraint raised to `^5.11`)

### Added

* Integration tests that render the Blackbox audit page (including event and user filters) and open the Blackbox slide-over action for an audited record under Filament 5.11

### Fixed

* Dev Docker image: clear the base image entrypoint (it ran `php artisan optimize`, which does not exist in a package) and install `pcov` so `pest` no longer exits 1 on "No code coverage driver available"
* phpstan: drop a redundant `?? []` on the non-nullable `Blackbox::$data` property
* Pint: import `Livewire` and `Audit` instead of using inline fully qualified names

## v0.0.10 - 2026-02-20

### What's Changed

* 8 support soft delete feature by @redfieldchristabel in https://github.com/redfieldchristabel/filament-blackbox/pull/9

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.9...v0.0.10

## v0.0.9 - 2026-02-19

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.8...v0.0.9

## v0.0.8 - 2026-02-19

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.7...v0.0.8

## v0.0.7 - 2026-02-19

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.6...v0.0.7

## v0.0.6 - 2026-02-19

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.5...v0.0.6

## v0.0.5 - 2026-02-19

### What's Changed

* Fix the issue where some audit and main audit cannot be open like showing 500  error

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.3...v0.0.5

## v0.0.4 - 2026-02-19

### What's Changed

* chore(deps): bump actions/checkout from 4 to 6 by @dependabot[bot] in https://github.com/redfieldchristabel/filament-blackbox/pull/6
* chore(deps): bump stefanzweifel/git-auto-commit-action from 5 to 7 by @dependabot[bot] in https://github.com/redfieldchristabel/filament-blackbox/pull/7

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.3...v0.0.4

## v0.0.3 - 2026-02-16

### What's Changed

* Remove the Livewire v4 bolt icon in the filename, as the composer doesn't support the icon in the filename out of the box

## v0.0.2 - 2026-02-16

### What's Changed

* Suppoer prebuilt tailwind css by @redfieldchristabel in https://github.com/redfieldchristabel/filament-blackbox/pull/5

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/compare/v0.0.1...v0.0.2

## v0.0.1 - Initial Beta Release - 2026-02-15

### What's Changed

* Bump aglipanci/laravel-pint-action from 2.4 to 2.6 by @dependabot[bot] in https://github.com/redfieldchristabel/filament-blackbox/pull/1
* Bump dependabot/fetch-metadata from 1.6.0 to 2.5.0 by @dependabot[bot] in https://github.com/redfieldchristabel/filament-blackbox/pull/2
* Initialize by @redfieldchristabel in https://github.com/redfieldchristabel/filament-blackbox/pull/3
* Blackbox action by @redfieldchristabel in https://github.com/redfieldchristabel/filament-blackbox/pull/4

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/redfieldchristabel/filament-blackbox/pull/1
* @redfieldchristabel made their first contribution in https://github.com/redfieldchristabel/filament-blackbox/pull/3

**Full Changelog**: https://github.com/redfieldchristabel/filament-blackbox/commits/v0.0.1

## 1.0.0 - 202X-XX-XX

- initial release
