# Changelog

All notable changes to `explorin/laravel-log-inspector` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-17

### Added
- **Reverse Chunk Reader**: Block-by-block reverse file streaming (64 KB) using `fseek()` to eliminate high memory consumption.
- **Sidecar Offset Index**: Compact binary/JSON offset index tracking entry positions, timestamp, log level, channel, and exception signature hash.
- **Incremental Indexing**: Automatic index updates for new bytes appended to files, with detection of log rotation and file truncation.
- **Strict Data Masking**: Pre-ingestion regex scrubber filtering out secrets, passwords, Bearer tokens, API keys, and credit cards before indexing and query display.
- **Comprehensive Parsers**:
  - `LaravelLogParser` for Monolog LineFormatter (multiline stack traces, context JSON, exception classes).
  - `JsonLogParser` for Monolog JsonFormatter entries.
- **Security & Authorization**:
  - `PathGuard` verifying realpaths, whitelists, preventing null byte and symlink breakouts.
  - Active log file protection forbidding deletion of files currently being written to.
  - *Deny by default* Gate architecture (`view-log-inspector`, `download-log-inspector`, `download-raw-log-inspector`, `delete-log-inspector`).
  - Strict escaping in Blade views to eliminate XSS risks.
  - Audit logging for administrative file downloads and deletions.
- **Interactive Blade Dashboard**:
  - Bundled local Bootstrap 5 assets (no CDN dependency, CSP safe).
  - Realtime log filtering (level, literal keyword search, exception hash).
  - Frequent Exception Inspector widget grouping identical exceptions by signature hash.
  - Server-side offset pagination with partial scan warnings.
  - Masked and Raw download capabilities.
- **Console Commands**:
  - `log-inspector:retention`: Dry-run by default retention cleaner with `--force` confirmation.
  - `log-inspector:archive`: Compresses old log files into `.gz` format.
  - `log-inspector:reindex`: Rebuilds sidecar offset indices.
- **Performance Benchmark Script**: Validates sub-50ms indexing of 10,000 entries and deep pagination in <10ms with 0MB memory delta.
