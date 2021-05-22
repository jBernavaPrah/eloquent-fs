<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('fs_file_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->integer('n');
            $table->binary('data');


            $table->foreignUuid('fs_file_id')
                ->references('id')
                ->on('fs_files')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->index(['fs_file_id', 'n']);

        });
    }

};
