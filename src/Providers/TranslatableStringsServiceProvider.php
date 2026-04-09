<?php

namespace Codedor\TranslatableStrings\Providers;

use Codedor\TranslatableStrings\Console\Commands\ExportTranslationsToLang;
use Codedor\TranslatableStrings\Console\Commands\ExtractAndParseTranslatableStrings;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TranslatableStringsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-translatable-strings')
            ->setBasePath(__DIR__ . '/../')
            ->hasConfigFile()
            ->hasMigration('2022_03_20_161514_create_translatable_strings_table')
            ->hasMigration('2026_04_09_150515_add_domain_support_to_translatable_strings_table')
            ->hasCommands(
                ExtractAndParseTranslatableStrings::class,
                ExportTranslationsToLang::class,
            )
            ->hasViews()
            ->runsMigrations()
            ->hasTranslations();
    }
}
