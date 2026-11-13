<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churn_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_customer_id')->constrained('churn_dataset_customers')->restrictOnDelete();
            $table->date('as_of');
            $table->string('model_version', 100);
            $table->string('schema_version', 100);
            $table->unsignedSmallInteger('horizon_days');
            $table->double('probability');
            $table->string('risk', 10);
            $table->boolean('limited_history');
            $table->boolean('automatic_action_allowed')->default(false);
            $table->string('model_status', 30);
            $table->string('input_sha256', 64);
            $table->dateTime('predicted_at', 6);
            $table->json('response');
            $table->timestamps();
            $table->unique(['dataset_customer_id', 'as_of', 'model_version'], 'churn_prediction_identity');
            $table->index(['dataset_customer_id', 'as_of']);
        });
        Schema::create('churn_prediction_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_customer_id')->constrained('churn_dataset_customers')->restrictOnDelete();
            $table->date('as_of');
            $table->string('input_sha256', 64);
            $table->string('status', 20);
            $table->foreignId('prediction_id')->nullable()->constrained('churn_predictions')->restrictOnDelete();
            $table->string('error_code', 100)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedSmallInteger('attempts')->nullable();
            $table->dateTime('started_at', 6);
            $table->dateTime('finished_at', 6)->nullable();
            $table->index(['dataset_customer_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churn_prediction_runs');
        Schema::dropIfExists('churn_predictions');
    }
};
