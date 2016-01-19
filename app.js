var textarea;
var results;
var loader;

function convertRules() {

    if ($(this).data('oldVal') == $(this).val()) {
        return;
    }

    var rules = $(this).val();

    if (rules != '') {
        loader.addClass('visible');
        $.ajax({
            type: 'POST',
            data: {rules: rules}
        }).done(function (result) {
            loader.removeClass('visible');
            results.val(result);
        })
    } else {
        results.val('');
    }

    $(this).data('oldVal', $(this).val());
}

$(function () {
    textarea = $('#apache').find('textarea');
    results = $('#nginx').find('textarea');
    loader = $('#loader');
    textarea.on('propertychange change click keyup input paste', convertRules).data('oldVal', textarea.val());
});