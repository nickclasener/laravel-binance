<?php

namespace sabramooz\binance;

use Exception;

class BinanceAPI {

    protected string $key;       // API key
    protected string $secret;    // API secret
    protected string $url;       // API base URL
    protected string $surl;       // SAPI base URL
    protected int $recvWindow;   // The number of milliseconds after timestamp the request is valid for
    protected string $version;   // API version
    protected string $wapi_url;  // WAPI URL
    protected $curl;             // curl handle

    function __construct()
    {
        $this->boot();
        $this->setupCurl();
    }

    public function boot()
    {
        $this->key = config('binance.auth.key');
        $this->secret = config('binance.auth.secret');
        $this->url = config('binance.urls.api');
        $this->surl = config('binance.urls.sapi');
        $this->wapi_url = config('binance.urls.wapi');
        $this->recvWindow = config('binance.settings.timing');
    }

    public function setupCurl(): void
    {
        $this->curl = curl_init();

        $curl_options = [
            CURLOPT_SSL_VERIFYPEER => config('binance.settings.ssl'),
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Binance PHP API Agent',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 300
        ];

        curl_setopt_array($this->curl, $curl_options);
    }

    /**
     * Key and Secret setter function. It's required for TRADE, USER_DATA, USER_STREAM, MARKET_DATA endpoints.
     * https://github.com/binance-exchange/binance-official-api-docs/blob/master/rest-api.md#endpoint-security-type
     *
     * @param string $key API Key
     * @param string $secret API Secret
     */
    public function setAPI(string $key, string $secret): void
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * Get ticker
     *
     * @return mixed
     * @throws Exception
     */
    public function getTickers(): array
    {
        return $this->request('v3/ticker/price');
    }

    /**
     * Make public requests (Security Type: NONE)
     *
     * @param string $url URL Endpoint
     * @param array $params Required and optional parameters
     * @param string $method GET, POST, PUT, DELETE
     * @return mixed
     * @throws Exception
     */
    private function request(string $url, array $params = [], string $method = 'GET'): array
    {
        curl_setopt($this->curl, CURLOPT_URL, $this->url . $url);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array());

        if ($method == 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, count($params));
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $params);
        }

        $result = curl_exec($this->curl);
        if ($result === false) {
            throw new Exception('CURL error: ' . curl_error($this->curl));
        }

        $result = json_decode($result, true);

        if (!is_array($result) || json_last_error()) {
            throw new Exception('JSON decode error');
        }

        return $result;
    }

    /**
     * Get ticker
     *
     * @param string $symbol
     * @return mixed
     * @throws Exception
     */
    public function getSymbolPriceByTicker(string $symbol): array
    {
        $data = [
            'symbol' => $symbol
        ];
        return $this->request('v3/ticker/price?symbol=' . $symbol, $data);
    }

    public function this()
    {
        return $this;
    }

    /**
     * Get ticker
     *
     * @param  $symbol
     * @return mixed
     * @throws Exception
     */
    public function getSymbolAvgPrice(string $symbol): array
    {
        $data = [
            'symbol' => $symbol
        ];
        return $this->request('v3/avgPrice?symbol=' . $symbol, $data);
    }

    public function getCurrencies(): bool
    {
        //Seems to be no such functionality
        return false;
    }

    /**
     * Current exchange trading rules and symbol information
     *
     * @return mixed
     * @throws Exception
     */
    public function getMarkets(): array
    {
        $return = $this->request('v3/exchangeInfo');
        return $return['symbols'];
    }

    /**
     * Get current account information
     *
     * @return mixed
     * @throws Exception
     */
    public function getBalances(): array
    {
        $b = $this->privateRequest('v3/account');
        return $b['balances'];
    }

    /**
     * Make private requests (Security Type: TRADE, USER_DATA, USER_STREAM, MARKET_DATA)
     *
     * @param string $url URL Endpoint
     * @param array $params Required and optional parameters
     * @param string $method GET, POST, PUT, DELETE
     * @return mixed
     * @throws Exception
     */
    public function sRequest($url, $params = [], $method = 'GET'): array
    {
        // build the POST data string
        $params['timestamp'] = number_format((microtime(true) * 1000), 0, '.', '');
        $params['recvWindow'] = $this->recvWindow;

        $query = http_build_query($params);

        // set API key and sign the message
        $sign = hash_hmac('sha256', $query, $this->secret);

        $headers = array(
            'X-MBX-APIKEY: ' . $this->key
        );

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);

        //$postdata = $params;

        curl_setopt($this->curl, CURLOPT_URL, $this->surl . $url . "?{$query}&signature={$sign}");

        if ($method == "POST") {
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, []);
        }

        $result = curl_exec($this->curl);
        if ($result === false) {
            throw new Exception('CURL error: ' . curl_error($this->curl));
        }

        $result = json_decode($result, true);
        if (!is_array($result) || json_last_error()) {
            throw new Exception('JSON decode error');
        }

        return $result;
    }

    /**
     * Make private requests (Security Type: TRADE, USER_DATA, USER_STREAM, MARKET_DATA)
     *
     * @param string $url URL Endpoint
     * @param array $params Required and optional parameters
     * @param string $method GET, POST, PUT, DELETE
     * @return mixed
     * @throws Exception
     */
    private function privateRequest($url, $params = [], $method = 'GET'): array
    {
        // build the POST data string
        $params['timestamp'] = number_format((microtime(true) * 1000), 0, '.', '');
        $params['recvWindow'] = $this->recvWindow;

        $query = http_build_query($params);

        // set API key and sign the message
        $sign = hash_hmac('sha256', $query, $this->secret);

        $headers = array(
            'X-MBX-APIKEY: ' . $this->key
        );

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);

        //$postdata = $params;

        curl_setopt($this->curl, CURLOPT_URL, $this->url . $url . "?{$query}&signature={$sign}");

        if ($method == "POST") {
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, []);
        }

        $result = curl_exec($this->curl);
        if ($result === false) {
            throw new Exception('CURL error: ' . curl_error($this->curl));
        }

        $result = json_decode($result, true);
        if (!is_array($result) || json_last_error()) {
            throw new Exception('JSON decode error');
        }

        return $result;
    }

    /**
     * Get trades for a specific account and symbol
     *
     * @param string $symbol Currency pair
     * @param int $limit Limit of trades. Max. 500
     * @return mixed
     * @throws Exception
     */
    public function getRecentTrades(string $symbol = 'BNBBTC', int $limit = 500): array
    {
        $data = [
            'symbol' => $symbol,
            'limit' => $limit,
        ];

        return $this->privateRequest('v3/myTrades', $data);
    }

    public function getOpenOrders(): array
    {
        return $this->privateRequest('v3/openOrders');
    }

    public function getAllOrders(string $symbol)
    {
        $data = [
            'symbol' => $symbol
        ];

        return $this->privateRequest('v3/allOrders', $data);
    }

    /**
     * Sell at market price
     *
     * @param string $symbol Asset pair to trade
     * @param string $quantity Amount of trade asset
     * @return mixed
     * @throws Exception
     */
    public function marketSell(string $symbol, string $quantity): array
    {
        return $this->trade($symbol, $quantity, 'SELL');
    }

    /**
     * Buy at market price
     *
     * @param string $symbol Asset pair to trade
     * @param string $quantity Amount of trade asset
     * @return mixed
     * @throws Exception
     */
    public function marketBuy(string $symbol, string $quantity): array
    {
        return $this->trade($symbol, $quantity, 'BUY');
    }

    /**
     * Sell limit
     *
     * @param string $symbol Asset pair to trade
     * @param string $quantity Amount of trade asset
     * @param float $price Limit price to sell
     * @return mixed
     * @throws Exception
     */
    public function limitSell(string $symbol, string $quantity, float $price): array
    {
        return $this->trade($symbol, $quantity, 'SELL', 'LIMIT', $price);
    }

    /**
     * Buy limit
     *
     * @param string $symbol Asset pair to trade
     * @param string $quantity Amount of trade asset
     * @param float $price Limit price to buy
     * @return mixed
     * @throws Exception
     */
    public function limitBuy(string $symbol, string $quantity, float $price): array
    {
        return $this->trade($symbol, $quantity, 'BUY', 'LIMIT', $price);
    }

    /**
     * Base trade function
     *
     * @param string $symbol Asset pair to trade
     * @param string $quantity Amount of trade asset
     * @param string $side BUY, SELL
     * @param string $type MARKET, LIMIT, STOP_LOSS, STOP_LOSS_LIMIT, TAKE_PROFIT, TAKE_PROFIT_LIMIT, LIMIT_MAKER
     * @param bool $price Limit price
     * @return mixed
     * @throws Exception
     */
    public function trade(string $symbol, string $quantity, string $side, string $type = 'MARKET', bool $price = false): array
    {
        $data = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'quantity' => $quantity
        ];

        if ($price !== false) {
            $data['price'] = $price;
        }

        return $this->privateRequest('v3/order', $data, 'POST');
    }

    /**
     * Deposit Address
     *
     * @param string $symbol Asset symbol
     * @return mixed
     *
     * @throws Exception
     */
    public function getDepositAddress(string $symbol): array
    {
        return $this->wapiRequest("v3/depositAddress.html", ['asset' => $symbol]);
    }

    /**
     * Make wapi requests
     *
     * @param string $url URL Endpoint
     * @param array $params Required and optional parameters
     * @param string $method GET, POST, PUT, DELETE
     * @return mixed
     * @throws Exception
     */
    private function wapiRequest(string $url, array $params = [], string $method = 'GET'): array
    {
        $params['timestamp'] = number_format((microtime(true) * 1000), 0, '.', '');
        $params['recvWindow'] = $this->recvWindow;

        $query = http_build_query($params);

        $sign = hash_hmac('sha256', $query, $this->secret);

        $headers = array(
            'X-MBX-APIKEY: ' . $this->key
        );

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);

        $postdata = $params;

        curl_setopt($this->curl, CURLOPT_URL, $this->wapi_url . $url . "?{$query}&signature={$sign}");

        if ($method == "POST") {
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, array($postdata));
        }

        $result = curl_exec($this->curl);
        if ($result === false) {
            throw new Exception('CURL error: ' . curl_error($this->curl));
        }

        $result = json_decode($result, true);
        if (!is_array($result) || json_last_error()) {
            throw new Exception('JSON decode error');
        }

        return $result;
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }
}
