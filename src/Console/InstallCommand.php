<?php

declare(strict_types=1);

namespace JorgeCortesDev\Claudify\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Composer;
use JorgeCortesDev\Claudify\Detection\StackDetector;
use JorgeCortesDev\Claudify\Enums\WriteResult;
use JorgeCortesDev\Claudify\Writers\JsonWriter;
use JorgeCortesDev\Claudify\Writers\SkillWriter;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;

class InstallCommand extends Command
{
    protected $signature = 'claudify:install
        {--refresh : Re-detect stack and update configuration}
        {--dry-run : Show what would be configured without writing files}
        {--no-boost : Skip laravel/boost installation}';

    protected $description = 'Configure Claude Code for this Laravel project';

    private StackDetector $detector;

    public function handle(): int
    {
        $this->detector = new StackDetector;

        info('Claudify :: Configure Claude Code for Laravel');
        $this->displayDetectedStack();

        if ($this->option('dry-run')) {
            $this->displayDryRun();

            return self::SUCCESS;
        }

        $this->installBoost();
        $this->installHooks();
        $this->installSettings();
        $this->installMcpConfig();
        $this->installSkills();

        note('Done. Claude Code is configured for this project.');

        return self::SUCCESS;
    }

    private function displayDetectedStack(): void
    {
        $detected = $this->detector->detected();

        if ($detected->isEmpty()) {
            note('No additional packages detected beyond Laravel.');

            return;
        }

        table(
            ['Package', 'Detected'],
            $detected->keys()->map(fn (string $key): array => [$key, 'yes'])->toArray()
        );
    }

    private function displayDryRun(): void
    {
        note('Dry run — these files would be written:');

        $this->line('');
        $this->line('  .claude/settings.json');
        $this->displayJsonPreview($this->buildSettings());

        $this->line('');
        $this->line('  .mcp.json');
        $this->displayJsonPreview($this->buildMcpConfig());

        $hookScripts = $this->hookScriptsToInstall();

        if ($hookScripts !== []) {
            $this->line('');
            $this->line('  .claude/hooks/');

            foreach ($hookScripts as $script) {
                $this->line("    {$script}");
            }
        }

        $writer = $this->makeSkillWriter();
        $skills = $writer->availableSkills();

        if ($skills !== []) {
            $this->line('');
            $this->line('  .claude/skills/');

            foreach ($skills as $name) {
                $this->line("    {$name}/");
            }
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
        $settings = $this->buildSettings();

        if ($writer->exists() && ! $this->option('refresh')) {
            if (! confirm('.claude/settings.json already exists. Merge new settings?', true)) {
                return;
            }
        }

        $writer->write($settings);
        $this->components->twoColumnDetail('.claude/settings.json', '<fg=green>written</>');
    }

    private function installMcpConfig(): void
    {
        $mcpConfig = $this->buildMcpConfig();

        if (empty($mcpConfig['mcpServers'])) {
            return;
        }

        $writer = new JsonWriter(base_path('.mcp.json'));

        if ($writer->exists() && ! $this->option('refresh')) {
            if (! confirm('.mcp.json already exists. Merge new settings?', true)) {
                return;
            }
        }

        $writer->write($mcpConfig);
        $this->components->twoColumnDetail('.mcp.json', '<fg=green>written</>');
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

    /**
     * @return array<string, mixed>
     */
    private function buildMcpConfig(): array
    {
        $servers = [];

        return ['mcpServers' => $servers];
    }

    private function installBoost(): void
    {
        if ($this->option('no-boost') || $this->detector->hasBoost()) {
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
        $sourcePath = dirname(__DIR__, 2).'/resources/hooks';
        $targetPath = base_path('.claude/hooks');

        $scripts = $this->hookScriptsToInstall();

        if ($scripts === []) {
            return;
        }

        if (! is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        foreach ($scripts as $script) {
            $source = $sourcePath.'/'.$script;
            $target = $targetPath.'/'.$script;

            if (! file_exists($source)) {
                continue;
            }

            copy($source, $target);
            chmod($target, 0755);

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
        $writer = $this->makeSkillWriter();
        $results = $writer->sync();

        foreach ($results as $name => $result) {
            $status = match ($result) {
                WriteResult::SUCCESS => '<fg=green>written</>',
                WriteResult::UPDATED => '<fg=yellow>updated</>',
                WriteResult::FAILED => '<fg=red>failed</>',
            };

            $this->components->twoColumnDetail(".claude/skills/{$name}", $status);
        }
    }

    private function makeSkillWriter(): SkillWriter
    {
        return new SkillWriter(
            dirname(__DIR__, 2).'/resources/skills',
            base_path('.claude/skills'),
        );
    }
}
