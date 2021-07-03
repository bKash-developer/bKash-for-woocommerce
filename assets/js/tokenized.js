jQuery(function ($) {
    window.$ = $.noConflict(true);

    $('form.woocommerce-checkout').on('click', ".cancelAgreementButton", function (event) {
        var agreement = $(this).data('agreement');
        if (agreement) {
            CancelAgreement(agreement, $(this));
        } else {
            submit_error("Please select a valid agreement to cancel");
        }
    });

    function CancelAgreement(agreementID, that) {
        if (confirm("Are you sure to cancel this?")) {
            blockUI();
            $.ajax({
                type: 'POST',
                url: bKash_objects.cancelAgreement,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                enctype: 'multipart/form-data',
                data: {'id': agreementID},
                success: function (result) {
                    try {
                        result = JSON.parse(result);
                    } catch (e) {
                    }

                    if (result.result && result.result === 'success') {
                        submit_error(result.message ? result.message : "Deleted", null, 'info');
                        that.closest('tr').remove();
                    } else {
                        submit_error(result.message ? result.message : "Cannot remove the agreement right now");
                    }
                    blockUI(true);
                },
                error: function (error) {
                    submit_error("Cannot remove the agreement right now");
                    blockUI(true);
                }
            });
        }
    }


    function submit_error(error_message, error_messages, group = "error") {
        var header = "<h3 style='color: #fff;font-weight: bold;margin: 0;font-size: 20px;line-height: 14px;'>Error</h3>";

        var checkout_form = $('form.checkout');
        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        if (error_message) {
            checkout_form.prepend('<div class="woocommerce-' + group + ' woocommerce-NoticeGroup-checkout">' + header + error_message + '</div>'); // eslint-disable-line max-len
        } else if (error_messages !== false) {
            checkout_form.prepend(error_messages);
        } else {
            checkout_form.prepend('<div class="woocommerce-' + group + ' woocommerce-NoticeGroup-checkout">' + header + ' Something went wrong! Try again</div>')
        }

        if (checkout_form.removeClass('processing').unblock === 'function') {
            checkout_form.removeClass('processing').unblock();
        }
        checkout_form.find('.input-text, select, input:checkbox').trigger('validate').blur();
        scroll_to_notices();
        $(document.body).trigger('checkout_error', [error_message]);
    }
    
    function scroll_to_notices() {
        var scrollElement = $('.woocommerce-error, .woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');

        if (!scrollElement.length) {
            scrollElement = $('.form.checkout');
        }
        if (typeof $.scroll_to_notices === 'function') {
            $.scroll_to_notices(scrollElement);
        } else {
            $('html, body').animate(
                {
                    scrollTop: scrollElement.offset().top - 50,
                },
                1000
            );
        }
    }

    function blockUI(unblock = false) {
        if (unblock) {
            if (typeof $.unblockUI === 'function') {
                $.unblockUI();
            }
        } else {
            if (typeof $.blockUI === 'function') {
                $.blockUI({message: ''});
            }
        }
    }
});



