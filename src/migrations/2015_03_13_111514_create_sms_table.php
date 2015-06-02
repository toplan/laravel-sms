<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateSmsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('sms', function(Blueprint $table)
            {
                $table->increments('id');
                $table->string('to')->default('');
                $table->string('temp_id')->default('');
                $table->text('data')->nullable();
                $table->mediumInteger('fail_times')->default(0);
                $table->integer('last_fail_time')->unsigned()->default(0);
                $table->integer('sent_time')->unsigned()->default(0);
                $table->string('result_info')->default('');

                $table->timestamps();
                $table->softDeletes();
                $table->engine = 'InnoDB';
            });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::dropIfExists('sms');
	}

}
