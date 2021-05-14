<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Capsule\Manager as DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::schema()->create('files', function (Blueprint $table) {
            //$table->string('id')->primary();
            $table->bigIncrements('id');
            $table->integer('chunk_size');

            $table->string('filename')->nullable();
            $table->string('mime')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });
    }

};
