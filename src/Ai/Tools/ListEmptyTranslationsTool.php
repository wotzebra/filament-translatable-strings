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
    private const DEFAULT_LIMIT = 50;

    public function description(): string
    {
        return 'List translatable strings that have at least one missing translation. Returns scope, key and missing locales only — use the get-translatable-string tool to see existing values for context.';
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
            'limit' => $schema
                ->integer()
                ->nullable()
                ->description('Maximum number of records to return. Defaults to ' . self::DEFAULT_LIMIT . '.'),
        ];
    }

    public function handle(Request $request): string
    {
        $locale = (string) $request->string('locale') ?: null;
        $scope = (string) $request->string('scope') ?: null;
        $limit = (int) $request->integer('limit') ?: self::DEFAULT_LIMIT;

        $query = TranslatableString::query()
            ->byOneEmptyValue()
            ->when($locale, fn ($query) => $query->whereNull("value->{$locale}"))
            ->when($scope, fn ($query) => $query->where('scope', $scope));

        $total = $query->count();

        if ($total === 0) {
            return 'No missing translations found.';
        }

        $records = $query->limit($limit)->get();

        $localesToCheck = $locale
            ? [$locale]
            : LocaleCollection::map(fn (Locale $locale) => $locale->locale())->all();

        return json_encode([
            'total' => $total,
            'returned' => $records->count(),
            'records' => $records->map(fn (TranslatableString $record) => (new TranslatableStringResource($record, $localesToCheck))->resolve()),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
