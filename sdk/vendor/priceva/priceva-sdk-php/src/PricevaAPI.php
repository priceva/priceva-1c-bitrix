<?php
/**
 * Created by PhpStorm.
 * S.Belichenko, email: stanislav@priceva.com
 * Date: 01.10.2018
 * Time: 16:52
 */

namespace Priceva;


use Priceva\Params\Filters;
use Priceva\Params\ProductFields;
use Priceva\Params\Sources;

/**
 * Class PricevaAPI
 *
 * @package Priceva
 */
class PricevaAPI
{
    const ACTION_MAIN_PING = 'main/ping';
    const ACTION_MAIN_DEMO = 'main/demo';

    const ACTION_PRODUCT_LIST = 'product/list';
    const ACTION_REPORT_LIST  = 'report/list';

    /**
     * @var string $api_key
     * @var int    $api_version
     * @var string $request_method
     */
    private $api_key        = '';
    private $api_version    = '';
    private $request_method = '';

    /**
     * @var Filters $filters
     */
    private $filters;
    /**
     * @var ProductFields $product_fields
     */
    private $product_fields;
    /**
     * @var Sources $sources
     */
    private $sources;

    /**
     * PricevaAPI constructor.
     *
     * @param        $api_key
     * @param string $api_version
     * @param string $request_method
     */
    public function __construct ( $api_key, $api_version = '1', $request_method = Request::METHOD_POST )
    {
        $this->api_key        = $api_key;
        $this->api_version    = $api_version;
        $this->request_method = $request_method;

        $this->filters        = new Filters();
        $this->sources        = new Sources();
        $this->product_fields = new ProductFields();
    }

    /**
     * @param array|Filters $filters
     *
     * @throws PricevaException
     */
    public function set_filters ( $filters )
    {
        $this->filters->merge($filters);
    }

    /**
     * @param array|Sources $sources
     *
     * @throws PricevaException
     */
    public function set_sources ( $sources )
    {
        $this->sources->merge($sources);
    }

    /**
     * @param array|ProductFields $product_fields
     *
     * @throws PricevaException
     */
    public function set_product_fields ( $product_fields )
    {
        $this->product_fields->merge($product_fields);
    }

    /**
     * @return Filters
     */
    public function get_filters ()
    {
        return $this->filters;
    }

    /**
     * @return Sources
     */
    public function get_sources ()
    {
        return $this->sources;
    }


    /**
     * @return ProductFields
     */
    public function get_product_fields ()
    {
        return $this->product_fields;
    }

    /**
     * @return Result
     * @throws PricevaException
     */
    public function main_ping ()
    {
        $request = new Request([
                                   'key'     => $this->api_key,
                                   'version' => $this->api_version,
                                   'action'  => self::ACTION_MAIN_PING,
                               ]);

        return $request->start();
    }

    /**
     * @return Result
     * @throws PricevaException
     */
    public function main_demo ()
    {
        $request = new Request([
                                   'key'     => $this->api_key,
                                   'version' => $this->api_version,
                                   'action'  => self::ACTION_MAIN_DEMO,
                               ]);

        return $request->start();
    }

    /**
     * @param array|Filters $filters
     * @param array|Sources $sources
     *
     * @return Result
     * @throws PricevaException
     */
    public function product_list ( $filters = [], $sources = [] )
    {
        $request = new Request([
                                   'key'     => $this->api_key,
                                   'version' => $this->api_version,
                                   'action'  => self::ACTION_PRODUCT_LIST,
                               ]);

        $this->set_filters($filters);
        $this->set_sources($sources);

        $request_params = [
            'params' => [
                'filters' => $filters,
                'sources' => $sources,
            ],
        ];


        return $request->start($request_params);
    }

    /**
     * @param array|Filters       $filters
     * @param array|ProductFields $product_fields
     *
     * @return Result
     * @throws PricevaException
     */
    public function report_list ( $filters = [], $product_fields = [] )
    {
        $request = new Request([
                                   'key'     => $this->api_key,
                                   'version' => $this->api_version,
                                   'action'  => self::ACTION_REPORT_LIST,
                               ]);

        $this->set_filters($filters);
        $this->set_product_fields($product_fields);

        $request_params = [
            'params' => [
                'filters'        => $filters,
                'product_fields' => $product_fields,
            ],
        ];

        return $request->start($request_params);
    }
}
