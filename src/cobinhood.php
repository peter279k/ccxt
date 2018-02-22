<?php

namespace ccxt;

class cobinhood extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'cobinhood',
            'name' => 'COBINHOOD',
            'countries' => 'TW',
            'rateLimit' => 1000 / 10,
            'has' => array (
                'fetchTickers' => true,
                'fetchOHLCV' => true,
                'fetchOpenOrders' => true,
                'fetchClosedOrders' => true,
                'fetchOrder' => true,
            ),
            'requiredCredentials' => array (
                'apiKey' => true,
                'secret' => false,
            ),
            'timeframes' => array (
                // the first two don't seem to work at all
                '1m' => '1m',
                '5m' => '5m',
                '15m' => '15m',
                '30m' => '30m',
                '1h' => '1h',
                '3h' => '3h',
                '6h' => '6h',
                '12h' => '12h',
                '1d' => '1D',
                '7d' => '7D',
                '14d' => '14D',
                '1M' => '1M',
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/35755576-dee02e5c-0878-11e8-989f-1595d80ba47f.jpg',
                'api' => array (
                    'web' => 'https://api.cobinhood.com/v1',
                    'ws' => 'wss://feed.cobinhood.com',
                ),
                'test' => array (
                    'web' => 'https://sandbox-api.cobinhood.com',
                    'ws' => 'wss://sandbox-feed.cobinhood.com',
                ),
                'www' => 'https://cobinhood.com',
                'doc' => 'https://cobinhood.github.io/api-public',
            ),
            'api' => array (
                'system' => array (
                    'get' => array (
                        'info',
                        'time',
                        'messages',
                        'messages/{message_id}',
                    ),
                ),
                'admin' => array (
                    'get' => array (
                        'system/messages',
                        'system/messages/{message_id}',
                    ),
                    'post' => array (
                        'system/messages',
                    ),
                    'patch' => array (
                        'system/messages/{message_id}',
                    ),
                    'delete' => array (
                        'system/messages/{message_id}',
                    ),
                ),
                'public' => array (
                    'get' => array (
                        'market/currencies',
                        'market/trading_pairs',
                        'market/orderbooks/{trading_pair_id}',
                        'market/stats',
                        'market/tickers/{trading_pair_id}',
                        'market/trades/{trading_pair_id}',
                        'chart/candles/{trading_pair_id}',
                    ),
                ),
                'private' => array (
                    'get' => array (
                        'trading/orders/{order_id}',
                        'trading/orders/{order_id}/trades',
                        'trading/orders',
                        'trading/order_history',
                        'trading/trades/{trade_id}',
                        'wallet/balances',
                        'wallet/ledger',
                        'wallet/deposit_addresses',
                        'wallet/withdrawal_addresses',
                        'wallet/withdrawals/{withdrawal_id}',
                        'wallet/withdrawals',
                        'wallet/deposits/{deposit_id}',
                        'wallet/deposits',
                    ),
                    'post' => array (
                        'trading/orders',
                        'wallet/deposit_addresses',
                        'wallet/withdrawal_addresses',
                        'wallet/withdrawals',
                    ),
                    'delete' => array (
                        'trading/orders/{order_id}',
                    ),
                ),
            ),
            'fees' => array (
                'trading' => array (
                    'maker' => 0.0,
                    'taker' => 0.0,
                ),
            ),
            'precision' => array (
                'amount' => 8,
                'price' => 8,
            ),
        ));
    }

    public function fetch_currencies ($params = array ()) {
        $response = $this->publicGetMarketCurrencies ($params);
        $currencies = $response['result'];
        $result = array ();
        for ($i = 0; $i < count ($currencies); $i++) {
            $currency = $currencies[$i];
            $id = $currency['currency'];
            $code = $this->common_currency_code($id);
            $result[$code] = array (
                'id' => $id,
                'code' => $code,
                'name' => $currency['name'],
                'active' => true,
                'status' => 'ok',
                'fiat' => false,
                'lot' => floatval ($currency['min_unit']),
                'precision' => 8,
                'funding' => array (
                    'withdraw' => array (
                        'active' => true,
                        'fee' => floatval ($currency['withdrawal_fee']),
                    ),
                    'deposit' => array (
                        'active' => true,
                        'fee' => floatval ($currency['deposit_fee']),
                    ),
                ),
                'info' => $currency,
            );
        }
        return $result;
    }

    public function fetch_markets () {
        $response = $this->publicGetMarketTradingPairs ();
        $markets = $response['result']['trading_pairs'];
        $result = array ();
        for ($i = 0; $i < count ($markets); $i++) {
            $market = $markets[$i];
            $id = $market['id'];
            list ($base, $quote) = explode ('-', $id);
            $symbol = $base . '/' . $quote;
            $result[] = array (
                'id' => $id,
                'symbol' => $symbol,
                'base' => $this->common_currency_code($base),
                'quote' => $this->common_currency_code($quote),
                'active' => true,
                'lot' => floatval ($market['quote_increment']),
                'limits' => array (
                    'amount' => array (
                        'min' => floatval ($market['base_min_size']),
                        'max' => floatval ($market['base_max_size']),
                    ),
                ),
                'info' => $market,
            );
        }
        return $result;
    }

    public function parse_ticker ($ticker, $market = null) {
        $symbol = $market['symbol'];
        $timestamp = null;
        if (is_array ($ticker) && array_key_exists ('timestamp', $ticker)) {
            $timestamp = $ticker['timestamp'];
        } else {
            $timestamp = $this->milliseconds ();
        }
        $info = $ticker;
        // from fetchTicker
        if (is_array ($ticker) && array_key_exists ('info', $ticker))
            $info = $ticker['info'];
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['high_24hr']),
            'low' => floatval ($ticker['low_24hr']),
            'bid' => floatval ($ticker['highest_bid']),
            'ask' => floatval ($ticker['lowest_ask']),
            'vwap' => null,
            'open' => null,
            'close' => null,
            'first' => null,
            'last' => $this->safe_float($ticker, 'last_price'),
            'change' => $this->safe_float($ticker, 'percentChanged24hr'),
            'percentage' => null,
            'average' => null,
            'baseVolume' => floatval ($ticker['base_volume']),
            'quoteVolume' => $this->safe_float($ticker, 'quote_volume'),
            'info' => $info,
        );
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $response = $this->publicGetMarketTickersTradingPairId (array_merge (array (
            'trading_pair_id' => $market['id'],
        ), $params));
        $ticker = $response['result']['ticker'];
        $ticker = array (
            'last_price' => $ticker['last_trade_price'],
            'highest_bid' => $ticker['highest_bid'],
            'lowest_ask' => $ticker['lowest_ask'],
            'base_volume' => $ticker['24h_volume'],
            'high_24hr' => $ticker['24h_high'],
            'low_24hr' => $ticker['24h_low'],
            'timestamp' => $ticker['timestamp'],
            'info' => $response,
        );
        return $this->parse_ticker($ticker, $market);
    }

    public function fetch_tickers ($symbols = null, $params = array ()) {
        $this->load_markets();
        $response = $this->publicGetMarketStats ($params);
        $tickers = $response['result'];
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

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            'trading_pair_id' => $this->market_id($symbol),
        );
        if ($limit !== null)
            $request['limit'] = $limit; // 100
        $response = $this->publicGetMarketOrderbooksTradingPairId (array_merge ($request, $params));
        return $this->parse_order_book($response['result']['orderbook'], null, 'bids', 'asks', 0, 2);
    }

    public function parse_trade ($trade, $market = null) {
        $symbol = null;
        if ($market)
            $symbol = $market['symbol'];
        $timestamp = $trade['timestamp'];
        $price = floatval ($trade['price']);
        $amount = floatval ($trade['size']);
        $cost = floatval ($this->cost_to_precision($symbol, $price * $amount));
        $side = $trade['maker_side'] === 'bid' ? 'sell' : 'buy';
        return array (
            'info' => $trade,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $symbol,
            'id' => $trade['id'],
            'order' => null,
            'type' => null,
            'side' => $side,
            'price' => $price,
            'amount' => $amount,
            'cost' => $cost,
            'fee' => null,
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = 50, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $response = $this->publicGetMarketTradesTradingPairId (array_merge (array (
            'trading_pair_id' => $market['id'],
            'limit' => $limit, // default 20, but that seems too little
        ), $params));
        $trades = $response['result']['trades'];
        return $this->parse_trades($trades, $market, $since, $limit);
    }

    public function parse_ohlcv ($ohlcv, $market = null, $timeframe = '5m', $since = null, $limit = null) {
        return [
            // they say that timestamps are Unix Timestamps in seconds, but in fact those are milliseconds
            $ohlcv['timestamp'],
            floatval ($ohlcv['open']),
            floatval ($ohlcv['high']),
            floatval ($ohlcv['low']),
            floatval ($ohlcv['close']),
            floatval ($ohlcv['volume']),
        ];
    }

    public function fetch_ohlcv ($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        //
        // they say in their docs that end_time defaults to current server time
        // but if you don't specify it, their range limits does not allow you to query anything
        //
        // they also say that start_time defaults to 0,
        // but most calls fail if you do not specify any of end_time
        //
        // to make things worse, their docs say it should be a Unix Timestamp
        // but with seconds it fails, so we set milliseconds (somehow it works that way)
        //
        $endTime = $this->milliseconds ();
        $request = array (
            'trading_pair_id' => $market['id'],
            'timeframe' => $this->timeframes[$timeframe],
            'end_time' => $endTime,
        );
        if ($since !== null)
            $request['start_time'] = $since;
        $response = $this->publicGetChartCandlesTradingPairId (array_merge ($request, $params));
        $ohlcv = $response['result']['candles'];
        return $this->parse_ohlcvs($ohlcv, $market, $timeframe, $since, $limit);
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $response = $this->privateGetWalletBalances ($params);
        $result = array ( 'info' => $response );
        $balances = $response['result']['balances'];
        for ($i = 0; $i < count ($balances); $i++) {
            $balance = $balances[$i];
            $id = $balance['currency'];
            $currency = $this->common_currency_code($id);
            $account = array (
                'free' => floatval ($balance['total']),
                'used' => floatval ($balance['on_order']),
                'total' => 0.0,
            );
            $account['total'] = $this->sum ($account['free'], $account['used']);
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function parse_order ($order, $market = null) {
        $symbol = null;
        if (!$market) {
            $marketId = $order['trading_pair'];
            $market = $this->markets_by_id[$marketId];
        }
        if ($market)
            $symbol = $market['symbol'];
        $timestamp = $order['timestamp'];
        $price = floatval ($order['price']);
        $amount = floatval ($order['size']);
        $filled = floatval ($order['filled']);
        $remaining = $amount - $filled;
        // new, queued, open, partially_filled, $filled, cancelled
        $status = $order['state'];
        if ($status === 'filled') {
            $status = 'closed';
        } else if ($status === 'cancelled') {
            $status = 'canceled';
        } else {
            $status = 'open';
        }
        $side = $order['side'] === 'bid' ? 'buy' : 'sell';
        return array (
            'id' => $order['id'],
            'datetime' => $this->iso8601 ($timestamp),
            'timestamp' => $timestamp,
            'status' => $status,
            'symbol' => $symbol,
            'type' => $order['type'], // $market, limit, stop, stop_limit, trailing_stop, fill_or_kill
            'side' => $side,
            'price' => $price,
            'cost' => $price * $amount,
            'amount' => $amount,
            'filled' => $filled,
            'remaining' => $remaining,
            'trades' => null,
            'fee' => null,
            'info' => $order,
        );
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $side = ($side === 'sell' ? 'ask' : 'bid');
        $request = array (
            'trading_pair_id' => $market['id'],
            // $market, limit, stop, stop_limit
            'type' => $type,
            'side' => $side,
            'size' => $this->amount_to_string($symbol, $amount),
        );
        if ($type !== 'market')
            $request['price'] = $this->price_to_precision($symbol, $price);
        $response = $this->privatePostTradingOrders (array_merge ($request, $params));
        $order = $this->parse_order($response['result']['order'], $market);
        $id = $order['id'];
        $this->orders[$id] = $order;
        return $order;
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $response = $this->privateDeleteTradingOrdersOrderId (array_merge (array (
            'order_id' => $id,
        ), $params));
        return $response;
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $response = $this->privateGetTradingOrdersOrderId (array_merge (array (
            'order_id' => (string) $id,
        ), $params));
        return $this->parse_order($response['result']['order']);
    }

    public function fetch_order_trades ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $response = $this->privateGetTradingOrdersOrderIdTrades (array_merge (array (
            'order_id' => $id,
        ), $params));
        $market = ($symbol === null) ? null : $this->market ($symbol);
        return $this->parse_trades($response['result'], $market);
    }

    public function create_deposit_address ($code, $params = array ()) {
        $this->load_markets();
        $currency = $this->currency ($code);
        $response = $this->privatePostWalletDepositAddresses (array (
            'currency' => $currency['id'],
        ));
        $address = $this->safe_string($response['result']['deposit_address'], 'address');
        if (!$address)
            throw new ExchangeError ($this->id . ' createDepositAddress failed => ' . $this->last_http_response);
        return array (
            'currency' => $code,
            'address' => $address,
            'status' => 'ok',
            'info' => $response,
        );
    }

    public function fetch_deposit_address ($code, $params = array ()) {
        $this->load_markets();
        $currency = $this->currency ($code);
        $response = $this->privateGetWalletDepositAddresses (array_merge (array (
            'currency' => $currency['id'],
        ), $params));
        $address = $this->safe_string($response['result']['deposit_addresses'], 'address');
        if (!$address)
            throw new ExchangeError ($this->id . ' fetchDepositAddress failed => ' . $this->last_http_response);
        return array (
            'currency' => $code,
            'address' => $address,
            'status' => 'ok',
            'info' => $response,
        );
    }

    public function withdraw ($code, $amount, $address, $params = array ()) {
        $this->load_markets();
        $currency = $this->currency ($code);
        $response = $this->privatePostWalletWithdrawals (array_merge (array (
            'currency' => $currency['id'],
            'amount' => $amount,
            'address' => $address,
        ), $params));
        return array (
            'id' => $response['result']['withdrawal_id'],
            'info' => $response,
        );
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api']['web'] . '/' . $this->implode_params($path, $params);
        $query = $this->omit ($params, $this->extract_params($path));
        $headers = array ();
        if ($api === 'private') {
            $this->check_required_credentials();
            // $headers['device_id'] = $this->apiKey;
            $headers['nonce'] = (string) $this->nonce ();
            $headers['Authorization'] = $this->apiKey;
        }
        if ($method === 'GET') {
            $query = $this->urlencode ($query);
            if (strlen ($query))
                $url .= '?' . $query;
        } else {
            $headers['Content-type'] = 'application/json; charset=UTF-8';
            $body = $this->json ($query);
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function handle_errors ($code, $reason, $url, $method, $headers, $body) {
        if ($code < 400 || $code >= 600) {
            return;
        }
        if ($body[0] !== '{') {
            throw new ExchangeError ($this->id . ' ' . $body);
        }
        $response = $this->unjson ($body);
        $message = $this->safe_value($response['error'], 'error_code');
        throw new ExchangeError ($this->id . ' ' . $message);
    }
}