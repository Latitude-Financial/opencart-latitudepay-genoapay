<?php

if (!class_exists('ModelExtensionPaymentGenoapay')) {
    include __DIR__ . DIRECTORY_SEPARATOR . "genoapay.php";
}

class ModelExtensionPaymentLatitudePay extends ModelExtensionPaymentGenoapay
{
    const PAYMENT_MODEL_CODE = 'latitude';

    protected function _getPaymentModelCode() {
        return self::PAYMENT_MODEL_CODE;
    }
}