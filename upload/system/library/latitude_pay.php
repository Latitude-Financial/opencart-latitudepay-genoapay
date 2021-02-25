<?php
final class latitude_pay {
    const LATITUDE_PAYMENT_CODE = "latitude_pay";
    const GENOAPAY_PAYMENT_CODE = "genoapay";
    const MODE_PRODUCTION = 'production';
    const MODE_SANDBOX = 'sandbox';

    private $session;
    private $url;
    private $config;
    private $log;
    private $customer;
    private $currency;
    private $registry;

    /**
     * @var \Log
     */
    protected $logger;

    public function __construct($registry) {
        $this->session = $registry->get('session');
        $this->url = $registry->get('url');
        $this->config = $registry->get('config');
        $this->log = $registry->get('log');
        $this->customer = $registry->get('customer');
        $this->currency = $registry->get('currency');
        $this->registry = $registry;
        require DIR_SYSTEM . 'library/latitude_pay/includes/autoload.php';
    }

    /**
     * Get the payment gateway base on current localisation settings
     * @param string $paymentGatewayCode
     * @param array $config
     * @return Genoapay|Latitudepay|null
     */
    public function getPaymentGateway($paymentGatewayCode, $config) {
        try {
            $configData = $this->buildConfigArray($config, $paymentGatewayCode);
            if (!$configData) {
                return null;
            }
            switch ($paymentGatewayCode) {
                case self::LATITUDE_PAYMENT_CODE:
                    $this->initLogger($paymentGatewayCode);
                    return new Latitudepay($configData);
                case self::GENOAPAY_PAYMENT_CODE:
                    $this->initLogger($paymentGatewayCode);
                    return new Genoapay($configData);
                default:
                    return null;
            }
        } catch (\Exception $exception) {
            $this->logger->write($exception->getMessage());
            return null;
        }
    }

    public function initLogger($paymentGatewayCode) {
        if (!$this->logger) {
            $this->logger = new \Log('latitude_finance_'.$paymentGatewayCode.'.log');
        }
        return $this;
    }

    public function log($message) {
        $this->logger->write($message);
    }

    protected function buildConfigArray($config, $paymentGatewayCode) {
         if (isset($config['payment_'.$paymentGatewayCode.'_environment'])) {
            switch ($config['payment_'.$paymentGatewayCode.'_environment']) {
                case self::MODE_PRODUCTION:
                    return array(
                        BinaryPay_Variable::ENVIRONMENT => self::MODE_PRODUCTION,
                        BinaryPay_Variable::USERNAME => $config['payment_'.$paymentGatewayCode.'_production_api_key'],
                        BinaryPay_Variable::PASSWORD => $config['payment_'.$paymentGatewayCode.'_production_api_secret']

                    );
                case self::MODE_SANDBOX:
                    return array(
                        BinaryPay_Variable::ENVIRONMENT => self::MODE_SANDBOX,
                        BinaryPay_Variable::USERNAME => $config['payment_'.$paymentGatewayCode.'_sandbox_api_key'],
                        BinaryPay_Variable::PASSWORD => $config['payment_'.$paymentGatewayCode.'_sandbox_api_secret']

                    );
                default:
                    return [];
            }
        }
        return null;
    }
}