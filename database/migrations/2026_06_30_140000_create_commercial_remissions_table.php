<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_remissions')) {
            return;
        }

        Schema::create('commercial_remissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('users_id');
            $table->unsignedBigInteger('commercial_quote_id')->nullable();
            $table->unsignedBigInteger('commercial_client_id');
            $table->unsignedBigInteger('commercial_contact_id')->nullable();
            $table->bigInteger('fiscal_client_id')->nullable();
            $table->unsignedBigInteger('commercial_document_template_id')->nullable();
            $table->unsignedBigInteger('created_by_id');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('folio_prefix', 20)->default('REM');
            $table->unsignedBigInteger('folio_number');
            $table->string('folio', 40);
            $table->date('issue_date');
            $table->string('currency', 3)->default('MXN');
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('status', 40)->default('draft');
            $table->decimal('global_discount_amount', 18, 6)->default(0);
            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('line_discount_total', 18, 6)->default(0);
            $table->decimal('discount_total', 18, 6)->default(0);
            $table->decimal('transfers_total', 18, 6)->default(0);
            $table->decimal('withholdings_total', 18, 6)->default(0);
            $table->decimal('tax_total', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            $table->text('notes_visible')->nullable();
            $table->text('notes_internal')->nullable();
            $table->text('conditions')->nullable();
            $table->string('template_name_snapshot')->nullable();
            $table->string('logo_path_snapshot')->nullable();
            $table->string('header_title_snapshot')->nullable();
            $table->text('header_text_snapshot')->nullable();
            $table->text('footer_text_snapshot')->nullable();
            $table->text('terms_text_snapshot')->nullable();
            $table->json('template_options_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['users_id', 'folio'], 'cr_user_folio_uq');
            $table->index(['users_id', 'folio_number'], 'cr_user_num_idx');
            $table->index('created_by_id', 'cr_creator_idx');
            $table->index('assigned_user_id', 'cr_assigned_idx');
            $table->index('commercial_quote_id', 'cr_quote_idx');
            $table->index('commercial_client_id', 'cr_client_idx');
            $table->index('commercial_contact_id', 'cr_contact_idx');
            $table->index('fiscal_client_id', 'cr_fiscal_idx');
            $table->index('commercial_document_template_id', 'cr_tpl_idx');
            $table->index('status', 'cr_status_idx');
            $table->index('issue_date', 'cr_issue_idx');

            $table->foreign('commercial_quote_id', 'cr_quote_fk')->references('id')->on('commercial_quotes')->nullOnDelete();
            $table->foreign('commercial_client_id', 'cr_client_fk')->references('id')->on('commercial_clients')->restrictOnDelete();
            $table->foreign('commercial_contact_id', 'cr_contact_fk')->references('id')->on('commercial_contacts')->nullOnDelete();
            $table->foreign('commercial_document_template_id', 'cr_tpl_fk')->references('id')->on('commercial_document_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_remissions');
    }
};
