<?php

namespace Wotz\TranslatableStrings\Ai\Data;

class TranslationData
{
    public function __construct(
        public readonly string $scope,
        public readonly string $key,
        public readonly array $values,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            scope: $data['scope'],
            key: $data['key'],
            values: $data['values'],
        );
    }
}