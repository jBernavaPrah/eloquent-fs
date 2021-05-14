<?php


namespace JBernavaPrah\EloquentFS;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as DB;

class Migration extends \Illuminate\Database\Migrations\Migration
{

    public function up()
    {
        DB::schema()->create('files', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('chunk_size');

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });

        DB::schema()->create('file_chunks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('n');

            $table->string('file_id');
            $table->foreign('file_id')
                ->references('id')
                ->on('files')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->binary('data');

        });
    }

}