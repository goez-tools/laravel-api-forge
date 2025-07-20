<?php

namespace App\Commands;

use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use LaravelZero\Framework\Commands\Command;

class SelfUpdateCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'self-update
                            {--check : Only check for updates, do not update}
                            {--rollback : Rollback to the previous version}
                            {--force : Force update even if versions match}
                            {--pre-release : Include pre-release versions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the application to the latest version from GitHub';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if running in PHAR environment
        if (! \Phar::running()) {
            $this->error('This command can only be used when running from a PHAR build.');
            return self::FAILURE;
        }

        if ($this->option('rollback')) {
            return $this->rollback();
        }

        if ($this->option('check')) {
            return $this->checkForUpdates();
        }

        return $this->update();
    }

    /**
     * Update the application.
     */
    protected function update(): int
    {
        $this->info('🔍 Checking for updates...');

        try {
            $updater = $this->createUpdater();
            $result = $updater->update();

            if ($result) {
                $newVersion = $updater->getNewVersion();
                $oldVersion = $updater->getOldVersion();

                $this->info("✅ Successfully updated from <comment>{$oldVersion}</comment> to <comment>{$newVersion}</comment>!");
                $this->line('');
                $this->line('🔥 <comment>What\'s new in this version:</comment>');
                $this->line("   Visit: <href=https://github.com/goez-tools/laravel-api-forge/releases/tag/{$newVersion}>GitHub Releases</>");
                $this->line('');
                $this->info('💡 <comment>Tip:</comment> You can roll back using <info>self-update --rollback</info> if needed.');

            } else {
                $this->info('✨ You already have the latest version installed!');

                if ($this->option('force')) {
                    $this->line('');
                    $this->comment('🔧 Force update requested...');
                    $this->forcedUpdate();
                }

            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Update failed: {$e->getMessage()}");
            $this->line('');
            $this->line('💡 <comment>Try:</comment>');
            $this->line('   • Check your internet connection');
            $this->line('   • Visit GitHub releases manually: <href=https://github.com/goez-tools/laravel-api-forge/releases>releases page</>');

            return self::FAILURE;
        }
    }

    /**
     * Check for available updates.
     */
    protected function checkForUpdates(): int
    {
        $this->info('🔍 Checking for available updates...');

        try {
            $updater = $this->createUpdater();
            $hasUpdate = $updater->hasUpdate();

            if ($hasUpdate) {
                $currentVersion = $this->getCurrentVersion();
                $newVersion = $updater->getNewVersion();

                $this->line('');
                $this->info("🎉 A new version is available!");
                $this->line("   Current version: <comment>{$currentVersion}</comment>");
                $this->line("   Latest version:  <comment>{$newVersion}</comment>");
                $this->line('');
                $this->line('🚀 Run <info>self-update</info> to update to the latest version.');
                $this->line("📝 Release notes: <href=https://github.com/goez-tools/laravel-api-forge/releases/tag/{$newVersion}>GitHub</>");

                return self::SUCCESS;
            } else {
                $currentVersion = $this->getCurrentVersion();
                $this->info("✅ You have the latest version installed: <comment>{$currentVersion}</comment>");

                return self::SUCCESS;
            }
        } catch (\Exception $e) {
            $this->error("❌ Failed to check for updates: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Rollback to the previous version.
     */
    protected function rollback(): int
    {
        $this->info('🔙 Rolling back to the previous version...');

        try {
            $updater = $this->createUpdater();
            $result = $updater->rollback();

            if ($result) {
                $this->info('✅ Successfully rolled back to the previous version!');
                $this->line('');
                $this->comment('💡 You can update again using <info>self-update</info> command.');

                return self::SUCCESS;
            } else {
                $this->error('❌ Rollback failed. No backup version found.');
                $this->line('');
                $this->comment('💡 Backup versions are created automatically when you update.');

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ Rollback failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Force an update even if versions match.
     */
    protected function forcedUpdate(): void
    {
        try {
            $updater = $this->createUpdater();

            // Force download latest version
            $strategy = $updater->getStrategy();
            $pharPath = \Phar::running(false);
            $backupPath = $pharPath . '-old.phar';

            // Create backup
            if (file_exists($pharPath)) {
                copy($pharPath, $backupPath);
            }

            // Download and replace file
            $remoteVersion = $strategy->getCurrentRemoteVersion($updater);
            if ($remoteVersion) {
                $this->info("🔄 Force downloading version: <comment>{$remoteVersion}</comment>");
                $updater->update();
                $this->info('✅ Force update completed!');
            }
        } catch (\Exception $e) {
            $this->error("❌ Force update failed: {$e->getMessage()}");
        }
    }

    /**
     * Create and configure the updater.
     */
    protected function createUpdater(): Updater
    {
        $pharPath = \Phar::running(false);
        $updater = new Updater($pharPath, false);

        // Set GitHub strategy
        $updater->setStrategy(Updater::STRATEGY_GITHUB);
        /** @var GithubStrategy $strategy */
        $strategy = $updater->getStrategy();

        // Configure GitHub Strategy API settings
        $strategy->setPackageName('goez/laravel-api-forge');
        $strategy->setPharName(basename($pharPath));
        $strategy->setCurrentLocalVersion($this->getCurrentVersion());

        // Set stability
        if ($this->option('pre-release')) {
            $strategy->setStability('any');
        } else {
            $strategy->setStability('stable');
        }

        return $updater;
    }

    /**
     * Get the current application version.
     */
    protected function getCurrentVersion(): string
    {
        return config('app.version', 'unknown');
    }
}
