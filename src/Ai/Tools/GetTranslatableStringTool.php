<?php

namespace Wotz\TranslatableStrings\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Wotz\TranslatableStrings\Models\TranslatableString;

class GetTranslatableStringTool implements Tool
{
    public function description(): string
    {
        return 'Get the existing translation values for a specific translatable string by scope and key. Use this for stylistic context before generating new translations.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema
                ->string()
                ->required()
                ->description('The scope/group of the translatable string (e.g. "auth").'),
            'key' => $schema
                ->string()
                ->required()
                ->description('The key of the translatable string (e.g. "failed").'),
        ];
    }

    public function handle(Request $request): string
    {
        $scope = (string) $request->string('scope');
        $key = (string) $request->string('key');

        $record = TranslatableString::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        if (! $record) {
            return "No translatable string found for scope \"{$scope}\" and key \"{$key}\".";
        }

        return json_encode([
            'scope' => $record->scope,
            'key' => $record->key,
            'values' => Arr::where(
                $record->getTranslations('value'),
                fn (string $value) => $value !== ''
            ),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
