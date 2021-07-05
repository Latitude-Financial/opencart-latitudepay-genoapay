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
     * Get payment snippet content
     * @param $amount
     * @param false $isFullBlock
     * @return string|string[]
     */
    protected function getSnippetContent($amount, $isFullBlock = false) {
        $this->twig->set('images_api_url', $this->getImagesApiUrl());
        $this->twig->set('amount', $amount);
        $this->twig->set('gateway_name', $this->getPaymentTitle());
        $this->twig->set('full_block', $isFullBlock);

        return $this->twig->render('/default/template/extension/payment/latitudepay_snippet');
    }

    /**
     * Get payment modal content
     * @return mixed
     */
    public function getModalContent()
    {
        $this->twig->set('images_api_url', $this->getImagesApiUrl());
        return $this->twig->render('/default/template/extension/payment/latitudepay_modal');
    }

    /**
     * Get Images API URL
     * @return string
     */
    private function getImagesApiUrl() {
        return $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_images_api_url');
    }

    /**
     * Get payment title
     * @return string
     */
    private function getPaymentTitle() {
        return $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_title');
    }
}
