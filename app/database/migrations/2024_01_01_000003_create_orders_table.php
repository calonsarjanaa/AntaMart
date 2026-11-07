<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_email')->nullable()->index();
            $table->string('guest_phone', 30)->nullable();
            $table->string('status', 50)->default('pending_payment')->index();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_cost', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2);
            $table->string('idempotency_key')->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['id', 'guest_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

