<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_document_templates')) {
            return;
        }

        Schema::create('commercial_document_templates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->string('name', 120);
            $table->string('document_type', 30)->default('quote');
            $table->boolean('is_default')->default(false);
            $table->string('logo_path')->nullable();
            $table->string('header_title')->nullable();
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('terms_text')->nullable();
            $table->string('accent_style', 40)->nullable();
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_contact_info')->default(true);
            $table->boolean('show_fiscal_info')->default(false);
            $table->boolean('show_item_tax')->default(true);
            $table->boolean('show_item_sku')->default(true);
            $table->boolean('show_notes')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['users_id', 'document_type'], 'cdt_user_type_idx');
            $table->index(['users_id', 'document_type', 'is_default'], 'cdt_default_idx');
            $table->index(['users_id', 'document_type', 'is_active'], 'cdt_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_document_templates');
    }
};
