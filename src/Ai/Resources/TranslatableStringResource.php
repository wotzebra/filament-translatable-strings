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
        return [
            'scope' => $this->resource->scope,
            'key' => $this->resource->key,
            'missing_locales' => array_values(Arr::where(
                $this->localesToCheck,
                fn (string $loc) => ! $this->resource->getTranslation('value', $loc, false)
            )),
        ];
    }
}
