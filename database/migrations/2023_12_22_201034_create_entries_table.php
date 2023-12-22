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
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 8, 2)
                ->comment('The amount of the entry. Positive for income, negative for expenses.');
            $table->string('recipient_sender');
            $table->enum('payment_method', ['cash', 'bank_transfer'])->nullable();
            $table->text('description');
            $table->boolean('no_invoice')->default(false);
            $table->date('date')->comment('The date of the entry.');
            $table->foreignId('entry_id')
                ->comment('The previous version of this entry.')
                ->nullable()
                ->constrained('entries')
                ->onDelete('cascade');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
