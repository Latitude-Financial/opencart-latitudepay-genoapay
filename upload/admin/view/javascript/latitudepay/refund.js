;(function ($) {
    $('body').on('click', '#lattitude-refund-button', function (event) {
        event.preventDefault();
        var refundAmountInput = $("#latitude-refund-amount");
        var refundAmount = refundAmountInput.val();
        var maxAmount = refundAmountInput.data('maxAmount');
        if (refundAmount <= maxAmount) {
            var rBuntton = $(event.target);
            var refundUrl = rBuntton.data('href');
            refundUrl += "&amount="+refundAmount;
            $.ajax({
                url: refundUrl,
                beforeSend: function () {
                    rBuntton.text("Refunding...");
                    rBuntton.prop('disabled', true);
                },
                success: function (res) {
                    if (res.success) {
                        rBuntton.text("Success")
                        location.reload();
                    }
                },
                error: function () {
                    rBuntton.text("Failed");
                    setTimeout(function () {
                        rBuntton.text("Try again");
                    }, 1000);
                }
            });
        }
        event.stopPropagation();
    })
})(jQuery);