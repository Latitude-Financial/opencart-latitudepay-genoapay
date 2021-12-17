<?php
class ControllerExtensionPaymentGenoapay extends Controller {

    const DEFAULT_VALUE = 'NO_VALUE';

    /**
     * @var integer - Order state
     */
    const PAYMENT_ACCEPECTED = 2;

    /**
     * @var string
     */
    const PENDING_ORDER_STATUS = 'pending';

    /**
     * @var string
     */
    const PROCESSING_ORDER_STATUS = 'processing';

    /**
     * @var string
     */
    const FAILED_ORDER_STATUS = 'failed';

    /**
     * @var string
     */
    const PAYMENT_METHOD_CODE = 'genoapay';

    /**
     * @var string
     */
    const LIBRARY_CODE = 'latitudepaylib';

    /**
     * @var string
     */
    const CURRENCY_CODE = 'NZD';

    /**
     * @var string
     */
    const DEFAULT_IMAGES_API_URL = 'https://images.latitudepayapps.com/';

    /**
     * @var string
     */
    const IMAGES_API_VERSION = 'v2';

    /**
     * ControllerExtensionPaymentGenoapay constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->library(self::LIBRARY_CODE);
        $this->load->model('setting/setting');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/' . $this->_getPaymentMethodCode());
        $this->twig = new Template('Twig');
    }

    /**
     * Show Place order button with purchase URL generated from payment gateway
     * @return mixed
     */
    public function index() {
        $this->load->language('extension/payment/' . $this->_getPaymentMethodCode());
        $data['button_confirm'] = $this->language->get('button_confirm');
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['payment_available'] = '0';
        if ($order_info) {
            $purchase_data = $this->_buildPurchaseData($order_info);
            $gateway = $this->_getPaymentGateway();
            if ($gateway) {
                $purchase_response = $gateway->purchase($purchase_data);
                if (isset($purchase_response['paymentUrl'])) {
                    $data['payment_available'] = '1';
                    $data['payment_url'] = $purchase_response['paymentUrl'];
                    $data['text_info_payment_checkout'] = $this->getSnippetContent($order_info['total'], true);
                    $data['text_info_payment_checkout'] .= $this->getModalContent();
                    $this->session->data['purchase_token'] = $purchase_response['token'] ?? null;
                    $this->session->data['reference'] = (string) $order_info['order_id'];
                }
            }
        }
        $data['warning_message'] = $this->language->get('unable_to_checkout');
        return $this->load->view('extension/payment/' . $this->_getPaymentMethodCode(), $data);
    }

    /**
     * Handle return request
     */
    public function callback() {
        try {
            $this->load->language('extension/payment/' . $this->_getPaymentMethodCode());
            $reference = $this->request->get['reference'];
            $order = $this->model_checkout_order->getOrder((int) $reference);
            $response = $this->request->get;
            $message = $this->_getArrayData($response, 'message');
            $result = $this->_getArrayData($response, 'result');
            $paymentGateway = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getCurrentPaymentGateway();

            if ($this->config->get('payment_' . $this->_getPaymentMethodCode() . '_debug')) {
                $logMessage = "======CALLBACK INFO STARTS======\n";
                $logMessage .= json_encode($this->request->get, JSON_PRETTY_PRINT);
                $logMessage .= "\n======CALLBACK INFO ENDS======";
                BinaryPay::log($logMessage, true, 'latitudepay-finance-' . date('Y-m-d') . '.log');
            }

            // Verify payment token
            $token = $this->session->data['purchase_token'];
            $this->session->data['payment_token'] = null;
            if ($token !== $this->request->get['token']) {
                $this->session->data[$this->_getPaymentMethodCode() . '_warning_message'] = "Invalid payment token";
                $this->response->redirect($this->url->link('checkout/checkout', '', true));
            }

            // Verify signature
            if (!$this->validateSignature()) {
                $this->session->data[$this->_getPaymentMethodCode() . '_warning_message'] = "Invalid response signature.";
                $this->response->redirect($this->url->link('checkout/checkout', '', true));
            }


            switch ($result) {
                case BinaryPay_Variable::STATUS_COMPLETED:
                    $this->order_status = self::PROCESSING_ORDER_STATUS;
                    if (is_array($response)) {
                        $message = sprintf($this->language->get('payment_success_message'),
                            $this->currency->format($order['total'], $order['currency_code']),
                            $this->_getArrayData($response, 'token'),
                            $paymentGateway
                        );
                    }
                    $this->model_checkout_order->addOrderHistory($order['order_id'], $this->model_setting_setting->getSettingValue('payment_'.$paymentGateway.'_order_completed_status_id',$this->config->get('config_store_id')), $message, false);
                    $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addPaymentTransaction(array(
                        'order_id' => $order['order_id'],
                        'payment_token' => $this->_getArrayData($response, 'token'),
                        'payment_gateway' => $paymentGateway,
                        'currency_code' => $order['currency_code'],
                        'total' => floatval($order['total']),
                        'date_added' => 'NOW()',
                        'date_modified' => 'NOW()'
                    ));

                    $this->response->redirect($this->url->link('checkout/success', '', true));
                    break;
                case BinaryPay_Variable::STATUS_UNKNOWN:
                case BinaryPay_Variable::STATUS_FAILED:
                    $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->updateOrderStatus(
                        $order['order_id'],
                        $this->model_setting_setting->getSettingValue(
                            'payment_'.$paymentGateway.'_order_failed_status_id',
                            $this->config->get('config_store_id')
                        )
                    );
                    $this->session->data[$this->_getPaymentMethodCode() . '_warning_message'] = $this->language->get('order_cancelled_message');
                    $this->response->redirect($this->url->link('checkout/checkout', '', true));
                    break;
            }
        } catch (\Exception $exception) {
            $this->response->redirect($this->url->link('checkout/failure', '', true));
        }
    }

    /**
     * Display payment snippet and modal in PDP
     * @param $route
     * @param $data
     * @param $output
     */
    public function productInfo(&$route, &$data, &$output){
        if ($data['price'] && !empty($this->request->get['product_id']) && $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_status')) {
            $this->load->model('catalog/product');
            $product_info = $this->model_catalog_product->getProduct($this->request->get['product_id']);
            if($product_info['quantity']) {
                $amount = $this->tax->calculate(($data['special'] ? $product_info['special']  : $product_info['price']), $product_info['tax_class_id'], $this->config->get('config_tax'));
                if ($this->_isMethodAvailable(true)) {
                    $this->displayPaymentSnippet($amount,$data);
                }
            }
        }
    }

    /**
     * Display payment snippet in cart page
     * @param $route
     * @param $data
     * @param $output
     */
    public function cartInfo(&$route, &$data, &$output){
        $this->load->language('extension/payment/' . $this->_getPaymentMethodCode());
        $this->load->model('checkout/order');
        $cartTotal = $this->_collectTotal();
        if ($this->_isMethodAvailable($cartTotal)) {
            $snippet = $this->getSnippetContent($cartTotal);
            $modal = $this->getModalContent();
            $data[$this->_getPaymentMethodCode() . '_payment_info_snippet'] = $snippet . $modal;
        }
    }

    /**
     * Display payment snippet in the checkout page
     * @param $route
     * @param $data
     * @param $output
     */
    public function checkoutInfo(&$route, &$data, &$output){
        $this->load->language('extension/payment/' . $this->_getPaymentMethodCode());
        if (
            isset($this->session->data[$this->_getPaymentMethodCode() . '_warning_message']) &&
            $message = $this->session->data[$this->_getPaymentMethodCode() . '_warning_message']
        ) {
            $data['error_warning'] = $message;
            unset($this->session->data[$this->_getPaymentMethodCode() . '_warning_message']);
        }
    }

    /**
     * Check if payment method is available
     * @param float|bool $amount
     * @param null|string $currencyCode
     * @return bool
     */
    protected function _isMethodAvailable($amount, $currencyCode = null) {
        if (!$this->config->get('payment_' . $this->_getPaymentMethodCode() . '_status')) {
            return false;
        }
        if (is_null($currencyCode)) {
            $currencyCode = $this->session->data['currency'];
        }

        if ($currencyCode === $this->_getCurrencyCode()) {
            // This is for product page check
            if ($amount === true) {
                return true;
            }
            return $amount >= $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_order_total');
        }
        return false;
    }

    /**
     * Build an array of data will be used to send over to payment gateway
     * @param $order_info
     * @return array
     */
    protected function _buildPurchaseData($order_info) {
        return array(
            BinaryPay_Variable::REFERENCE                => (string) $order_info['order_id'],
            BinaryPay_Variable::AMOUNT                   => floatval($order_info['total']),
            BinaryPay_Variable::CURRENCY                 => $order_info['currency_code'],
            BinaryPay_Variable::RETURN_URL               => $this->_getReturnUrl(),
            BinaryPay_Variable::MOBILENUMBER             => $order_info['telephone'] ?: '0210123456',
            BinaryPay_Variable::EMAIL                    => $order_info['email'],
            BinaryPay_Variable::FIRSTNAME                => $order_info['firstname'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::SURNAME                  => $order_info['lastname'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::SHIPPING_ADDRESS         => $this->_getFullAddress($order_info),
            BinaryPay_Variable::SHIPPING_COUNTRY_CODE    => $order_info['shipping_country'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::SHIPPING_POSTCODE        => $order_info['shipping_postcode'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::SHIPPING_SUBURB          => $order_info['shipping_city'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::SHIPPING_CITY            => $order_info['shipping_city'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::BILLING_ADDRESS          => $this->_getFullAddress($order_info) ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::BILLING_COUNTRY_CODE     => $order_info['payment_iso_code_2'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::BILLING_POSTCODE         => $order_info['payment_postcode'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::BILLING_SUBURB           => $order_info['payment_city'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::BILLING_CITY             => $order_info['payment_city'] ?: self::DEFAULT_VALUE,
            BinaryPay_Variable::TAX_AMOUNT               => $this->_getTaxAmount(),
            BinaryPay_Variable::PRODUCTS                 => $this->_getQuoteProducts($order_info),
            BinaryPay_Variable::SHIPPING_LINES           => [
                $this->_getShippingData($order_info)
            ]
        );
    }

    /**
     * Generate callback URL
     * @return mixed
     */
    protected function _getReturnUrl() {
        return $this->url->link('extension/payment/' . $this->_getPaymentMethodCode() . '/callback', '', true);
    }

    /**
     * Get full address text
     * @param $order_info
     * @return mixed|string
     */
    protected function _getFullAddress($order_info) {
        return isset($order_info['shipping_address_1']) ? (isset($order_info['shipping_address_2']) ? $order_info['shipping_address_1'] . ', ' . $order_info['shipping_address_2'] : $order_info['shipping_address_1'] ) : self::DEFAULT_VALUE;
    }

    /**
     * Build the products array structure base on latitude finance API documentation
     * @see  https://api.uat.latitudepay.com/v3/api-doc/index.html#operation/createEcommerceSale
     * @param array $order_information
     * @return array
     */
    protected function _getQuoteProducts($order_information)
    {
        $items = $this->model_checkout_order->getOrderProducts($order_information['order_id']);
        $currencyCode = $order_information['currency_code'];

        $products = [];
        foreach ($items as $_item) {
            $_item = (object) $_item;
            $product_line_item = [
                'name'          => $_item->name,
                'price' => [
                    'amount'    => round($_item->price, 2),
                    'currency'  => $currencyCode
                ],
                'sku'           => $_item->model,
                'quantity'      => $_item->quantity,
                'taxIncluded'   => isset($_item->tax) && $_item->tax > 0
            ];
            array_push($products, $product_line_item);
        }

        return $products;
    }

    /**
     * Build the shipping line array structure base on latitude finance API documentation
     * @see  https://api.uat.latitudepay.com/v3/api-doc/index.html#operation/createEcommerceSale
     * @return array
     */
    protected function _getShippingData($order_info)
    {
        if (!isset($order_info['shipping_method']) || !$order_info['shipping_method']) {
            return array(
                'carrier' => self::DEFAULT_VALUE,
                'price' => [
                    'amount' => 0,
                    'currency' => $this->session->data['currency']
                ],
                'taxIncluded' => 0
            );
        }
        $shippingDetail = array(
            'carrier' => $this->session->data['shipping_method']['title'],
            'price' => [
                'amount' => floatval($this->session->data['shipping_method']['cost']),
                'currency' => $this->session->data['currency']
            ],
            'taxIncluded' => $this->_isTaxIncluded()
        );
        return $shippingDetail;
    }

    /**
     * Check if shipping included taxes
     * @return bool
     */
    protected function _isTaxIncluded() {
        return $this->tax->calculate($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']) > $this->session->data['shipping_method']['cost'];
    }

    /**
     * Get payment gateway base on current order data
     * @param string $currency
     * @return mixed
     */
    protected function _getPaymentGateway() {
        $paymentGatewayCode = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getCurrentPaymentGateway();
        return $this->registry->get(self::LIBRARY_CODE)->getPaymentGateway(
            $paymentGatewayCode
            , $this->model_setting_setting->getSetting(
            'payment_'.$paymentGatewayCode,
            $this->config->get('config_store_id')
        ));
    }

    /**
     * Get order tax amount
     * @return int|mixed
     */
    protected function _getTaxAmount() {
        $taxAmount = 0;
        $taxes = $this->cart->getTaxes();
        foreach ($taxes as $id => $amount) {
            $taxAmount += $amount;
        }
        return $taxAmount;
    }

    /**
     * Get array element with a default value
     * @param array $data
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    protected function _getArrayData(array $data, $key, $default = null) {
        return isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @return mixed
     */
    protected function _collectTotal() {
        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep var references so we put them into an array.
        $total_data = array(
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        );
        $sort_order = array();
        $results = $this->model_setting_extension->getExtensions('total');
        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }
        array_multisort($sort_order, SORT_ASC, $results);
        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);

                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
            }
        }
        $sort_order = array();
        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }
        array_multisort($sort_order, SORT_ASC, $totals);
        return $total_data['total'];
    }

    /**
     * @return string
     */
    protected function _getPaymentMethodCode() {
        return self::PAYMENT_METHOD_CODE;
    }

    /**
     * @return string
     */
    protected function _getCurrencyCode() {
        return self::CURRENCY_CODE;
    }

    /**
     * Get minimum order total configuration
     */
    protected function getMinimumOrderTotal() {
        $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_order_total');
    }

    /**
     * @param $amount
     * @param $data
     * @param false $isFullBlock
     */
    protected function displayPaymentSnippet ($amount, &$data, $isFullBlock = false) {
        $this->load->language('extension/payment/' . $this->_getPaymentMethodCode());
        $textPayment = $this->getSnippetContent($amount, $isFullBlock);
        $modalContent = $this->getModalContent();
        $data['footer'] = $this->generateFooter($textPayment, $modalContent, $data['footer']);
    }

    /**
     * @param $textPayment
     * @param $modalContent
     * @param $currentFooter
     * @return string
     */
    protected function generateFooter($textPayment, $modalContent, $currentFooter) {
        $this->twig->set('text_payment', $textPayment);
        $this->twig->set('modal_content', $modalContent);
        $this->twig->set('current_footer', $currentFooter);
        return $this->twig->render('/default/template/extension/payment/' . $this->_getPaymentMethodCode() . '_footer');
    }

    /**
     * @param $amount
     * @param false $isFullBlock
     * @return string|string[]
     */
    protected function getSnippetContent($amount, $isFullBlock = false) {
        $product = $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_product');
        $imgUrl = $this->getImagesApiDomain() . self::IMAGES_API_VERSION . '/snippet.svg' . '?amount=' . $amount;
        if ($isFullBlock) {
            $imgUrl .= '&style=checkout';
        }
        if ($this->_getPaymentMethodCode() === ControllerExtensionPaymentGenoapay::PAYMENT_METHOD_CODE) {
            $imgUrl .= '&services=GPAY';
        } else {
            $imgUrl .= '&services=' . $product;
            if ($product !== 'LPAY') {
                $paymentTerms = $this->config->get('payment_latitudepay_payment_terms');
                $imgUrl .= '&terms=' . implode(',', $paymentTerms);
            }

        }
        return "<img style='max-width: 100%' src='". $imgUrl ."' />";
    }

    /**
     * @return mixed
     */
    protected function getModalContent() {
        return "<script type='application/javascript' src='". $this->getImagesApiDomain() . self::IMAGES_API_VERSION . '/util.js' ."'></script>";
    }

    /**
     * @return string
     */
    protected function getImagesApiDomain() {
        return $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_images_api_url') ? $this->config->get('payment_' . $this->_getPaymentMethodCode() . '_images_api_url') : self::DEFAULT_IMAGES_API_URL;
    }

    /**
     * Check if response signature is valid
     * @param $gatewayName
     * @param $request
     * @return bool
     */
    private function validateSignature()
    {
        /**
         * @var BinaryPay $gateway
         */
        $gateway = $this->_getPaymentGateway();
        $gluedString = $gateway->recursiveImplode(
            array(
                'token' => $this->request->get['token'],
                'reference' => isset($this->session->data['reference']) ? $this->session->data['reference'] : null,
                'message' => $this->request->get['message'],
                'result' => $this->request->get['result'],
            ),
            '',
            true
        );
        $signature = hash_hmac( 'sha256', base64_encode( $gluedString ), $gateway->getConfig( 'password' ) );
        return $signature === $this->request->get['signature'];
    }
}
