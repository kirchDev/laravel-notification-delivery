<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/**
 * The directory Laravel Boost discovers this package's resources in. Boost reads the consumer's
 * root composer.json and looks for `vendor/<package>/resources/boost/{guidelines,skills}` — the
 * path is the whole of the contract, so it is asserted rather than assumed.
 */
function boostPath(string $subpath = ''): string
{
    $path = __DIR__.'/../../resources/boost'.($subpath === '' ? '' : '/'.$subpath);

    return realpath($path) ?: $path;
}

/**
 * Every guideline file Boost would load. Third-party guidelines get no version resolution: Boost
 * walks the directory recursively and always loads everything it finds, `.blade.php` and `.md`,
 * skipping only a nested `skill` directory. The walk here is recursive for the same reason — a
 * guideline filed in a subdirectory would otherwise escape every assertion below, which is the
 * silence these tests exist to close. `glob()` cannot do it: `**` is one path segment to it.
 *
 * @return list<string>
 */
function boostGuidelines(): array
{
    $directory = boostPath('guidelines');

    if (! is_dir($directory)) {
        return [];
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $entry): bool => $entry->getFilename() !== 'skill',
        ),
    );

    $files = [];

    foreach ($entries as $entry) {
        if ($entry instanceof SplFileInfo
            && (str_ends_with($entry->getFilename(), '.blade.php') || str_ends_with($entry->getFilename(), '.md'))) {
            $files[] = $entry->getPathname();
        }
    }

    sort($files);

    return array_values(array_unique($files));
}

/**
 * Every skill directory Boost would discover — one level below `skills/`, each holding a SKILL file.
 *
 * @return list<string>
 */
function boostSkillDirectories(): array
{
    $directories = glob(boostPath('skills').'/*', GLOB_ONLYDIR) ?: [];
    sort($directories);

    return array_values($directories);
}

/**
 * The methods Boost's GuidelineAssist exposes to a guideline. A guideline reaching for anything
 * outside this list renders against an API the installed Boost may not have — and a third-party
 * guideline that fails to render is silently replaced by an empty string, with no fallback.
 *
 * @return list<string>
 */
function guidelineAssistApi(): array
{
    return [
        'enums', 'enumContents', 'inertia', 'supportsPintAgentFormatter', 'hasPackage',
        'nodePackageManager', 'nodePackageManagerCommand', 'artisanCommand', 'composerCommand',
        'binCommand', 'artisan', 'sailBinaryPath', 'appPath', 'hasSkillsEnabled', 'hasMcpEnabled',
    ];
}

/**
 * Render a guideline the way Boost's RendersBladeGuidelines does: swap the constructs Blade would
 * otherwise try to execute for placeholders, render, then swap them back. Replicating the swap is
 * the point — it is what makes code samples inside a `.blade.php` guideline safe.
 */
function renderGuideline(string $file): string
{
    $placeholders = [
        '`' => '___SINGLE_BACKTICK___',
        '<?php' => '___OPEN_PHP_TAG___',
        '@volt' => '___VOLT_DIRECTIVE___',
        '@endvolt' => '___ENDVOLT_DIRECTIVE___',
        '@can' => '___CAN_DIRECTIVE___',
        '@include' => '___INCLUDE_DIRECTIVE___',
        '@props' => '___PROPS_DIRECTIVE___',
        '</x-' => '___BLADE_COMPONENT_CLOSE___',
        '<x-' => '___BLADE_COMPONENT_OPEN___',
    ];

    $content = (string) file_get_contents($file);

    if (! str_ends_with($file, '.blade.php')) {
        return $content;
    }

    $content = str_replace(array_keys($placeholders), array_values($placeholders), $content);

    $rendered = Blade::render($content, ['assist' => guidelineAssistStub()]);
    $rendered = html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5);

    return str_replace(array_values($placeholders), array_keys($placeholders), $rendered);
}

/**
 * Stands in for Boost's GuidelineAssist, which only exists once laravel/boost is installed in the
 * consumer's application. The stub answers the whole documented surface, so a guideline calling
 * anything on it renders here exactly as it would there.
 */
function guidelineAssistStub(): object
{
    return new class
    {
        public function artisanCommand(string $command): string
        {
            return 'php artisan '.$command;
        }

        public function composerCommand(string $command): string
        {
            return 'composer '.$command;
        }

        public function nodePackageManagerCommand(string $command): string
        {
            return 'npm '.$command;
        }

        public function binCommand(string $command): string
        {
            return 'vendor/bin/'.$command;
        }

        public function nodePackageManager(): string
        {
            return 'npm';
        }

        public function artisan(): string
        {
            return 'artisan';
        }

        public function sailBinaryPath(): string
        {
            return 'vendor/bin/sail';
        }

        public function appPath(string $path = ''): string
        {
            return 'app/'.$path;
        }

        public function hasPackage(string $package, ?string $constraint = null): bool
        {
            return false;
        }

        public function hasSkillsEnabled(): bool
        {
            return true;
        }

        public function hasMcpEnabled(): bool
        {
            return true;
        }

        public function supportsPintAgentFormatter(): bool
        {
            return true;
        }

        /** @return array<string, mixed> */
        public function enums(): array
        {
            return [];
        }

        public function enumContents(): string
        {
            return '';
        }
    };
}

/**
 * Parse a SKILL file's YAML frontmatter. Boost runs symfony/yaml over it, which this package does
 * not depend on — so the frontmatter is deliberately kept to flat `key: value` pairs that both
 * parsers read identically.
 *
 * @return array<string, string>
 */
function skillFrontmatter(string $content): array
{
    $content = (string) preg_replace('/^(\s*<!--.*?-->\s*)+/s', '', $content);

    if (! preg_match('/^\s*---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
        return [];
    }

    $frontmatter = [];

    foreach (preg_split('/\R/', $matches[1]) ?: [] as $line) {
        if (! preg_match('/^(?<key>[A-Za-z0-9_-]+):\s*(?<value>.*)$/', trim($line), $pair)) {
            continue;
        }

        $frontmatter[$pair['key']] = trim($pair['value'], " \t\"'");
    }

    return $frontmatter;
}

it('exposes the two directories Boost discovers a third-party package by', function () {
    expect(is_dir(boostPath('guidelines')))->toBeTrue()
        ->and(is_dir(boostPath('skills')))->toBeTrue();
});

it('ships a core guideline', function () {
    expect(boostGuidelines())->toContain(boostPath('guidelines').'/core.blade.php');
});

it('renders every guideline to non-empty content', function () {
    // A guideline that throws while rendering is replaced by an empty string, and third-party
    // guidelines have no fallback — the failure reaches the consumer as silently missing context.
    // A directive that compiles instead of printing is the quieter half of the same failure: the
    // render succeeds and ships its own compiled PHP as documentation, so '<?php' is asserted too.
    // Writing an annotation like the trait's type argument into a guideline needs the '@' split
    // off ({{ '@'.'use' }}), because Blade compiles the directive even inside a string literal.
    foreach (boostGuidelines() as $guideline) {
        $rendered = trim(renderGuideline($guideline));

        expect($rendered)->not->toBe('', $guideline)
            ->and(str_contains($rendered, '@if'))->toBeFalse($guideline)
            ->and(str_contains($rendered, '{{'))->toBeFalse($guideline)
            ->and(str_contains($rendered, '<?php'))->toBeFalse($guideline)
            ->and(str_contains($rendered, '___'))->toBeFalse($guideline);
    }
});

it('opens every guideline with the heading Boost derives its description from', function () {
    // Boost takes everything after the first '# ' up to the newline as the guideline's description.
    foreach (boostGuidelines() as $guideline) {
        $description = trim(str(renderGuideline($guideline))->after('# ')->before("\n")->toString());

        expect($description)->not->toBe('', $guideline);
    }
});

it('calls nothing on $assist that Boost does not expose', function () {
    foreach (boostGuidelines() as $guideline) {
        preg_match_all('/\$assist->(?<method>\w+)\s*\(/', (string) file_get_contents($guideline), $matches);

        foreach (array_unique($matches['method']) as $method) {
            expect(in_array($method, guidelineAssistApi(), true))
                ->toBeTrue($guideline.': $assist->'.$method.'()');
        }
    }
});

it('ships a notification-delivery-development skill', function () {
    expect(boostSkillDirectories())->toContain(boostPath('skills').'/notification-delivery-development');
});

it('gives every skill the frontmatter Boost requires', function () {
    // A SKILL file without both keys is discarded without a warning.
    expect(boostSkillDirectories())->not->toBeEmpty();

    foreach (boostSkillDirectories() as $directory) {
        $file = $directory.'/SKILL.md';

        expect(file_exists($file))->toBeTrue($file);

        $frontmatter = skillFrontmatter((string) file_get_contents($file));

        expect($frontmatter['name'] ?? '')->toBe(basename($directory), $file)
            ->and($frontmatter['description'] ?? '')->not->toBe('', $file);
    }
});
