<?php

namespace Wotz\TranslatableStrings\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Wotz\LocaleCollection\Facades\LocaleCollection;
use Wotz\LocaleCollection\Locale;
use Wotz\TranslatableStrings\Ai\Resources\TranslatableStringResource;
use Wotz\TranslatableStrings\Models\TranslatableString;

class ListEmptyTranslationsTool implements Tool
{
    public function description(): string
    {
        return 'List all translatable strings that have at least one missing translation. Optionally filter by locale or scope.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'locale' => $schema
                ->string()
                ->nullable()
                ->description('Filter by a specific locale (e.g. "nl", "fr"). Leave empty to include all locales.'),
            'scope' => $schema
                ->string()
                ->nullable()
                ->description('Filter by a specific scope/group (e.g. "auth", "vendor/filament/filament"). Leave empty to include all scopes.'),
        ];
    }

    public function handle(Request $request): string
    {
        $locale = (string) $request->string('locale') ?: null;
        $scope = (string) $request->string('scope') ?: null;

        $records = TranslatableString::query()
            ->byOneEmptyValue()
            ->when($locale, fn ($query) => $query->whereNull("value->{$locale}"))
            ->when($scope, fn ($query) => $query->where('scope', $scope))
            ->get();

        if ($records->isEmpty()) {
            return 'No missing translations found.';
        }

        $locales = LocaleCollection::map(fn (Locale $locale) => $locale->locale())->all();
        $localesToCheck = $locale ? [$locale] : $locales;

        return $records
            ->map(fn (TranslatableString $record) => new TranslatableStringResource($record, $localesToCheck))
            ->toJson(JSON_PRETTY_PRINT);
    }
}
