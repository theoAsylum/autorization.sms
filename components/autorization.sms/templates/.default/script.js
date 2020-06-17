function sendAgain(){
    $('input[name="code"]').val('');
    $('.pa-form').submit();
};
function endWordSeconds(num){
    switch (num) {
        case 1:
        case 21:
        case 31:
        case 41:
        case 51:
            return 'у';
        case 2:
        case 3:
        case 4:
        case 22:
        case 23:
        case 24:
        case 32:
        case 33:
        case 34:
        case 42:
        case 43:
        case 44:
        case 52:
        case 53:
        case 54:
            return 'ы';
        default:
            return '';
    }
};
function timeRemain(){
    if($('#exp_time').length){
        $('.pa-form__resend').hide();
        let timer = setInterval(function(){
            var remain = +$('#exp_time').text() - 1;
            if(remain == 0){
                clearInterval(timer);
                $('#exp_time').parent().remove();
                $('.pa-form__resend').show();
            }
            $('#exp_time').text(remain);
            $('#end_word').text(endWordSeconds(remain));
        }, 1000);
    }
}
$(document).ready(function(){
    timeRemain();
});
$(document).on('submit','.pa-form',function(e){
    e.preventDefault();

    var form = $(this)[0];
    var formData = {};
    $(form).find('input').each(function () {
        formData[$(this).attr('name')] = $(this).val();
    });
    $(form).find('select').each(function () {
        formData[$(this).attr('name')] = $(this).val();
    });
    formData['AJAX'] = 'Y';
    var action = $(this).attr('action');

    if(window.google_public_key) {
        grecaptcha.execute(window.google_public_key, {action: 'authorize'})
            .then(function (token) {
                formData['g-recaptcha-response'] = token;

                $.ajax({
                    type: "POST",
                    url: action,
                    data: formData,
                    dataType: 'html',
                    success: function success(data) {
                        if ($(data).find('.pa-form').html()) {
                            $('.pa-form').replaceWith($(data).find('.pa-form'));
                        }
                        if ($(data).find('.pa-form__text-part').html()) {
                            $('.pa-form__text-part').replaceWith($(data).find('.pa-form__text-part'));
                        } else {
                            $('.pa-form__text-part').text('');
                        }
                        if ($(data).find('.pa-form__success-text').html()) {
                            setTimeout(function () {
                                window.location = '/personal/'
                            }, 1000);
                        }
                        $(".js-masked").inputmask({"mask": "+7 (999) 999-9999"});
                        timeRemain();
                    },
                    error: function error(jqXHR, textStatus, errorThrown) {
                        $('.js-pa-error-text').text('Ошибка! Пожалуйста, перезагрузите страницу');
                        console.log(textStatus);
                    }
                });
            });
    }
})