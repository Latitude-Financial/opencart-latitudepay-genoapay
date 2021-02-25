<?php

class ModelExtensionPaymentGenoapay extends Model
{
    const CURRENCY_CODE = "NZD";
    const PAYMENT_METHOD_CODE = "genoapay";
    const PAYMENT_MODEL_CODE = self::PAYMENT_METHOD_CODE;
    const TYPE_COLUMN = 'column';
    const TYPE_VALUE = 'value';
    const MYSQL_SPECIAL_FUNCTIONS = ['NOW()'];

    /**
     * Get payment method data
     * @param $address
     * @param $total
     * @return array
     */
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/' . $this->_getPaymentMethodCode());
        $currentGateway = $this->getCurrentPaymentGateway();
        $payment_data = array();
        if ($currentGateway && $currentGateway === $this->_getPaymentMethodCode()) {
            if ($this->config->get('payment_' . $currentGateway . '_status')) {
                $minimum_order_amount = floatval($this->config->get('payment_' . $currentGateway . '_order_total'));
                if ($total >= $minimum_order_amount) {
                    return array(
                        'code' => $this->_getPaymentMethodCode(),
                        'title' => $this->language->get('text_title_' . $currentGateway),
                        'sort_order' => (int)$this->config->get('payment_' . $currentGateway . '_sort_order')
                    );
                }
            }
        }
        return $payment_data;
    }

    /**
     * Get the current payment gateway code base on current country and currency configurations
     * @param string|null $currentCurrency
     * @return false|string
     */
    public function getCurrentPaymentGateway($currentCurrency = null)
    {
        if (is_null($currentCurrency)) {
            $currentCurrency = $this->session->data["currency"];
        }
        if ($currentCurrency === $this->_getCurrencyCode()) {
            return $this->_getPaymentMethodCode();
        }
        return false;
    }

    /**
     * Get order status data by given status name
     * @param $orderStatusName
     * @return mixed
     */
    public function getOrderStatusByName($orderStatusName)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE name = '" . (string)$orderStatusName . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
        return $query->row;
    }

    /**
     * Create an order status with a given name
     * @param $orderStatusName
     * @return mixed
     */
    public function createOrderStatusByName($orderStatusName)
    {
        $sql = "
            INSERT INTO " . DB_PREFIX . "order_status(`name`, `language_id`) VALUES('" . $orderStatusName . "', '" . (int)$this->config->get('config_language_id') . "' );
        ";
        return $this->db->query($sql);
    }

    /**
     * Add new payment transaction
     * @param array $transactionData
     * @return mixed
     */
    public function addPaymentTransaction(array $transactionData)
    {
        $sql = "
            INSERT INTO " . DB_PREFIX . $this->_getPaymentModelCode() . "_payment_transactions(" . $this->_quoteSqlString(array_keys($transactionData), self::TYPE_COLUMN) . ") VALUES(" . $this->_quoteSqlString(array_values($transactionData)) . ");
        ";
        return $this->db->query($sql);
    }

    /**
     * Update order status
     * @param $orderId
     * @param $orderStatusId
     * @return mixed
     */
    public function updateOrderStatus($orderId, $orderStatusId)
    {
        return $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$orderStatusId . "', date_modified = NOW() WHERE order_id = '" . (int)$orderId . "'");
    }

    /**
     * Correct the sql text
     * @param array $data
     * @param string $type
     * @return string
     */
    protected function _quoteSqlString(array $data, $type = self::TYPE_VALUE)
    {
        $result = [];
        foreach ($data as $string) {
            switch ($type) {
                case self::TYPE_COLUMN:
                    $result[] = "`" . $string . "`";
                    break;
                case self::TYPE_VALUE:
                    if (in_array($string, self::MYSQL_SPECIAL_FUNCTIONS)) {
                        $result[] = $string;
                    } else {
                        $result[] = "'" . $string . "'";
                    }
                    break;
                default:
                    $result[] = "'" . $string . "'";
            }
        }
        return implode(", ", $result);
    }
    
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
