<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Export Path
    |--------------------------------------------------------------------------
    |
    | The default path where database exports will be saved. This can be
    | overridden on a per-export basis.
    |
    */
    'default_path' => storage_path('app/db-exports'),

    /*
    |--------------------------------------------------------------------------
    | Compression Settings
    |--------------------------------------------------------------------------
    |
    | Configure compression for database exports.
    |
    */
    'compression' => [
        'enabled' => true,
        'level' => 6, // 1-9, higher = more compression but slower
    ],

    /*
    |--------------------------------------------------------------------------
    | MySQL Dump Options
    |--------------------------------------------------------------------------
    |
    | Options passed to mysqldump for zero-impact exports.
    |
    | Note: 'dump_binary_path' can be set if mysqldump is not in your PATH.
    | Example: '/usr/local/mysql/bin' or '/opt/homebrew/bin'
    |
    | The 'set_gtid_purged' and 'column_statistics' options are MySQL-specific.
    | Set them to null for MariaDB compatibility.
    |
    */
    'mysql_options' => [
        'dump_binary_path' => env('DB_EXPORT_DUMP_BINARY_PATH'),
        'single_transaction' => true,
        'quick' => true,
        'skip_lock_tables' => true,
        'set_gtid_purged' => null, // Set to 'OFF' for MySQL with GTID
        'column_statistics' => null, // Set to false for MySQL 8+
        'routines' => true,
        'triggers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign Key Handling
    |--------------------------------------------------------------------------
    |
    | Wrap exports with FK disable/enable statements.
    |
    */
    'foreign_keys' => [
        'disable_during_import' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | View Handling
    |--------------------------------------------------------------------------
    |
    | Configure how database views are handled during export.
    |
    */
    'views' => [
        'include' => true,
        'definer' => 'strip', // 'strip', 'keep', or 'replace'
        'replace_with' => 'CURRENT_USER',
    ],

    /*
    |--------------------------------------------------------------------------
    | Disk Space Check
    |--------------------------------------------------------------------------
    |
    | Pre-flight disk space validation settings.
    |
    */
    'disk_check' => [
        'enabled' => true,
        'safety_margin' => 1.5, // Require 1.5x estimated size as free space
        'minimum_free_mb' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Automatically clean up old export files before creating new ones.
    |
    */
    'cleanup' => [
        'enabled' => true,
        'keep_recent' => 0, // Number of recent exports to keep (0 = delete all)
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Profiles
    |--------------------------------------------------------------------------
    |
    | Define reusable export profiles. Each profile can specify tables to
    | exclude, tables to export structure-only, and anonymization rules.
    |
    */
    'profiles' => [
        'default' => [
            'description' => 'Full database export with no exclusions',
            'exclude' => [],
            'structure_only' => [],
            'include_only' => null, // null means all tables
            'anonymize' => [],
        ],

        'clean' => [
            'description' => 'Export without logs, sessions, and cache tables',
            'exclude' => [
                'telescope_*',
                'pulse_*',
                'sessions',
                'cache',
                'cache_locks',
                'jobs',
                'job_batches',
                'failed_jobs',
                '*_logs',
                'password_reset_tokens',
                'personal_access_tokens',
            ],
            'structure_only' => [
                'audits', // Large audit log - use --include-data=audits to include
            ],
            'include_only' => null,
            'anonymize' => [],
        ],

        'inspection' => [
            'description' => 'Export only telescope and audit data for debugging',
            'exclude' => [],
            'structure_only' => [],
            'include_only' => [
                'telescope_*',
                'audits',
            ],
            'anonymize' => [],
        ],

        'minimal' => [
            'description' => 'Minimal export with structure-only for large tables',
            'exclude' => [
                'telescope_*',
                'pulse_*',
                'sessions',
                'cache',
                'cache_locks',
                'jobs',
                'job_batches',
                'failed_jobs',
                '*_logs',
            ],
            'structure_only' => [
                'activity_log',
                'notifications',
                'audits',
            ],
            'include_only' => null,
            'anonymize' => [],
        ],

        'schema' => [
            'description' => 'Structure-only export (no data)',
            'exclude' => [],
            'structure_only' => ['*'],
            'include_only' => null,
            'anonymize' => [],
        ],

        'anonymized' => [
            'description' => 'Clean export with anonymized PII data',
            'exclude' => [
                'telescope_*',
                'pulse_*',
                'sessions',
                'cache',
                'cache_locks',
                'jobs',
                'job_batches',
                'failed_jobs',
                '*_logs',
                'password_reset_tokens',
                'personal_access_tokens',
            ],
            'structure_only' => [],
            'include_only' => null,
            'anonymize' => [
                'users' => [
                    'name' => ['strategy' => 'faker', 'method' => 'name'],
                    'email' => ['strategy' => 'faker', 'method' => 'safeEmail'],
                    'password' => ['strategy' => 'hash', 'value' => 'password'],
                    'phone' => ['strategy' => 'faker', 'method' => 'phoneNumber'],
                    'remember_token' => ['strategy' => 'null'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Anonymization Rules
    |--------------------------------------------------------------------------
    |
    | Anonymization rules that apply across all profiles unless overridden.
    | Column names matching these patterns will be anonymized.
    |
    */
    'global_anonymization' => [
        // 'ip_address' => ['strategy' => 'faker', 'method' => 'ipv4'],
        // 'credit_card*' => ['strategy' => 'mask', 'char' => '*', 'keep_last' => 4],
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Exclusions
    |--------------------------------------------------------------------------
    |
    | Columns to exclude from exports (e.g., large BLOB/LONGTEXT columns).
    |
    */
    'exclude_columns' => [
        // 'table_name' => ['large_blob_column'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for exports. Null uses the default.
    |
    */
    'connection' => null,
];
