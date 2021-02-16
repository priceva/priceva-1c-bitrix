<?php
/**
 * Created by PhpStorm.
 * S.Belichenko, email: stanislav@priceva.com
 * Date: 08.10.2018
 * Time: 14:30
 */

namespace Priceva;


/**
 * Class Request
 *
 * @package Priceva
 */
class Request
{
    const URL_API_V1 = 'https://api.priceva.com/api/v%s/';

    const METHOD_POST = 'POST';
    const METHOD_GET  = 'GET';

    private $api_params = [];

    /**
     * Request constructor.
     *
     * @param $api_params
     */
    public function __construct ( $api_params )
    {
        $this->api_params = $api_params;
    }

    /**
     * @param string $action
     *
     * @return string
     */
    private function get_url ( $action )
    {
        return sprintf(self::URL_API_V1, $this->api_params[ 'version' ]) . $action;
    }

    /**
     *
     * @param array $request_params
     *
     * @return Result
     * @throws PricevaException
     */
    public function start ( $request_params = [] )
    {
        $ch = curl_init();

        $url = $this->get_url($this->api_params[ 'action' ]);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Apikey: " . $this->api_params[ 'key' ],
        ]);

        $response = curl_exec($ch);

        if ( $response ) {
            $response_result = json_decode($response);
            curl_close($ch);

            if ( $json_last_error = json_last_error() ) {
                throw new PricevaException(
                    'Server answer cannot be decoded. ' .
                    'JSON error code: ' . $json_last_error . ' ' .
                    'Part of API response: ' . substr($response, 0, 256),
                    500);
            } else {
                return new Result($response_result);
            }
        } else {
            $curl_error = curl_error($ch);
            curl_close($ch);
            throw new PricevaException('cURL error: ' . $curl_error, 500);
        }
    }
}
