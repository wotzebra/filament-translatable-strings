<?php

namespace Wotz\TranslatableStrings\Imports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Wotz\LocaleCollection\Facades\LocaleCollection;
use Wotz\LocaleCollection\Locale;
use Wotz\TranslatableStrings\Jobs\ExportToLang;
use Wotz\TranslatableStrings\Models\TranslatableString;

class TranslatableStringScopeSheet implements ToCollection, WithHeadingRow
{
    public function __construct(
        private string $scope
    ) {}

    public function collection(Collection $rows)
    {
        $rows->each(function (Collection $row) {
            if (! $row->has('name') || $row->get('name') === '') {
                return;
            }

            /** @var TranslatableString $string */
            $string = TranslatableString::where('name', $row->get('name'))
                ->where('scope', $this->scope)
                ->first();

            if (! $string) {
                return;
            }

            LocaleCollection::each(function (Locale $locale) use ($row, &$string) {
                if (! $row->has($locale->locale())) {
                    return;
                }

                $string->setTranslation('value', $locale->locale(), $row->get($locale->locale()));
            });

            $string->save();
        });

        ExportToLang::dispatch($this->scope);
    }
}
