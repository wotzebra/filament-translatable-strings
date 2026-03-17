<?php

namespace Wotz\TranslatableStrings\Filament\Resources;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Wotz\LocaleCollection\Facades\LocaleCollection;
use Wotz\LocaleCollection\Locale;
use Wotz\TranslatableStrings\Filament\Resources\TranslatableStringResource\Pages;
use Wotz\TranslatableStrings\Models\Builders\TranslatableStringBuilder;
use Wotz\TranslatableStrings\Models\TranslatableString;
use Wotz\TranslatableTabs\Forms\TranslatableTabs;
use Wotz\TranslatableTabs\Tables\LocalesColumn;

class TranslatableStringResource extends Resource
{
    protected static ?string $model = TranslatableString::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make()
                    ->icon(fn (string $locale, \Filament\Schemas\Components\Utilities\Get $get) => 'heroicon-o-' . (
                        empty($get("{$locale}.value")) ? 'x-circle' : 'check-circle'
                    ))
                    ->defaultFields([
                        TextInput::make('scope')
                            ->label(__('filament-translatable-strings::admin.scope'))
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('name')
                            ->label(__('filament-translatable-strings::admin.name'))
                            ->disabled()
                            ->dehydrated(false),

                        Checkbox::make('is_html')
                            ->label(__('filament-translatable-strings::admin.is html'))
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->translatableFields(function (TranslatableString $record) {
                        if ($record->is_html) {
                            return [
                                RichEditor::make('value')
                                    ->label(__('filament-translatable-strings::admin.value')),
                            ];
                        }

                        return [
                            TextInput::make('value')
                                ->label(__('filament-translatable-strings::admin.value')),
                        ];
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    TextColumn::make('created_at')
                        ->dateTime()
                        ->label(__('filament-translatable-strings::admin.created at'))
                        ->sortable(),

                    TextColumn::make('clean_scope')
                        ->label(__('filament-translatable-strings::admin.scope'))
                        ->sortable(['scope'])
                        ->searchable(['scope']),

                    TextColumn::make('name')
                        ->label(__('filament-translatable-strings::admin.name'))
                        ->sortable()
                        ->searchable(),

                    TextColumn::make('key')
                        ->label(__('filament-translatable-strings::admin.key'))
                        ->hidden()
                        ->searchable(),

                    LocalesColumn::make('locales')
                        ->attribute('value'),

                ]),
                Panel::make([
                    Stack::make([
                        ViewColumn::make('value')
                            ->view('filament-translatable-strings::table.value-column')
                            ->searchable(
                                query: fn (Builder $query, string $search) => $query->where(
                                    fn ($query) => LocaleCollection::each(
                                        fn (Locale $locale) => $query->whereRaw(
                                            'LOWER(json_unquote(json_extract(value, \'$."' . $locale->locale() . '"\'))) LIKE ? ',
                                            ['%' . Str::lower($search) . '%']
                                        )
                                    )
                                ),
                            ),
                    ]),
                ])->collapsible(),
            ])
            ->filters([
                TernaryFilter::make('filled_in')
                    ->label(__('filament-translatable-strings::admin.filled in'))
                    ->placeholder(__('filament-translatable-strings::admin.all records'))
                    ->trueLabel(__('filament-translatable-strings::admin.only filled in records'))
                    ->falseLabel(__('filament-translatable-strings::admin.only not filled in records'))
                    ->queries(
                        true: fn (TranslatableStringBuilder $query) => $query->byFilledInValues(),
                        false: fn (TranslatableStringBuilder $query) => $query->byOneEmptyValue(),
                        blank: fn (TranslatableStringBuilder $query) => $query,
                    ),

                SelectFilter::make('scope')
                    ->options(fn () => TranslatableString::groupedScopes()->toArray())
                    ->label(__('filament-translatable-strings::admin.scope'))
                    ->placeholder(__('filament-translatable-strings::admin.all scopes')),
            ])
            ->recordUrl(null)
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTranslatableStrings::route('/'),
            'edit' => Pages\EditTranslatableString::route('/{record}/edit'),
        ];
    }

    public static function getTranslatableLocales(): array
    {
        return LocaleCollection::map(fn (Locale $locale) => $locale->locale())->toArray();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::byOneEmptyValue()->count()
            . ' '
            . __('filament-translatable-strings::admin.empty badge');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-translatable-strings::admin.navigation label');
    }

    public static function getTitleCaseModelLabel(): string
    {
        return self::getNavigationLabel();
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return self::getNavigationLabel();
    }
}
