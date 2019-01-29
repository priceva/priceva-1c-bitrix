<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 21.01.2019
 * Time: 16:03
 */

namespace Priceva\Connector\Bitrix\Helpers;


use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;

class CommonHelpers
{
    CONST MODULE_ID = 'priceva.connector';

    CONST NAME_PRICE_TYPE = 'PRICEVA';

    private static $instance;

    /**
     * @var \Bitrix\Main\Application|bool
     */
    public $app;
    /**
     * @var bool|\CAllMain|\CMain
     */
    public $APPLICATION;

    public function __construct()
    {
        $this->app         = $this->get_app();
        $this->APPLICATION = $this->get_application();
    }

    /**
     * @param string $val
     *
     * @return bool
     */
    private static function convert_to_bool( $val )
    {
        return $val === "YES";
    }

    /**
     * @return \Bitrix\Main\Application|bool
     */
    private function get_app()
    {
        global $APPLICATION;
        try{
            return \Bitrix\Main\Application::getInstance();
        }catch( \Bitrix\Main\SystemException $e ){
            $APPLICATION->ThrowException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_BITRIX_VERSION"));

            return false;
        }
    }

    private function get_application()
    {
        global $APPLICATION;

        if( $APPLICATION === null ){
            return false;
        }else{
            return $APPLICATION;
        }
    }

    /**
     * @return \Priceva\Connector\Bitrix\Helpers\CommonHelpers
     */
    public static function getInstance()
    {
        if( null === static::$instance ){
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param array $select_values
     *
     * @return array
     */
    public static function add_not_selected( $select_values )
    {
        return [ '0' => "Не выбрано" ] + $select_values;
    }

    /**
     * @return string|null
     */
    public function request_method()
    {
        return $this->app->getContext()->getRequest()->getRequestMethod();
    }

    /**
     * @param string $name
     *
     * @return string|null
     */
    public function get_post_param( $name )
    {
        return $this->app->getContext()->getRequest()->getPost($name);
    }

    /**
     * @return bool|string|null
     */
    public static function get_type_price_ID()
    {
        return \COption::GetOptionString(self::MODULE_ID, 'ID_TYPE_PRICE');
    }

    /**
     * @return bool|string|null
     */
    public static function get_api_key()
    {
        return \COption::GetOptionString(self::MODULE_ID, 'API_KEY');
    }

    /**
     * @return bool|string|null
     */
    public static function get_sync_dominance()
    {
        return \COption::GetOptionString(self::MODULE_ID, 'SYNC_DOMINANCE');
    }

    /**
     * @return bool|string|null
     */
    public static function get_sync_method()
    {
        return \COption::GetOptionString(self::MODULE_ID, 'SYNC_METHOD');
    }

    /**
     * @return bool|string|null
     */
    public static function get_sync_field()
    {
        return \COption::GetOptionString(self::MODULE_ID, 'SYNC_FIELD');
    }

    /**
     * @return bool|string|null
     */
    public static function get_price_recalc()
    {
        return self::convert_to_bool(\COption::GetOptionString(self::MODULE_ID, 'PRICE_RECALC'));
    }

    /**
     * @return bool|string|null
     */
    public static function get_currency()
    {
        return \COption::GetOptionString(self::MODULE_ID, 'CURRENCY');
    }

    /**
     * @return array
     * @throws LoaderException
     */
    public static function get_types_of_price()
    {
        $arr = [];

        if( !\Bitrix\Main\Loader::includeModule('catalog') ){
            throw new LoaderException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_CATALOG_NOT_INSTALLED"));
        }

        $dbPriceType = \CCatalogGroup::GetList();
        while( $arPriceType = $dbPriceType->Fetch() ){
            $arr[ $arPriceType[ 'ID' ] ] = $arPriceType[ 'NAME' ];
        }

        return $arr;
    }

    /**
     * @return array
     * @throws LoaderException
     */
    public static function get_currencies()
    {
        $arr = [];

        if( !\Bitrix\Main\Loader::includeModule('catalog') ){
            throw new LoaderException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_CATALOG_NOT_INSTALLED"));
        }
        $by           = "currency";
        $order        = "asc";
        $dbCurrencies = \CCurrency::GetList($by, $order);
        while( $dbCurrency = $dbCurrencies->Fetch() ){
            $arr[ $dbCurrency[ 'CURRENCY' ] ] = $dbCurrency[ 'FULL_NAME' ];
        }

        return $arr;
    }

    /**
     * @return bool|string|null
     */
    public static function get_agent_id()
    {
        return \COption::GetOptionString(self::MODULE_ID, 'AGENT_ID');
    }
}