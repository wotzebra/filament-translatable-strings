<?php

namespace Wotz\TranslatableStrings\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Wotz\LocaleCollection\Facades\LocaleCollection;
use Wotz\LocaleCollection\Locale;
use Wotz\TranslatableStrings\Models\TranslatableString;

class SaveTranslationsTool implements Tool
{
    public function description(): string
    {
        return 'Save translation values for a specific translatable string identified by its scope and key.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema
                ->string()
                ->required()
                ->description('The scope/group of the translatable string (e.g. "auth", "vendor/filament/filament").'),
            'key' => $schema
                ->string()
                ->required()
                ->description('The key of the translatable string (e.g. "failed").'),
            'translations' => $schema
                ->object()
                ->required()
                ->description('An object mapping locale codes to their translation values (e.g. {"en": "Hello", "nl": "Hallo"}).'),
        ];
    }

    public function handle(Request $request): string
    {
        $scope = (string) $request->string('scope');
        $key = (string) $request->string('key');
        $translations = $request->array('translations');

        $record = TranslatableString::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        if (! $record) {
            return "No translatable string found for scope \"{$scope}\" and key \"{$key}\".";
        }

        $validLocales = LocaleCollection::map(fn (Locale $locale) => $locale->locale());

        [$valid, $skipped] = collect($translations)->partition(
            fn ($value, $locale) => $validLocales->contains($locale)
        );

        if ($valid->isEmpty()) {
            return "No valid locales provided. Known locales: {$validLocales->implode(', ')}.";
        }

        $valid->each(fn ($value, $locale) => $record->setTranslation('value', $locale, $value));
        $record->save();

        $message = "Saved locales [{$valid->keys()->implode(', ')}] for \"{$scope} > {$key}\".";

        if ($skipped->isNotEmpty()) {
            $message .= " Skipped unknown locales: {$skipped->keys()->implode(', ')}.";
        }

        return $message;
    }
}
