<?php
# Main in Payment Gateway Module
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function mugglepay_MetaData()
{
    return array(
        "DisplayName" => "MugglePay For WHMCS",
        "APIVersion" => "1.1"
    );
}

function mugglepay_config()
{
    $mugglepay = new WHMCS\Module\Gateway\MugglePay\MugglePay();
    $config = array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'MugglePay',
        ),
        'mp_token' => array(
            'FriendlyName' => 'API Auth Token (Api Key)<script>$(document).ready(function() {$("input[name=\"field[mp_token]\"]").attr("placeholder", "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx")})</script>',
            'Type' => 'text',
            'Size' => '36',
            'Default' => '',
            'Description' => sprintf('<br>Register your MugglePay merchant accounts with your invitation code and get your API key at <a href="%s" style="color: #2e6da4" target="_blank">Merchants Portal</a>. You will find your API Auth Token (API key) for authentication. <a href="%s" style="color: #2e6da4" target="_blank">MORE</a>', 'https://merchants.mugglepay.com/user/register?ref=MP9237F1193789', 'https://mugglepay.docs.stoplight.io/api-overview/authentication'),
        ),
        'mp_currency' => array(
            'FriendlyName' => 'Price Currency',
            'Type' => 'dropdown',
            'Options' => array(
                '' => 'Default System Settings',
                'CNY' => 'CNY',
                'USD' => 'USD',
            ),
            'Description' => '<br>The currency in which you wish to price your merchandise; used to define price parameter.',
        ),
        'mp_pay_currency' => array(
            'FriendlyName' => 'Pay Currency',
            'Description' => 'Set the MugglePay default payment gateway',
        )
    );

    $methods = $mugglepay->get_methods();
    foreach ($methods as $sysname => $gateway) {
        $config['mp_pay_currency_' . $sysname] = array(
            'FriendlyName' => '',
            'Type'  => 'yesno',
            'Description'   => $gateway['title']
        );
    }

    return $config;
}

function mugglepay_link($params)
{
    array(parse_str(html_entity_decode($_SERVER["QUERY_STRING"]), $query));

    $mugglepay = new WHMCS\Module\Gateway\MugglePay\MugglePay($params['mp_token']);

    $pay_currency = '';
    if (isset($query['mpayment'])) {
        $method = $mugglepay->get_methods($query['mpayment']);
        if ($method) {
            $pay_currency = $method['currency'];
        }
    }

    $merchant_order_id = $mugglepay->convert_order_id($params["invoiceid"]);
    $token = $mugglepay->sign($mugglepay->prepareSignId($merchant_order_id));

    // Only Use USD\CNY is allowed for settlement
    $price_currency = $params["currency"];
    if (!empty($params["mp_currency"])) {
        $price_currency = $params["mp_currency"];
    }
    if ($price_currency !== "CNY" && $price_currency !== "USD") {
        $price_currency = "USD";
    }

    // Fixed a 301 jump that could cause unreachable
    $params["systemurl"] = $mugglepay->is_http_to_https($params["systemurl"]);
    $params["returnurl"] = $mugglepay->is_http_to_https($params["returnurl"]);

    // Create description for charge based on order"s products. Ex: 1 x Product1, 2 x Product2
    try {
        $cart_items = array();
        foreach ($params["cart"]->items as $item) {
            array_push($cart_items, $item->name . " x " . $item->qty);
        }
        $description = mb_substr(implode(", ", $cart_items), 0, 200);
    } catch (Exception $e) {
        $description = '';
    }
    
    $mugglepay_args = array_filter(
        array(
            "merchant_order_id"	=> $merchant_order_id,
            "price_amount"		=> (float) $params["amount"],
            "price_currency"	=> $price_currency,
            "pay_currency"		=> $pay_currency,
            "title"				=> sprintf("Payment order #%s", $params["invoiceid"]),
            "description"		=> $description,
            "callback_url"		=> $params["systemurl"] . "modules/gateways/callback/mugglepay.php",
            "cancel_url"		=> $params["returnurl"] . "&paymentfailed=true",
            "success_url"		=> $params["returnurl"] . "&success=true",
            "mobile"			=> "",
            "fast"				=> "",
            "token"				=> $token
        )
    );
    
    try {
        $request = $mugglepay->mprequest($mugglepay_args);

        if ($request === false) {
            throw new Exception("MugglePay Connect Error");
        }

        $raw_response = json_decode($request);
        if (
            (($raw_response->status === 200 || $raw_response->status === 201) && $raw_response->payment_url) ||
            (($raw_response->status === 400 && $raw_response->error_code === 'ORDER_MERCHANTID_EXIST') && $raw_response->payment_url)
        ) {
            switch ($raw_response->order->status) {
                // Check the status of non-archived MugglePay orders.
                case 'PAID':
                    // Update Order Status
                    $mugglepay->order_paid($raw_response->order);

                    return <<<EOT
                        <script>
                            $(document).ready(function() {
                                window.location = "{$params["returnurl"]}";
                            })
                        </script>
                    EOT;
                    break;
                default:
                    $lang = $params["clientdetails"]["language"] === 'chinese' ? 'zh' : 'en';
                    $raw_response->payment_url .= '&lang=' . $lang;
                    return <<<EOT
                        <script>
                            $(document).ready(function() {
                                setTimeout(function() {
                                    window.location = "{$raw_response->payment_url}";
                                }, 1000)
                            })
                        </script>
                        <form action="viewinvoice.php?id={$params["invoiceid"]}" method="POST" enctype="text/plain">
                            <a href="{$raw_response->payment_url}" class="btn btn-primary btn-sm btn-block spinner-on-click">
                                {$params["langpaynow"]}
                            </a>
                        </form>
                    EOT;
                break;
            }
        } else {
            logTransaction("mugglepay", $raw_response, "Request Error");
            throw new Exception($mugglepay->get_error_str($raw_response->error_code, $pay_currency));
        }
    } catch (Exception $e) {
        return "<div class=\"alert alert alert-danger\">" . $e->getMessage() . "</div>";
    }
}

/**
 * Refunds
 */
function mugglepay_refund($params)
{
    $mugglepay = new WHMCS\Module\Gateway\MugglePay\MugglePay($params['mp_token']);
    $merchant_order_id = $params['transid'];
    $mugglepay_args = array_filter(
        array(
            "merchant_order_id"	=> $merchant_order_id
        )
    );
    
    try {
        $request = $mugglepay->mprequest($mugglepay_args, 'refund');

        if ($request === false) {
            throw new Exception("MugglePay Connect Error");
        }

        $raw_response = json_decode($request, false);

        if (empty($raw_response->status) || $raw_response->status !== 200) {
            throw new Exception($raw_response->error);
        }

        // Upon completion of the refund attempt, return the results in the following format:
        return array(
            'status'    => 'success',
            'rawdata'   => $raw_response,
            'transid'   => $merchant_order_id
        );
    } catch (Exception $e) {
        return array(
            'status'    => 'declined',
            'rawdata'   => 'MugglePay OrderId: [' . $merchant_order_id . "] \r\n" . print_r($raw_response, true),
            'transid'   => $merchant_order_id
        );
    }
}
