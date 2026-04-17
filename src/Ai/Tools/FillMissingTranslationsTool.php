<?php

namespace Wotz\TranslatableStrings\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Wotz\LocaleCollection\Facades\LocaleCollection;
use Wotz\LocaleCollection\Locale;
use Wotz\TranslatableStrings\Ai\Data\TranslationData;
use Wotz\TranslatableStrings\Ai\Resources\TranslatableStringResource;
use Wotz\TranslatableStrings\Models\TranslatableString;

class FillMissingTranslationsTool implements Tool
{
    public function description(): string
    {
        return <<<'DESC'
        Fill in missing translations in two sequential steps.

        STEP 1 — Discover: Call this tool with only scope and/or locale (leave translations empty).
        You will receive a list of strings that need translations.

        STEP 2 — Save: Call this tool again with the "translations" array filled in.
        Generate appropriate translation values for every missing locale based on the key name,
        scope, and any existing values. Do NOT call step 2 in the same request as step 1.

        Never ask the user to provide translation values — always generate them yourself.
        DESC;
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
                ->description('Filter by a specific scope/group (e.g. "auth", "webinar"). Leave empty to include all scopes.'),
            'translations' => $schema
                ->array()
                ->nullable()
                ->items(
                    $schema->object([
                        'scope' => $schema->string()->required()
                            ->description('The scope of the translatable string.'),
                        'key' => $schema->string()->required()
                            ->description('The key of the translatable string.'),
                        'values' => $schema->object()->required()
                            ->description('Object mapping locale codes to your generated translation values. Example: {"nl": "Hallo", "fr": "Bonjour"}. Must include a value for every locale listed in missing_locales.'),
                    ])
                )
                ->description('Only provide this in step 2, after receiving the list from step 1. Each item must contain scope, key, and values.'),
        ];
    }

    public function handle(Request $request): string
    {
        $locale = (string) $request->string('locale') ?: null;
        $scope = (string) $request->string('scope') ?: null;
        $translations = $request->array('translations');

        if (empty($translations)) {
            return $this->listMissing($locale, $scope);
        }

        return $this->saveAll($translations);
    }

    private function listMissing(?string $locale, ?string $scope): string
    {
        $records = TranslatableString::query()
            ->byOneEmptyValue()
            ->when($locale, fn ($query) => $query->whereNull("value->$locale"))
            ->when($scope, fn ($query) => $query->where('scope', $scope))
            ->get();

        if ($records->isEmpty()) {
            return 'No missing translations found.';
        }

        $locales = LocaleCollection::map(fn (Locale $locale) => $locale->locale())->all();
        $localesToCheck = $locale ? [$locale] : $locales;

        $list = $records
            ->map(fn (TranslatableString $record) => new TranslatableStringResource($record, $localesToCheck))
            ->toJson(JSON_PRETTY_PRINT);

        $count = $records->count();

        return "Step 1 complete. Found $count string(s) with missing translations. "
            . "Now call this tool again with the 'translations' array filled in with your generated values for every missing locale:\n\n$list";
    }

    private function saveAll(array $translations): string
    {
        $validLocales = LocaleCollection::map(fn (Locale $locale) => $locale->locale());

        $results = collect($translations)->map(function (array $item) use ($validLocales) {
            if (empty($item['scope']) || empty($item['key']) || empty($item['values'] ?? [])) {
                return ['error' => 'Invalid item: missing scope, key, or values.'];
            }

            $translationData = TranslationData::fromArray($item);

            /** @var TranslatableString|null $record */
            $record = TranslatableString::query()
                ->where('scope', $translationData->scope)
                ->where('key', $translationData->key)
                ->first();

            if (! $record) {
                return ['error' => "No record found for scope \"$translationData->scope\", key \"$translationData->key\"."];
            }

            $valid = collect($translationData->values)
                ->filter(fn ($value, $locale) => $validLocales->contains($locale));

            if ($valid->isEmpty()) {
                return ['error' => "No valid locales for scope \"$translationData->scope\", key \"$translationData->key\". "
                    . "Known locales: {$validLocales->implode(', ')}."];
            }

            $valid->each(fn ($value, $locale) => $record->setTranslation('value', $locale, $value));
            $record->save();

            return ['saved' => "\"$translationData->scope > $translationData->key\""];
        });

        $saved = $results->pluck('saved')->filter();
        $errors = $results->pluck('error')->filter();

        return collect()
            ->when($saved->isNotEmpty(), fn ($parts) => $parts->push('Saved translations for: ' . $saved->implode(', ') . '.'))
            ->when($errors->isNotEmpty(), fn ($parts) => $parts->push('Errors: ' . $errors->implode('; ')))
            ->implode(' ') ?: 'No translations were saved.';
    }
}
