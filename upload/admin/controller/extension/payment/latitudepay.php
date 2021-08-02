<?php

if (!class_exists('ControllerExtensionPaymentGenoapay')) {
    include __DIR__ . DIRECTORY_SEPARATOR . "genoapay.php";
}

class ControllerExtensionPaymentLatitudePay extends ControllerExtensionPaymentGenoapay
{
    const CURRENCY_CODE = "AUD";
    const PAYMENT_METHOD_CODE = "latitudepay";
    const DEPRECATED_EXTENSION_NAME = 'latitude';

    /**
     * @return string
     */
    protected function _getCurrencyCode() {
        return self::CURRENCY_CODE;
    }

    /**
     * @return string
     */
    protected function _getPaymentMethodCode() {
        return self::PAYMENT_METHOD_CODE;
    }

    /**
     * @inheritDoc
     */
    public function install()
    {
        parent::install();
        $this->cleanUp();
    }

    /**
     * Unlink all files from the old version to avoid duplicate payment method issue
     */
    private function cleanUp() {
        $deprecatedExtFilePaths = [
            DIR_APPLICATION . '../*/controller/extension/payment/',
            DIR_APPLICATION . '../*/model/extension/payment/',
            DIR_APPLICATION . '../*/language/*/extension/payment/',
            DIR_APPLICATION . '../*/view/theme/*/template/extension/payment/',
            DIR_APPLICATION . '../*/view/template/extension/payment/',
            DIR_APPLICATION . '../*/view/template/extension/payment/',
        ];
        foreach($deprecatedExtFilePaths as $deprecatedExtFilePath) {
            $files = glob($deprecatedExtFilePath . self::DEPRECATED_EXTENSION_NAME . ".*");
            if ($files) {
                foreach($files as $file) {
                    unlink($file);
                }
            }
        }
    }
}
