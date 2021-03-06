<?php

namespace ccxt;

use Exception as Exception; // a common import

class flowbtc extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'flowbtc',
            'name' => 'flowBTC',
            'countries' => 'BR', // Brazil
            'version' => 'v1',
            'rateLimit' => 1000,
            'has' => array (
                'CORS' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/28162465-cd815d4c-67cf-11e7-8e57-438bea0523a2.jpg',
                'api' => 'https://api.flowbtc.com:8405/ajax',
                'www' => 'https://trader.flowbtc.com',
                'doc' => 'http://www.flowbtc.com.br/api/',
            ),
            'requiredCredentials' => array (
                'apiKey' => true,
                'secret' => true,
                'uid' => true,
            ),
            'api' => array (
                'public' => array (
                    'post' => array (
                        'GetTicker',
                        'GetTrades',
                        'GetTradesByDate',
                        'GetOrderBook',
                        'GetProductPairs',
                        'GetProducts',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'CreateAccount',
                        'GetUserInfo',
                        'SetUserInfo',
                        'GetAccountInfo',
                        'GetAccountTrades',
                        'GetDepositAddresses',
                        'Withdraw',
                        'CreateOrder',
                        'ModifyOrder',
                        'CancelOrder',
                        'CancelAllOrders',
                        'GetAccountOpenOrders',
                        'GetOrderFee',
                    ),
                ),
            ),
            'fees' => array (
                'trading' => array (
                    'tierBased' => false,
                    'percentage' => true,
                    'maker' => 0.0035,
                    'taker' => 0.0035,
                ),
            ),
        ));
    }

    public function fetch_markets () {
        $response = $this->publicPostGetProductPairs ();
        $markets = $response['productPairs'];
        $result = array ();
        for ($p = 0; $p < count ($markets); $p++) {
            $market = $markets[$p];
            $id = $market['name'];
            $base = $market['product1Label'];
            $quote = $market['product2Label'];
            $precision = array (
                'amount' => $this->safe_integer($market, 'product1DecimalPlaces'),
                'price' => $this->safe_integer($market, 'product2DecimalPlaces'),
            );
            $symbol = $base . '/' . $quote;
            $result[$symbol] = array (
                'id' => $id,
                'symbol' => $symbol,
                'base' => $base,
                'quote' => $quote,
                'precision' => $precision,
                'limits' => array (
                    'amount' => array (
                        'min' => null,
                        'max' => null,
                    ),
                    'price' => array (
                        'min' => null,
                        'max' => null,
                    ),
                    'cost' => array (
                        'min' => null,
                        'max' => null,
                    ),
                ),
                'info' => $market,
            );
        }
        return $result;
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $response = $this->privatePostGetAccountInfo ();
        $balances = $response['currencies'];
        $result = array ( 'info' => $response );
        for ($b = 0; $b < count ($balances); $b++) {
            $balance = $balances[$b];
            $currency = $balance['name'];
            $account = array (
                'free' => $balance['balance'],
                'used' => $balance['hold'],
                'total' => 0.0,
            );
            $account['total'] = $this->sum ($account['free'], $account['used']);
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $orderbook = $this->publicPostGetOrderBook (array_merge (array (
            'productPair' => $market['id'],
        ), $params));
        return $this->parse_order_book($orderbook, null, 'bids', 'asks', 'px', 'qty');
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $ticker = $this->publicPostGetTicker (array_merge (array (
            'productPair' => $market['id'],
        ), $params));
        $timestamp = $this->milliseconds ();
        $last = floatval ($ticker['last']);
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['high']),
            'low' => floatval ($ticker['low']),
            'bid' => floatval ($ticker['bid']),
            'bidVolume' => null,
            'ask' => floatval ($ticker['ask']),
            'askVolume' => null,
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => floatval ($ticker['volume24hr']),
            'quoteVolume' => floatval ($ticker['volume24hrProduct2']),
            'info' => $ticker,
        );
    }

    public function parse_trade ($trade, $market) {
        $timestamp = $trade['unixtime'] * 1000;
        $side = ($trade['incomingOrderSide'] === 0) ? 'buy' : 'sell';
        return array (
            'info' => $trade,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $market['symbol'],
            'id' => (string) $trade['tid'],
            'order' => null,
            'type' => null,
            'side' => $side,
            'price' => $trade['px'],
            'amount' => $trade['qty'],
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $response = $this->publicPostGetTrades (array_merge (array (
            'ins' => $market['id'],
            'startIndex' => -1,
        ), $params));
        return $this->parse_trades($response['trades'], $market, $since, $limit);
    }

    public function price_to_precision ($symbol, $price) {
        return $this->decimal_to_precision($price, ROUND, $this->markets[$symbol]['precision']['price'], $this->precisionMode);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        $orderType = ($type === 'market') ? 1 : 0;
        $order = array (
            'ins' => $this->market_id($symbol),
            'side' => $side,
            'orderType' => $orderType,
            'qty' => $amount,
            'px' => $this->price_to_precision($symbol, $price),
        );
        $response = $this->privatePostCreateOrder (array_merge ($order, $params));
        return array (
            'info' => $response,
            'id' => $response['serverOrderId'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        if (is_array ($params) && array_key_exists ('ins', $params)) {
            return $this->privatePostCancelOrder (array_merge (array (
                'serverOrderId' => $id,
            ), $params));
        }
        throw new ExchangeError ($this->id . ' requires `ins` $symbol parameter for cancelling an order');
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'] . '/' . $this->version . '/' . $path;
        if ($api === 'public') {
            if ($params) {
                $body = $this->json ($params);
            }
        } else {
            $this->check_required_credentials();
            $nonce = $this->nonce ();
            $auth = (string) $nonce . $this->uid . $this->apiKey;
            $signature = $this->hmac ($this->encode ($auth), $this->encode ($this->secret));
            $body = $this->json (array_merge (array (
                'apiKey' => $this->apiKey,
                'apiNonce' => $nonce,
                'apiSig' => strtoupper ($signature),
            ), $params));
            $headers = array (
                'Content-Type' => 'application/json',
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array ($response) && array_key_exists ('isAccepted', $response))
            if ($response['isAccepted'])
                return $response;
        throw new ExchangeError ($this->id . ' ' . $this->json ($response));
    }
}
