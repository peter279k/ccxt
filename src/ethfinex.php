<?php

namespace ccxt;

use Exception as Exception; // a common import

class ethfinex extends bitfinex {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'ethfinex',
            'name' => 'Ethfinex',
            'countries' => 'VG',
            'version' => 'v1',
            'rateLimit' => 1500,
            // new metainfo interface
            'has' => array (
                'CORS' => false,
                'createDepositAddress' => true,
                'deposit' => true,
                'fetchClosedOrders' => true,
                'fetchDepositAddress' => true,
                'fetchFees' => true,
                'fetchFundingFees' => true,
                'fetchMyTrades' => true,
                'fetchOHLCV' => true,
                'fetchOpenOrders' => true,
                'fetchOrder' => true,
                'fetchTickers' => true,
                'fetchTradingFees' => true,
                'withdraw' => true,
            ),
            'timeframes' => array (
                '1m' => '1m',
                '5m' => '5m',
                '15m' => '15m',
                '30m' => '30m',
                '1h' => '1h',
                '3h' => '3h',
                '6h' => '6h',
                '12h' => '12h',
                '1d' => '1D',
                '1w' => '7D',
                '2w' => '14D',
                '1M' => '1M',
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/37555526-7018a77c-29f9-11e8-8835-8e415c038a18.jpg',
                'api' => 'https://api.ethfinex.com',
                'www' => 'https://www.ethfinex.com',
                'doc' => array (
                    'https://bitfinex.readme.io/v1/docs',
                    'https://github.com/bitfinexcom/bitfinex-api-node',
                    'https://www.ethfinex.com/api_docs',
                ),
            ),
        ));
    }
}
