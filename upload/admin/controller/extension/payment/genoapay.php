<?php
class ControllerExtensionPaymentGenoapay extends Controller
{
    const CURRENCY_CODE = "NZD";
    const PAYMENT_METHOD_CODE = "genoapay";
    const LIBRARY_CODE = 'latitudepaylib';
    const DEFAULT_IMAGES_API_URL = 'https://images.latitudepayapps.com/';

    private $error = array();

    /**
     * @var GatewayInterface|null
     */
    private $_gateway;

    /**
     * ControllerExtensionPaymentGenoapay constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/' . $this->_getPaymentMethodCode());
        $this->load->language('extension/payment/' . $this->_getPaymentMethodCode());
        $this->_gateway = $this->_getPaymentGateway($this->getCurrentPaymentGateway());
    }

    /**
     * Installation hook
     */
    public function install() {
        // Create transactions table
        $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->install();

        // Add event hooks
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent($this->_getPaymentMethodCode() . '_product_info', 'catalog/view/product/product/before', 'extension/payment/' . $this->_getPaymentMethodCode() . '/productInfo');
        $this->model_setting_event->addEvent($this->_getPaymentMethodCode() . '_product_modal_script', 'catalog/controller/product/product/before', 'extension/payment/' . $this->_getPaymentMethodCode() . '/modalScript');
        $this->model_setting_event->addEvent($this->_getPaymentMethodCode() . '_cart_info', 'catalog/view/checkout/cart/before', 'extension/payment/' . $this->_getPaymentMethodCode() . '/cartInfo');
        $this->model_setting_event->addEvent($this->_getPaymentMethodCode() . '_cart_modal_script', 'catalog/controller/checkout/cart/before', 'extension/payment/' . $this->_getPaymentMethodCode() . '/modalScript');
        $this->model_setting_event->addEvent($this->_getPaymentMethodCode() . '_checkout_warning_check', 'catalog/view/checkout/checkout/before', 'extension/payment/' . $this->_getPaymentMethodCode() . '/checkoutInfo');
        $this->model_setting_event->addEvent($this->_getPaymentMethodCode() . '_checkout_modal_script', 'catalog/controller/checkout/checkout/before', 'extension/payment/' . $this->_getPaymentMethodCode() . '/modalScript');
        $this->model_setting_event->addEvent($this->_getPaymentMethodCode() . '_admin_refund_button', 'admin/view/sale/order_info/before', 'extension/payment/' . $this->_getPaymentMethodCode() . '/orderInfo');

        // Add default order statuses configuration
        $this->_setDefaultOrderStatuses();
    }

    /**
     * Uninstallation hook
     */
    public function uninstall() {
        // Remove the created event hooks
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode($this->_getPaymentMethodCode() . '_product_info');
        $this->model_setting_event->deleteEventByCode($this->_getPaymentMethodCode() . '_product_modal_script');
        $this->model_setting_event->deleteEventByCode($this->_getPaymentMethodCode() . '_cart_info');
        $this->model_setting_event->deleteEventByCode($this->_getPaymentMethodCode() . '_cart_modal_script');
        $this->model_setting_event->deleteEventByCode($this->_getPaymentMethodCode() . '_checkout_warning_check');
        $this->model_setting_event->deleteEventByCode($this->_getPaymentMethodCode() . '_checkout_modal_script');
        $this->model_setting_event->deleteEventByCode($this->_getPaymentMethodCode() . '_admin_refund_button');
    }

    /**
     * @inheritdoc
     */
    public function index()
    {
        if (!$this->getCurrentPaymentGateway()) {
            return $this->showErrorPage();
        }
        $this->language->set('heading_title', $this->language->get('heading_title'));
        $this->language->set('text_edit', $this->language->get('text_edit_'.$this->_getPaymentMethodCode()));
        $this->document->setTitle($this->language->get('heading_title'));

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $configData = $this->request->post;
            // Validate the configuration first
            $environment = $configData['payment_' . $this->_getPaymentMethodCode() . '_environment'];
            $error = false;
            if ($environment) {
                switch ($environment) {
                    case 'production':
                        if (
                            !$configData['payment_' . $this->_getPaymentMethodCode() . '_production_api_key'] ||
                            !$configData['payment_' . $this->_getPaymentMethodCode() . '_production_api_secret']
                        ) {
                            $error = $this->language->get($this->_getPaymentMethodCode() . '_api_credentials_required');
                        }
                        break;
                    case 'sandbox':
                        if (
                            !$configData['payment_' . $this->_getPaymentMethodCode() . '_sandbox_api_key'] ||
                            !$configData['payment_' . $this->_getPaymentMethodCode() . '_sandbox_api_secret']
                        ) {
                            $error = $this->language->get($this->_getPaymentMethodCode() . '_api_credentials_required');
                        }
                        break;

                    default:
                        $error = $this->language->get($this->_getPaymentMethodCode() . '_invalid_environment');
                }
            } else {
                $error = $this->language->get($this->_getPaymentMethodCode() . '_environment_required');
            }

            if ($error) {
                $this->session->data[$this->_getPaymentMethodCode() . '_error_message'] = $error;
                $this->response->redirect($this->url->link('extension/payment/'.$this->_getPaymentMethodCode(), 'user_token=' . $this->session->data['user_token'], true));
            }
            if (!class_exists('ControllerExtensionPaymentLatitudePay')) {
                require_once __DIR__ . '/latitudepay.php';
            }
            if ($this->_getPaymentMethodCode() === ControllerExtensionPaymentLatitudePay::PAYMENT_METHOD_CODE) {
                $imagesApiUrlConfigCode = 'payment_' . $this->_getPaymentMethodCode() . '_images_api_url';
                if (!isset($configData[$imagesApiUrlConfigCode]) || empty($configData[$imagesApiUrlConfigCode])) {
                    $configData[$imagesApiUrlConfigCode] = self::DEFAULT_IMAGES_API_URL;
                }
            }

            $this->model_setting_setting->editSetting('payment_'.$this->_getPaymentMethodCode(), $configData, $this->config->get('config_store_id'));
            if (!$this->_gateway) {
                $this->_gateway = $this->_getPaymentGateway($this->getCurrentPaymentGateway());
            }
            $this->_syncGatewayConfig();
            $this->session->data[$this->_getPaymentMethodCode() . '_success_message'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/payment/'.$this->_getPaymentMethodCode(), 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }
        $data = array();
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $this->buildPageLayout($data, $this->_getPaymentMethodCode());

        // Load the configurations
        $currentConfigurations = $this->model_setting_setting->getSetting('payment_'.$this->_getPaymentMethodCode(), $this->config->get('config_store_id'));
        $data = array_merge($data, $currentConfigurations);
        if (isset($this->session->data[$this->_getPaymentMethodCode() . '_error_message']) && $message = $this->session->data[$this->_getPaymentMethodCode() . '_error_message']) {
            $data['error_warning'] = $message;
            unset($this->session->data[$this->_getPaymentMethodCode() . '_error_message']);
        }
        if (isset($this->session->data[$this->_getPaymentMethodCode() . '_success_message']) && $message = $this->session->data[$this->_getPaymentMethodCode() . '_success_message']) {
            $data['success_message'] = $message;
            unset($this->session->data[$this->_getPaymentMethodCode() . '_success_message']);
        }
        $data['log_files'] = $this->_getLogFiles();
        $data['current_log_file'] = $this->_getCurrentLogFile($data['log_files']);
        $data['log'] = $this->_getLog($data['current_log_file']);
        return $this->response->setOutput($this->load->view('extension/payment/'.$this->_getPaymentMethodCode(), $data));
    }

    /**
     * Make order refund
     */
    public function refund() {
        $pToken = $this->request->get['token'];
        $amount = $this->request->get['amount'];
        $json = array();

        try {
            $pTran = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getPaymentTransactionByToken($pToken);
            if (empty($pTran)) {
                $json['success'] = false;
                $json['message'] = $this->language->get($this->_getPaymentMethodCode() . '_transaction_not_found');
            } else {
                $availableRefundAmount = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getAvailableRefundAmount($pTran['order_id']);
                if ($amount <= $availableRefundAmount) {
                    $refundResponse = $this->_refund($pTran['order_id'], $pTran['payment_token'], $pTran['currency_code'], $amount);

                    if ($refundResponse && isset($refundResponse['refundId'])) {
                        if ($this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addRefundTransaction(
                            $refundResponse['refundId'],
                            $pTran['order_id'],
                            $refundResponse['refundDate'],
                            $refundResponse['reference'],
                            $amount,
                            $refundResponse['commissionAmount'],
                            $this->_getPaymentMethodCode()
                        )) {
                            if ($this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getAvailableRefundAmount($pTran['order_id']) == 0) {
                                $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addOrderHistory(
                                    $pTran['order_id'],
                                    $this->model_setting_setting->getSettingValue('payment_'.$this->_getPaymentMethodCode().'_order_refunded_status_id',$this->config->get('config_store_id')),
                                    sprintf(
                                        $this->language->get($this->_getPaymentMethodCode() . '_refund_order_history_message'),
                                        $refundResponse['refundId'],
                                        $this->currency->format($amount, $pTran['currency_code'])
                                    )
                                );
                            } else {
                                $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addOrderHistory(
                                    $pTran['order_id'],
                                    $this->model_setting_setting->getSettingValue('payment_'.$this->_getPaymentMethodCode().'_order_partial_refunded_status_id',$this->config->get('config_store_id')),
                                    sprintf(
                                        $this->language->get($this->_getPaymentMethodCode() . 'refund_order_history_message'),
                                        $refundResponse['refundId'],
                                        $this->currency->format($amount, $pTran['currency_code'])
                                    )
                                );
                            }
                            $json['success'] = true;
                            $json['message'] = $this->language->get($this->_getPaymentMethodCode() . '_pay_refund_order_message');
                        }
                    }
                } else {
                    $json['success'] = false;
                    $json['message'] = $this->language->get($this->_getPaymentMethodCode() . '_transaction_amount_exceed');
                }
            }

            $json['availableAmount'] = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getAvailableRefundAmount($pTran['order_id']);
        } catch (\Exception $exception) {
            $json['success'] = false;
            $json['message'] = $exception->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function _getLogFiles() {
        $logDir = DIR_SYSTEM . 'library/latitudepay/includes/log';
        $files = glob($logDir . '/*.log');
        foreach ($files as $file => &$path) {
            $fileInfo = pathinfo($path);
            $path = $fileInfo['filename'];
        }
        return $files;
    }

    /**
     * Get payment method log records
     * @param string $logFile
     * @return false|string
     */
    protected function _getLog($logFile) {
        if ($this->validate()) {
            $logDir = DIR_SYSTEM . 'library/latitudepay/includes/log/' . $logFile . '.log';
            if (file_exists($logDir)) {
                return file_get_contents($logDir);
            }
        }
        return false;
    }

    /**
     * Get the current log file should be loaded
     * @param $logFiles
     * @return mixed
     */
    protected function _getCurrentLogFile($logFiles) {
        $logFile = end($logFiles);
        if (isset($this->registry->get('request')->get['log_file'])) {
            $logFile = $this->registry->get('request')->get['log_file'];
        }
        return $logFile;
    }

    /**
     * A hook to order detail page used to add custom script and append refund data before rendering the template
     * @param $route
     * @param $data
     * @param $template
     */
    public function orderInfo(&$route, &$data, &$template) {
        if (isset($data['order_id'])) {
            $order = $this->model_sale_order->getOrder($data['order_id']);
            $payment_method = $order['payment_code'];
            if ($payment_method === $this->_getPaymentMethodCode()) {
                $transaction = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getPaymentTransaction($data['order_id']);
                if ($transaction && isset($transaction['payment_token'])) {
                    $availableRefundAmount = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getAvailableRefundAmount($data['order_id']);
                    if ($availableRefundAmount !== false) {
                        $refund_url = $this->url->link('extension/payment/' . $this->_getPaymentMethodCode() . '/refund', 'user_token=' . $this->session->data['user_token'] . '&token=' . $transaction['payment_token'], true);
                        $refund_button = str_replace("{{{refund_url}}}", $refund_url, $this->language->get($this->_getPaymentMethodCode() . '_refund_button'));
                        $refund_button = str_replace("{{{refund_amount}}}", $availableRefundAmount, $refund_button);
                        $refund_script = '<script src="view/javascript/' . $this->_getPaymentMethodCode() . '/refund.js"></script>';
                        $data['footer'] = str_replace("{{{refund_button}}}", $refund_button, str_replace("{{{text_payment_method}}}", $this->language->get('text_payment_method'), $this->language->get($this->_getPaymentMethodCode() . '_refund_script'))) . "\n" . $refund_script . "\n" . $data['footer'];
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/'.$this->_getPaymentMethodCode())) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Get the current payment gateway code base on current country and currency configurations
     * @param null $currentCurrency
     * @return false|string
     */
    protected function getCurrentPaymentGateway($currentCurrency = null) {
        if (is_null($currentCurrency)) {
            $this->load->model('localisation/country');
            $currentCurrency = $this->config->get("config_currency");
        }

        if ($currentCurrency === $this->_getCurrencyCode()) {
            return $this->_getPaymentMethodCode();
        }
        return false;
    }

    /**
     * Show error page that warn user to configure the correct currency
     * @return mixed
     */
    protected function showErrorPage() {
        $this->document->setTitle($this->language->get('heading_title'));

        $data = array();

        $this->buildPageLayout($data, $this->_getPaymentMethodCode(), true);

        return $this->response->setOutput($this->load->view('extension/payment/'.$this->_getPaymentMethodCode(), $data));
    }

    /**
     * Generate data for payment configuration page
     * @param $data
     * @param $paymentGatewayCode
     * @param false $errorPage
     */

    protected function buildPageLayout(&$data, $paymentGatewayCode, $errorPage = false) {
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if ($errorPage) {
            $data['invalid_configuration'] = true;
            $data['store_config_url'] = $this->url->link('setting/setting', 'user_token=' . $this->session->data['user_token'], true);
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/'.$paymentGatewayCode, 'user_token=' . $this->session->data['user_token'], true),
        );

        if (!$errorPage) {
            $data['action'] = $this->url->link('extension/payment/'.$paymentGatewayCode, 'user_token=' . $this->session->data['user_token'], true);
        }

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
    }

    /**
     * @param $paymentGatewayCode
     * @return GatewayInterface|null
     */
    protected function _getPaymentGateway($paymentGatewayCode) {
        $this->load->library(self::LIBRARY_CODE);
        if ($paymentGatewayCode) {
            return $this->registry->get(self::LIBRARY_CODE)->getPaymentGateway($paymentGatewayCode, $this->model_setting_setting->getSetting('payment_'.$this->_getPaymentMethodCode(), $this->config->get('config_store_id')));
        }
        return null;
    }

    /**
     * Sync some settings from payment gateway here
     */
    protected function _syncGatewayConfig() {
        if ($this->_gateway && $paymentGatewayCode = $this->getCurrentPaymentGateway()) {
            $gatewayConfiguration = $this->_gateway->configuration();
            if (isset($gatewayConfiguration['minimumAmount'])) {
                $this->model_setting_setting->editSettingValue('payment_'.$this->_getPaymentMethodCode(), 'payment_'.$this->_getPaymentMethodCode().'_order_total', $gatewayConfiguration['minimumAmount'],  $this->config->get('config_store_id'));
            }
            if (isset($gatewayConfiguration['name'])) {
                $this->model_setting_setting->editSettingValue('payment_'.$this->_getPaymentMethodCode(), 'payment_'.$this->_getPaymentMethodCode().'_title', $gatewayConfiguration['name'],  $this->config->get('config_store_id'));
            }
            if (isset($gatewayConfiguration['description'])) {
                $this->model_setting_setting->editSettingValue('payment_'.$this->_getPaymentMethodCode(), 'payment_'.$this->_getPaymentMethodCode().'_description', $gatewayConfiguration['description'],  $this->config->get('config_store_id'));
            }
        }
    }

    /**
     * Set default order status values at the first installation
     */
    protected function _setDefaultOrderStatuses() {
        $completedOrderStatusId = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getOrderStatusByName($this->language->get('completed_order_status'));
        $failedOrderStatusId = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getOrderStatusByName($this->language->get('cancelled_order_status'));
        $partialRefundedOrderStatusId = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getOrderStatusByName($this->language->get('partial_refunded_order_status'));
        $refundedOrderStatusId = $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->getOrderStatusByName($this->language->get('refunded_order_status'));

        if ($completedOrderStatusId) {
            $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addSettingValue('payment_'.$this->_getPaymentMethodCode(), 'payment_'.$this->_getPaymentMethodCode().'_order_completed_status_id', $completedOrderStatusId,  $this->config->get('config_store_id'));
        }
        if ($failedOrderStatusId) {
            $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addSettingValue('payment_'.$this->_getPaymentMethodCode(), 'payment_'.$this->_getPaymentMethodCode().'_order_failed_status_id', $failedOrderStatusId,  $this->config->get('config_store_id'));
        }
        if ($partialRefundedOrderStatusId) {
            $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addSettingValue('payment_'.$this->_getPaymentMethodCode(), 'payment_'.$this->_getPaymentMethodCode().'_order_partial_refunded_status_id', $partialRefundedOrderStatusId,  $this->config->get('config_store_id'));
        }
        if ($refundedOrderStatusId) {
            $this->registry->get('model_extension_payment_' . $this->_getPaymentMethodCode())->addSettingValue('payment_'.$this->_getPaymentMethodCode(), 'payment_'.$this->_getPaymentMethodCode().'_order_refunded_status_id', $refundedOrderStatusId,  $this->config->get('config_store_id'));
        }
        $this->cache->delete("order_status." . (int)$this->config->get('config_language_id'));
    }

    /**
     * Send refund request
     * @param $orderId
     * @param $transactionId
     * @param $currencyCode
     * @param $amount
     * @return mixed
     */
    protected function _refund($orderId, $transactionId, $currencyCode, $amount) {
        $this->_gateway = $this->_getPaymentGateway($this->getCurrentPaymentGateway($currencyCode));
        if ($this->_gateway) {
            $paymentConfig = $this->model_setting_setting->getSetting('payment_'.$this->_getPaymentMethodCode(), $this->config->get('config_store_id'));
            $refund = array(
                BinaryPay_Variable::PURCHASE_TOKEN  => $transactionId,
                BinaryPay_Variable::CURRENCY        => $currencyCode,
                BinaryPay_Variable::AMOUNT          => $amount,
                BinaryPay_Variable::REFERENCE       => $orderId,
                BinaryPay_Variable::REASON          => '',
                BinaryPay_Variable::PASSWORD        => $paymentConfig['payment_' . $this->_getPaymentMethodCode() . '_' . $paymentConfig['payment_' . $this->_getPaymentMethodCode() . '_environment'] . '_api_secret']
            );
            return $this->_gateway->refund($refund);
        }
        return false;
    }

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
}
