<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commercial_quotes')) {
            return;
        }

        Schema::table('commercial_quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('commercial_quotes', 'commercial_document_template_id')) {
                $table->bigInteger('commercial_document_template_id')->nullable()->after('assigned_user_id');
            }

            if (!Schema::hasColumn('commercial_quotes', 'template_name_snapshot')) {
                $table->string('template_name_snapshot')->nullable()->after('total');
                $table->string('logo_path_snapshot')->nullable()->after('template_name_snapshot');
                $table->string('header_title_snapshot')->nullable()->after('logo_path_snapshot');
                $table->text('header_text_snapshot')->nullable()->after('header_title_snapshot');
                $table->text('footer_text_snapshot')->nullable()->after('header_text_snapshot');
                $table->text('terms_text_snapshot')->nullable()->after('footer_text_snapshot');
                $table->json('template_options_snapshot')->nullable()->after('terms_text_snapshot');
            }

            $table->index('commercial_document_template_id', 'cq_template_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('commercial_quotes')) {
            return;
        }

        Schema::table('commercial_quotes', function (Blueprint $table) {
            if (Schema::hasColumn('commercial_quotes', 'commercial_document_template_id')) {
                $table->dropIndex('cq_template_idx');
                $table->dropColumn('commercial_document_template_id');
            }

            foreach ([
                'template_name_snapshot',
                'logo_path_snapshot',
                'header_title_snapshot',
                'header_text_snapshot',
                'footer_text_snapshot',
                'terms_text_snapshot',
                'template_options_snapshot',
            ] as $column) {
                if (Schema::hasColumn('commercial_quotes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
