<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->bigInteger('amount')
                ->comment('The amount of the entry in cents.')
                ->nullable();
            $table->boolean('is_income')->comment('True if the entry is an income, false if it is an expense. Separate field as amount might be empty, but user still wants to select if it is an income or expense.');
            $table->string('recipient_sender');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'not_payed'])->default('not_payed');
            $table->text('description');
            $table->boolean('no_invoice')->default(false);
            $table->date('date')->comment('The date of the entry.');
            $table->foreignId('entry_id')
                ->comment('The previous version of this entry.')
                ->nullable()
                ->constrained('entries')
                ->onDelete('cascade');
            $table->foreignId('user_id_last_modified')->nullable()->constrained('users')->onDelete('cascade');


            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('entries');
    }
};
