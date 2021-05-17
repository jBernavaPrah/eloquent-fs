<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        DB::schema()->create('file_chunks', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->integer('n');
            $table->string('file_id');
            $table->binary('data');

            $table->foreign('file_id')
                ->references('id')
                ->on('files')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->index(['file_id', 'n']);

        });
    }

};