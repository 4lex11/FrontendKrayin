<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churn_work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_customer_id')->constrained('churn_dataset_customers')->restrictOnDelete();
            $table->date('as_of');
            $table->string('model_version', 100);
            $table->unsignedInteger('requested_by')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('outcome', 100)->nullable();
            $table->unsignedBigInteger('prediction_id')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
            $table->index(['dataset_customer_id', 'as_of', 'model_version'], 'churn_work_identity');
            $table->index(['status', 'id']);
        });
        Schema::table('churn_prediction_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('work_item_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('churn_prediction_runs', function (Blueprint $table) {
            $table->dropColumn('work_item_id');
        });
        Schema::dropIfExists('churn_work_items');
    }
};
