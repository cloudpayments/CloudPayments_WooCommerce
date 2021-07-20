jQuery(document).ready(function ($) {


    window.saveCartBox = () => {
        if ($('#cp_card_checkout').is(":checked")) {
            $('.cp_save_card').removeClass('hide-save_cart');
            $('#cp_save_card').prop("checked", true);
        } else {
            $('.cp_save_card').addClass('hide-save_cart');
            $('#cp_save_card').prop("checked", false);
        }
    }

});