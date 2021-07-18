<?php
namespace WHMCS\Module\Gateway\MugglePay;

include(dirname(__FILE__) . '/../vendor/autoload.php');
include(dirname(__FILE__) . '/../mugglepay-php-sdk-main/Core.php');

class MugglePay extends \Mugglepay
{
    /** @var string MugglePay API url. */
    public static $API_URL = "https://api.mugglepay.com/v1/";

    /** @var array Status description if found, an empty string otherwise. */
    public static $HEADER_CODE_DESC = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',

        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        226 => 'IM Used',

        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',

        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    );

    public function __construct($api_key = '')
    {
        parent::__construct($api_key, self::$API_URL);
    }
    
    /**
     * Convert Invoice ID To Mugglepay Order ID
     * @param { Number } $invoices - Invoice ID Number
     */
    public function convert_order_id($invoices)
    {
        return 'WHMCS_' . $invoices;
    }

    /**
     * Convert Order ID To Mugglepay Invoice ID
     * @param { String } $order_id - MugglePayOrderId
     */
    public function convert_invoices_id($order_id)
    {
        return (int)str_replace("WHMCS_", "", $order_id);
    }

    /**
     * Convert HTTP URLs to HTTPS
     */
    public function is_http_to_https($url)
    {
        if ($this->is_https() && !empty($url) && substr(strtolower($url), 0, 5) !== 'https') {
            $url =  'https' . substr($url, 0 - strlen($url) + 4);
        }
        return $url;
    }

    /**
     * Inserts a new object anywhere in the object
     */
    public function array_insert(&$array, $position, $insert_array)
    {
        $first_array = array_splice($array, 0, $position);
        $array = array_merge($first_array, $insert_array, $array);
    }

    /**
     * Check that HTTPS is currently available
     */
    public function is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        return false;
    }

    /**
     * Get all payment gateways
     * @return Returns the specified gateway configuration, default returns all
     */
    public function get_methods($sysname = '')
    {
        $methods = array(
            'card' => array(
                'title' => 'MugglePay Card',
                'currency'   => 'CARD'
            ),
            'alipay' => array(
                'title' => 'MugglePay Alipay',
                'currency'   => 'ALIPAY'
            ),
            'alipay_global' => array(
                'title' => 'MugglePay Alipay Global',
                'currency'   => 'ALIGLOBAL'
            ),
            'wechat' => array(
                'title' => 'MugglePay Wechat',
                'currency'   => 'WECHAT'
            ),
            'btc' => array(
                'title' => 'MugglePay BTC',
                'currency'   => 'BTC'
            ),
            'ltc' => array(
                'title' => 'MugglePay LTC',
                'currency'   => 'LTC'
            ),
            'eth' => array(
                'title' => 'MugglePay ETH',
                'currency'   => 'ETH'
            ),
            'eos' => array(
                'title' => 'MugglePay EOS',
                'currency'   => 'EOS'
            ),
            'bch' => array(
                'title' => 'MugglePay BCH',
                'currency'   => 'BCH'
            ),
            'lbtc' => array(
                'title' => 'MugglePay LBTC (for Lightening BTC)',
                'currency'   => 'LBTC'
            ),
            'cusd' => array(
                'title' => 'MugglePay CUSD (for Celo Dollars)',
                'currency'   => 'CUSD'
            )
        );

        if ($sysname) {
            if (is_array($sysname)) {
                $ret = array();
                foreach ($sysname as $name) {
                    $ret[] = $methods[$name];
                }
                return $ret;
            } else {
                if (!isset($methods[$sysname])) {
                    return false;
                }
    
                return $methods[$sysname];
            }
        }

        return $methods;
    }

    /**
     * HTTP Response and Error Codes
     * Most common API errors are as follows, including message, reason and status code.
     */
    public function get_error_str($code, $pay_currency = '')
    {
        switch ($code) {
            case 'AUTHENTICATION_FAILED':
                return 'Authentication Token is not set or expired.';
            case 'INVOICE_NOT_EXIST':
                return 'Invoice does not exist.';
            case 'INVOICE_VERIFIED_ALREADY':
                return 'It has been verified already.';
            case 'INVOICE_CANCELED_FAIILED':
                return 'Invoice does not exist, or it cannot be canceled.';
            case 'ORDER_NO_PERMISSION':
                return 'Order does not exist or permission denied.';
            case 'ORDER_CANCELED_FAIILED':
                return 'Order does not exist, or it cannot be canceled.';
            case 'ORDER_REFUND_FAILED':
                return 'Order does not exist, or it`s status is not refundable.';
            case 'ORDER_VERIFIED_ALREADY':
                return 'Payment has been verified with payment already.';
            case 'ORDER_VERIFIED_PRICE_NOT_MATCH':
                return 'Payment money does not match the order money, please double check the price.';
            case 'ORDER_VERIFIED_MERCHANT_NOT_MATCH':
                return 'Payment money does not the order of current merchant , please double check the order.';
            case 'ORDER_NOT_VALID':
                return 'Order id is not valid.';
            case 'ORDER_PAID_FAILED':
                return 'Order not exist or is not paid yet.';
            case 'ORDER_MERCHANTID_EXIST':
                return 'Order with same merchant_order_id exisits.';
            case 'ORDER_NOT_NEW':
                return 'The current order is not new, and payment method cannot be switched.';
            case 'PAYMENT_NOT_AVAILABLE':
                return 'The payment method is not working, please retry later.';
            case 'MERCHANT_CALLBACK_STATUS_WRONG':
                return 'The current payment status not ready to send callback.';
            case 'PARAMETERS_MISSING':
                return 'Missing parameters.';
            case 'PAY_PRICE_ERROR':
                switch ($pay_currency) {
                    case 'WECHAT':
                    case 'ALIPAY':
                    case 'ALIGLOBAL':
                        return 'The payment is temporarily unavailable, please use another payment method';
                }
                return 'Price amount or currency is not set correctly.';
            case 'CREDENTIALS_NOT_MATCH':
                return 'The email or password does not match.';
            case 'USER_NOT_EXIST':
                return 'The user does not exist or no permission.';
            case 'USER_FAILED':
                return 'The user operatioin failed.';
            case 'INVITATION_FAILED':
                return 'The invitation code is not filled correctly.';
            case 'ERROR':
                return 'Error.';
            case '(Unauthorized)':
                return 'API credentials are not valid';
            case '(Not Found)':
                return 'Page, action not found';
            case '(Too Many Requests)':
                return 'API request limit is exceeded';
            case '(InternalServerError)':
                return 'Server error in MugglePay';
        }
        return 'Server error in MugglePay';
    }

    /**
     * Add Invoice Payment.
     * @return Boolean
     */
    public function order_paid($order)
    {
        $order = (object) $order;

        // Get Gateway Variables.
        $GATEWAY = getGatewayVariables('mugglepay');

        $invoiceId = $this->convert_invoices_id($order->merchant_order_id);
        
        //  Validate Callback Invoice ID.
        $invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY['paymentmethod']);

        // Check Callback Transaction ID.
        checkCbTransID($order->order_id);

        // Add Invoice Payment.
        addInvoicePayment(
            $invoiceId,
            $order->order_id,
            '0',
            '0.00',
            $GATEWAY['paymentmethod']
        );

        return true;
    }

    /**
     * Create Object Error logs
     */
    public function create_error_message($error_str, $logs = array())
    {
        return json_encode(
            array(
                'message'   => $error_str,
                'detail'    => $logs
            )
        );
    }

    /**
     * Send a JSON response back.
     */
    public function send_header_json($response, $status_code = null)
    {
        if (! headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            if (null !== $status_code) {
                $protocol = $_SERVER['SERVER_PROTOCOL'];
                $desc = self::$HEADER_CODE_DESC[(int) $status_code] || "";
                header("$protocol $status_code $desc", true, $status_code);
            }
        }
     
        $json = json_encode($response);
 
        // If json_encode() was successful, no need to do more sanity checking.
        if (false !== $json) {
            return $json;
        }
    
        return json_encode($response);
    }
}
