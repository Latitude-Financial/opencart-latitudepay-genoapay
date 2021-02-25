<?php

if (!class_exists('ControllerExtensionPaymentGenoapay')) {
    include __DIR__ . DIRECTORY_SEPARATOR . "genoapay.php";
}

class ControllerExtensionPaymentLatitudePay extends ControllerExtensionPaymentGenoapay {

    const PAYMENT_METHOD_CODE = 'latitude_pay';

    const CURRENCY_CODE = 'AUD';

    protected function _getPaymentMethodCode() {
        return self::PAYMENT_METHOD_CODE;
    }

    protected function _getCurrencyCode() {
        return self::CURRENCY_CODE;
    }
}
