<?php

namespace Wotz\TranslatableStrings\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Wotz\TranslatableStrings\Imports\Sheets\TranslatableStringScopeSheet;
use Wotz\TranslatableStrings\Models\TranslatableString;

class TranslatableStringsImport implements Import, SkipsUnknownSheets, WithMultipleSheets
{
    public function sheets(): array
    {
        HeadingRowFormatter::default('none');

        return TranslatableString::groupedScopesWithoutFilament()->mapWithKeys(function ($title, $scope) {
            return [
                $title => new TranslatableStringScopeSheet($scope),
            ];
        })->toArray();
    }

    public function onUnknownSheet(string|int $sheetName): void {}
}
