<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JorgeCortesDev\Claudify\Concerns\DisplayHelper;
use JorgeCortesDev\Claudify\Detection\StackDetector;
use JorgeCortesDev\Claudify\Enums\WriteResult;
use JorgeCortesDev\Claudify\Writers\DirectoryWriter;
use JorgeCortesDev\Claudify\Writers\JsonWriter;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;

class InstallCommand extends Command
{
    use DisplayHelper;

    protected $signature = 'claudify:install
        {--dry-run : Show what would be configured without writing files}';

    protected $description = 'Configure Claude Code for this Laravel project';

    private StackDetector $detector;

    public function handle(): int
    {
        if (! $this->claudeCliExists()) {
            $this->components->error('Claude Code CLI not found. Install it from https://claude.ai/download');

            return self::FAILURE;
        }

        $this->detector = new StackDetector;

        $this->displayHeader('Install', config('app.name', 'Laravel'));
        $this->displayDetectedStack();

        if ($this->option('dry-run')) {
            $this->displayDryRun();

            return self::SUCCESS;
        }

        $this->installBoost();
        $this->installHooks();
        $this->installSettings();
        $this->installSkills();
        $this->installAgents();
        $this->installPlugins();

        $this->displayOutro(' Claude Code is now configured for this project. ');

        return self::SUCCESS;
    }

    private function displayDetectedStack(): void
    {
        $detected = $this->detector->detected();

        if ($detected->isEmpty()) {
            note('No additional packages detected beyond Laravel.');

            return;
        }

        foreach ($detected->keys() as $package) {
            $this->components->twoColumnDetail($package, '<fg=green>detected</>');
        }
    }

    private function displayDryRun(): void
    {
        note('Dry run — these files would be written:');

        $this->line('');
        $this->line('  .claude/settings.json');
        $this->displayJsonPreview($this->buildSettings());

        $this->displayDryRunList('.claude/hooks/', $this->hookScriptsToInstall());
        $this->displayDryRunList('.claude/skills/', $this->makeSkillWriter()->available(), '/');
        $this->displayDryRunList('.claude/agents/', $this->makeAgentWriter()->available(), '/');
    }

    /**
     * @param  array<int, string>  $items
     */
    private function displayDryRunList(string $heading, array $items, string $suffix = ''): void
    {
        if ($items === []) {
            return;
        }

        $this->line('');
        $this->line("  {$heading}");

        foreach ($items as $item) {
            $this->line("    {$item}{$suffix}");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function displayJsonPreview(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        foreach (explode("\n", $json) as $line) {
            $this->line("    {$line}");
        }
    }

    private function installSettings(): void
    {
        $writer = new JsonWriter(base_path('.claude/settings.json'));

        $writer->write($this->buildSettings());
        $this->components->twoColumnDetail('.claude/settings.json', '<fg=green>written</>');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSettings(): array
    {
        $settings = [
            'permissions' => [
                'allow' => $this->buildPermissions(),
                'deny' => [
                    'Edit(.env*)',
                    'Write(.env*)',
                ],
            ],
        ];

        $hooks = $this->buildHooks();

        if ($hooks !== []) {
            $settings['hooks'] = $hooks;
        }

        return $settings;
    }

    /**
     * @return array<int, string>
     */
    private function buildPermissions(): array
    {
        $permissions = [
            'Bash(php:*)',
            'Bash(php artisan:*)',
            'Bash(composer:*)',
        ];

        if ($this->detector->hasPest()) {
            $permissions[] = 'Bash(vendor/bin/pest:*)';
        }

        if ($this->detector->hasPint()) {
            $permissions[] = 'Bash(vendor/bin/pint:*)';
        }

        if ($this->detector->hasNodeDependencies()) {
            $permissions[] = 'Bash(npm:*)';
            $permissions[] = 'Bash(npx:*)';
        }

        return $permissions;
    }

    private function installBoost(): void
    {
        if ($this->detector->hasBoost()) {
            return;
        }

        if (! $this->laravelSupportsBoost()) {
            return;
        }

        if (! confirm('Install laravel/boost? It provides MCP tools, guidelines, and skills for Claude Code.', true)) {
            return;
        }

        $this->components->twoColumnDetail('laravel/boost', '<fg=yellow>installing</>');

        $composer = $this->laravel->make(Composer::class);
        $composer->requirePackages(['laravel/boost'], true);

        $this->components->twoColumnDetail('laravel/boost', '<fg=green>installed</>');
    }

    private function laravelSupportsBoost(): bool
    {
        $version = Application::VERSION;
        $major = (int) explode('.', $version)[0];

        return match ($major) {
            11 => version_compare($version, '11.45.3', '>='),
            12 => version_compare($version, '12.41.1', '>='),
            default => $major >= 13,
        };
    }

    private function installHooks(): void
    {
        $scripts = $this->hookScriptsToInstall();

        if ($scripts === []) {
            return;
        }

        $sourcePath = $this->resourcePath('hooks');
        $targetPath = base_path('.claude/hooks');

        File::ensureDirectoryExists($targetPath);

        foreach ($scripts as $script) {
            $source = $sourcePath.'/'.$script;

            if (! file_exists($source)) {
                continue;
            }

            File::copy($source, $targetPath.'/'.$script);
            chmod($targetPath.'/'.$script, 0755);

            $this->components->twoColumnDetail(".claude/hooks/{$script}", '<fg=green>written</>');
        }
    }

    /**
     * @return array<int, string>
     */
    private function hookScriptsToInstall(): array
    {
        $scripts = [];

        if ($this->detector->hasPint()) {
            $scripts[] = 'pint-format.sh';
        }

        if ($this->detector->hasPrettier()) {
            $scripts[] = 'prettier-format.sh';
        }

        return $scripts;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function buildHooks(): array
    {
        $scripts = $this->hookScriptsToInstall();

        if ($scripts === []) {
            return [];
        }

        $postToolUseHooks = array_map(fn (string $script): array => [
            'type' => 'command',
            'command' => ".claude/hooks/{$script}",
        ], $scripts);

        return [
            'PostToolUse' => [
                [
                    'matcher' => 'Edit|Write',
                    'hooks' => $postToolUseHooks,
                ],
            ],
        ];
    }

    private function installSkills(): void
    {
        $this->syncAndReport($this->makeSkillWriter(), '.claude/skills');
    }

    private function installAgents(): void
    {
        $this->syncAndReport($this->makeAgentWriter(), '.claude/agents');
    }

    private function syncAndReport(DirectoryWriter $writer, string $prefix): void
    {
        foreach ($writer->sync() as $name => $result) {
            $status = match ($result) {
                WriteResult::SUCCESS => '<fg=green>written</>',
                WriteResult::UPDATED => '<fg=yellow>updated</>',
                WriteResult::FAILED => '<fg=red>failed</>',
            };

            $this->components->twoColumnDetail("{$prefix}/{$name}", $status);
        }
    }

    private function installPlugins(): void
    {
        $installed = $this->installedPlugins();

        foreach ($this->desiredPlugins() as $pluginId) {
            if (in_array($pluginId, $installed, true)) {
                $this->components->twoColumnDetail($pluginId, '<fg=blue>already installed</>');

                continue;
            }

            $result = Process::run("claude plugin install {$pluginId} --scope project");

            $status = $result->successful()
                ? '<fg=green>installed</>'
                : '<fg=red>failed</>';

            $this->components->twoColumnDetail($pluginId, $status);
        }
    }

    /**
     * @return array<int, string>
     */
    private function installedPlugins(): array
    {
        $result = Process::run('claude plugin list --json');

        if (! $result->successful()) {
            return [];
        }

        $plugins = json_decode($result->output(), true);

        if (! is_array($plugins)) {
            return [];
        }

        return array_column($plugins, 'id');
    }

    /**
     * @return array<int, string>
     */
    private function desiredPlugins(): array
    {
        $plugins = [
            'laravel-simplifier@laravel',
            'php-lsp@claude-plugins-official',
        ];

        if ($this->detector->hasNodeDependencies()) {
            $plugins[] = 'typescript-lsp@claude-plugins-official';
        }

        return $plugins;
    }

    private function claudeCliExists(): bool
    {
        return Process::run('which claude')->successful();
    }

    private function resourcePath(string $path = ''): string
    {
        return dirname(__DIR__, 2).'/resources'.($path ? "/{$path}" : '');
    }

    private function makeSkillWriter(): DirectoryWriter
    {
        return new DirectoryWriter(
            $this->resourcePath('skills'),
            base_path('.claude/skills'),
        );
    }

    private function makeAgentWriter(): DirectoryWriter
    {
        return new DirectoryWriter(
            $this->resourcePath('agents'),
            base_path('.claude/agents'),
        );
    }
}
