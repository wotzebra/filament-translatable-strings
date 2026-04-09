<?php

namespace Codedor\TranslatableStrings;

use Codedor\LocaleCollection\Facades\LocaleCollection;
use Codedor\LocaleCollection\Locale;
use Codedor\TranslatableStrings\Models\TranslatableString;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExportToLang
{
    public function __construct(
        protected Filesystem $files
    ) {}

    public function exportAll(): void
    {
        TranslatableString::distinct()
            ->get('scope')
            ->each(fn ($scope) => $this->export($scope->scope));
    }

    public function export(string $scope): void
    {
        $basePath = lang_path();

        // Export global translations
        $this->exportGlobal($scope, $basePath);

        // Export domain-specific translations if configured
        $this->exportDomains($scope, $basePath);
    }

    protected function exportGlobal(string $scope, string $basePath): void
    {
        $translations = $this->mapTranslatableStringsForScope($scope);

        if ($scope !== ExtractTranslatableStrings::JSON_GROUP) {
            $vendor = Str::startsWith($scope, 'vendor');

            foreach ($translations as $locale => $strings) {
                $filename = $scope;

                if ($vendor) {
                    $groupParts = explode('/', $scope);
                    $filename = $groupParts[2];
                    $localePath = $basePath . '/' . $groupParts[0] . '/' . $groupParts[1] . '/' . $locale . '/' . $filename;
                } else {
                    $localePath = $basePath . '/' . $locale . '/' . $filename;
                }

                $this->writePhpFile($localePath, $strings);
            }
        } else {
            foreach ($translations as $locale => $strings) {
                $path = $basePath . '/' . $locale . '.json';
                $output = json_encode(
                    $strings->toArray(),
                    \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE
                );

                $this->files->put($path, $output);
            }
        }
    }

    protected function exportDomains(string $scope, string $basePath): void
    {
        $domainsProvider = config('filament-translatable-strings.domains_provider');

        if (! $domainsProvider || ! is_callable($domainsProvider)) {
            return;
        }

        $domains = $domainsProvider();

        foreach (array_keys($domains) as $domainIdentifier) {
            $this->exportForDomain($scope, $basePath, $domainIdentifier);
        }
    }

    protected function exportForDomain(string $scope, string $basePath, string $domainIdentifier): void
    {
        $translations = $this->mapDomainTranslationsForScope($scope, $domainIdentifier);

        // Check if there are any domain-specific overrides
        $hasOverrides = $translations->contains(fn ($strings) => $strings instanceof Collection ? $strings->isNotEmpty() : ! empty($strings));

        if (! $hasOverrides) {
            return;
        }

        $domainPath = $basePath . '/domains/' . $domainIdentifier;

        if ($scope !== ExtractTranslatableStrings::JSON_GROUP) {
            $vendor = Str::startsWith($scope, 'vendor');

            foreach ($translations as $locale => $strings) {
                if ($strings instanceof Collection && $strings->isEmpty()) {
                    continue;
                }

                $filename = $scope;

                if ($vendor) {
                    $groupParts = explode('/', $scope);
                    $filename = $groupParts[2];
                    $localePath = $domainPath . '/' . $groupParts[0] . '/' . $groupParts[1] . '/' . $locale . '/' . $filename;
                } else {
                    $localePath = $domainPath . '/' . $locale . '/' . $filename;
                }

                $this->writePhpFile($localePath, $strings);
            }
        } else {
            foreach ($translations as $locale => $strings) {
                if ($strings instanceof Collection && $strings->isEmpty()) {
                    continue;
                }

                $path = $domainPath . '/' . $locale . '.json';

                if (! $this->files->isDirectory(dirname($path))) {
                    $this->files->makeDirectory(dirname($path), 0755, true);
                }

                $output = json_encode(
                    $strings instanceof Collection ? $strings->toArray() : $strings,
                    \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE
                );

                $this->files->put($path, $output);
            }
        }
    }

    protected function writePhpFile(string $localePath, Collection|array $strings): void
    {
        if (! $this->files->isDirectory(dirname($localePath))) {
            $this->files->makeDirectory(dirname($localePath), 0755, true);
        }

        $stringsArray = $strings instanceof Collection ? $strings->toArray() : $strings;
        $output = "<?php\n\nreturn " . var_export($stringsArray, true) . ';' . \PHP_EOL;

        $this->files->put("{$localePath}.php", $output);
    }

    public function mapTranslatableStringsForScope(string $scope): Collection
    {
        $translatableStrings = TranslatableString::whereScope($scope)->get();

        return LocaleCollection::mapToGroups(fn (Locale $locale) => [
            $locale->locale() => $translatableStrings
                ->mapWithKeys(fn ($translatableString) => [
                    $translatableString->name => $translatableString->getTranslation('value', $locale->locale(), false),
                ])
                ->filter(),
        ])
            ->mapWithKeys(fn (Collection $items, string $locale) => [$locale => $items->first()]);
    }

    public function mapDomainTranslationsForScope(string $scope, string $domainIdentifier): Collection
    {
        $translatableStrings = TranslatableString::whereScope($scope)
            ->where('use_on_all_domains', false)
            ->get();

        return LocaleCollection::mapToGroups(fn (Locale $locale) => [
            $locale->locale() => $translatableStrings
                ->mapWithKeys(function ($translatableString) use ($locale, $domainIdentifier) {
                    $domainValues = $translatableString->domain_values ?? [];
                    $value = $domainValues[$domainIdentifier][$locale->locale()] ?? null;

                    return $value ? [$translatableString->name => $value] : [];
                })
                ->filter(),
        ])
            ->mapWithKeys(fn (Collection $items, string $locale) => [$locale => $items->first() ?? collect()]);
    }
}
