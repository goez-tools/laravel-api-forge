<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Process\Process;

class NewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'new {name}
                           {--redis : Use Redis as cache store}
                           {--rbac : Install and configure RBAC package}
                           {--modules : Install and configure modular architecture}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Laravel API project with forge configuration';

    private string $projectName;

    private string $projectPath;

    private string $phpExecutable;

    private bool $useRedis = false;

    private bool $useRbac = false;

    private bool $useModules = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->projectName = $this->argument('name');
        $this->projectPath = getcwd().'/'.$this->projectName;

        // Check environment requirements
        if (! $this->checkEnvironment()) {
            return SymfonyCommand::FAILURE;
        }

        // Find PHP executable path
        $this->findPhpExecutable();

        // Get optional features
        $this->useRedis = $this->option('redis') ?: $this->confirm('Do you want to use Redis as cache store?', true);
        $this->useRbac = $this->option('rbac') ?: $this->confirm('Do you want to install RBAC package?', true);
        $this->useModules = $this->option('modules') ?: $this->confirm('Do you want to install modular architecture?', true);

        $this->info("Creating Laravel API project: {$this->projectName}");
        $this->displaySelectedFeatures();

        try {
            $this->createLaravelProject();
            $this->initializeGitRepo();

            $this->adjustTests();
            $this->commitStep('Adjust test configuration');

            $this->setupApiEnvironment();
            $this->commitStep('Setup API environment');

            if ($this->useRedis) {
                $this->setupRedisCache();
                $this->commitStep('Setup Redis cache');
            }

            if ($this->useRbac) {
                $this->setupRbac();
                $this->commitStep('Setup RBAC package');
            }

            if ($this->useModules) {
                $this->setupModules();
                $this->commitStep('Setup modular architecture');
            }

            $this->installLaravelData();
            $this->commitStep('Install Laravel Data package');

            $this->installSpectator();
            $this->commitStep('Install Spectator package');

            $this->setupSail();
            $this->commitStep('Setup Laravel Sail');

            $this->setupGitHooks();
            $this->commitStep('Setup Git hooks');

            if ($this->useRbac) {
                $this->finalizeRbac();
                $this->commitStep('Finalize RBAC configuration');
            }

            $this->finalizeSetup();

            $this->info("✅ Laravel API project '{$this->projectName}' has been created successfully!");
            $this->displayNextSteps();

        } catch (\Exception $e) {
            $this->error('❌ Error occurred: '.$e->getMessage());

            return SymfonyCommand::FAILURE;
        }

        return SymfonyCommand::SUCCESS;
    }

    private function displaySelectedFeatures(): void
    {
        $this->line('Selected features:');
        $this->line(($this->useRedis ? '✅' : '❌').' Redis Cache');
        $this->line(($this->useRbac ? '✅' : '❌').' RBAC Package');
        $this->line(($this->useModules ? '✅' : '❌').' Modular Architecture');
        $this->newLine();
    }

    private function createLaravelProject(): void
    {
        $this->task('Creating Laravel project', function () {
            $this->executeCommand([
                'laravel', 'new', $this->projectName,
                '--pest',
                '--no-interaction',
            ]);
        });
    }

    private function initializeGitRepo(): void
    {
        $this->task('Initializing Git repository', function () {
            chdir($this->projectPath);

            $this->executeCommand(['git', 'init']);

            // Set git config if available
            if ($email = $this->ask('Enter your git email (optional)')) {
                $this->executeCommand(['git', 'config', 'user.email', $email]);
            }
            if ($name = $this->ask('Enter your git name (optional)')) {
                $this->executeCommand(['git', 'config', 'user.name', $name]);
            }

            $this->executeCommand(['git', 'add', '.']);
            $this->executeCommand(['git', 'commit', '-m', 'Init commit']);
        });
    }

    private function setupGitHooks(): void
    {
        $this->task('Setting up Git hooks', function () {
            // Create .git-hooks directory
            File::makeDirectory($this->projectPath.'/.git-hooks', 0755, false, true);

            // Create sail-utils file
            $sailUtils = <<<'SHELL'
#!/bin/sh

check_and_start_sail() {
    local sail_output=$(./vendor/bin/sail ps 2>/dev/null || echo "")
    local line_count=$(echo "$sail_output" | wc -l | tr -d ' ')

    if [ "$line_count" -eq 1 ] || [ -z "$sail_output" ]; then
        echo "Starting Sail..."
        ./vendor/bin/sail up -d
        sleep 1
    fi
}
SHELL;
            File::put($this->projectPath.'/.git-hooks/sail-utils', $sailUtils);
            chmod($this->projectPath.'/.git-hooks/sail-utils', 0755);

            // Create pre-commit hook
            $preCommit = <<<'SHELL'
#!/bin/sh
set -e
. "$(dirname "$0")/sail-utils"
check_and_start_sail
./vendor/bin/sail composer lint -- --dirty
echo Committing as $(git config user.email)
SHELL;
            File::put($this->projectPath.'/.git-hooks/pre-commit', $preCommit);
            chmod($this->projectPath.'/.git-hooks/pre-commit', 0755);

            // Create pre-push hook
            $prePush = <<<'SHELL'
#!/bin/sh
set -e
. "$(dirname "$0")/sail-utils"
check_and_start_sail
./vendor/bin/sail composer test -- --parallel --processes=10
echo Pushing as $(git config user.email)
SHELL;
            File::put($this->projectPath.'/.git-hooks/pre-push', $prePush);
            chmod($this->projectPath.'/.git-hooks/pre-push', 0755);

            // Create post-merge hook
            $postMerge = <<<'SHELL'
#!/bin/sh
set -e
. "$(dirname "$0")/sail-utils"
check_and_start_sail
./vendor/bin/sail composer install --no-scripts
SHELL;
            File::put($this->projectPath.'/.git-hooks/post-merge', $postMerge);
            chmod($this->projectPath.'/.git-hooks/post-merge', 0755);

            // Update composer.json
            $this->updateComposerJson();
        });
    }

    private function updateComposerJson(): void
    {
        $composerFile = $this->projectPath.'/composer.json';
        $composer = json_decode(File::get($composerFile), true);

        // Update post-autoload-dump
        $composer['scripts']['post-autoload-dump'][] = 'git config --local core.hooksPath .git-hooks/ || exit 0';

        // Add lint script
        $composer['scripts']['lint'] = ['vendor/bin/pint'];

        File::put($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function setupRedisCache(): void
    {
        $this->task('Setting up Redis cache', function () {
            // Remove cache migration
            $cacheMigration = $this->projectPath.'/database/migrations/0001_01_01_000001_create_cache_table.php';
            if (File::exists($cacheMigration)) {
                File::delete($cacheMigration);
            }

            // Update .env and .env.example files
            $this->updateEnvFiles([
                'CACHE_STORE=database' => 'CACHE_STORE=redis',
            ]);
        });
    }

    private function adjustTests(): void
    {
        $this->task('Adjusting test configuration', function () {
            // Remove example tests
            $exampleTests = [
                $this->projectPath.'/tests/Feature/ExampleTest.php',
                $this->projectPath.'/tests/Unit/ExampleTest.php',
            ];

            foreach ($exampleTests as $test) {
                if (File::exists($test)) {
                    File::delete($test);
                }
            }

            // Create .gitkeep files
            File::put($this->projectPath.'/tests/Feature/.gitkeep', '');
            File::put($this->projectPath.'/tests/Unit/.gitkeep', '');

            // Update Pest.php
            $this->updatePestConfig();
        });
    }

    private function updatePestConfig(): void
    {
        $pestFile = $this->projectPath.'/tests/Pest.php';
        $pestContent = File::get($pestFile);

        $pestContent = str_replace(
            '// ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)',
            '->use(Illuminate\Foundation\Testing\LazilyRefreshDatabase::class)',
            $pestContent
        );

        // Comment out example functions
        $pestContent = preg_replace(
            '/expect\(\)->extend.*?}\);/s',
            '// expect()->extend(\'toBeOne\', function () {'."\n".'//    return $this->toBe(1);'."\n".'//});',
            $pestContent
        );

        $pestContent = preg_replace(
            '/function something\(\).*?}/s',
            '// function something()'."\n".'//{'."\n".'//    // ..'."\n".'//}',
            $pestContent
        );

        File::put($pestFile, $pestContent);
    }

    private function setupSail(): void
    {
        $this->task('Setting up Laravel Sail', function () {
            // Update .env and .env.example for MySQL
            $this->updateEnvFiles([
                'DB_CONNECTION=sqlite' => 'DB_CONNECTION=mysql',
                '# DB_HOST=127.0.0.1' => 'DB_HOST=mysql',
                '# DB_PORT=3306' => 'DB_PORT=3306',
                '# DB_DATABASE=laravel' => "DB_DATABASE={$this->projectName}",
                '# DB_USERNAME=root' => 'DB_USERNAME=sail',
                '# DB_PASSWORD=' => 'DB_PASSWORD=password',
            ]);

            // Install Sail
            $this->executeCommand([$this->phpExecutable, 'artisan', 'sail:install', '--with=mysql,redis,mailpit', '--no-interaction']);
        });
    }

    private function setupApiEnvironment(): void
    {
        $this->task('Setting up API environment', function () {
            // Install API
            $this->executeCommand([$this->phpExecutable, 'artisan', 'install:api', '--no-interaction']);

            // Update User model
            $this->updateUserModel();

            // Update routing configuration
            $this->updateBootstrapApp();

            // Create docs directory
            File::makeDirectory($this->projectPath.'/docs/v1', 0755, true, true);
            File::put($this->projectPath.'/docs/v1/.gitkeep', '');
        });
    }

    private function updateUserModel(): void
    {
        $userModelFile = $this->projectPath.'/app/Models/User.php';
        $userModel = File::get($userModelFile);

        // Add HasApiTokens import
        $userModel = str_replace(
            'use Illuminate\Foundation\Auth\User as Authenticatable;',
            "use Illuminate\Foundation\Auth\User as Authenticatable;\nuse Laravel\Sanctum\HasApiTokens;",
            $userModel
        );

        // Add HasApiTokens trait
        $userModel = str_replace(
            'use HasFactory, Notifiable;',
            'use HasApiTokens, HasFactory, Notifiable;',
            $userModel
        );

        File::put($userModelFile, $userModel);
    }

    private function updateBootstrapApp(): void
    {
        $bootstrapFile = $this->projectPath.'/bootstrap/app.php';
        $bootstrap = File::get($bootstrapFile);

        $bootstrap = str_replace(
            'health: \'/up\',',
            "health: '/up',\n        apiPrefix: 'v1',",
            $bootstrap
        );

        File::put($bootstrapFile, $bootstrap);
    }

    private function setupRbac(): void
    {
        $this->task('Setting up RBAC package', function () {
            // Install RBAC package
            $this->executeCommand(['composer', 'require', 'binary-cats/laravel-rbac']);

            // Publish configurations
            $this->executeCommand([$this->phpExecutable, 'artisan', 'vendor:publish', '--provider=Spatie\Permission\PermissionServiceProvider', '--no-interaction']);
            $this->executeCommand([$this->phpExecutable, 'artisan', 'vendor:publish', '--tag=rbac-config', '--no-interaction']);

            // Create Abilities directory
            File::makeDirectory($this->projectPath.'/app/Abilities', 0755, false, true);

            // Update permission config for teams
            $this->updatePermissionConfig();

            // Update User model for roles
            $this->updateUserModelForRoles();

            // Run migrations
            $this->executeCommand(['composer', 'update', '--lock']);
        });
    }

    private function updatePermissionConfig(): void
    {
        $configFile = $this->projectPath.'/config/permission.php';
        $config = File::get($configFile);
        $config = str_replace("'teams' => false,", "'teams' => true,", $config);
        File::put($configFile, $config);
    }

    private function updateUserModelForRoles(): void
    {
        $userModelFile = $this->projectPath.'/app/Models/User.php';
        $userModel = File::get($userModelFile);

        // Add HasRoles import
        if (! str_contains($userModel, 'use Spatie\Permission\Traits\HasRoles;')) {
            $userModel = str_replace(
                'use Laravel\Sanctum\HasApiTokens;',
                "use Laravel\Sanctum\HasApiTokens;\nuse Spatie\Permission\Traits\HasRoles;",
                $userModel
            );
        }

        // Add HasRoles trait
        $userModel = str_replace(
            'use HasApiTokens, HasFactory, Notifiable;',
            'use HasApiTokens, HasFactory, HasRoles, Notifiable;',
            $userModel
        );

        File::put($userModelFile, $userModel);
    }

    private function updateComposerForRbac(): void
    {
        $composerFile = $this->projectPath.'/composer.json';
        $composer = json_decode(File::get($composerFile), true);

        // Add RBAC reset to post-autoload-dump
        $index = array_search('@php artisan package:discover --ansi', $composer['scripts']['post-autoload-dump']);
        if ($index !== false) {
            array_splice($composer['scripts']['post-autoload-dump'], $index + 1, 0, '@php artisan rbac:reset');
        }

        File::put($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function finalizeRbac(): void
    {
        $this->task('Finalizing RBAC configuration', function () {
            // Now it's safe to add RBAC reset command since Sail should be running
            $this->updateComposerForRbac();
        });
    }

    private function setupModules(): void
    {
        $this->task('Setting up modular architecture', function () {
            // Pre-configure composer.json to avoid wikimedia/composer-merge-plugin prompt
            $this->preConfigureComposerForModules();

            // Install Laravel Modules
            $this->executeCommand(['composer', 'require', 'nwidart/laravel-modules']);

            // Publish configuration
            $this->executeCommand([$this->phpExecutable, 'artisan', 'vendor:publish', '--provider=Nwidart\Modules\LaravelModulesServiceProvider', '--no-interaction']);

            // Setup directories and files
            if (File::exists($this->projectPath.'/stubs')) {
                File::deleteDirectory($this->projectPath.'/stubs');
            }
            File::makeDirectory($this->projectPath.'/modules', 0755, false, true);
            File::put($this->projectPath.'/modules/.gitkeep', '');
            File::put($this->projectPath.'/modules_statuses.json', '{}');

            // Update modules config
            $this->updateModulesConfig();

            // Update composer.json for merge-plugin
            $this->updateComposerForModules();

            // Create vite module loader
            $this->createViteModuleLoader();

            // Update vite config
            $this->updateViteConfig();

            $this->executeCommand(['composer', 'update', '--lock']);
        });
    }

    private function updateModulesConfig(): void
    {
        $configFile = $this->projectPath.'/config/modules.php';
        $config = File::get($configFile);
        $config = str_replace("'modules' => base_path('Modules'),", "'modules' => base_path('modules'),", $config);
        File::put($configFile, $config);
    }

    private function updateComposerForModules(): void
    {
        $composerFile = $this->projectPath.'/composer.json';
        $composer = json_decode(File::get($composerFile), true);

        $composer['extra']['merge-plugin'] = [
            'include' => ['modules/*/composer.json'],
        ];

        File::put($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function preConfigureComposerForModules(): void
    {
        $composerFile = $this->projectPath.'/composer.json';
        $composer = json_decode(File::get($composerFile), true);

        // Add wikimedia/composer-merge-plugin to allow-plugins to avoid prompt
        $composer['config']['allow-plugins']['wikimedia/composer-merge-plugin'] = true;

        File::put($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function createViteModuleLoader(): void
    {
        $viteLoader = <<<'JS'
import { readdir, stat } from 'fs/promises';
import { join } from 'path';

async function collectModuleAssetsPaths(paths, modulesDir) {
    try {
        const modules = await readdir(modulesDir);
        const allPaths = [...paths];

        for (const module of modules) {
            const modulePath = join(modulesDir, module);
            const moduleStats = await stat(modulePath);

            if (moduleStats.isDirectory()) {
                const resourcesPath = join(modulePath, 'resources');

                try {
                    await stat(resourcesPath);

                    const cssPath = join(resourcesPath, 'css', 'app.css');
                    const jsPath = join(resourcesPath, 'js', 'app.js');

                    try {
                        await stat(cssPath);
                        allPaths.push(cssPath);
                    } catch {}

                    try {
                        await stat(jsPath);
                        allPaths.push(jsPath);
                    } catch {}
                } catch {}
            }
        }

        return allPaths;
    } catch (error) {
        console.error('Error collecting module assets:', error);
        return paths;
    }
}

export default collectModuleAssetsPaths;
JS;
        File::put($this->projectPath.'/vite-module-loader.js', $viteLoader);
    }

    private function updateViteConfig(): void
    {
        $viteConfigFile = $this->projectPath.'/vite.config.js';
        $viteConfig = File::get($viteConfigFile);

        $newViteConfig = <<<'JS'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import collectModuleAssetsPaths from './vite-module-loader.js';

async function getConfig() {
    const paths = [
        'resources/css/app.css',
        'resources/js/app.js',
    ];
    const allPaths = await collectModuleAssetsPaths(paths, 'modules');

    return defineConfig({
        plugins: [
            laravel({
                input: allPaths,
                refresh: true,
            }),
        ],
    });
}

export default getConfig();
JS;

        File::put($viteConfigFile, $newViteConfig);
    }

    private function installLaravelData(): void
    {
        $this->task('Installing Laravel Data', function () {
            $this->executeCommand(['composer', 'require', 'spatie/laravel-data']);
            $this->executeCommand([$this->phpExecutable, 'artisan', 'vendor:publish', '--provider=Spatie\LaravelData\LaravelDataServiceProvider', '--tag=data-config', '--no-interaction']);
        });
    }

    private function installSpectator(): void
    {
        $this->task('Installing Spectator', function () {
            $this->executeCommand(['composer', 'require', 'hotmeteor/spectator', '--dev']);
            $this->executeCommand([$this->phpExecutable, 'artisan', 'vendor:publish', '--provider=Spectator\SpectatorServiceProvider', '--no-interaction']);

            // Add SPEC_PATH to .env files
            $this->appendToEnvFiles("\nSPEC_PATH=docs\n");
        });
    }

    private function finalizeSetup(): void
    {
        $this->task('Finalizing setup', function () {
            // Final git commit is now handled by commitStep
            $this->line('<fg=green>✓ All steps completed successfully</>');
        });
    }

    /**
     * Create a git commit for the current step
     */
    private function commitStep(string $stepName): void
    {
        $this->task("Committing step: {$stepName}", function () use ($stepName) {
            // Check if there are any PHP file changes and run Pint if needed
            if ($this->hasPhpFileChanges()) {
                $this->runPint();
            }

            $this->executeCommand(['git', 'add', '.']);
            $this->executeCommand(['git', 'commit', '-m', $stepName, '--allow-empty']);
        });
    }

    /**
     * Check if there are any PHP file changes
     */
    private function hasPhpFileChanges(): bool
    {
        try {
            // Check git status for PHP files
            $process = new Process(['git', 'status', '--porcelain']);
            $process->run();

            if (! $process->isSuccessful()) {
                return false;
            }

            $output = $process->getOutput();
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                // Extract filename from git status output
                $filename = trim(substr($line, 3));

                // Check if it's a PHP file
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'php') {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            // If we can't determine, assume there are no PHP changes
            return false;
        }
    }

    /**
     * Run Pint to format PHP code
     */
    private function runPint(): void
    {
        try {
            $this->line('<info>Running Pint to format PHP code...</info>');

            // Check if Pint is available in vendor/bin
            $pintPath = $this->projectPath.'/vendor/bin/pint';

            if (file_exists($pintPath)) {
                // Get list of changed PHP files
                $changedPhpFiles = $this->getChangedPhpFiles();

                if (! empty($changedPhpFiles)) {
                    // Run Pint only on changed PHP files
                    $pintCommand = ['./vendor/bin/pint'];
                    $pintCommand = array_merge($pintCommand, $changedPhpFiles);

                    $this->executeCommand($pintCommand);
                } else {
                    $this->line('<comment>No PHP files to format</comment>');
                }
            } else {
                $this->line('<comment>Pint not found, skipping code formatting</comment>');
            }
        } catch (\Exception $e) {
            $this->line('<comment>Failed to run Pint: '.$e->getMessage().'</comment>');
        }
    }

    /**
     * Get list of changed PHP files
     */
    private function getChangedPhpFiles(): array
    {
        try {
            $process = new Process(['git', 'status', '--porcelain']);
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $output = $process->getOutput();
            $lines = explode("\n", trim($output));
            $phpFiles = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                // Extract filename from git status output
                $filename = trim(substr($line, 3));

                // Check if it's a PHP file and exists
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'php' && file_exists($this->projectPath.'/'.$filename)) {
                    $phpFiles[] = $filename;
                }
            }

            return $phpFiles;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function displayNextSteps(): void
    {
        $this->newLine();
        $this->line('🚀 <comment>Next steps:</comment>');
        $this->line("   cd {$this->projectName}");
        $this->line('   ./vendor/bin/sail up -d');
        $this->line('   ./vendor/bin/sail artisan migrate');
        $this->newLine();
        $this->line('📦 <comment>Frontend assets (using pnpm):</comment>');
        $this->line('   ./vendor/bin/sail pnpm install    # Install frontend packages');
        $this->line('   ./vendor/bin/sail pnpm build      # Build frontend assets');
        $this->newLine();
        $this->line('📚 <comment>Documentation:</comment>');
        $this->line('   API docs will be available in: docs/v1/');
        $this->newLine();
    }

    /**
     * Check environment requirements
     */
    private function checkEnvironment(): bool
    {
        $this->info('🔍 Checking environment requirements...');

        $checks = [
            'PHP' => $this->checkPhp(),
            'Composer' => $this->checkComposer(),
            'Laravel Installer' => $this->checkLaravelInstaller(),
            'Git' => $this->checkGit(),
        ];

        $allPassed = true;
        foreach ($checks as $tool => $result) {
            if ($result['status']) {
                $this->line("✅ {$tool}: {$result['message']}");
            } else {
                $this->error("❌ {$tool}: {$result['message']}");
                $allPassed = false;
            }
        }

        if (! $allPassed) {
            $this->newLine();
            $this->error('Environment check failed. Please install the missing tools and try again.');
            $this->newLine();
            $this->line('📚 <comment>Installation guides:</comment>');
            $this->line('   PHP 8.2+: https://www.php.net/downloads.php');
            $this->line('   Composer: https://getcomposer.org/download/');
            $this->line('   Laravel Installer: composer global require laravel/installer');
            $this->line('   Git: https://git-scm.com/downloads');
        }

        $this->newLine();

        return $allPassed;
    }

    /**
     * Check PHP version
     */
    private function checkPhp(): array
    {
        try {
            $process = new Process(['php', '--version']);
            $process->run();

            if (! $process->isSuccessful()) {
                return ['status' => false, 'message' => 'PHP not found in PATH'];
            }

            $output = $process->getOutput();
            if (preg_match('/PHP (\d+\.\d+)/', $output, $matches)) {
                $version = $matches[1];
                if (version_compare($version, '8.2', '>=')) {
                    return ['status' => true, 'message' => "Version {$version} (✓ >= 8.2)"];
                } else {
                    return ['status' => false, 'message' => "Version {$version} (❌ requires >= 8.2)"];
                }
            }

            return ['status' => false, 'message' => 'Unable to determine PHP version'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'PHP not found or not executable'];
        }
    }

    /**
     * Check Composer
     */
    private function checkComposer(): array
    {
        try {
            $process = new Process(['composer', '--version']);
            $process->run();

            if (! $process->isSuccessful()) {
                return ['status' => false, 'message' => 'Composer not found in PATH'];
            }

            $output = $process->getOutput();
            if (preg_match('/Composer version (\d+\.\d+\.\d+)/', $output, $matches)) {
                $version = $matches[1];

                return ['status' => true, 'message' => "Version {$version}"];
            }

            return ['status' => true, 'message' => 'Available (version not detected)'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Composer not found or not executable'];
        }
    }

    /**
     * Check Laravel Installer
     */
    private function checkLaravelInstaller(): array
    {
        try {
            $process = new Process(['laravel', '--version']);
            $process->run();

            if (! $process->isSuccessful()) {
                return ['status' => false, 'message' => 'Laravel Installer not found in PATH'];
            }

            $output = $process->getOutput();
            if (preg_match('/Laravel Installer (\d+\.\d+\.\d+)/', $output, $matches)) {
                $version = $matches[1];
                $majorVersion = explode('.', $version)[0];
                if (intval($majorVersion) >= 5) {
                    return ['status' => true, 'message' => "Version {$version} (✓ >= 5.0)"];
                } else {
                    return ['status' => false, 'message' => "Version {$version} (❌ requires >= 5.0)"];
                }
            }

            return ['status' => false, 'message' => 'Unable to determine Laravel Installer version'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Laravel Installer not found or not executable'];
        }
    }

    /**
     * Check Git
     */
    private function checkGit(): array
    {
        try {
            $process = new Process(['git', '--version']);
            $process->run();

            if (! $process->isSuccessful()) {
                return ['status' => false, 'message' => 'Git not found in PATH'];
            }

            $output = $process->getOutput();
            if (preg_match('/git version (\d+\.\d+\.\d+)/', $output, $matches)) {
                $version = $matches[1];

                return ['status' => true, 'message' => "Version {$version}"];
            }

            return ['status' => true, 'message' => 'Available (version not detected)'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Git not found or not executable'];
        }
    }

    /**
     * Find the PHP executable path
     */
    private function findPhpExecutable(): void
    {
        // Try to find PHP executable using various methods
        $possiblePaths = [
            // Current PHP binary (most reliable)
            PHP_BINARY,
            // Common locations
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/homebrew/bin/php',
            '/opt/local/bin/php',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $this->phpExecutable = $path;
                $this->line('<info>Using PHP executable:</info> '.$this->phpExecutable);

                return;
            }
        }

        // Fallback to which command if available paths don't work
        try {
            $process = new Process(['which', 'php']);
            $process->run();

            if ($process->isSuccessful()) {
                $this->phpExecutable = trim($process->getOutput());
                $this->line('<info>Using PHP executable:</info> '.$this->phpExecutable);

                return;
            }
        } catch (\Exception $e) {
            // Fallback failed
        }

        // Last resort - use 'php' and hope it's in PATH
        $this->phpExecutable = 'php';
        $this->line('<comment>Using default PHP command:</comment> '.$this->phpExecutable);
    }

    /**
     * Update both .env and .env.example files with the same changes
     */
    private function updateEnvFiles(array $replacements): void
    {
        $envFiles = [
            $this->projectPath . '/.env',
            $this->projectPath . '/.env.example',
        ];

        foreach ($envFiles as $envFile) {
            if (File::exists($envFile)) {
                $content = File::get($envFile);

                foreach ($replacements as $search => $replace) {
                    $content = str_replace($search, $replace, $content);
                }

                File::put($envFile, $content);
            }
        }
    }

    /**
     * Append content to both .env and .env.example files
     */
    private function appendToEnvFiles(string $content): void
    {
        $envFiles = [
            $this->projectPath . '/.env',
            $this->projectPath . '/.env.example',
        ];

        foreach ($envFiles as $envFile) {
            if (File::exists($envFile)) {
                $existingContent = File::get($envFile);
                $existingContent .= $content;
                File::put($envFile, $existingContent);
            }
        }
    }

    private function executeCommand(array $command): void
    {
        $this->line('<comment>Running:</comment> '.implode(' ', $command));

        $process = new Process($command);
        $process->setTimeout(300); // 5 minutes timeout

        // 設定環境變數來保留顏色輸出
        $process->setEnv(array_merge($_ENV, [
            'FORCE_COLOR' => '1',
            'TERM' => 'xterm-256color',
            'COLORTERM' => 'truecolor',
        ]));

        // 如果支援 TTY，則使用 TTY 模式來保留色彩
        if (Process::isTtySupported()) {
            $process->setTty(true);
            $process->run();
        } else {
            // 實時顯示輸出，保留 ANSI 色彩代碼
            $process->run(function ($type, $buffer) {
                if ($type === Process::ERR) {
                    // 對於錯誤輸出，如果本身沒有顏色，則加上黃色
                    if (! preg_match('/\033\[[0-9;]*m/', $buffer)) {
                        $this->line('<fg=yellow>'.rtrim($buffer).'</>');
                    } else {
                        $this->line(rtrim($buffer));
                    }
                } else {
                    // 標準輸出直接顯示，保留原有顏色
                    $this->line(rtrim($buffer));
                }
            });
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Command failed: '.implode(' ', $command)."\n".$process->getErrorOutput());
        }

        $this->line('<fg=green>✓ Command completed successfully</>');
    }
}
