/*
 * send verify sms
 *---------------------------
 * top lan <toplan710@gmail.com>
 * https://github.com/toplan/laravel-sms
 * --------------------------
 * Date 2015/06/08
 */
(function($){
    var _this, btnOriginContent, timeId;

    $.fn.sms = function(options) {
        var opts = $.extend(
            $.fn.sms.defaults,
            options
        );
        var self = this;
        self.on('click', function (e) {
            if (typeof _this !== 'undefined') {
                clearTimeout(timeId);
                changeBtn(btnOriginContent, false);
            }
            _this = self;
            btnOriginContent = _this.html() || _this.val() || '';
            changeBtn('短信发送中...', true);
            sendSms(opts);
        });
    };

    function sendSms(opts) {
        var url = getUrl(opts);
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

    function getUrl(opts) {
        var domain = opts.domain || '';
        var prefix = opts.prefix || 'laravel-sms';
        if (opts.voice) {
            return domain + '/' + prefix + '/voice-verify';
        }
        return domain + '/' + prefix + '/verify-code';
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
            timeId = setTimeout(function() {
                changeBtn(seconds + ' 秒后再次发送', true);
                seconds -= 1;
                timer(seconds);
            }, 1000);
        } else {
            clearTimeout(timeId);
            changeBtn(btnOriginContent, false);
        }
    }

    function changeBtn(content, disabled) {
        _this.html(content);
        _this.val(content);
        _this.prop('disabled', !!disabled);
    }

    $.fn.sms.defaults = {
        token       : null,
        interval    : 60,
        voice       : false,
        domain      : null,
        prefix      : 'laravel-sms',
        requestData : null,
        alertMsg    : function (msg, type) {
            alert(msg);
        }
    };
})(window.jQuery || window.Zepto);
