# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-02-05

### Added
- Initial release
- Profile-based exports (default, clean, minimal, schema, inspection, anonymized)
- Zero-impact exports using `--single-transaction`, `--quick`, `--skip-lock-tables`
- Automatic gzip compression with streaming for large databases
- Pre-flight disk space validation
- Structure-only tables with `--include-data` override
- Data anonymization with multiple strategies (faker, mask, hash, null, fixed)
- Built-in fallbacks when faker is not installed
- `preserve_rows` config to skip anonymization for specific email domains
- Automatic cleanup of old exports
- `db:export:setup` command to find mysqldump binary path
- MariaDB and MySQL compatibility
- Artisan commands: `db:export`, `db:export:estimate`, `db:export:list-profiles`, `db:export:setup`
