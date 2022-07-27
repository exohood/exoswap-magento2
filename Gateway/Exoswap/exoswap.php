<?php

namespace exohood-exoswap-magento2\Payment\Gateway\exoswap;

class exoswap
{
    const URL = 'https://api.exoswap.com/api/v2';
    const ENDPOINT = 'https://exoswap.com';

    public static $public_token = '';
    public static $secret_token = '';
    public static $engine = '';

    public static function config($config)
    {
        if (isset($config['public_token'])) {
            self::$public_token = $config['public_token'];
        }

        if (isset($config['secret_token'])) {
            self::$secret_token = $config['secret_token'];
        }

        if (isset($config['engine'])) {
            self::$engine = $config['engine'];
        }
    }

    /**
     * @param $data
     * @param $secret
     * @param $timestamp
     * @return string
     */
    public static function generateHash($data, $secret, $timestamp)
    {
        return md5($data['amount'] . $data['currency'] . $data['shop_order_id'] . self::$engine . $secret . $timestamp);
    }

		/**
		 * @param string $method
		 * @param array $params
		 * @param array $header
		 * @return bool|mixed|string
		 * @throws \Exception
		 */
    public static function request($method, array $params = [], array $header = [])
    {
        if (empty(self::$public_token) || empty(self::$secret_token)) {
            throw new \Exception('No public or secret token', 0);
        }

        $timestamp = time();
        $id = md5($timestamp);

        $body = json_encode(
            [
                'jsonrpc' => '2.0',
                'id' => $id,
                'method' => $method,
                'params' => $params
            ]
        );

        $headers = array_merge(
            [
                'Content-Type: application/json',
                'Public-Key: ' . self::$public_token,
            ],
            $header
        );
        $curl = curl_init(self::URL);

        $curl_options = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
        ];

        curl_setopt_array($curl, $curl_options);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $raw_response = curl_exec($curl);
        $decoded_response = json_decode($raw_response, true);
        $response = $decoded_response ? $decoded_response : $raw_response;
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        curl_close($curl);

        if (!empty($err)) {
            throw new \Exception($err . ', please contact merchant', $http_status);
        }

        if ($http_status === 200) {
            return $response;
        } else {
            throw new \Exception('No access, please contact merchant', $http_status);
        }
    }

    /**
     * @param array $data
     * @return bool|mixed|string
     * @throws \Exception
     */
    public static function createOrder(array $data)
    {
        $timestamp = time();
        $hash = self::generateHash($data, self::$secret_token, $timestamp);
        $params = array_merge(
            $data,
            [
                'timestamp' => $timestamp,
                'account_engine' => self::$engine,
            ]
        );

        $response = self::request(
            'payment-gateway.order.create',
            $params,
            ['Signature: ' . $hash]
        );

        if ($response && $response['success']) {
            return self::ENDPOINT . "/pg-invoice/?" . http_build_query(
                    [
                        'id' => $response['result']['order_uuid'],
                        'key' => self::$public_token
                    ]
            );
        } else {
            throw new \Exception($response['error']['message']);
        }
    }
}
