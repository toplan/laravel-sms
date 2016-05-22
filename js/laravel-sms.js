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
        var mobile = $(opts.mobile_selector).val();
        var url = opts.domain + '/laravel-sms/verify-code';
        if (opts.voice) {
            url = opts.domain + '/laravel-sms/voice-verify';
        }
        $.ajax({
            url  : url,
            type : 'post',
            data : {
                _token: opts.token,
                access_token: opts.access_token,
                interval: opts.interval,
                mobile: mobile,
                mobile_rule: opts.mobile_rule
            },
            success : function (data) {
               if (data.success) {
                   timer(elem, opts.interval, opts.btnContent)
               } else {
                   elem.html(opts.btnContent);
                   elem.prop('disabled', false);
                   opts.alertMsg(data.message, data.type);
               }
            },
            error: function(xhr, type){
                elem.html(opts.btnContent);
                elem.prop('disabled', false);
                opts.alertMsg('请求失败，请重试', 'request_failure');
            }
        });
    }

    function timer(elem, seconds, btnContent){
        if(seconds >= 0){
            setTimeout(function(){
                elem.html(seconds + ' 秒后再次发送');
                seconds -= 1;
                timer(elem, seconds, btnContent);
            }, 1000);
        }else{
            elem.html(btnContent);
            elem.prop('disabled', false);
        }
    }

    $.fn.sms.default = {
        token           : '',
        access_token    : '',
        mobile_rule     : '',
        mobile_selector : '',
        interval        : 60,
        voice           : false,
        domain          : '',
        alertMsg        : function (msg, type) {
            alert(msg);
        }
    };
})(window.jQuery || window.Zepto);