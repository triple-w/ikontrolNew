<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commercial_quote_taxes') || Schema::hasColumn('commercial_quote_taxes', 'tax_mode')) {
            return;
        }

        Schema::table('commercial_quote_taxes', function (Blueprint $table) {
            $table->string('tax_mode', 20)->default('rate')->after('tax_type');
            $table->index('tax_mode', 'cqt_mode_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('commercial_quote_taxes') || !Schema::hasColumn('commercial_quote_taxes', 'tax_mode')) {
            return;
        }

        Schema::table('commercial_quote_taxes', function (Blueprint $table) {
            $table->dropIndex('cqt_mode_idx');
            $table->dropColumn('tax_mode');
        });
    }
};
