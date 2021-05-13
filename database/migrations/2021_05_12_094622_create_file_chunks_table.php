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
        Schema::create('file_chunks', function (Blueprint $table) {
            //$table->string('id')->primary();
            $table->bigIncrements('id');

            $table->integer('n');

            $table->foreignId('file_id')
                ->references('id')
                ->on('files')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->binary('data');

        });
    }

};
