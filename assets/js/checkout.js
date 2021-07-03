(function ($) {
    $(function () {
        window.$ = $.noConflict(true);
        var paymentObj = {paymentID: "", orderID: ""};
        var paymentReq = {amount: '0', intent: 'sale'};
        InitiatebKashPayment();

        $('form.woocommerce-checkout').on('click', "#place_order", function (event) {
            var payment_method = $('form.checkout').find('input[name^="payment_method"]:checked').val();
            if (payment_method === 'bkash_pgw') {
                event.preventDefault();

                if (bKash !== undefined) {
                    var button = document.getElementById("bKash_button");
                    bKash.reconfigure({
                        paymentReq
                    });
                    button.click();
                } else {
                    submit_error("bKash JS SDK is missing");
                }
            }
        });

        function InitiatebKashPayment() {
            $.getScript(bKash_objects.bKashScriptURL, function () {
                var button = document.getElementById("bKash_button");
                if (!button) {
                    button = document.createElement("button");
                    button.id = "bKash_button";
                    button.disabled = false;
                    button.style.display = "hidden";

                    var body = document.getElementsByTagName("body")[0];
                    body.appendChild(button);
                }

                if (typeof bKash === 'undefined') {
                    console.log("bKash SDK is not set properly!");
                } else {
                    bKash.init({
                        paymentMode: 'checkout',
                        paymentRequest: paymentReq,

                        createRequest: function (request) {
                            blockUI();
                            var post_data = $('form.checkout').serialize()
                            post_data['action'] = 'ajax_order';
                            $.ajax({
                                type: 'POST',
                                url: bKash_objects.submit_order,
                                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                                enctype: 'multipart/form-data',
                                data: post_data,
                                success: function (result) {
                                    if (result.result && result.result === 'success') {
                                        paymentObj = result.order;
                                        paymentReq.amount = result?.response?.amount;
                                        bKash.create().onSuccess(result.response);
                                    } else {
                                        bKash.execute().onError();
                                        submit_error(result.message, result.messages)
                                    }
                                    blockUI(true);
                                },
                                error: function (error) {
                                    blockUI(true);
                                    bKash.execute().onError();
                                    submit_error(error)
                                }
                            });

                        },
                        executeRequestOnAuthorization: function () {
                            blockUI();
                            $.ajax({
                                type: 'POST',
                                url: bKash_objects.wcAjaxURL,
                                dataType: "json",
                                data: {
                                    action: 'bk_execute',
                                    security: $('#bkash-ajax-nonce').val(),
                                    'orderId': paymentObj.orderId,
                                    'paymentID': paymentObj.paymentID,
                                    'invoiceID': paymentObj.invoiceID,
                                    'status': 'success',
                                    'apiVersion': 'v1.2.0-beta'
                                },
                                success: function (resp) {
                                    if (resp.result && resp.result === 'success') {
                                        if (resp.redirect) {
                                            window.location.href = resp.redirect;
                                        }
                                    } else {
                                        submit_error(resp.message);
                                        bKash.execute().onError();
                                    }
                                    blockUI(true);
                                },
                                error: function (error) {
                                    blockUI(true);
                                    submit_error(error)
                                    bKash.execute().onError();
                                }
                            });
                        },
                        onClose: function () {
                            bKash.execute().onError();
                            submit_error("You have chosen to cancel the payment", null, 'cancel');
                        }
                    });

                }
            });
        }


        function submit_error(error_message, error_messages, group = "error") {
            var msg = '';
            if (group === 'cancel') {
                msg = 'Payment Canceled';
                group = 'error';
            }
            var header = "<h3 style='color: #fff;font-weight: bold;margin: 0;font-size: 20px;line-height: 14px;'>" + msg + "</h3>";

            var checkout_form = $('form.checkout');
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
            if (error_message) {
                checkout_form.prepend('<div class="woocommerce-' + group + ' woocommerce-NoticeGroup-checkout">' + header + error_message + '</div>'); // eslint-disable-line max-len
            } else if (error_messages) {
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
})(jQuery);



