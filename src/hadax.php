<?php

namespace ccxt;

use Exception as Exception; // a common import

class hadax extends huobipro {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'hadax',
            'name' => 'HADAX',
            'countries' => 'CN',
            'hostname' => 'api.hadax.com',
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/38059952-4756c49e-32f1-11e8-90b9-45c1eccba9cd.jpg',
                'api' => 'https://api.hadax.com',
                'www' => 'https://www.hadax.com',
                'doc' => 'https://github.com/huobiapi/API_Docs/wiki',
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'hadax/common/symbols', // 查询系统支持的所有交易对
                        'hadax/common/currencys', // 查询系统支持的所有币种
                        'common/timestamp', // 查询系统当前时间
                    ),
                ),
            ),
        ));
    }

    public function fetch_markets () {
        $response = $this->publicGetHadaxCommonSymbols ();
        return $this->parseMarkets ($response['data']);
    }
}
