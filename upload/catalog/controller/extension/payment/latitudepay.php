<?php

if (!class_exists('ControllerExtensionPaymentGenoapay')) {
    include __DIR__ . DIRECTORY_SEPARATOR . "genoapay.php";
}

class ControllerExtensionPaymentLatitudePay extends ControllerExtensionPaymentGenoapay {

    const PAYMENT_METHOD_CODE = 'latitudepay';
    const CURRENCY_CODE = 'AUD';

    /**
     * Get payment method code
     * @return string
     */
    protected function _getPaymentMethodCode() {
        return self::PAYMENT_METHOD_CODE;
    }

    /**
     * Get curency code
     * @return string
     */
    protected function _getCurrencyCode() {
        return self::CURRENCY_CODE;
    }

    /**
     * Get payment title
     * @return string
     */
    private function getPaymentTitle() {
        return $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_title');
    }
}
