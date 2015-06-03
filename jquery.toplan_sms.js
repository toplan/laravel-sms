//

var defaultSendSmsButtonSelector = '#sendVerifySmsButton';

function sendVerifySms(mobileNumber, elementSelector) {
    if ( ! elementSelector || elementSelector == undefined) {
        elementSelector = defaultSendSmsButtonSelector;
    }
    var elem = $(elementSelector);
    if (elem.length != 1) {
        console.log('send verify sms error!请指定发送控件.');
        alert('请指定发送控件');
        return false;
    }
    var buttonText = elem.text();
    elem.on('click', function(){
        elem.prop('disabled', true);
        if ( ! mobileNumber || mobileNumber == undefined) {
            mobileNumber = $('input[name="mobile"]').val();
        }
        $.ajax({
            url  : '/sms/send-code',
            type : 'get',
            data : {mobile:mobileNumber}
        }).success(function (data) {
            console.log(data);
               if (data.success) {
                   sendVerifyCodeTimer(60, elementSelector, buttonText);
               } else {
                   elem.text(buttonText);
                   elem.prop('disabled', false);
                   alert(data.msg);
               }
        }).fail(function () {
            elem.prop('disabled', false);
        });
    });
}

function sendVerifyCodeTimer(all_seconds, elementSelector, buttonText){
    if(all_seconds == undefined){
        all_seconds = 60;
    }
    if(all_seconds >= 0){
        setTimeout(function(){
            //显示倒计时
            $(elementSelector).text(all_seconds + ' 秒后再次发送');
            //递归
            sendVerifyCodeTimer(all_seconds-1, elementSelector, buttonText)
        }, 1000);
    }else{
        $(elementSelector).text(buttonText);
        $(elementSelector).prop('disabled', false);
    }
}