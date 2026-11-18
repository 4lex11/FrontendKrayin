<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churn_sales_lines', function (Blueprint $table): void {
            // Existing lines are enriched from the original CSV in a separate import.
            $table->date('ship_date')->nullable();
            $table->string('ship_mode', 100)->nullable();
            $table->string('segment', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('product_id', 100)->nullable();
            $table->string('product_name', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('churn_sales_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'ship_date', 'ship_mode', 'segment', 'country', 'city',
                'state', 'postal_code', 'region', 'product_id', 'product_name',
            ]);
        });
    }
};
