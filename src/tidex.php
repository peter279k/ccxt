<?php

namespace ccxt;

use Exception as Exception; // a common import

class tidex extends liqui {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'tidex',
            'name' => 'Tidex',
            'countries' => 'UK',
            'rateLimit' => 2000,
            'version' => '3',
            'has' => array (
                // 'CORS' => false,
                // 'fetchTickers' => true
                'fetchCurrencies' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/30781780-03149dc4-a12e-11e7-82bb-313b269d24d4.jpg',
                'api' => array (
                    'web' => 'https://web.tidex.com/api',
                    'public' => 'https://api.tidex.com/api/3',
                    'private' => 'https://api.tidex.com/tapi',
                ),
                'www' => 'https://tidex.com',
                'doc' => 'https://tidex.com/exchange/public-api',
                'fees' => array (
                    'https://tidex.com/exchange/assets-spec',
                    'https://tidex.com/exchange/pairs-spec',
                ),
            ),
            'api' => array (
                'web' => array (
                    'get' => array (
                        'currency',
                        'pairs',
                        'tickers',
                        'orders',
                        'ordershistory',
                        'trade-data',
                        'trade-data/{id}',
                    ),
                ),
            ),
            'fees' => array (
                'trading' => array (
                    'tierBased' => false,
                    'percentage' => true,
                    'taker' => 0.1 / 100,
                    'maker' => 0.1 / 100,
                ),
            ),
            'commonCurrencies' => array (
                'MGO' => 'WMGO',
                'EMGO' => 'MGO',
            ),
        ));
    }

    public function fetch_currencies ($params = array ()) {
        $currencies = $this->webGetCurrency ($params);
        $result = array ();
        for ($i = 0; $i < count ($currencies); $i++) {
            $currency = $currencies[$i];
            $id = $currency['symbol'];
            $precision = $currency['amountPoint'];
            $code = strtoupper ($id);
            $code = $this->common_currency_code($code);
            $active = $currency['visible'] === true;
            $status = 'ok';
            if (!$active) {
                $status = 'disabled';
            }
            $canWithdraw = $currency['withdrawEnable'] === true;
            $canDeposit = $currency['depositEnable'] === true;
            if (!$canWithdraw || !$canDeposit) {
                $active = false;
            }
            $result[$code] = array (
                'id' => $id,
                'code' => $code,
                'name' => $currency['name'],
                'active' => $active,
                'status' => $status,
                'precision' => $precision,
                'funding' => array (
                    'withdraw' => array (
                        'active' => $canWithdraw,
                        'fee' => $currency['withdrawFee'],
                    ),
                    'deposit' => array (
                        'active' => $canDeposit,
                        'fee' => 0.0,
                    ),
                ),
                'limits' => array (
                    'amount' => array (
                        'min' => null,
                        'max' => pow (10, $precision),
                    ),
                    'price' => array (
                        'min' => pow (10, -$precision),
                        'max' => pow (10, $precision),
                    ),
                    'cost' => array (
                        'min' => null,
                        'max' => null,
                    ),
                    'withdraw' => array (
                        'min' => $currency['withdrawMinAmout'],
                        'max' => null,
                    ),
                    'deposit' => array (
                        'min' => $currency['depositMinAmount'],
                        'max' => null,
                    ),
                ),
                'info' => $currency,
            );
        }
        return $result;
    }

    public function get_version_string () {
        return '';
    }
}
