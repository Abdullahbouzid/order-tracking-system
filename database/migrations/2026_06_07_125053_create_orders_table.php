<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('customerName')->nullable();
            $table->string('customerContactNumber')->nullable();
            $table->string('orderId')->unique();
            $table->date('orderDate');
            $table->enum('priceList', ['cash', 'half_half', 'hawala'])->nullable()->comment('نوع الدفع: كاش - 50% 50% - حوالة');
            $table->decimal('total', 10, 2)->nullable();
            $table->string('employeeName')->nullable(); // اسم المنشئ (من جدول users)
            $table->timestamp('orderForApprove')->nullable();
            $table->timestamp('orderApproved')->nullable();
            $table->timestamp('orderForPayment')->nullable();
            $table->timestamp('collectPayment')->nullable();
            $table->timestamp('sellApprove')->nullable();
            $table->timestamp('releaseApprove')->nullable();
            $table->timestamp('startPreparation')->nullable();
            $table->timestamp('readyToDeliver')->nullable();
            $table->timestamp('outForDeliver')->nullable();
            $table->timestamp('delivered')->nullable();
            $table->string('currentStatus')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};