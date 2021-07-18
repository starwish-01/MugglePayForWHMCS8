<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Register hook function call.
 */
add_hook('ClientAreaPageCart', 1, function ($vars) {
    if (isset($vars['gateways']['mugglepay'])) {
        $mugglepay = new WHMCS\Module\Gateway\MugglePay\MugglePay();

        $active_gateway = array();
        $gateway_var = getGatewayVariables('mugglepay');
        foreach ($gateway_var as $key => $val) {
            if (strpos($key, 'mp_pay_currency_') !== false && $val === 'on') {
                $active_gateway[] = str_replace('mp_pay_currency_', '', $key);
            }
        }

        $index = 0;
        foreach ($vars['gateways'] as $sysname => $gateway) {
            $index++;
            if ($sysname === 'mugglepay') {
                break;
            }
        }

        $mugglepay_methods = $mugglepay->get_methods($active_gateway);
        $appends= array();
        foreach ($mugglepay_methods as $sysname => $gateway) {
            $key = 'mugglepay_' . $sysname;
            $appends[$key] = $vars['gateways']['mugglepay'];
            $appends[$key]['sysname'] = $sysname;
            $appends[$key]['name'] = $gateway['title'];
        }
        $mugglepay->array_insert($vars['gateways'], $index, $appends);
    }

    return $vars;
});

add_hook('ShoppingCartCheckoutOutput', 1, function ($vars) {
    return <<<HTML
    <script>
        var $ = jQuery;
        $(document).ready(function() {
            $('input[name="paymentmethod"]').on('ifChecked change', function(){
                var checkeMethod = $('input[name="paymentmethod"]:checked').val()

                if (checkeMethod.indexOf('mugglepay')) {
                    $('input[name="paymentmethod"]:checked').val('mugglepay')
                }

                var form = $('form[name="orderfrm"]');
                var formActionPrePath = $(form).attr('action').split('?')[0];
                var formActionUrl = $(form).attr('action').split('?')[1];
                var searchParams = new URLSearchParams(formActionUrl);

                if (searchParams.has('mpayment')) {
                    searchParams.delete('mpayment')
                }
                searchParams.append('mpayment', checkeMethod)

                $(form).attr('action', formActionPrePath + '?' + searchParams.toString())
            })
        })
    </script>
    HTML;
});
