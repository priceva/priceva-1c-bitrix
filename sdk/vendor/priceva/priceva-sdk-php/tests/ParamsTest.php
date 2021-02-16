<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 11.10.2018
 * Time: 8:47
 */

namespace Priceva;


use Priceva\Contracts\Params;
use Priceva\Params\Filters;
use Priceva\Params\ProductFields;
use Priceva\Params\Sources;

class ParamsTest extends \PHPUnit_Framework_TestCase
{

    public function emptyParams()
    {
        $filters        = new Filters();
        $sources        = new Sources();
        $product_fields = new ProductFields();

        return [
            [
                $filters,
                'VALUES' => [
                    'page'        => 1,
                    'limit'       => 2,
                ],
            ],
            [
                $sources,
                'VALUES' => [
                    'add'      => true,
                    'add_term' => true,
                ],
            ],
            [
                $product_fields,
                'VALUES' => [
                    'client_code',
                    'articul',
                ],
            ],
        ];
    }

    public function fullyParams()
    {
        $filters        = new Filters();
        $sources        = new Sources();
        $product_fields = new ProductFields();

        $filters[ 'page' ]        = 1;
        $filters[ 'limit' ]       = 2;

        $sources [ 'add' ]      = true;
        $sources [ 'add_term' ] = true;

        $product_fields[] = 'client_code';
        $product_fields[] = 'articul';

        return [
            [
                $filters,
                'VALUES' => [
                    'page'        => 1,
                    'limit'       => 2,
                ],
            ],
            [
                $sources,
                'VALUES' => [
                    'add'      => true,
                    'add_term' => true,
                ],
            ],
            [
                $product_fields,
                'VALUES' => [
                    'client_code',
                    'articul',
                ],
            ],
        ];
    }

    /**
     * @param Params $instance
     * @param string $property
     *
     * @return mixed
     * @throws \ReflectionException
     */
    private function _getInnerPropertyValueByReflection( $instance, $property = 'flat' )
    {
        $reflector          = new \ReflectionClass($instance);
        $reflector_property = $reflector->getProperty($property);
        $reflector_property->setAccessible(true);

        return $reflector_property->getValue($instance);
    }

    /**
     * @dataProvider emptyParams
     *
     * @param Params $params
     * @param array  $values
     *
     * @return Params
     * @throws \ReflectionException
     */
    public function testOffsetSet( $params, $values )
    {
        $flat = $this->_getInnerPropertyValueByReflection($params);

        if( !$flat ){
            foreach( $values as $key => $value ){
                $params[ $key ] = $value;
            }
        }else{
            foreach( $values as $value ){
                $params[] = $value;
            }
        }

        return $params;
    }

    /**
     * @dataProvider fullyParams
     *
     * @param Params $params
     * @param array  $values
     */
    public function testGet_array( $params, $values )
    {
        $this->AssertEquals($params->get_array(), $values);
    }

    /**
     * @dataProvider fullyParams
     *
     * @param Params $params
     * @param array  $values
     *
     * @throws \ReflectionException
     */
    public function testOffsetGet( $params, $values )
    {
        $flat = $this->_getInnerPropertyValueByReflection($params);

        if( !$flat ){
            $params[ key($values) ] = 'test';
        }
    }

    /**
     * @dataProvider fullyParams
     *
     * @param Params $params
     * @param array  $values
     *
     * @throws \ReflectionException
     */
    public function testOffsetExists( $params, $values )
    {
        $flat = $this->_getInnerPropertyValueByReflection($params);

        if( !$flat ){
            $this->assertTrue($params->offsetExists(key($values)));
        }

    }

    /**
     * @dataProvider             fullyParams
     *
     * @expectedException \Priceva\PricevaException
     * @expectedExceptionMessageRegExp /(You can use only valid parameter names)|(You can use only valid options in flat parameter)/u
     *
     * @param Params $params
     *
     * @throws \ReflectionException
     */
    public function testOffsetSetThrowException( $params )
    {
        $flat = $this->_getInnerPropertyValueByReflection($params);

        if( !$flat ){
            $params[ 'wrong_param' ] = 1;
        }else{
            $params[] = 1;
        }
    }

    /**
     * @dataProvider fullyParams
     *
     * @param Params $params
     */
    public function testCount( $params )
    {
        $this->assertEquals(count($params), 2);
    }

    /**
     * @dataProvider fullyParams
     *
     * @param Params $params
     * @param array  $values
     *
     * @throws \ReflectionException
     */
    public function testOffsetUnset( $params, $values )
    {
        $flat = $this->_getInnerPropertyValueByReflection($params);

        if( !$flat ){
            unset($params[ key($values) ]);
            $this->assertEquals(count($params), 1);
        }
    }
}
