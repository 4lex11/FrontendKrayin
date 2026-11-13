<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churn_datasets', function (Blueprint $table) {
            $table->string('source', 40)->primary();
            $table->string('sha256', 64)->nullable();
            $table->date('default_cutoff')->nullable();
            $table->boolean('is_demo')->default(true);
            $table->timestamp('imported_at')->nullable();
        });
        DB::table('churn_datasets')->insert(['source' => 'superstore-sales']);
        Schema::create('churn_dataset_customers', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);
            $table->string('external_customer_id', 100);
            $table->unsignedInteger('person_id');
            $table->string('dataset_name', 100);
            $table->string('link_method', 30);
            $table->timestamps();
            $table->unique(['source', 'external_customer_id'], 'churn_customer_external_unique');
            $table->unique(['source', 'person_id'], 'churn_customer_person_unique');
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->foreign('source')->references('source')->on('churn_datasets')->restrictOnDelete();
        });
        Schema::create('churn_sales_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_customer_id')->constrained('churn_dataset_customers')->restrictOnDelete();
            $table->string('source', 40);
            $table->string('line_id', 100);
            $table->string('order_id', 100);
            $table->date('order_date');
            $table->decimal('sales', 18, 6);
            $table->string('category', 100);
            $table->string('subcategory', 100);
            $table->timestamps();
            $table->unique(['source', 'line_id'], 'churn_line_external_unique');
            $table->index(['dataset_customer_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churn_sales_lines');
        Schema::dropIfExists('churn_dataset_customers');
        Schema::dropIfExists('churn_datasets');
    }
};
