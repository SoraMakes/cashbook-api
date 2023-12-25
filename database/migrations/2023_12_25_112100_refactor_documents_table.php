<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RefactorDocumentsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('documents', function (Blueprint $table) {
            // Rename file_path to original_file
            $table->renameColumn('file_path', 'original_path');

            // Add new column document_path
            $table->string('document_path')->nullable()->after('original_file');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('documents', function (Blueprint $table) {
            // Revert the column name change
            $table->renameColumn('original_path', 'file_path');

            // Remove the document_path column
            $table->dropColumn('document_path');
        });
    }
}
