<?php

declare(strict_types=1);

namespace Xve\DbExport\Commands;

use Illuminate\Console\Command;

class SetupCommand extends Command
{
    protected $signature = 'db:export:setup';

    protected $description = 'Find mysqldump binary and show configuration instructions';

    /**
     * @var array<int, string>
     */
    protected array $searchPaths = [
        '/usr/bin',
        '/usr/local/bin',
        '/usr/local/mysql/bin',
        '/opt/homebrew/bin',
        '/opt/homebrew/opt/mysql/bin',
        '/opt/homebrew/opt/mariadb/bin',
        '/Users/Shared/Herd/services/mysql/*/bin',
        '/Users/Shared/Herd/services/mariadb/*/bin',
        '/Applications/MAMP/Library/bin',
        '/Applications/XAMPP/xamppfiles/bin',
        'C:\\xampp\\mysql\\bin',
        'C:\\wamp64\\bin\\mysql\\*\\bin',
        'C:\\laragon\\bin\\mysql\\*\\bin',
    ];

    public function handle(): int
    {
        $this->info('Searching for mysqldump binary...');
        $this->newLine();

        $found = $this->findMysqldump();

        if ($found === []) {
            $this->error('mysqldump not found in common locations.');
            $this->newLine();
            $this->line('You can manually find it with:');
            $this->line('  <comment>which mysqldump</comment>');
            $this->line('  <comment>find /usr -name mysqldump 2>/dev/null</comment>');
            $this->line('  <comment>mdfind -name mysqldump</comment> (macOS)');

            return self::FAILURE;
        }

        $this->info('Found mysqldump:');
        $this->newLine();

        $rows = [];
        foreach ($found as $path => $version) {
            $rows[] = [$path, $version];
        }

        $this->table(['Path', 'Version'], $rows);

        $this->newLine();
        $this->info('Configuration:');
        $this->newLine();

        $firstPath = array_key_first($found);
        $dirPath = dirname((string) $firstPath);

        $this->line('Add to your <comment>.env</comment> file:');
        $this->newLine();
        $this->line(sprintf('  <fg=green>DB_EXPORT_DUMP_BINARY_PATH=%s</>', $dirPath));
        $this->newLine();

        $this->line('Or set in <comment>config/db-export.php</comment>:');
        $this->newLine();
        $this->line(sprintf("  <fg=green>'dump_binary_path' => '%s',</>", $dirPath));

        /** @var string|null $currentPath */
        $currentPath = config('db-export.mysql_options.dump_binary_path');
        if (! empty($currentPath)) {
            $this->newLine();
            $this->info('Current configuration:');
            $this->line('  '.$currentPath);

            if (file_exists($currentPath.'/mysqldump') || file_exists($currentPath.'/mysqldump.exe')) {
                $this->line('  <fg=green>OK - mysqldump found</>');
            } else {
                $this->line('  <fg=red>Warning - mysqldump not found at configured path</>');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    protected function findMysqldump(): array
    {
        $found = [];

        // Check PATH first
        $pathBinary = $this->findInPath();
        if ($pathBinary !== null) {
            $version = $this->getVersion($pathBinary);
            $found[$pathBinary] = $version;
        }

        // Check common locations
        foreach ($this->searchPaths as $searchPath) {
            $paths = $this->expandGlob($searchPath);
            foreach ($paths as $path) {
                $binary = $path.'/mysqldump';
                $binaryExe = $path.'/mysqldump.exe';

                if (file_exists($binary) && is_executable($binary)) {
                    if (! isset($found[$binary])) {
                        $found[$binary] = $this->getVersion($binary);
                    }
                } elseif (file_exists($binaryExe)) {
                    if (! isset($found[$binaryExe])) {
                        $found[$binaryExe] = $this->getVersion($binaryExe);
                    }
                }
            }
        }

        return $found;
    }

    protected function findInPath(): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'which mysqldump';
        $result = @exec($command, $output, $returnCode);

        if ($returnCode === 0 && ! in_array($result, ['', '0', false], true)) {
            return $result;
        }

        return null;
    }

    protected function getVersion(string $binary): string
    {
        $output = @exec($binary.' --version 2>&1', $lines, $returnCode);

        if ($returnCode === 0 && ! in_array($output, ['', '0', false], true)) {
            // Extract version from output like "mysqldump  Ver 10.11.6-MariaDB..."
            if (preg_match('/Ver\s+([^\s]+)/', $output, $matches)) {
                return $matches[1];
            }

            return $output;
        }

        return 'unknown';
    }

    /**
     * @return array<int, string>
     */
    protected function expandGlob(string $path): array
    {
        if (! str_contains($path, '*')) {
            return [$path];
        }

        $expanded = glob($path, GLOB_ONLYDIR);

        return $expanded !== false ? $expanded : [];
    }
}
