# Explorin Laravel Log Inspector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/explorin/laravel-log-inspector.svg?style=flat-square)](https://packagist.org/packages/explorin/laravel-log-inspector)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/explorin/laravel-log-inspector/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/explorin/laravel-log-inspector/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/explorin/laravel-log-inspector.svg?style=flat-square)](https://packagist.org/packages/explorin/laravel-log-inspector)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

A production-ready, memory-efficient Laravel log reader, search engine, and Blade dashboard. Reads multi-gigabyte log files in reverse chronological order without loading whole files into memory, builds incremental byte-offset sidecar indices, masks sensitive data, and provides an interactive UI without third-party CDN dependencies.

---

## Features

- **⚡ Zero-Memory Overhead**: Implements `ReverseChunkReader` (64 KB reverse block reading via `fseek()`). No `file_get_contents()` or `file()` calls.
- **📑 Incremental Sidecar Indexing**: Maps entry positions, timestamps, levels, channels, and exception hashes into a compact index. Detects log rotation and truncation automatically.
- **🛡️ Enterprise Security**:
  - `PathGuard` prevents path traversal, symlink breakout, null-byte injection, and unauthorized file access.
  - **Active Log Protection**: Active files (e.g. today's daily log or `laravel.log`) cannot be deleted.
  - **Pre-Ingestion Masking**: Passwords, tokens, Bearer headers, and secrets are scrubbed before indexing or displaying.
  - **Deny-by-Default Gates**: Host application must explicitly define Gates (`view-log-inspector`, `download-log-inspector`, `download-raw-log-inspector`, `delete-log-inspector`).
  - **Audit Logging**: All downloads and deletions are recorded to an independent audit channel.
- **📊 Interactive Blade Dashboard**:
  - Bundled Bootstrap 5 assets (no external CDN, safe for internal networks and strict CSP).
  - Exception Inspector grouping identical exceptions by signature hash.
  - Literal keyword search with budget-bounded streaming limits (MB and timeout controls).
- **🛠️ Artisan Tooling**:
  - `php artisan log-inspector:retention --days=30` (Dry-run by default, requires `--force` for actual deletion).
  - `php artisan log-inspector:archive <file>` (Compresses inactive logs into `.gz`).
  - `php artisan log-inspector:reindex` (Rebuilds sidecar offset indices).

---

## Requirements & Compatibility Matrix

| PHP Version | Laravel 10.x | Laravel 11.x | Laravel 12.x |
|:-----------:|:------------:|:------------:|:------------:|
| **PHP 8.1** | ✅ Supported  | —            | —            |
| **PHP 8.2** | ✅ Supported  | ✅ Supported  | ✅ Supported  |
| **PHP 8.3** | ✅ Supported  | ✅ Supported  | ✅ Supported  |
| **PHP 8.4** | ✅ Supported  | ✅ Supported  | ✅ Supported  |

---

## Installation

Install the package via Composer:

```bash
composer require explorin/laravel-log-inspector
```

Publish configuration and dashboard assets:

```bash
php artisan vendor:publish --tag=log-inspector-config
php artisan vendor:publish --tag=log-inspector-assets
```

---

## Authorization & Gates Configuration

By design, **all access is denied by default**. You must define the authorization Gates in your `AppServiceProvider` or `AuthServiceProvider`:

```php
namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // View Dashboard and read logs
        Gate::define('view-log-inspector', function (?User $user) {
            return $user !== null && $user->is_admin;
        });

        // Download masked log files
        Gate::define('download-log-inspector', function (?User $user) {
            return $user !== null && $user->is_admin;
        });

        // Download raw/unmasked log files (Sensitive)
        Gate::define('download-raw-log-inspector', function (?User $user) {
            return $user !== null && $user->is_super_admin;
        });

        // Delete inactive log files
        Gate::define('delete-log-inspector', function (?User $user) {
            return $user !== null && $user->is_super_admin;
        });
    }
}
```

Enable the inspector in your `.env`:

```env
LOG_INSPECTOR_ENABLED=true
```

Access the dashboard in your browser: `https://your-domain.test/log-inspector`.

---

## Programmatic API Reference

Use the `LogInspector` Facade to query and analyze logs programmatically:

```php
use Explorin\LogInspector\Facades\LogInspector;
use Explorin\LogInspector\Enums\LogLevel;

// 1. List detected log files
$files = LogInspector::files();

// 2. Query logs with fluent builder
$entries = LogInspector::query()
    ->file('laravel.log')
    ->level(LogLevel::ERROR)
    ->dateRange('2026-11-01', '2026-11-17')
    ->search('Allowed memory size')
    ->get(limit: 50);

// 3. Offset-based Deep Pagination (Instant hydration via fseek)
$page = LogInspector::query()
    ->file('laravel-2026-11-17.log')
    ->channel('production')
    ->paginate(perPage: 50, page: 10);

// 4. Memory-safe Lazy Cursor (Generator)
foreach (LogInspector::query()->file('laravel.log')->cursor() as $entry) {
    echo $entry->message . PHP_EOL;
}

// 5. Aggregate Statistics & Top Exceptions
$stats = LogInspector::statistics('laravel.log');
```

---

## Performance & Benchmark

A standalone benchmark script is included to verify execution speed and zero memory growth:

```bash
php benchmark.php
```

### Benchmark Results (10,000 Entries / 1.22 MB):
- **Indexing Throughput**: ~200,000 entries/second (Indexed 10,000 entries in **49.5 ms**).
- **RAM Peak Delta**: **0 MB** (strictly bounded streaming).
- **Deep Pagination (Page 50)**: Hydrated 50 records in **7.1 ms** via `fseek()`.

---

## Artisan Commands

### Retention Cleaner
Safely deletes inactive log files older than a specified number of days:

```bash
# Dry run: Preview files to be deleted
php artisan log-inspector:retention --days=30

# Force deletion with confirmation
php artisan log-inspector:retention --days=30 --force
```

### Archive Logs (.gz)
Compresses old files to save disk space:

```bash
php artisan log-inspector:archive laravel-2026-10-01.log
```

### Rebuild Offset Indices
Rebuilds or refreshes sidecar indices:

```bash
php artisan log-inspector:reindex
```

---

## Security Model

1. **Path Traversal Protection**: Inputs are resolved via `realpath()` and checked against whitelisted directories. Symlink escapes and directory traversal sequences (`..`, null bytes) trigger a `SecurityException`.
2. **Active File Protection**: The active log currently being written to is protected from deletion and archiving.
3. **Data Masking**: Secrets, passwords, bearer tokens, and credit card numbers are scrubbed pre-indexing. Raw downloads are gated under a dedicated permission.
4. **XSS Protection**: Blade views strictly use `{{ }}` escaping on all log fields. Raw `{!! !!}` is never used.
5. **Denial of Service (ReDoS / OOM)**: Literal string searching (`stripos`) is used instead of unbounded regex. Time and byte scan budgets guarantee predictable response times.

---

## Testing & Quality

Run the test suite:

```bash
vendor/bin/phpunit
```

Run code formatting and static analysis:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
