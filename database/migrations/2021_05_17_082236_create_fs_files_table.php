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

        Schema::create('fs_files', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->integer('chunk_size');
            $table->integer('size')->default(0);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index('metadata');

        });

    }

};
