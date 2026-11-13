<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churn_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_customer_id')->constrained('churn_dataset_customers')->restrictOnDelete();
            $table->date('as_of');
            $table->string('model_version', 100);
            $table->string('status', 20);
            $table->unsignedInteger('responsible_id');
            $table->string('responsible_name');
            $table->unsignedInteger('author_id');
            $table->string('author_name');
            $table->text('note');
            $table->dateTime('created_at', 6);
            $table->index(['dataset_customer_id', 'as_of', 'model_version'], 'churn_review_context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churn_reviews');
    }
};
