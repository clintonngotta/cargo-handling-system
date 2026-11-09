<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Enforces the dependency rule described in docs/ARCHITECTURE.md. */
class ArchitectureTest extends TestCase
{
    /** @return array<string, array{string, list<string>}> */
    public static function layerRules(): array
    {
        return [
            'domain depends on nothing outside itself' => [
                'Domain',
                ['Illuminate\\', 'Filament\\', 'Livewire\\', 'App\\Application\\', 'App\\Models\\', 'App\\Observers\\', 'App\\Filament\\'],
            ],
            'application does not depend on the UI' => [
                'Application',
                ['Filament\\', 'Livewire\\', 'App\\Filament\\'],
            ],
        ];
    }

    /** @param list<string> $forbiddenPrefixes */
    #[DataProvider('layerRules')]
    public function test_layer_imports_respect_the_dependency_rule(string $layer, array $forbiddenPrefixes): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/'.$layer, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        $checked = 0;

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            preg_match_all('/^use\s+([\w\\\\]+)/m', (string) file_get_contents($file->getPathname()), $imports);
            $checked++;

            foreach ($imports[1] as $import) {
                foreach ($forbiddenPrefixes as $prefix) {
                    $this->assertStringStartsNotWith($prefix, $import, $file->getPathname().' imports '.$import);
                }
            }
        }

        $this->assertGreaterThan(0, $checked);
    }
}
