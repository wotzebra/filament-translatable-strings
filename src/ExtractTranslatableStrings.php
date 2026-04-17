<?php

namespace Wotz\TranslatableStrings;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Wotz\LocaleCollection\Facades\LocaleCollection;
use Wotz\LocaleCollection\Locale;
use Wotz\TranslatableRoutes\Facades\TranslateRouteParts;
use Wotz\TranslatableStrings\Models\TranslatableString;

class ExtractTranslatableStrings
{
    const JSON_GROUP = '_json';

    protected Collection $groupKeys;

    protected Collection $stringKeys;

    protected Collection $vendorKeys;

    public function __construct(
        protected Filesystem $files
    ) {
        $this->groupKeys = new Collection;
        $this->stringKeys = new Collection;
        $this->vendorKeys = new Collection;
    }

    public function find(string $path): self
    {
        // Find all PHP files in the app folder, except for storage
        $finder = (new Finder)
            ->in($path)
            ->exclude(config('filament-translatable-strings.exclude_folders', []))
            ->name('*.php')
            ->name('*.vue')
            ->files();

        foreach ($finder as $file) {
            // Search the current file for the pattern
            if (preg_match_all("/{$this->getVendorPattern()}/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $index => $key) {
                    $this->vendorKeys->add([
                        'key' => $key,
                        'method' => $matches[1][$index] ?? null,
                    ]);
                }
            }

            if (preg_match_all("/{$this->getGroupPattern()}/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $index => $key) {
                    $this->groupKeys->add([
                        'key' => $key,
                        'method' => $matches[1][$index] ?? null,
                    ]);
                }
            }

            if (preg_match_all("/{$this->getStringPattern()}/siU", $file->getContents(), $matches)) {
                foreach ($matches['string'] as $index => $key) {
                    if (preg_match("/(^[a-zA-Z0-9_-]+([.][^\1)\ ]+)+$)/siU", $key)) {
                        // group{.group}.key format, already in $groupKeys but also matched here
                        // do nothing, it has to be treated as a group
                        continue;
                    }

                    if ((! (Str::contains($key, '::') && Str::contains($key, '.')) || Str::contains($key, ' '))
                        && $this->groupKeys->doesntContain('key', $key) && $this->vendorKeys->doesntContain('key', $key)
                    ) {
                        $this->stringKeys->add([
                            'key' => $key,
                            'method' => $matches[1][$index] ?? null,
                        ]);
                    }
                }
            }
        }

        if (class_exists(TranslateRouteParts::class)) {
            if (TranslateRouteParts::isEmpty()) {
                LocaleCollection::registerRoutes(base_path('routes/translatable.php'));
            }

            TranslateRouteParts::each(function (string $key) {
                $this->groupKeys->add([
                    'key' => $key,
                    'method' => '__',
                ]);
            });
        }

        return $this;
    }

    public function importValidationStrings(): self
    {
        collect(__('validation'))->dot()->keys()->each(function ($key) {
            $this->missingKey(
                'validation',
                $key,
                "validation.{$key}",
                false
            );
        });

        return $this;
    }

    public function save(): self
    {
        $this
            ->saveVendorKeys()
            ->saveGroupKeys()
            ->saveStringKeys();

        return $this;
    }

    public function missingKey(string $scope, string $name, string $key, bool $isHtml = false)
    {
        TranslatableString::flushEventListeners();

        /** @var TranslatableString $translatableString */
        $translatableString = TranslatableString::firstOrNew([
            'scope' => $scope,
            'name' => $name,
        ]);

        // Set/update fields that should always be updated
        $translatableString->key = $key;
        $translatableString->is_html = $isHtml;

        // Only set value on creation (not on update)
        if (! $translatableString->exists) {
            $translatableString->value = $this->getValue($key);
            $translatableString->save();
        }

        $translatableString->save();
    }

    public function getAllFunctions(): Collection
    {
        return $this->getTransFunctions()
            ->merge($this->getHtmlTransFunctions());
    }

    public function getTransFunctions(): Collection
    {
        return collect(config('filament-translatable-strings.trans_functions', []));
    }

    public function getHtmlTransFunctions(): Collection
    {
        return collect(config('filament-translatable-strings.html_trans_functions', []));
    }

    public function getVendorPattern()
    {
        // See https://regex101.com/r/WEJqdL/6
        return '[^\w|>]' . // Must not have an alphanum or _ or > before real method
        '(' . $this->getAllFunctions()->implode('|') . ')' . // Must start with one of the functions
        '\(' . // Match opening parenthesis
        '[\'\"]' . // Match " or '
        '(' . // Start a new group to match:
        '[a-zA-Z0-9_-]+' . // Must start with group
        '([\:\:](?! )[^\1)]+)+' . // Be followed by one or more items/keys
        ')' . // Close group
        '[\'\"]' . // Closing quote
        '[\),]'; // Close parentheses or new parameter
    }

    public function getGroupPattern()
    {
        // See https://regex101.com/r/WEJqdL/6
        return '[^\w|>]' . // Must not have an alphanum or _ or > before real method
        '(' . $this->getAllFunctions()->implode('|') . ')' . // Must start with one of the functions
        '\(' . // Match opening parenthesis
        '[\'\"]' . // Match " or '
        '(' . // Start a new group to match:
        '[a-zA-Z0-9_-]+' . // Must start with group
        '([.](?! )[^\1)]+)+' . // Be followed by one or more items/keys
        ')' . // Close group
        '[\'\"]' . // Closing quote
        '[\),]'; // Close parentheses or new parameter
    }

    public function getStringPattern()
    {
        return '[^\w]' . // Must not have an alphanum before real method
        '(' . $this->getAllFunctions()->implode('|') . ')' . // Must start with one of the functions
        '\(' . // Match opening parenthesis
        '(?P<quote>[\'"])' . // Match " or ' and store in {quote}
        '(?P<string>(?:\\\k{quote}|(?!\k{quote}).)*)' . // Match any string that can be {quote} escaped
        '\k{quote}' . // Match " or ' previously matched
        '[\),]'; // Close parentheses or new parameter
    }

    protected function getValue(string $key): array
    {
        return LocaleCollection::mapWithKeys(function (Locale $locale) use ($key) {
            $translation = app('translator')->get($key, [], $locale->locale(), false);

            if ($key === $translation && $locale->urlLocale() !== $locale->locale()) {
                $translation = app('translator')->get($key, [], $locale->urlLocale(), false);
            }

            $value = null;

            if ($key !== $translation && ! is_array($translation)) {
                $value = $translation;
            }

            return [
                $locale->locale() => $value,
            ];
        })->toArray();
    }

    protected function saveVendorKeys(): self
    {
        $this->getVendorKeys()->each(function ($vendorKey) {
            if (! Str::contains($vendorKey['key'], '.')) {
                return;
            }

            [$scope, $name] = explode('.', $vendorKey['key'], 2);

            $this->missingKey(
                'vendor/' . str_replace('::', '/', $scope),
                $name,
                $vendorKey['key'],
                $this->isHtml($vendorKey['method'])
            );
        });

        return $this;
    }

    protected function saveGroupKeys(): self
    {
        $this->getGroupKeys()->each(function ($groupKey) {
            if (! Str::contains($groupKey['key'], '.')) {
                return;
            }

            [$scope, $name] = explode('.', $groupKey['key'], 2);

            $this->missingKey(
                $scope,
                $name,
                $groupKey['key'],
                $this->isHtml($groupKey['method'])
            );
        });

        return $this;
    }

    protected function saveStringKeys(): self
    {
        $this->getStringKeys()->each(function ($stringKey) {
            $this->missingKey(
                self::JSON_GROUP,
                $stringKey['key'],
                $stringKey['key'],
                $this->isHtml($stringKey['method']),
            );
        });

        return $this;
    }

    public function getGroupKeys(): Collection
    {
        return $this->groupKeys->unique();
    }

    public function getStringKeys(): Collection
    {
        return $this->stringKeys->unique();
    }

    public function getVendorKeys(): Collection
    {
        return $this->vendorKeys->unique();
    }

    protected function isHtml(string $method): bool
    {
        return $this->getHtmlTransFunctions()->contains($method);
    }
}
