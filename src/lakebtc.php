<?php

namespace ccxt;

use Exception as Exception; // a common import

class lakebtc extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'lakebtc',
            'name' => 'LakeBTC',
            'countries' => 'US',
            'version' => 'api_v2',
            'has' => array (
                'CORS' => true,
                'createMarketOrder' => false,
                'fetchTickers' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/28074120-72b7c38a-6660-11e7-92d9-d9027502281d.jpg',
                'api' => 'https://api.lakebtc.com',
                'www' => 'https://www.lakebtc.com',
                'doc' => array (
                    'https://www.lakebtc.com/s/api_v2',
                    'https://www.lakebtc.com/s/api',
                ),
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'bcorderbook',
                        'bctrades',
                        'ticker',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'buyOrder',
                        'cancelOrders',
                        'getAccountInfo',
                        'getExternalAccounts',
                        'getOrders',
                        'getTrades',
                        'openOrders',
                        'sellOrder',
                    ),
                ),
            ),
            'fees' => array (
                'trading' => array (
                    'maker' => 0.15 / 100,
                    'taker' => 0.2 / 100,
                ),
            ),
        ));
    }

    public function fetch_markets () {
        $markets = $this->publicGetTicker ();
        $result = array ();
        $keys = is_array ($markets) ? array_keys ($markets) : array ();
        for ($k = 0; $k < count ($keys); $k++) {
            $id = $keys[$k];
            $market = $markets[$id];
            $baseId = mb_substr ($id, 0, 3);
            $quoteId = mb_substr ($id, 3, 6);
            $base = strtoupper ($baseId);
            $quote = strtoupper ($quoteId);
            $symbol = $base . '/' . $quote;
            $result[] = array (
                'id' => $id,
                'symbol' => $symbol,
                'base' => $base,
                'quote' => $quote,
                'baseId' => $baseId,
                'quoteId' => $quoteId,
                'info' => $market,
            );
        }
        return $result;
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $response = $this->privatePostGetAccountInfo ();
        $balances = $response['balance'];
        $result = array ( 'info' => $response );
        $ids = is_array ($balances) ? array_keys ($balances) : array ();
        for ($i = 0; $i < count ($ids); $i++) {
            $id = $ids[$i];
            $code = $id;
            if (is_array ($this->currencies_by_id) && array_key_exists ($id, $this->currencies_by_id)) {
                $currency = $this->currencies_by_id[$id];
                $code = $currency['code'];
            }
            $balance = floatval ($balances[$id]);
            $account = array (
                'free' => $balance,
                'used' => 0.0,
                'total' => $balance,
            );
            $result[$code] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $orderbook = $this->publicGetBcorderbook (array_merge (array (
            'symbol' => $this->market_id($symbol),
        ), $params));
        return $this->parse_order_book($orderbook);
    }

    public function parse_ticker ($ticker, $market = null) {
        $timestamp = $this->milliseconds ();
        $symbol = null;
        if ($market !== null)
            $symbol = $market['symbol'];
        $last = $this->safe_float($ticker, 'last');
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => $this->safe_float($ticker, 'high'),
            'low' => $this->safe_float($ticker, 'low'),
            'bid' => $this->safe_float($ticker, 'bid'),
            'bidVolume' => null,
            'ask' => $this->safe_float($ticker, 'ask'),
            'askVolume' => null,
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => $this->safe_float($ticker, 'volume'),
            'quoteVolume' => null,
            'info' => $ticker,
        );
    }

    public function fetch_tickers ($symbols = null, $params = array ()) {
        $this->load_markets();
        $tickers = $this->publicGetTicker ($params);
        $ids = is_array ($tickers) ? array_keys ($tickers) : array ();
        $result = array ();
        for ($i = 0; $i < count ($ids); $i++) {
            $symbol = $ids[$i];
            $ticker = $tickers[$symbol];
            $market = null;
            if (is_array ($this->markets_by_id) && array_key_exists ($symbol, $this->markets_by_id)) {
                $market = $this->markets_by_id[$symbol];
                $symbol = $market['symbol'];
            }
            $result[$symbol] = $this->parse_ticker($ticker, $market);
        }
        return $result;
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $tickers = $this->publicGetTicker ($params);
        return $this->parse_ticker($tickers[$market['id']], $market);
    }

    public function parse_trade ($trade, $market) {
        $timestamp = $trade['date'] * 1000;
        return array (
            'info' => $trade,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $market['symbol'],
            'id' => (string) $trade['tid'],
            'order' => null,
            'type' => null,
            'side' => null,
            'price' => floatval ($trade['price']),
            'amount' => floatval ($trade['amount']),
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $response = $this->publicGetBctrades (array_merge (array (
            'symbol' => $market['id'],
        ), $params));
        return $this->parse_trades($response, $market, $since, $limit);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        if ($type === 'market')
            throw new ExchangeError ($this->id . ' allows limit orders only');
        $method = 'privatePost' . $this->capitalize ($side) . 'Order';
        $market = $this->market ($symbol);
        $order = array (
            'params' => [ $price, $amount, $market['id'] ],
        );
        $response = $this->$method (array_merge ($order, $params));
        return array (
            'info' => $response,
            'id' => (string) $response['id'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        return $this->privatePostCancelOrder (array ( 'params' => $id ));
    }

    public function nonce () {
        return $this->microseconds ();
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'] . '/' . $this->version;
        if ($api === 'public') {
            $url .= '/' . $path;
            if ($params)
                $url .= '?' . $this->urlencode ($params);
        } else {
            $this->check_required_credentials();
            $nonce = $this->nonce ();
            if ($params)
                $params = implode (',', $params);
            else
                $params = '';
            $query = $this->urlencode (array (
                'tonce' => $nonce,
                'accesskey' => $this->apiKey,
                'requestmethod' => strtolower ($method),
                'id' => $nonce,
                'method' => $path,
                'params' => $params,
            ));
            $body = $this->json (array (
                'method' => $path,
                'params' => $params,
                'id' => $nonce,
            ));
            $signature = $this->hmac ($this->encode ($query), $this->encode ($this->secret), 'sha1');
            $auth = $this->encode ($this->apiKey . ':' . $signature);
            $headers = array (
                'Json-Rpc-Tonce' => (string) $nonce,
                'Authorization' => 'Basic ' . $this->decode (base64_encode ($auth)),
                'Content-Type' => 'application/json',
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array ($response) && array_key_exists ('error', $response))
            throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        return $response;
    }
}
