/*
 * send verify sms
 *---------------------------
 * top lan <toplan710@gmail.com>
 * https://github.com/toplan/laravel-sms
 * --------------------------
 * Date 2015/06/08
 */
(function($){

    $.fn.sms = function(options){
        var opts = $.extend(
            $.fn.sms.default,
            options
        );
        $(document).on('click', this.selector, function(e){
            var _this = $(this);
            opts = $.extend(
                opts,
                {btnContent: _this.html()}
            );
            _this.html('短信发送中...');
            _this.prop('disabled', true);
            sendSms(opts, _this)
        });
    };

    function sendSms(opts, elem) {
        var mobile = $(opts.mobileSelector).val();
        var url = '/sms/verify-code/mobile/' + mobile + '/rule/' + opts.mobileRule;
        if (opts.voice) {
            url = '/sms/voice-verify/mobile/' + mobile + '/rule/' + opts.mobileRule;
        }
        $.ajax({
            url  : url,
            type : 'post',
            data : {_token:opts.token, seconds:opts.seconds, uuid:opts.uuid},
            success : function (data) {
               if (data.success) {
                   timer(elem, opts.seconds, opts.btnContent)
               } else {
                   elem.html(opts.btnContent);
                   elem.prop('disabled', false);
                   opts.alertMsg(data.message, data.type);
               }
            },
            error: function(xhr, type){
                elem.html(opts.btnContent);
                elem.prop('disabled', false);
                opts.alertMsg('请求失败，请重试');
            }
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
        token          : '',
        mobileRule     : 'check_mobile_unique',
        mobileSelector : '',
        seconds        : 60,
        uuid           : '',
        voice          : false,
        alertMsg       : function (msg, type) {
            alert(msg);
        }
    };

})(Zepto);