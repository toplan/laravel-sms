/*
 * toplan/laravel-sms package
 * js file for send sms with verify code
 *---------------------------
 * author top lan <toplan710@gmail.com>
 * --------------------------
 * 2015/06/05
 *
 * example usage:
 *   $('#sendVerifySmsButton').sms({
 *       mobileSelector : 'input[name="mobile"]'
 *       alertMsg       : function (msg) {
 *           alert(msg);
 *        }
 *   });
 */
(function($){

    $.fn.sms = function(options){
        var opts = $.extend(
            $.fn.sms.default,
            options,
            {btnContent: this.html()}
        );
        this.click(function(){
            //开始发送
            var _this = $(this);
            _this.html('短信发送中...');
            _this.prop('disabled', true);
            sendSms(opts, _this)
        });
    };

    function sendSms(opts, elem) {
        var mobile = $(opts.mobileSelector).val();
        $.ajax({
            url  : '/sms/verify-code/rule/' + opts.rule + '/mobile/' + mobile,
            //url  : '/sms/send-code?mobile=' + mobile,
            type : 'get'
        }).success(function (data) {
            console.log(data);
           if (data.success) {
               timer(elem, opts.seconds, opts.btnContent)
           } else {
               elem.html(opts.btnContent);
               elem.prop('disabled', false);
               opts.alertMsg(data.msg);
           }
        }).fail(function () {
            opts.alertMsg('请求失败，请重试');
            elem.html(opts.btnContent);
            elem.prop('disabled', false);
        });
    }

    function timer(elem, seconds, btnContent){
        if(seconds >= 0){
            setTimeout(function(){
                //显示倒计时
                elem.html(seconds + ' 秒后再次发送');
                //递归
                seconds -= 1;
                timer(elem, seconds, btnContent);
            }, 1000);
        }else{
            elem.html(btnContent);
            elem.prop('disabled', false);
        }
    }

    $.fn.sms.default = {
        rule           : 'check_mobile_unique',
        mobileSelector : '',
        seconds        : 60,
        btnContent     : '',
        alertMsg       : function (msg) {
            alert(msg);
        }
    };

})(jQuery);