<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 12.10.2018
 * Time: 11:58
 */

namespace Priceva\Params;


use Priceva\Contracts\Params;

/**
 * Class ProductFields
 *
 * @package Priceva\Params
 */
class ProductFields extends Params
{
    protected $flat = true;
    protected $valid_parameters = [
        'client_code',
        'articul',
        'name',
        'active',
        'default_price',
        'default_available',
        'default_discount_type',
        'default_discount',
        'repricing_min',
        'default_currency',
    ];
}
