<?php

namespace Codedor\TranslatableStrings\Models;

use Codedor\LocaleCollection\Facades\LocaleCollection;
use Codedor\LocaleCollection\Locale;
use Codedor\TranslatableStrings\ExtractTranslatableStrings;
use Codedor\TranslatableStrings\Jobs\ExportToLang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $scope
 * @property string $key
 * @property string $name
 * @property bool $is_html
 * @property bool $use_on_all_domains
 * @property array $value
 * @property array|null $domain_values
 */
class TranslatableString extends Model
{
    use HasTranslations;

    protected $table = 'translatable_strings';

    protected $translatable = ['value'];

    protected $fillable = ['scope', 'name', 'key', 'is_html', 'value', 'use_on_all_domains', 'domain_values'];

    protected $casts = [
        'is_html' => 'boolean',
        'use_on_all_domains' => 'boolean',
        'domain_values' => 'array',
    ];

    /**
     * Get translation for a specific locale and domain.
     * Falls back to global value if no domain-specific override exists.
     */
    public function getTranslationForDomain(string $locale, ?string $domainIdentifier = null): ?string
    {
        // If use_on_all_domains is true or no domain specified, use global value
        if ($this->use_on_all_domains || is_null($domainIdentifier)) {
            return $this->getTranslation('value', $locale, false);
        }

        // Check for domain-specific override
        $domainValues = $this->domain_values ?? [];

        if (isset($domainValues[$domainIdentifier][$locale])) {
            $value = $domainValues[$domainIdentifier][$locale];
            // Return domain value if not empty, otherwise fallback to global
            if (! empty($value)) {
                return $value;
            }
        }

        // Fallback to global value
        return $this->getTranslation('value', $locale, false);
    }

    public function scopeByOneEmptyValue(Builder $query): void
    {
        $query->orWhere(
            fn ($query) => LocaleCollection::each(
                fn (Locale $locale) => $query->orWhereNull("value->{$locale->locale()}")
            )
        );
    }

    public function scopeByAllEmptyValues(Builder $query): void
    {
        $query->where(
            fn ($query) => LocaleCollection::each(
                fn (Locale $locale) => $query->whereNull("value->{$locale->locale()}")
            )
        );
    }

    public function scopeByFilledInValues(Builder $query): void
    {
        $query->where(
            fn ($query) => LocaleCollection::each(
                fn (Locale $locale) => $query->whereNotNull("value->{$locale->locale()}")
            )
        );
    }

    public function getCleanScopeAttribute(): string
    {
        $scope = $this->scope;

        if ($scope === ExtractTranslatableStrings::JSON_GROUP) {
            return 'Default';
        }

        if (Str::contains($scope, '/')) {
            return Str::of($scope)
                ->explode('/')
                ->reject(fn (string $part) => $part === 'vendor')
                ->map(fn (string $part) => Str::headline($part))
                ->implode(' > ');
        }

        return Str::headline($scope);
    }

    public static function groupedScopes(): Collection
    {
        return self::query()
            ->select('scope')
            ->distinct()
            ->orderBy('scope')
            ->get()
            ->mapWithKeys(fn (TranslatableString $string) => [$string->scope => $string->clean_scope]);
    }

    public static function groupedScopesWithoutFilament(): Collection
    {
        return self::query()
            ->select('scope')
            ->where('scope', 'not like', 'vendor/filament%')
            ->distinct()
            ->orderBy('scope')
            ->get()
            ->mapWithKeys(fn (TranslatableString $string) => [$string->scope => $string->clean_scope]);
    }

    public static function booted()
    {
        self::updated(fn (self $record) => ExportToLang::dispatch($record->scope));
    }
}
