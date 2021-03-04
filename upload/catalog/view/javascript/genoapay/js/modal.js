// Pure JS just in case if the merchant website is not using jQuery
;(function ($) {
    function openPopup(element) {
        element.style.display = 'block';
    }

    function closePopup(element) {
        element.style.display = 'none';
    }

    $('body').on('click', '#genoapay-popup', function(event) {
        // prevent default
        event.preventDefault();
        event.stopImmediatePropagation();
        var popup = document.getElementById('g-infomodal-container');
        document.body.appendChild(popup);
        // popup the Genoapay HTML
        openPopup(popup);
    });

    $('body').on('click', '#g-infomodal-close', function () {
        var popup = document.getElementById('g-infomodal-container');
        closePopup(popup);
    });
})(jQuery);