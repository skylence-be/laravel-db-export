# Package Comparison

A SWOT analysis comparing Laravel database export packages.

## xve/laravel-db-export (this package)

| **Strengths** | **Weaknesses** |
|---------------|----------------|
| Zero-impact production exports (`--single-transaction`, `--quick`, `--skip-lock-tables`) | MySQL/MariaDB only |
| Data anonymization with multiple strategies (faker, mask, hash, null, fixed) | New package, less battle-tested |
| Faker optional with built-in fallbacks for production | No import command |
| Profile-based configuration | No cloud storage integration |
| Preserve rows by email domain (keep admin accounts) | |
| Pre-flight disk space validation | |
| Views, routines, triggers included | |
| Automatic cleanup of old exports | |

| **Opportunities** | **Threats** |
|-------------------|-------------|
| Add PostgreSQL support | spatie/laravel-backup is well-established |
| Add import command | corrivate/laravel-mysqldump is simpler alternative |
| Add scheduled export support | |
| Cloud storage (S3) integration | |

---

## spatie/laravel-backup

| **Strengths** | **Weaknesses** |
|---------------|----------------|
| Full backup solution (DB + files) | Complex configuration |
| Cloud storage (S3, GCS, etc.) | No data anonymization |
| Notifications (Slack, Mail) | Heavier dependency footprint |
| Scheduled backups | Overkill for DB-only exports |
| Multi-database support | No profile-based table exclusion |
| Well-maintained, popular | |

| **Opportunities** | **Threats** |
|-------------------|-------------|
| De facto standard for Laravel backups | Focused packages may be preferred |

---

## corrivate/laravel-mysqldump

| **Strengths** | **Weaknesses** |
|---------------|----------------|
| Simple, focused | No anonymization |
| Has import command | Config in database.php (not dedicated) |
| Strip tables feature | No production-safe options by default |
| Lightweight | No pre-flight validation |
| | No profiles system |

| **Opportunities** | **Threats** |
|-------------------|-------------|
| Could add anonymization | xve/laravel-db-export offers more features |

---

## nwidart/db-exporter & elimuswift/db-exporter

| **Strengths** | **Weaknesses** |
|---------------|----------------|
| Exports as migrations/seeders | Abandoned (Laravel 4/5 only) |
| Useful for DB versioning | Not for production backups |
| | No anonymization |
| | PHP 5.3/5.5 requirement |

---

## spatie/db-dumper (library)

| **Strengths** | **Weaknesses** |
|---------------|----------------|
| Low-level, flexible | Not a complete solution |
| Supports MySQL, PostgreSQL, SQLite, MongoDB | Requires wrapper code |
| Well-maintained | No Laravel integration |
| Used as dependency by this package | No anonymization |

---

## When to Use What

| Package | Use Case |
|---------|----------|
| **xve/laravel-db-export** | Production DB exports with anonymization, zero-impact, profile-based |
| **spatie/laravel-backup** | Full application backup (DB + files + cloud + notifications) |
| **corrivate/laravel-mysqldump** | Quick dump/import without anonymization needs |
| **spatie/db-dumper** | Building custom export solutions |
