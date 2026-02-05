<?php

declare(strict_types=1);

namespace Xve\DbExport;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Xve\DbExport\Commands\EstimateCommand;
use Xve\DbExport\Commands\ExportCommand;
use Xve\DbExport\Commands\ListProfilesCommand;
use Xve\DbExport\Commands\SetupCommand;
use Xve\DbExport\Config\ProfileManager;
use Xve\DbExport\Contracts\AnonymizerInterface;
use Xve\DbExport\Contracts\DiskCheckerInterface;
use Xve\DbExport\Contracts\ExporterInterface;
use Xve\DbExport\Contracts\SizeEstimatorInterface;
use Xve\DbExport\Contracts\TableResolverInterface;

class DbExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/db-export.php',
            'db-export'
        );

        $this->app->singleton(ProfileManager::class, function (Application $app): ProfileManager {
            /** @var \Illuminate\Config\Repository $config */
            $config = $app->make('config');
            /** @var array<string, array<string, mixed>> $profiles */
            $profiles = $config->get('db-export.profiles', []);

            return new ProfileManager($profiles);
        });

        $this->app->singleton(DbExportManager::class, fn (Application $app): DbExportManager => new DbExportManager($app));

        $this->app->alias(DbExportManager::class, 'db-export');

        $this->registerContracts();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/db-export.php' => config_path('db-export.php'),
            ], 'db-export-config');

            $this->commands([
                ExportCommand::class,
                EstimateCommand::class,
                ListProfilesCommand::class,
                SetupCommand::class,
            ]);
        }
    }

    protected function registerContracts(): void
    {
        $this->app->bind(TableResolverInterface::class, function (Application $app): Actions\Tables\ResolveTablesAction {
            /** @var \Illuminate\Database\DatabaseManager $db */
            $db = $app->make('db');

            return new Actions\Tables\ResolveTablesAction(
                $db,
                $app->make(Actions\Tables\ExpandWildcardsAction::class)
            );
        });

        $this->app->bind(SizeEstimatorInterface::class, function (Application $app): Actions\Estimation\EstimateSizeAction {
            /** @var \Illuminate\Database\DatabaseManager $db */
            $db = $app->make('db');

            return new Actions\Estimation\EstimateSizeAction($db);
        });

        $this->app->bind(DiskCheckerInterface::class, function (Application $app): Actions\Estimation\CheckDiskSpaceAction {
            /** @var \Illuminate\Config\Repository $config */
            $config = $app->make('config');
            /** @var array<string, mixed> $diskCheck */
            $diskCheck = $config->get('db-export.disk_check', []);

            return new Actions\Estimation\CheckDiskSpaceAction($diskCheck);
        });

        $this->app->bind(AnonymizerInterface::class, fn (Application $app): Actions\Anonymization\AnonymizeTableAction => new Actions\Anonymization\AnonymizeTableAction(
            $app->make(Actions\Anonymization\LoadAnonymizationRulesAction::class)
        ));

        $this->app->bind(ExporterInterface::class, fn (Application $app): Actions\Export\ExecuteExportAction => new Actions\Export\ExecuteExportAction(
            $app->make(Actions\Export\BuildDumperAction::class),
            $app->make(Actions\Export\CompressExportAction::class),
            $app->make(Actions\Export\WrapWithForeignKeysAction::class),
            $app->make(Actions\Export\ExportAnonymizedTableAction::class)
        ));
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            DbExportManager::class,
            'db-export',
            ProfileManager::class,
            TableResolverInterface::class,
            SizeEstimatorInterface::class,
            DiskCheckerInterface::class,
            AnonymizerInterface::class,
            ExporterInterface::class,
        ];
    }
}
