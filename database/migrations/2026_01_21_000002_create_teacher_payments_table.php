<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_teacher_earning_id');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('batch_teacher_earning_id')->references('id')->on('batch_teacher_earnings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_payments');
    }
};
