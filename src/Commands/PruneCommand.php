<?php

declare(strict_types=1);

namespace Xve\DbExport\Commands;

use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'db:export:prune';

    protected $description = 'Delete all database exports';

    public function handle(): int
    {
        /** @var string $path */
        $path = config('db-export.default_path', storage_path('app/db-exports'));

        if (! is_dir($path)) {
            $this->info('No export directory found.');

            return self::SUCCESS;
        }

        $files = glob($path.'/*.sql*') ?: [];

        if ($files === []) {
            $this->info('No export files found.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        $this->info(sprintf('Deleted %d export file(s).', $deleted));

        return self::SUCCESS;
    }
}
