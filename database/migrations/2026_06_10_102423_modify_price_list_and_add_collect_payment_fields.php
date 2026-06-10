<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تعديل priceList من enum إلى string
        Schema::table('orders', function (Blueprint $table) {
            $table->string('priceList', 50)->nullable()->change();
        });

        // إضافة حقلي تحصيل الدفع (كاش وحوالة)
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('collect_payment_cash')->nullable()->after('collectPayment');
            $table->timestamp('collect_payment_hawala')->nullable()->after('collect_payment_cash');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // لا يمكن إعادة enum بسهولة, لكن يمكن إرجاعه إلى نص
            $table->string('priceList', 50)->nullable()->change();
            $table->dropColumn(['collect_payment_cash', 'collect_payment_hawala']);
        });
    }
};