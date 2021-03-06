<?php

namespace ccxt;

use Exception as Exception; // a common import

class bleutrade extends bittrex {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'bleutrade',
            'name' => 'Bleutrade',
            'countries' => 'BR', // Brazil
            'rateLimit' => 1000,
            'version' => 'v2',
            'has' => array (
                'CORS' => true,
                'fetchTickers' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/30303000-b602dbe6-976d-11e7-956d-36c5049c01e7.jpg',
                'api' => array (
                    'public' => 'https://bleutrade.com/api',
                    'account' => 'https://bleutrade.com/api',
                    'market' => 'https://bleutrade.com/api',
                ),
                'www' => 'https://bleutrade.com',
                'doc' => 'https://bleutrade.com/help/API',
                'fees' => 'https://bleutrade.com/help/fees_and_deadlines',
            ),
            'fees' => array (
                'funding' => array (
                    'withdraw' => array (
                        'ADC' => 0.1,
                        'BTA' => 0.1,
                        'BITB' => 0.1,
                        'BTC' => 0.001,
                        'BCC' => 0.001,
                        'BTCD' => 0.001,
                        'BTG' => 0.001,
                        'BLK' => 0.1,
                        'CDN' => 0.1,
                        'CLAM' => 0.01,
                        'DASH' => 0.001,
                        'DCR' => 0.05,
                        'DGC' => 0.1,
                        'DP' => 0.1,
                        'DPC' => 0.1,
                        'DOGE' => 10.0,
                        'EFL' => 0.1,
                        'ETH' => 0.01,
                        'EXP' => 0.1,
                        'FJC' => 0.1,
                        'BSTY' => 0.001,
                        'GB' => 0.1,
                        'NLG' => 0.1,
                        'HTML' => 1.0,
                        'LTC' => 0.001,
                        'MONA' => 0.01,
                        'MOON' => 1.0,
                        'NMC' => 0.015,
                        'NEOS' => 0.1,
                        'NVC' => 0.05,
                        'OK' => 0.1,
                        'PPC' => 0.1,
                        'POT' => 0.1,
                        'XPM' => 0.001,
                        'QTUM' => 0.1,
                        'RDD' => 0.1,
                        'SLR' => 0.1,
                        'START' => 0.1,
                        'SLG' => 0.1,
                        'TROLL' => 0.1,
                        'UNO' => 0.01,
                        'VRC' => 0.1,
                        'VTC' => 0.1,
                        'XVP' => 0.1,
                        'WDC' => 0.001,
                        'ZET' => 0.1,
                    ),
                ),
            ),
            'exceptions' => array (
                'Insufficient funds!' => '\\ccxt\\InsufficientFunds',
                'Invalid Order ID' => '\\ccxt\\InvalidOrder',
                'Invalid apikey or apisecret' => '\\ccxt\\AuthenticationError',
            ),
        ));
    }

    public function fetch_markets () {
        $markets = $this->publicGetMarkets ();
        $result = array ();
        for ($p = 0; $p < count ($markets['result']); $p++) {
            $market = $markets['result'][$p];
            $id = $market['MarketName'];
            $base = $market['MarketCurrency'];
            $quote = $market['BaseCurrency'];
            $base = $this->common_currency_code($base);
            $quote = $this->common_currency_code($quote);
            $symbol = $base . '/' . $quote;
            $precision = array (
                'amount' => 8,
                'price' => 8,
            );
            $active = $market['IsActive'];
            $result[] = array (
                'id' => $id,
                'symbol' => $symbol,
                'base' => $base,
                'quote' => $quote,
                'active' => $active,
                'info' => $market,
                'lot' => pow (10, -$precision['amount']),
                'precision' => $precision,
                'limits' => array (
                    'amount' => array (
                        'min' => $market['MinTradeSize'],
                        'max' => null,
                    ),
                    'price' => array (
                        'min' => null,
                        'max' => null,
                    ),
                    'cost' => array (
                        'min' => 0,
                        'max' => null,
                    ),
                ),
            );
        }
        return $result;
    }

    public function get_order_id_field () {
        return 'orderid';
    }

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            'market' => $this->market_id($symbol),
            'type' => 'ALL',
        );
        if ($limit !== null)
            $request['depth'] = $limit; // 50
        $response = $this->publicGetOrderbook (array_merge ($request, $params));
        $orderbook = $this->safe_value($response, 'result');
        if (!$orderbook)
            throw new ExchangeError ($this->id . ' publicGetOrderbook() returneded no result ' . $this->json ($response));
        return $this->parse_order_book($orderbook, null, 'buy', 'sell', 'Rate', 'Quantity');
    }
}
