<?php

if (!class_exists('ControllerExtensionPaymentGenoapay')) {
    include __DIR__ . DIRECTORY_SEPARATOR . "genoapay.php";
}

class ControllerExtensionPaymentLatitudePay extends ControllerExtensionPaymentGenoapay
{
    const CURRENCY_CODE = "AUD";
    const PAYMENT_METHOD_CODE = "latitudepay";

    protected function _getCurrencyCode() {
        return self::CURRENCY_CODE;
    }

    protected function _getPaymentMethodCode() {
        return self::PAYMENT_METHOD_CODE;
    }
}
