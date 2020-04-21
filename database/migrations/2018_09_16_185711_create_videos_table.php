<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('download_id');
            $table->string('title');
            $table->string('mediakey');
            $table->string('disk');
            $table->string('path');
            $table->string('target');
            $table->string('file')->nullable();
            $table->boolean('processed')->default(false);
            $table->datetime('converted_at')->nullable();
            $table->datetime('downloaded_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('videos');
    }
}
