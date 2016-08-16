/*
 * send verify sms
 *---------------------------
 * top lan <toplan710@gmail.com>
 * https://github.com/toplan/laravel-sms
 * --------------------------
 * Date 2015/06/08
 */
(function($){
    var _this, btnOriginContent;

    $.fn.sms = function(options) {
        var opts = $.extend(
            $.fn.sms.defaults,
            options
        );
        _this = this;

        _this.on('click', function(e) {
            btnOriginContent = _this.html() || _this.val() || ''
            changeBtn('短信发送中...', true);
            sendSms(opts);
        });
    };

    function sendSms(opts) {
        var url = opts.domain + '/laravel-sms/verify-code';
        if (opts.voice) {
            url = opts.domain + '/laravel-sms/voice-verify';
        }
        var requestData = getRequestData(opts);

        $.ajax({
            url  : url,
            type : 'post',
            data : requestData,
            success : function (data) {
               if (data.success) {
                   timer(opts.interval);
               } else {
                   changeBtn(btnOriginContent, false);
                   opts.alertMsg.call(null, data.message, data.type);
               }
            },
            error: function(xhr, type){
                changeBtn(btnOriginContent, false);
                opts.alertMsg.call(null, '请求失败，请重试', 'request_failure');
            }
        });
    }

    function getRequestData(opts) {
        var requestData = {
            _token: opts.token || ''
        };

        var data = $.isPlainObject(opts.requestData) ? opts.requestData : {};
        for (var key in data) {
            if (typeof data[key] === 'function') {
                requestData[key] = data[key].call(requestData);
            } else {
                requestData[key] = data[key];
            }
        }

        return requestData;
    }

    function timer(seconds) {
        if (seconds >= 0) {
            setTimeout(function() {
                changeBtn(seconds + ' 秒后再次发送', true);
                seconds -= 1;
                timer(seconds);
            }, 1000);
        } else {
            changeBtn(btnOriginContent, false);
        }
    }

    function changeBtn(content, disabled) {
        _this.html(content);
        _this.val(content);
        _this.prop('disabled', !!disabled);
    }

    $.fn.sms.defaults = {
        token       : '',
        interval    : 60,
        voice       : false,
        domain      : '',
        requestData : {
            mobile      : '',
            mobile_rule : ''
        },
        alertMsg    : function (msg, type) {
            alert(msg);
        }
    };
})(window.jQuery || window.Zepto);
