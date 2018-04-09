<?php

namespace ccxt;

use Exception as Exception; // a common import

class btcbox extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'btcbox',
            'name' => 'BtcBox',
            'countries' => 'JP',
            'rateLimit' => 1000,
            'version' => 'v1',
            'has' => array (
                'CORS' => false,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/31275803-4df755a8-aaa1-11e7-9abb-11ec2fad9f2d.jpg',
                'api' => 'https://www.btcbox.co.jp/api',
                'www' => 'https://www.btcbox.co.jp/',
                'doc' => 'https://www.btcbox.co.jp/help/asm',
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'depth',
                        'orders',
                        'ticker',
                        'allticker',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'balance',
                        'trade_add',
                        'trade_cancel',
                        'trade_list',
                        'trade_view',
                        'wallet',
                    ),
                ),
            ),
            'markets' => array (
                'BTC/JPY' => array ( 'id' => 'BTC/JPY', 'symbol' => 'BTC/JPY', 'base' => 'BTC', 'quote' => 'JPY' ),
            ),
            'exceptions' => array (
                '104' => '\\ccxt\\AuthenticationError',
                '105' => '\\ccxt\\PermissionDenied',
                '106' => '\\ccxt\\InvalidNonce',
                '107' => '\\ccxt\\InvalidOrder', // price should be an integer
                '200' => '\\ccxt\\InsufficientFunds',
                '201' => '\\ccxt\\InvalidOrder', // amount too small
                '202' => '\\ccxt\\InvalidOrder', // price should be [0 : 1000000]
                '203' => '\\ccxt\\OrderNotFound',
                '401' => '\\ccxt\\OrderNotFound', // cancel canceled, closed or non-existent order
                '402' => '\\ccxt\\DDoSProtection',
            ),
        ));
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $balances = $this->privatePostBalance ();
        $result = array ( 'info' => $balances );
        $currencies = is_array ($this->currencies) ? array_keys ($this->currencies) : array ();
        for ($i = 0; $i < count ($currencies); $i++) {
            $currency = $currencies[$i];
            $lowercase = strtolower ($currency);
            if ($lowercase === 'dash')
                $lowercase = 'drk';
            $account = $this->account ();
            $free = $lowercase . '_balance';
            $used = $lowercase . '_lock';
            if (is_array ($balances) && array_key_exists ($free, $balances))
                $account['free'] = floatval ($balances[$free]);
            if (is_array ($balances) && array_key_exists ($used, $balances))
                $account['used'] = floatval ($balances[$used]);
            $account['total'] = $this->sum ($account['free'], $account['used']);
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array ();
        $numSymbols = is_array ($this->symbols) ? count ($this->symbols) : 0;
        if ($numSymbols > 1)
            $request['coin'] = $market['id'];
        $orderbook = $this->publicGetDepth (array_merge ($request, $params));
        return $this->parse_order_book($orderbook);
    }

    public function parse_ticker ($ticker, $market = null) {
        $timestamp = $this->milliseconds ();
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
            'bid' => $this->safe_float($ticker, 'buy'),
            'bidVolume' => null,
            'ask' => $this->safe_float($ticker, 'sell'),
            'askVolume' => null,
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => $this->safe_float($ticker, 'vol'),
            'quoteVolume' => $this->safe_float($ticker, 'volume'),
            'info' => $ticker,
        );
    }

    public function fetch_tickers ($symbols = null, $params = array ()) {
        $this->load_markets();
        $tickers = $this->publicGetAllticker ($params);
        $ids = is_array ($tickers) ? array_keys ($tickers) : array ();
        $result = array ();
        for ($i = 0; $i < count ($ids); $i++) {
            $id = $ids[$i];
            $market = $this->markets_by_id[$id];
            $symbol = $market['symbol'];
            $ticker = $tickers[$id];
            $result[$symbol] = $this->parse_ticker($ticker, $market);
        }
        return $result;
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array ();
        $numSymbols = is_array ($this->symbols) ? count ($this->symbols) : 0;
        if ($numSymbols > 1)
            $request['coin'] = $market['id'];
        $ticker = $this->publicGetTicker (array_merge ($request, $params));
        return $this->parse_ticker($ticker, $market);
    }

    public function parse_trade ($trade, $market) {
        $timestamp = intval ($trade['date']) * 1000; // GMT time
        return array (
            'info' => $trade,
            'id' => $trade['tid'],
            'order' => null,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $market['symbol'],
            'type' => null,
            'side' => $trade['type'],
            'price' => $trade['price'],
            'amount' => $trade['amount'],
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array ();
        $numSymbols = is_array ($this->symbols) ? count ($this->symbols) : 0;
        if ($numSymbols > 1)
            $request['coin'] = $market['id'];
        $response = $this->publicGetOrders (array_merge ($request, $params));
        return $this->parse_trades($response, $market, $since, $limit);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array (
            'amount' => $amount,
            'price' => $price,
            'type' => $side,
        );
        $numSymbols = is_array ($this->symbols) ? count ($this->symbols) : 0;
        if ($numSymbols > 1)
            $request['coin'] = $market['id'];
        $response = $this->privatePostTradeAdd (array_merge ($request, $params));
        return array (
            'info' => $response,
            'id' => $response['id'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        return $this->privatePostTradeCancel (array_merge (array (
            'id' => $id,
        ), $params));
    }

    public function parse_order ($order) {
        // array ("$id":11,"datetime":"2014-10-21 10:47:20","type":"sell","$price":42000,"amount_original":1.2,"amount_outstanding":1.2,"$status":"closed","$trades":array ())
        $id = $this->safe_string($order, 'id');
        $timestamp = $this->parse8601 ($order['datetime'] . '+09:00'); // Tokyo time
        $amount = $this->safe_float($order, 'amount_original');
        $remaining = $this->safe_float($order, 'amount_outstanding');
        $filled = null;
        if ($amount !== null)
            if ($remaining !== null)
                $filled = $amount - $remaining;
        $price = $this->safe_float($order, 'price');
        $cost = null;
        if ($price !== null)
            if ($filled !== null)
                $cost = $filled * $price;
        // $status is set by fetchOrder method only
        $statuses = array (
            // TODO => complete list
            'part' => 'open', // partially or not at all executed
            'all' => 'closed', // fully executed
            'cancelled' => 'canceled',
            'closed' => 'closed', // never encountered, seems to be bug in the doc
        );
        $status = null;
        if (is_array ($statuses) && array_key_exists ($order['status'], $statuses))
            $status = $statuses[$order['status']];
        // fetchOrders do not return $status, use heuristic
        if ($status === null)
            if ($remaining !== null && $remaining === 0)
                $status = 'closed';
        $trades = null; // todo => $this->parse_trades($order['trades']);
        return array (
            'id' => $id,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'amount' => $amount,
            'remaining' => $remaining,
            'filled' => $filled,
            'side' => $order['type'],
            'type' => null,
            'status' => $status,
            'symbol' => 'BTC/JPY',
            'price' => $price,
            'cost' => $cost,
            'trades' => $trades,
            'fee' => null,
            'info' => $order,
        );
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $response = $this->privatePostTradeView (array_merge (array (
            'id' => $id,
        ), $params));
        return $this->parse_order($response);
    }

    public function fetch_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $response = $this->privatePostTradeList (array_merge (array (
            'type' => 'all', // 'open' or 'all'
        ), $params));
        // status (open/closed/canceled) is null
        return $this->parse_orders($response);
    }

    public function fetch_open_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $response = $this->privatePostTradeList (array_merge (array (
            'type' => 'open', // 'open' or 'all'
        ), $params));
        $orders = $this->parse_orders($response);
        // btcbox does not return status, but we know it's 'open' as we queried for open $orders
        for ($i = 0; $i < count ($orders); $i++) {
            $order = $orders[$i];
            $order['status'] = 'open';
        }
        return $orders;
    }

    public function nonce () {
        return $this->milliseconds ();
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'] . '/' . $this->version . '/' . $path;
        if ($api === 'public') {
            if ($params)
                $url .= '?' . $this->urlencode ($params);
        } else {
            $this->check_required_credentials();
            $nonce = (string) $this->nonce ();
            $query = array_merge (array (
                'key' => $this->apiKey,
                'nonce' => $nonce,
            ), $params);
            $request = $this->urlencode ($query);
            $secret = $this->hash ($this->encode ($this->secret));
            $query['signature'] = $this->hmac ($this->encode ($request), $this->encode ($secret));
            $body = $this->urlencode ($query);
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function handle_errors ($httpCode, $reason, $url, $method, $headers, $body) {
        // typical error $response => array ("$result":false,"code":"401")
        if ($httpCode >= 400)
            return; // resort to defaultErrorHandler
        if ($body[0] !== '{')
            return; // not json, resort to defaultErrorHandler
        $response = json_decode ($body, $as_associative_array = true);
        $result = $this->safe_value($response, 'result');
        if ($result === null || $result === true)
            return; // either public API (no error codes expected) or success
        $errorCode = $this->safe_value($response, 'code');
        $feedback = $this->id . ' ' . $this->json ($response);
        $exceptions = $this->exceptions;
        if (is_array ($exceptions) && array_key_exists ($errorCode, $exceptions))
            throw new $exceptions[$errorCode] ($feedback);
        throw new ExchangeError ($feedback); // unknown message
    }
}
