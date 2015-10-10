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
                //temp_id:存储模板标记，用于存储任何第三方服务商提供的短信模板标记/id
                $table->text('temp_id')->default('');
                //模板data:建议json格式
                $table->text('data')->default('');
                //内容
                $table->text('content')->default('');
                //发送失败次数
                $table->mediumInteger('fail_times')->default(0);
                //最后一次发送失败时间
                $table->integer('last_fail_time')->unsigned()->default(0);
                //发送成功时的时间
                $table->integer('sent_time')->unsigned()->default(0);
                //发送结果,记录发送状态,可用于排错
                $table->text('result_info')->default('');

                $table->timestamps();
                $table->softDeletes();
                $table->engine = 'InnoDB';

                //说明1：temp_id和data用于发送模板短信。
                //说明2：content用于直接发送短信内容，不使用模板。
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
