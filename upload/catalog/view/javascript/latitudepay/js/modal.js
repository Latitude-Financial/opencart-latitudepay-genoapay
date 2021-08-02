// Pure JS just in case if the merchant website is not using jQuery
;(function ($) {
    function openPopup(element) {
        element.style.display = 'block';
    }

    function closePopup(element) {
        element.style.display = 'none';
    }

    $('body').on('click', '#latitudepay-popup, .wc-latitudefinance-payment-method-container', function(event) {
        // prevent default
        event.preventDefault();
        event.stopImmediatePropagation();
        var popup = document.getElementById('lp-modal-container');
        if (popup) {
            document.body.appendChild(popup);
            // popup the Latitude Pay HTML
            openPopup(popup);
        }
    });

    $('body').on('click', '#lp-modal-close', function () {
        var popup = document.getElementById('lp-modal-container');
        closePopup(popup);
    });
})(jQuery);