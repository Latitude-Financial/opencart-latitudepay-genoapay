<?php
class ModelExtensionPaymentGenoapay extends Model
{
    const PAYMENT_MODEL_CODE = 'genoapay';

    /**
     * Add payment and refund transactions tables
     */
    public function install()
    {
        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . $this->_getPaymentModelCode() . "_payment_transactions` (
			  `payment_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `payment_token` CHAR(100) NOT NULL,
			  `order_id` INT(11) NOT NULL,
			  `date_added` DATETIME NOT NULL,
			  `date_modified` DATETIME NOT NULL,
			  `payment_gateway` CHAR(20) NOT NULL,
			  `currency_code` CHAR(3) NOT NULL,
			  `total` DECIMAL( 20, 2 ) NOT NULL,
			  PRIMARY KEY (`payment_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `".DB_PREFIX. $this->_getPaymentModelCode() . "_refund_transactions` (
			    `refund_id` INT(11) NOT NULL AUTO_INCREMENT,
                `refund_token` varchar(100) NOT null,
                `order_id` int(11) NOT null,
                `refund_date` varchar(255) NOT null,
                `reference` varchar(50) NOT null,
                `refund_amount` decimal(20,2) NOT null,
                `commission_amount` decimal(20,2) NOT null,
                `payment_gateway` varchar(20) NOT null,
                PRIMARY KEY (`refund_id`)
                ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }

    /**
     * Get order status id base on its name
     * @param $orderStatusName
     * @return mixed
     */
    public function getOrderStatusByName($orderStatusName)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE name = '" . (string)$orderStatusName . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
        if (empty($query->row)) {
            $this->createOrderStatusByName($orderStatusName);
            return $this->getOrderStatusByName($orderStatusName);
        }
        return $query->row['order_status_id'];
    }

    /**
     * Create a new order status with a given name
     * @param $orderStatusName
     */
    public function createOrderStatusByName($orderStatusName) {
        $sql = "
            INSERT INTO ".DB_PREFIX."order_status(`name`, `language_id`) VALUES('".$orderStatusName."', '". (int)$this->config->get('config_language_id') ."' );
        ";
        $this->db->query($sql);
    }

    /**
     * Get order transactions from order id
     * @param $order_id
     * @return mixed
     */
    public function getPaymentTransaction($order_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . $this->_getPaymentModelCode() . "_payment_transactions WHERE `order_id` = '$order_id'");
        return $query->row;
    }

    /**
     * Get order transactions from payment gateway token
     * @param $token
     * @return mixed
     */
    public function getPaymentTransactionByToken($token) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . $this->_getPaymentModelCode() . "_payment_transactions WHERE `payment_token` = '$token'");
        return $query->row;
    }

    /**
     * Get refund transactions from order id
     * @param $order_id
     * @return mixed
     */
    public function getRefundTransactions($order_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . $this->_getPaymentModelCode() . "_refund_transactions WHERE `order_id` = '$order_id'");
        return $query->rows;
    }

    /**
     * Get the refunded amount of an order
     * @param $order_id
     * @return float|int
     */
    public function getRefundedAmount($order_id) {
        $result = 0;
        $rTrans = $this->getRefundTransactions($order_id);
        foreach ($rTrans as $tran) {
            $result += floatval($tran['refund_amount']);
        }
        return $result;
    }

    /**
     * Get the available refund amount of an order
     * @param $orderId
     * @return false|float|int
     */
    public function getAvailableRefundAmount($orderId) {
        if ($pTran = $this->getPaymentTransaction($orderId)) {
            $paidAmount = floatval($pTran['total']);
            $refundedAMount = $this->getRefundedAmount($orderId);
            if ($refundedAMount < $paidAmount) {
                return $paidAmount - $refundedAMount;
            }
        }
        return false;
    }

    /**
     * Append a setting row to database
     * @param $code
     * @param $key
     * @param $value
     * @param $storeId
     * @param int $serialized
     */
    public function addSettingValue($code, $key, $value, $storeId, $serialized = 0) {
        $sql = "
            INSERT INTO ".DB_PREFIX."setting(`code`, `key`, `value`, `store_id`, `serialized`) VALUES('".$code."', '".$key."', '".$value."', '".$storeId."', '".$serialized."');
        ";
        $this->db->query($sql);
    }

    /**
     * Add a new refund transaction to payment gateway refund transactions table
     * @param $refundToken
     * @param $orderId
     * @param $refundDate
     * @param $reference
     * @param $refundAmount
     * @param $commissionAmount
     * @param $paymentGateway
     * @return mixed
     */
    public function addRefundTransaction($refundToken, $orderId, $refundDate, $reference, $refundAmount, $commissionAmount, $paymentGateway) {
        $sql = "
            INSERT INTO ".DB_PREFIX. $this->_getPaymentModelCode() . "_refund_transactions(`refund_token`, `order_id`, `refund_date`, `reference`, `refund_amount`, `commission_amount`, `payment_gateway`) VALUES('".$refundToken."', '".$orderId."', '".$refundDate."', '".$reference."', '".$refundAmount."', '".$commissionAmount."', '".$paymentGateway."');
        ";
        return $this->db->query($sql);
    }

    /**
     * Add a new order history
     * @param $orderId
     * @param $orderStatusId
     * @param $comment
     * @param int $notify
     * @return mixed
     */
    public function addOrderHistory($orderId, $orderStatusId, $comment, $notify = 0) {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$orderStatusId . "', date_modified = NOW() WHERE order_id = '" . (int)$orderId . "'");
        $sql = "
            INSERT INTO ".DB_PREFIX."order_history(`order_id`, `order_status_id`, `notify`, `comment`, `date_added`) VALUES('".$orderId."', '".$orderStatusId."', '".$notify."', '".$comment."', NOW());
        ";
        return $this->db->query($sql);
    }

    protected function _getPaymentModelCode() {
        return self::PAYMENT_MODEL_CODE;
    }
}
