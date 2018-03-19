<?php

namespace ccxt;

class wex extends liqui {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'wex',
            'name' => 'WEX',
            'countries' => 'NZ', // New Zealand
            'version' => '3',
            'has' => array (
                'CORS' => false,
                'fetchTickers' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/30652751-d74ec8f8-9e31-11e7-98c5-71469fcef03e.jpg',
                'api' => array (
                    'public' => 'https://wex.nz/api',
                    'private' => 'https://wex.nz/tapi',
                ),
                'www' => 'https://wex.nz',
                'doc' => array (
                    'https://wex.nz/api/3/docs',
                    'https://wex.nz/tapi/docs',
                ),
                'fees' => 'https://wex.nz/fees',
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'info',
                        'ticker/{pair}',
                        'depth/{pair}',
                        'trades/{pair}',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'getInfo',
                        'Trade',
                        'ActiveOrders',
                        'OrderInfo',
                        'CancelOrder',
                        'TradeHistory',
                        'TransHistory',
                        'CoinDepositAddress',
                        'WithdrawCoin',
                        'CreateCoupon',
                        'RedeemCoupon',
                    ),
                ),
            ),
            'fees' => array (
                'trading' => array (
                    'maker' => 0.2 / 100,
                    'taker' => 0.2 / 100,
                ),
                'funding' => array (
                    'withdraw' => array (
                        'BTC' => 0.001,
                        'LTC' => 0.001,
                        'NMC' => 0.1,
                        'NVC' => 0.1,
                        'PPC' => 0.1,
                        'DASH' => 0.001,
                        'ETH' => 0.003,
                        'BCH' => 0.001,
                        'ZEC' => 0.001,
                    ),
                ),
            ),
            'exceptions' => array (
                'messages' => array (
                    'bad status' => '\\ccxt\\OrderNotFound',
                    'Requests too often' => '\\ccxt\\DDoSProtection',
                    'not available' => '\\ccxt\\DDoSProtection',
                    'external service unavailable' => '\\ccxt\\DDoSProtection',
                ),
            ),
        ));
    }

    public function parse_ticker ($ticker, $market = null) {
        $timestamp = $ticker['updated'] * 1000;
        $symbol = null;
        if ($market)
            $symbol = $market['symbol'];
        $last = $this->safe_float($ticker, 'last');
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => $this->safe_float($ticker, 'high'),
            'low' => $this->safe_float($ticker, 'low'),
            'bid' => $this->safe_float($ticker, 'sell'),
            'bidVolume' => null,
            'ask' => $this->safe_float($ticker, 'buy'),
            'askVolume' => null,
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => $this->safe_float($ticker, 'avg'),
            'baseVolume' => $this->safe_float($ticker, 'vol_cur'),
            'quoteVolume' => $this->safe_float($ticker, 'vol'),
            'info' => $ticker,
        );
    }

    public function handle_errors ($code, $reason, $url, $method, $headers, $body) {
        if ($code === 200) {
            if ($body[0] !== '{') {
                // $response is not JSON -> resort to default $error handler
                return;
            }
            $response = json_decode ($body, $as_associative_array = true);
            if (is_array ($response) && array_key_exists ('success', $response)) {
                if (!$response['success']) {
                    $error = $this->safe_string($response, 'error');
                    if (!$error) {
                        throw new ExchangeError ($this->id . ' returned a malformed $error => ' . $body);
                    }
                    if ($error === 'no orders') {
                        // returned by fetchOpenOrders if no open orders (fix for #489) -> not an $error
                        return;
                    }
                    $feedback = $this->id . ' ' . $this->json ($response);
                    $messages = $this->exceptions.messages;
                    if (is_array ($messages) && array_key_exists ($error, $messages)) {
                        throw new $messages[$error] ($feedback);
                    }
                    if (mb_strpos ($error, 'It is not enough') !== false) {
                        throw new InsufficientFunds ($feedback);
                    } else {
                        throw new ExchangeError ($feedback);
                    }
                }
            }
        }
    }
}
