<?php

namespace MarwaMourad\LaravelMyfatoorah;

use Exception;

/**
 *  PaymentMyfatoorahApiV2 handle the payment process of MyFatoorah API endpoints
 *
 * @author    MyFatoorah <tech@myfatoorah.com>
 * @copyright 2021 MyFatoorah, All rights reserved
 * @license   GNU General Public License v3.0
 */
class PaymentMyfatoorahApiV2 extends MyfatoorahApiV2 {

    /**
     * To specify either the payment will be onsite or offsite
     * (default value: false)
     *
     * @var boolean
     */
    protected $isDirectPayment = false;

    /**
     *
     * @var string
     */
    public static $pmCachedFile = __DIR__ . '/mf-methods.json';

    /**
     *
     * @var array
     */
    protected static $paymentMethods;

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * List available Payment Gateways. (POST API)
     *
     * @param double|integer $invoiceValue
     * @param string         $displayCurrencyIso
     * @param boolean        $isCached
     *
     * @return array
     */
    public function getVendorGateways($invoiceValue = 0, $displayCurrencyIso = '', $isCached = false) {

        $postFields = [
            'InvoiceAmount' => $invoiceValue,
            'CurrencyIso'   => $displayCurrencyIso,
        ];

        $json = $this->callAPI("$this->apiURL/v2/InitiatePayment", $postFields, null, 'Initiate Payment');

        $paymentMethods = isset($json->Data->PaymentMethods) ? $json->Data->PaymentMethods : [];

        if (!empty($paymentMethods) && $isCached) {
            file_put_contents(self::$pmCachedFile, json_encode($paymentMethods));
        }
        return $paymentMethods;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * List available Cached Payment Gateways.
     *
     * @return array of Cached payment methods
     */
    public function getCachedVendorGateways() {

        if (file_exists(self::$pmCachedFile)) {
            $cache = file_get_contents(self::$pmCachedFile);
            return ($cache) ? json_decode($cache) : [];
        } else {
            return $this->getVendorGateways(0, '', true);
        }
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * List available Payment Gateways by type (direct, cards)
     *
     * @param boolean $isDirect
     *
     * @return array
     */
    public function getVendorGatewaysByType($isDirect = false) {

        $gateways = $this->getCachedVendorGateways();

        //        try {
        //            $gateways = $this->getVendorGateways();
        //        } catch (Exception $ex) {
        //            return [];
        //        }

        $paymentMethods = [
            'cards'  => [],
            'direct' => [],
        ];

        foreach ($gateways as $g) {
            if ($g->IsDirectPayment) {
                $paymentMethods['direct'][] = $g;
            } elseif ($g->PaymentMethodCode != 'ap') {
                $paymentMethods['cards'][] = $g;
            } elseif ($this->isAppleSystem()) {
                //add apple payment for IOS systems
                $paymentMethods['cards'][] = $g;
            }
        }

        return ($isDirect) ? $paymentMethods['direct'] : $paymentMethods['cards'];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * List available cached  Payment Methods
     *
     * @param  bool $isAppleRegistered
     * @return array
     */
    public function getCachedPaymentMethods($isAppleRegistered = false) {

        $gateways       = $this->getCachedVendorGateways();
        $paymentMethods = ['all' => [], 'cards' => [], 'form' => [], 'ap' => []];
        foreach ($gateways as $g) {
            $paymentMethods = $this->fillPaymentMethodsArray($g, $paymentMethods, $isAppleRegistered);
        }

        //add only one ap gateway
        $paymentMethods['ap'] = (isset($paymentMethods['ap'][0])) ? $paymentMethods['ap'][0] : [];

        return $paymentMethods;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * List available Payment Methods
     *
     * @param double|integer $invoiceValue
     * @param string         $displayCurrencyIso
     * @param bool           $isAppleRegistered
     *
     * @return array
     */
    public function getPaymentMethodsForDisplay($invoiceValue, $displayCurrencyIso, $isAppleRegistered = false) {

        if (!empty(self::$paymentMethods)) {
            return self::$paymentMethods;
        }

        $gateways = $this->getVendorGateways($invoiceValue, $displayCurrencyIso);
        $allRates = $this->getCurrencyRates();

        self::$paymentMethods = ['all' => [], 'cards' => [], 'form' => [], 'ap' => []];

        foreach ($gateways as $g) {
            $g->GatewayData = $this->calcGatewayData($g->TotalAmount, $g->CurrencyIso, $g->PaymentCurrencyIso, $allRates);

            self::$paymentMethods = $this->fillPaymentMethodsArray($g, self::$paymentMethods, $isAppleRegistered);
        }

        //add only one ap gateway
        self::$paymentMethods['ap'] = $this->getOneApplePayGateway(self::$paymentMethods['ap'], $displayCurrencyIso, $allRates);

        return self::$paymentMethods;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
    protected function getOneApplePayGateway($apGateways, $displayCurrency, $allRates) {

        $displayCurrencyIndex = array_search($displayCurrency, array_column($apGateways, 'PaymentCurrencyIso'));
        if ($displayCurrencyIndex) {
            return $apGateways[$displayCurrencyIndex];
        }

        //get defult mf account currency
        $defCurKey       = array_search('1', array_column($allRates, 'Value'));
        $defaultCurrency = $allRates[$defCurKey]->Text;

        $defaultCurrencyIndex = array_search($defaultCurrency, array_column($apGateways, 'PaymentCurrencyIso'));
        if ($defaultCurrencyIndex) {
            return $apGateways[$defaultCurrencyIndex];
        }

        if (isset($apGateways[0])) {
            return $apGateways[0];
        }

        return [];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param object  $g
     * @param array   $paymentMethods
     * @param boolean $isAppleRegistered
     *
     * @return array
     */
    protected function fillPaymentMethodsArray($g, $paymentMethods, $isAppleRegistered = false) {

        if ($g->PaymentMethodCode != 'ap') {
            if ($g->IsEmbeddedSupported) {
                $paymentMethods['form'][] = $g;
                $paymentMethods['all'][]  = $g;
            } elseif (!$g->IsDirectPayment) {
                $paymentMethods['cards'][] = $g;
                $paymentMethods['all'][]   = $g;
            }
        } elseif ($this->isAppleSystem()) {
            if ($isAppleRegistered) {
                //add apple payment for IOS systems
                $paymentMethods['ap'][] = $g;
            } else {
                $paymentMethods['cards'][] = $g;
            }
            $paymentMethods['all'][] = $g;
        }
        return $paymentMethods;
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Check if the system supports ApplePay or not
     *
     * @return boolean
     */
    protected static function isAppleSystem() {

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if ((stripos($userAgent, 'iPod') || stripos($userAgent, 'iPhone') || stripos($userAgent, 'iPad') || stripos($userAgent, 'Mac')) && (self::getBrowserName($userAgent) == 'Safari')) {
            return true;
        }

        return false;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param string $userAgent
     *
     * @return string
     */
    public static function getBrowserName($userAgent) {

        $browsers = [
            'Opera'             => ['Opera', 'OPR/'],
            'Edge'              => ['Edge'],
            'Chrome'            => ['Chrome', 'CriOS'],
            'Firefox'           => ['Firefox', 'FxiOS'],
            'Safari'            => ['Safari'],
            'Internet Explorer' => ['MSIE', 'Trident/7'],
        ];

        foreach ($browsers as $browser => $bArr) {
            foreach ($bArr as $needle) {
                if (strpos($userAgent, $needle)) {
                    return $browser;
                }
            }
        }

        return 'Other';
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Payment Method Object
     *
     * @param string         $gateway
     * @param string         $gatewayType        ['PaymentMethodId', 'PaymentMethodCode']
     * @param double|integer $invoiceValue
     * @param string         $displayCurrencyIso
     *
     * @return object
     *
     * @throws Exception
     */
    public function getPaymentMethod($gateway, $gatewayType = 'PaymentMethodId', $invoiceValue = 0, $displayCurrencyIso = '') {

        $paymentMethods = $this->getVendorGateways($invoiceValue, $displayCurrencyIso);

        $pm = null;
        foreach ($paymentMethods as $method) {
            if ($method->$gatewayType == $gateway) {
                $pm = $method;
                break;
            }
        }

        if (!isset($pm)) {
            throw new Exception('Please contact Account Manager to enable the used payment method in your account');
        }

        if ($this->isDirectPayment && !$pm->IsDirectPayment) {
            throw new Exception($pm->PaymentMethodEn . ' Direct Payment Method is not activated. Kindly contact your MyFatoorah account manager or sales representative to activate it.');
        }

        return $pm;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get the invoice/payment URL and the invoice id
     *
     * @param array          $curlData
     * @param string         $gatewayId (default value: 'myfatoorah')
     * @param integer|string $orderId   (default value: null) used in log file
     * @param string         $sessionId
     *
     * @return array
     */
    public function getInvoiceURL($curlData, $gatewayId = 0, $orderId = null, $sessionId = null) {

        $this->log('------------------------------------------------------------');

        $this->isDirectPayment = false;

        if (!empty($sessionId)) {
            return $this->embeddedPayment($curlData, $sessionId, $orderId);
        } elseif ($gatewayId == 'myfatoorah' || empty($gatewayId)) {
            return $this->sendPayment($curlData, $orderId);
        } else {
            return $this->excutePayment($curlData, $gatewayId, $orderId);
        }
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * (POST API)
     *
     * @param array          $curlData
     * @param integer|string $gatewayId
     * @param integer|string $orderId   (default value: null) used in log file
     *
     * @return array
     */
    protected function excutePayment($curlData, $gatewayId, $orderId = null) {

        $curlData['PaymentMethodId'] = $gatewayId;

        $json = $this->callAPI("$this->apiURL/v2/ExecutePayment", $curlData, $orderId, 'Excute Payment'); //__FUNCTION__

        return ['invoiceURL' => $json->Data->PaymentURL, 'invoiceId' => $json->Data->InvoiceId];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * (POST API)
     *
     * @param array          $curlData
     * @param integer|string $orderId  (default value: null) used in log file
     *
     * @return array
     */
    protected function sendPayment($curlData, $orderId = null) {

        $curlData['NotificationOption'] = 'Lnk';

        $json = $this->callAPI("$this->apiURL/v2/SendPayment", $curlData, $orderId, 'Send Payment');

        return ['invoiceURL' => $json->Data->InvoiceURL, 'invoiceId' => $json->Data->InvoiceId];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get the direct payment URL and the invoice id (POST API)
     *
     * @param array          $curlData
     * @param integer|string $gateway
     * @param array          $cardInfo
     * @param integer|string $orderId  (default value: null) used in log file
     *
     * @return array
     */
    public function directPayment($curlData, $gateway, $cardInfo, $orderId = null) {

        $this->log('------------------------------------------------------------');

        $this->isDirectPayment = true;

        $data = $this->excutePayment($curlData, $gateway, $orderId);

        $json = $this->callAPI($data['invoiceURL'], $cardInfo, $orderId, 'Direct Payment'); //__FUNCTION__
        return ['invoiceURL' => $json->Data->PaymentURL, 'invoiceId' => $data['invoiceId']];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get the Payment Transaction Status (POST API)
     *
     * @param string         $keyId
     * @param string         $KeyType
     * @param integer|string $orderId (default value: null)
     * @param string         $price
     * @param string         $currncy
     *
     * @return object
     *
     * @throws Exception
     */
    public function getPaymentStatus($keyId, $KeyType, $orderId = null, $price = null, $currncy = null) {

        //payment inquiry
        $curlData = ['Key' => $keyId, 'KeyType' => $KeyType];
        $json     = $this->callAPI("$this->apiURL/v2/GetPaymentStatus", $curlData, $orderId, 'Get Payment Status');

        $msgLog = 'Order #' . $json->Data->CustomerReference . ' ----- Get Payment Status';

        //check for the order information
        if (!$this->checkOrderInformation($json, $orderId, $price, $currncy)) {
            $err = 'Trying to call data of another order';
            $this->log("$msgLog - Exception is $err");
            throw new Exception($err);
        }


        //check invoice status (Paid and Not Paid Cases)
        if ($json->Data->InvoiceStatus == 'Paid' || $json->Data->InvoiceStatus == 'DuplicatePayment') {
            $json->Data = $this->getSuccessData($json);
            $this->log("$msgLog - Status is Paid");
        } elseif ($json->Data->InvoiceStatus != 'Paid') {
            $json->Data = $this->getErrorData($json, $keyId, $KeyType);
            $this->log("$msgLog - Status is " . $json->Data->InvoiceStatus . '. Error is ' . $json->Data->InvoiceError);
        }

        return $json->Data;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param object $json
     * @param string $orderId
     * @param string $price
     * @param string $currncy
     *
     * @return boolean
     */
    protected function checkOrderInformation($json, $orderId = null, $price = null, $currncy = null) {

        //check for the order ID
        if ($orderId && $json->Data->CustomerReference != $orderId) {
            return false;
        }

        //check for the order price and currency
        list($valStr, $mfCurrncy) = explode(' ', $json->Data->InvoiceDisplayValue);
        $mfPrice = floatval(preg_replace('/[^\d.]/', '', $valStr));

        if ($price && $price != $mfPrice) {
            return false;
        }
        if ($currncy && $currncy != $mfCurrncy) {
            return false;
        }

        return true;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param object $json
     *
     * @return object
     */
    protected function getSuccessData($json) {

        foreach ($json->Data->InvoiceTransactions as $transaction) {
            if ($transaction->TransactionStatus == 'Succss') {
                $json->Data->InvoiceStatus = 'Paid';
                $json->Data->InvoiceError  = '';

                $json->Data->focusTransaction = $transaction;
                return $json->Data;
            }
        }
        return $json->Data;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param object $json
     * @param string $keyId
     * @param string $KeyType
     *
     * @return object
     */
    protected function getErrorData($json, $keyId, $KeyType) {

        //------------------
        //case 1: payment is Failed
        $focusTransaction = $this->{"getLastTransactionOf$KeyType"}($json, $keyId);
        if ($focusTransaction && $focusTransaction->TransactionStatus == 'Failed') {
            $json->Data->InvoiceStatus = 'Failed';
            $json->Data->InvoiceError  = $focusTransaction->Error . '.';

            $json->Data->focusTransaction = $focusTransaction;

            return $json->Data;
        }

        //------------------
        //case 2: payment is Expired
        //all myfatoorah gateway is set to Asia/Kuwait
        $ExpiryDateTime = $json->Data->ExpiryDate . ' ' . $json->Data->ExpiryTime;
        $ExpiryDate     = new \DateTime($ExpiryDateTime, new \DateTimeZone('Asia/Kuwait'));
        $currentDate    = new \DateTime('now', new \DateTimeZone('Asia/Kuwait'));

        if ($ExpiryDate < $currentDate) {
            $json->Data->InvoiceStatus = 'Expired';
            $json->Data->InvoiceError  = 'Invoice is expired since ' . $json->Data->ExpiryDate . '.';

            return $json->Data;
        }

        //------------------
        //case 3: payment is Pending
        //payment is pending .. user has not paid yet and the invoice is not expired
        $json->Data->InvoiceStatus = 'Pending';
        $json->Data->InvoiceError  = 'Pending Payment.';

        return $json->Data;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param object         $json
     * @param integer|string $keyId
     *
     * @return object
     */
    protected function getLastTransactionOfPaymentId($json, $keyId) {

        foreach ($json->Data->InvoiceTransactions as $transaction) {
            if ($transaction->PaymentId == $keyId && $transaction->Error) {
                return $transaction;
            }
        }
    }

    /**
     *
     * @param object $json
     *
     * @return object
     */
    protected function getLastTransactionOfInvoiceId($json) {

        usort($json->Data->InvoiceTransactions, function ($a, $b) {
            return strtotime($a->TransactionDate) - strtotime($b->TransactionDate);
        });

        return end($json->Data->InvoiceTransactions);
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Refund a given Payment (POST API)
     *
     * @param integer|string        $paymentId    payment id that will be refunded
     * @param double|integer|string $amount       the refund amount
     * @param string                $currencyCode the refund currency
     * @param string                $reason       reason of the refund
     * @param integer|string        $orderId      used in log file (default value: null)
     *
     * @return object
     */
    public function refund($paymentId, $amount, $currencyCode, $reason, $orderId = null) {

        $rate = $this->getCurrencyRate($currencyCode);
        $url  = "$this->apiURL/v2/MakeRefund";

        $postFields = [
            'KeyType'                 => 'PaymentId',
            'Key'                     => $paymentId,
            'RefundChargeOnCustomer'  => false,
            'ServiceChargeOnCustomer' => false,
            'Amount'                  => $amount / $rate,
            'Comment'                 => $reason,
        ];

        return $this->callAPI($url, $postFields, $orderId, 'Make Refund');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Create an invoice using Embedded session (POST API)
     *
     * @param array          $curlData  invoice information
     * @param integer|string $sessionId session id used in payment process
     * @param integer|string $orderId   used in log file (default value: null)
     *
     * @return array
     */
    public function embeddedPayment($curlData, $sessionId, $orderId = null) {

        $curlData['SessionId'] = $sessionId;

        $json = $this->callAPI("$this->apiURL/v2/ExecutePayment", $curlData, $orderId, 'Embedded Payment');
        return ['invoiceURL' => $json->Data->PaymentURL, 'invoiceId' => $json->Data->InvoiceId];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get session Data (POST API)
     *
     * @param string         $userDefinedField Customer Identifier to dispaly its saved data
     * @param integer|string $orderId          used in log file (default value: null)
     *
     * @return object
     */
    public function getEmbeddedSession($userDefinedField = '', $orderId = null) {

        $customerIdentifier = ['CustomerIdentifier' => $userDefinedField];
        
        $json = $this->callAPI("$this->apiURL/v2/InitiateSession", $customerIdentifier, $orderId, 'Initiate Session');
        return $json->Data;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Register Apple Pay Domain (POST API)
     *
     * @param string $url Site URL
     *
     * @return object
     */
    public function registerApplePayDomain($url) {

        $domainName = ['DomainName' => parse_url($url, PHP_URL_HOST)];
        return $this->callAPI("$this->apiURL/v2/RegisterApplePayDomain", $domainName, '', 'Register Apple Pay Domain');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
}
