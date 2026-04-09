<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translatable_strings', function (Blueprint $table) {
            $table->boolean('use_on_all_domains')->default(true)->after('is_html');
            $table->json('domain_values')->nullable()->after('use_on_all_domains');
        });
    }

    public function down(): void
    {
        Schema::table('translatable_strings', function (Blueprint $table) {
            $table->dropColumn(['use_on_all_domains', 'domain_values']);
        });
    }
};
