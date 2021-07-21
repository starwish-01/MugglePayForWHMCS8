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
