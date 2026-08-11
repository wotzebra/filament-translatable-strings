<?php

namespace Wotz\TranslatableStrings\Filament\Resources\TranslatableStringResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Wotz\TranslatableStrings\Filament\Resources\TranslatableStringResource;

class EditTranslatableString extends EditRecord
{
    protected static string $resource = TranslatableStringResource::class;
}
