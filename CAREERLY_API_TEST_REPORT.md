# CareerLy MySQL Integration and Route Refactor Report

Audit date: 2026-10-10

## Summary

The current MySQL/routes verification completed successfully. The project uses a dedicated `careerly_test` MySQL schema, a fail-closed test database guard, and domain-organized API route files. The final full suite passed on MySQL 8.0.30: **40 tests, 267 assertions, 0 failures, 0 errors, 0 skipped**.

The development schema `careerly` was not used for migrations or tests. Its post-run table-name/count/size snapshot exactly matches the pre-test snapshot: **16 tables, 416 KB**. Test fixture tables are empty after test cleanup. No row-count/checksum snapshot was captured before testing, so the comparison is limited to schema metadata; isolation is additionally established by the explicit `careerly_test` connection and runtime database-name guard.

## Audit and Environment

- Laravel Framework `12.69.3`; PHP `8.3.16`; Composer `2.8.6`.
- MySQL server `8.0.30`.
- Normal `.env` connection is MySQL database `careerly`. Credentials are not included here.
- A separate schema named `careerly_test` was created after confirming it did not exist and the configured account had `CREATE` permission. The schemas are distinct.
- `config/database.php` defines `mysql_testing` using `TEST_DB_*` variables. `phpunit.xml` forces the test connection and `careerly_test`; it does not fall back to SQLite or the development database.
- `tests/TestCase.php` rejects testing unless the selected connection is MySQL, its configured database is exactly `careerly_test`, required test host/user settings exist, the URL is empty, and `SELECT DATABASE()` physically returns `careerly_test`. The check runs before `RefreshDatabase` can migrate.
- A deliberate run without test credentials failed closed with zero assertions before database access or migrations.
- Test commands received DB settings through temporary process environment values read from `.env`; those values were restored after each command and were not printed or committed.

## Database and Migrations

All **14 migrations** ran successfully against the initially empty `careerly_test` database, including `2026_10_10_000001_create_job_applications_table`. `php artisan migrate:status --database=mysql_testing` reported all migrations as `Ran`.

MySQL `information_schema` verification confirmed:

- `job_applications.job_post_id` references `job_posts.id`; `job_seeker_id` references `users.id`.
- Both foreign keys use `ON DELETE CASCADE`; cascade behavior was exercised with test-created records.
- A unique composite index exists on `(job_post_id, job_seeker_id)`; a duplicate insert was rejected.
- `job_applications.status` is non-null `varchar(255)` with default `pending`.
- Employer and job-seeker profile `user_id` columns retain their unique indexes and user foreign keys.
- `job_posts.expires_at` is nullable MySQL `TIMESTAMP`; expiry inclusion/exclusion behavior passed through MySQL-backed feature tests.
- MySQL reported session/global time zones as `SYSTEM`; exact boundary behavior can depend on the server timezone. Past/future expiry cases were verified, but a cross-timezone deployment comparison was not performed.

No existing migration was modified. No schema reset or destructive command was run against `careerly`. `RefreshDatabase` ran only on the explicitly selected `careerly_test` connection.

## Route Organization

The route entry point is `routes/api.php`. It requires these domain files:

- `routes/api/auth.php`: registration, login, logout.
- `routes/api/job-posts.php`: public listing/details and employer job-post CRUD.
- `routes/api/job-applications.php`: job-seeker submission and employer application management.
- `routes/api/profiles.php`: employer and job-seeker profile endpoints.
- `routes/api/admin.php`: category and skill administration.

The health route remains in `routes/api.php`. Before/after comparison used `php artisan route:list --path=api --json`: **28 routes before and 28 after; 0 method/URI/name differences**. `php artisan route:list --path=api` and JSON output both ran. The final list has no duplicate method/path pairs and no `/api/api/` prefixes. Controller actions, route names, and middleware were retained; public job reads are public, employer routes remain employer-only, application submission is job-seeker-only, profiles require Sanctum, and category/skill routes remain admin-only.

## Test Results

All final test commands ran on `mysql_testing` / `careerly_test`.

| Command | Result |
|---|---|
| `php artisan test tests/Feature/CareerLyApiSecurityTest.php` | **27 passed, 192 assertions** |
| `php artisan test tests/Feature/ApiAuthorizationTest.php` | **11 passed, 73 assertions** |
| `php artisan test` | **40 passed, 267 assertions, 0 failures, 0 errors, 0 skipped** |
| `php artisan migrate:status --database=mysql_testing` | All 14 migrations marked `Ran` |
| `php artisan route:list --path=api --json` comparison | 28/28 route keys match; no duplicates or doubled prefixes |

Feature coverage includes current-token logout and preservation of another token, roles/ownership, profile validation and privacy, application uniqueness and status transitions, MySQL indexes/foreign keys/cascades, closed/expired job visibility, filters, pagination, and invalid search parameters. The Laravel starter unit and feature tests also ran as part of the full suite.

## Files Changed

Modified:

- `config/database.php`
- `phpunit.xml`
- `tests/TestCase.php`
- `routes/api.php`
- `tests/Feature/CareerLyApiSecurityTest.php`
- `CAREERLY_API_TEST_REPORT.md`

Created:

- `routes/api/auth.php`
- `routes/api/job-posts.php`
- `routes/api/job-applications.php`
- `routes/api/profiles.php`
- `routes/api/admin.php`

Existing application behavior, migrations, and API contracts were not otherwise changed by this MySQL/route-organization follow-up.

## Development Database Snapshot

The pre-test `careerly` baseline had 16 tables and 416 KB total. The post-test, read-only snapshot has the same table count, names, and rounded per-table sizes, totaling 416 KB. All migration and test commands explicitly selected `mysql_testing`; the runtime guard independently queried the physical selected schema. `careerly_test` has 17 tables and zero users, job posts, or job applications after cleanup.

This verifies the recorded schema metadata is unchanged and provides evidence tests did not target the development database. Since pre-test row counts or checksums were not recorded, it does not constitute a row-by-row content comparison.

## Remaining Risks

- MySQL timezone behavior at the exact `expires_at` boundary should be confirmed for the intended deployment timezone.
- Cookie-based Sanctum SPA behavior and live HTTP/Postman behavior were not tested; automated feature tests use Laravel's application-level client.
- Production readiness, concurrency/load behavior beyond the unique-constraint protection, and deployment configuration were not assessed.
