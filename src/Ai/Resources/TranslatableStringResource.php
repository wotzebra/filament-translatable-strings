<?php

namespace Wotz\TranslatableStrings\Ai\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class TranslatableStringResource extends JsonResource
{
    public function __construct($resource, private readonly array $localesToCheck)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        $missingLocales = array_values(Arr::where(
            $this->localesToCheck,
            fn (string $loc) => ! $this->resource->getTranslation('value', $loc, false)
        ));

        $existingValues = Arr::where(
            $this->resource->getTranslations('value'),
            fn (string $value) => $value !== ''
        );

        return [
            'id' => $this->resource->id,
            'scope' => $this->resource->scope,
            'key' => $this->resource->key,
            'missing_locales' => $missingLocales,
            'existing_values' => $existingValues,
        ];
    }
}
