<?php

if (!class_exists('ModelExtensionPaymentGenoapay')) {
    include __DIR__ . DIRECTORY_SEPARATOR . "genoapay.php";
}

class ModelExtensionPaymentLatitudePay extends ModelExtensionPaymentGenoapay {
    const CURRENCY_CODE = "AUD";
    const PAYMENT_METHOD_CODE = "latitudepay";
    const PAYMENT_MODEL_CODE = 'latitude';

    protected function _getPaymentMethodCode() {
        return self::PAYMENT_METHOD_CODE;
    }

    protected function _getCurrencyCode() {
        return self::CURRENCY_CODE;
    }

    protected function _getPaymentModelCode () {
        return self::PAYMENT_MODEL_CODE;
    }
}