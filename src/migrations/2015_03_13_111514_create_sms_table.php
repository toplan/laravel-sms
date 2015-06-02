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
                //auto increment id
                $table->increments('id');
                //to:用于存储手机号
                $table->string('to')->default('');
                //temp_id:为模板标记/项目标记，用于存储任何第三方平台提供的短信模板标记/id
                $table->string('temp_id')->default('');
                //data:建议json格式
                $table->text('data')->nullable();
                //发送失败次数
                $table->mediumInteger('fail_times')->default(0);
                //最后一次发送失败时间
                $table->integer('last_fail_time')->unsigned()->default(0);
                //发送成功时的时间
                $table->integer('sent_time')->unsigned()->default(0);
                //发送结果,记录发送状态,可用于排错
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
