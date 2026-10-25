# Runtime regression checks

Run each `*-test.php` with `PACKAGE_AUTOLOAD` pointing at an installed Evolution CMS 3.6.x `core/vendor/autoload.php`. The test bootstrap loads this package from the checkout ahead of any consumer classmap. Fixtures use isolated SQLite databases and temporary files; no site database is used. The CI matrix executes PHP 8.4 and 8.5.

Shared entries use a five-minute TTL. Observed application writes invalidate immediately; writes made directly outside the application require a cache clear for immediate visibility. Transactions bypass shared entries and invalidate again after the outer commit/rollback.

Custom model scopes and retrieved observers keep the uncached database path, because they may depend on state not represented by the package cache key.
