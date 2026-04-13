<?php

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel;
use Mockery\MockInterface;
use Wotz\LocaleCollection\Facades\LocaleCollection;
use Wotz\LocaleCollection\Locale;
use Wotz\TranslatableStrings\Exports\TranslatableStringsExport;
use Wotz\TranslatableStrings\Filament\Resources\TranslatableStringResource\Pages\ListTranslatableStrings;
use Wotz\TranslatableStrings\Jobs\ExtractAndParseStrings;
use Wotz\TranslatableStrings\Models\TranslatableString;
use Wotz\TranslatableStrings\Tests\Fixtures\Models\User;

beforeEach(function () {
    LocaleCollection::push(new Locale('en'))
        ->push(new Locale('nl'));

    $this->strings = collect([
        createTranslatableString('a scope', 'b name', false, ['en' => 'en c value', 'nl' => 'nl c value']),
        createTranslatableString('d scope', 'e name', false, ['en' => 'en f value', 'nl' => 'nl f value']),
    ]);

    $this->actingAs(User::factory()->create());
});

it('can list translatable strings', function () {
    Livewire::test(ListTranslatableStrings::class)
        ->assertOk()
        ->assertCanSeeTableRecords($this->strings);
});

it('can sort table', function () {
    Livewire::test(ListTranslatableStrings::class)
        ->sortTable('scope')
        ->assertCanSeeTableRecords($this->strings->sortBy('scope'), inOrder: true)
        ->sortTable('name', 'desc')
        ->assertCanSeeTableRecords($this->strings->sortByDesc('name'), inOrder: true)
        ->sortTable('created_at', 'desc')
        ->assertCanSeeTableRecords($this->strings->sortByDesc('created_at'), inOrder: true)
        ->sortTable()
        ->assertCanSeeTableRecords($this->strings->sortBy('created_at'), inOrder: true);
})->skip('breaks for some weird reason');

it('can filter on filled in value', function () {
    $emptyStrings = $this->strings->filter(fn ($string) => blank($string->value));
    $notEmptyStrings = $this->strings->filter(fn ($string) => ! blank($string->value));

    Livewire::test(ListTranslatableStrings::class)
        ->filterTable('filled_in', '')
        ->assertCanSeeTableRecords($this->strings)
        ->filterTable('filled_in', true)
        ->assertCanSeeTableRecords($notEmptyStrings)
        ->assertCanNotSeeTableRecords($emptyStrings)
        ->filterTable('filled_in', false)
        ->assertCanSeeTableRecords($emptyStrings)
        ->assertCanNotSeeTableRecords($notEmptyStrings);
});

it('can filter on scope', function () {
    Livewire::test(ListTranslatableStrings::class)
        ->filterTable('scope', 'd scope')
        ->assertCanSeeTableRecords($this->strings->filter(fn ($string) => $string->scope === 'd scope'))
        ->assertCanNotSeeTableRecords($this->strings->filter(fn ($string) => $string->scope !== 'd scope'));
});

it('has an edit action', function () {
    Livewire::test(ListTranslatableStrings::class)
        ->assertTableActionExists('edit');
});

it('has no delete action', function () {
    Livewire::test(ListTranslatableStrings::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('has no create action', function () {
    Livewire::test(ListTranslatableStrings::class)
        ->assertTableActionDoesNotExist('create');
});

it('has an import action that can throw an error', function () {
    Livewire::test(ListTranslatableStrings::class)
        ->assertActionExists('import')
        ->callAction('import');

    Notification::assertNotified('Something went wrong during the import');
});

it('has an import action that can truncate the table', function () {
    Storage::disk('local')->put(
        'import_truncate.xlsx',
        file_get_contents(__DIR__ . '/../../../../Fixtures/import_truncate.xlsx', 'import_truncate.xlsx')
    );

    Livewire::test(ListTranslatableStrings::class)
        ->assertActionExists('import')
        ->callAction('import', [
            'overwrite' => true,
            'file' => ['file' => 'import_truncate.xlsx'],
        ]);

    Notification::assertNotified(
        Notification::make()
            ->success()
            ->title(__('filament-translatable-strings::admin.import completed'))
    );

    $this->assertDatabaseCount(TranslatableString::class, 2);
    $this->assertDatabaseHas(TranslatableString::class, [
        'scope' => 'a scope',
        'name' => 'b name',
        'value' => json_encode([
            'en' => 'new en value',
            'nl' => 'nieuwe nl waarde',
        ]),
    ]);
    $this->assertDatabaseHas(TranslatableString::class, [
        'scope' => 'd scope',
        'name' => 'e name',
        'value' => '{}',
    ]);
});

it('has an export action', function () {
    $this->instance(
        TranslatableStringsExport::class,
        Mockery::mock(TranslatableStringsExport::class, function (MockInterface $mock) {
            $mock->shouldReceive('download')->once()->with(
                Str::slug(config('app.name') . '_' . today()->toDateString(), '_') . '.xlsx',
                Excel::XLSX
            );
        })
    );

    Livewire::test(ListTranslatableStrings::class)
        ->assertActionExists('export')
        ->callAction('export');
});

it('has an extract and parse action', function () {
    Queue::fake();

    Livewire::test(ListTranslatableStrings::class)
        ->assertActionExists('extract_parse')
        ->callAction('extract_parse');

    Queue::assertPushed(ExtractAndParseStrings::class);

    Notification::assertNotified(
        Notification::make()
            ->success()
            ->title('Extract and Parse strings dispatched')
    );
});
