<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visas', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('name');
            $table->string('phone',11);
            $table->string('passport',20);

            // Foreign Keys
            $table->foreignId('country_id')
                ->constrained('countries')
                ->cascadeOnDelete();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->date('date')->nullable();

            // Financial
            $table->decimal('asset_valuation',12,2)->nullable();
            $table->decimal('salary_amount',12,2)->nullable();

            // Documents
            $table->string('image')->nullable();
            $table->string('bank_certificate')->nullable();
            $table->string('nid_file')->nullable();
            $table->string('birth_certificate')->nullable();
            $table->string('marriage_certificate')->nullable();
            $table->string('fixed_deposit_certificate')->nullable();
            $table->string('tax_certificate')->nullable();
            $table->string('tin_certificate')->nullable();
            $table->string('credit_card_copy')->nullable();
            $table->string('covid_certificate')->nullable();
            $table->string('noc_letter')->nullable();
            $table->string('office_id')->nullable();
            $table->string('salary_slips')->nullable();
            $table->string('government_order')->nullable();
            $table->string('visiting_card')->nullable();
            $table->string('company_bank_statement')->nullable();
            $table->string('blank_office_pad')->nullable();
            $table->string('renewal_trade_license')->nullable();
            $table->string('memorandum_limited')->nullable();

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visas');
    }
};
