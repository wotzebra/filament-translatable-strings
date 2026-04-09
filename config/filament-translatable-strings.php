<?php

return [
    'trans_functions' => [
        '__',
        'trans',
        'trans_choice',
        'Lang::get',
        'Lang::choice',
        '@lang',
        '@choice',
    ],
    'html_trans_functions' => [
        '__html',
    ],
    'exclude_folders' => [
        'storage',
        'node_modules',
        'database',
        'lang',
        'vendor/symfony',
        'tests',
    ],

    'skip_export_to_lang' => (bool) env('SKIP_EXPORT_TO_LANG', false),

    /*
    |--------------------------------------------------------------------------
    | Domain Support
    |--------------------------------------------------------------------------
    |
    | Configure domain-specific translations. Set these callbacks in your
    | application to enable domain-specific translation overrides.
    |
    */

    // Callback to fetch available domains dynamically
    // Should return an array: ['identifier' => 'Label', ...]
    // Example: fn () => Domain::all()->mapWithKeys(fn ($d) => [$d->id => $d->name])->toArray()
    'domains_provider' => null,

    // Callback to fetch locales for a specific domain
    // Should return an array: ['nl-be', 'fr-be', ...]
    // Example: fn (string $identifier) => Domain::find($identifier)->locales()
    'domain_locales_provider' => null,
];
