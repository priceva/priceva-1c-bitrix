<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 17.01.2019
 * Time: 18:08
 */

namespace Priceva\Connector\Bitrix;

require_once __DIR__ . "/../../sdk/vendor/autoload.php";


use Bitrix\Catalog\Model\Price;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CCatalogGroup;
use CCatalogProduct;
use CIBlockElement;
use CIBlockResult;
use COption;
use CPrice;
use Exception;
use Priceva\Connector\Bitrix\Helpers\CommonHelpers;
use Priceva\Params\{Filters as PricevaFilters, ProductFields as PricevaProductFields};
use Priceva\PricevaAPI;
use Priceva\PricevaException;
use stdClass;
use Throwable;

// вызов лингвистического файла
Loc::loadMessages(__FILE__);

class PricevaConnector
{
    private $info = [
        "product_not_found_priceva" => 0,
        "product_not_found_bitrix"  => 0,
        "articul_priceva_is_empty"  => 0,
        "articul_bitrix_is_empty"   => 0,
        "symbol_bitrix_is_empty"    => 0,
        "product_duplicate"         => 0,
        "price_is_null_priceva"     => 0,
        "product_synced"            => 0,
        "product_not_synced"        => 0,
        "module_errors"             => 0,
        "priceva_errors"            => 0,
    ];

    /**
     * @var Options
     */
    private $options;

    public function __construct()
    {
        $this->options = Options::getInstance();
    }

    /**
     * @param $aGlobalMenu
     * @param $aModuleMenu
     *
     * @return array
     */
    public function AddGlobalMenuItem( &$aGlobalMenu, &$aModuleMenu )
    {
        $aModuleMenu[] = [
            "parent_menu" => "global_menu_custom",
            "icon"        => "default_menu_icon",
            "page_icon"   => "default_page_icon",
            "sort"        => 100,
            "text"        => Loc::getMessage("PRICEVA_BC_MANUAL"),
            "title"       => Loc::getMessage("PRICEVA_BC_MANUAL"),
            "url"         => "/bitrix/admin/priceva_bc.php?lang=" . LANGUAGE_ID,
            "more_url"    => [],
        ];

        $arRes = [
            "global_menu_custom" => [
                "menu_id"      => "priceva",
                "page_icon"    => "services_title_icon",
                "index_icon"   => "services_page_icon",
                "text"         => "Priceva",
                "title"        => "Priceva",
                "sort"         => 150,
                "items_id"     => "global_menu_priceva",
                "help_section" => "custom",
                "items"        => [],
            ],
        ];

        return $arRes;
    }

    public static function agent()
    {
        ( new static() )->run();

        return "\Priceva\Connector\Bitrix\PricevaConnector::agent();";
    }

    public function run()
    {
        try{
            $common_helpers = CommonHelpers::getInstance();

            Loc::loadLanguageFile($common_helpers->get_current_path() . '/../../options.php');

            if( !Loader::includeModule('catalog') ){
                throw new PricevaModuleException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_CATALOG_NOT_INSTALLED"));
            }

            if( !CommonHelpers::check_php_ext() ){
                throw new PricevaModuleException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_PHP_EXT"));
            }

            $options = OptionsPage::get_main_options();

            $check_result = true;

            foreach( $options as $option_name => $option ){
                if( false !== strripos($option_name, 'HEADING') ){
                    continue;
                }

                $option_value                 = COption::GetOptionString(CommonHelpers::MODULE_ID, $option_name);
                if( !OptionsPage::check_option($option_name, $option_value) ){
                    $check_result = $check_result && false;
                }
            }

            if( !$check_result ){
                throw new PricevaModuleException('Sync failed. Check options op module options page.');
            }

            $api_key          = Options::api_key();
            $sync_only_active = Options::sync_only_active();

            $this->sync($api_key, $sync_only_active);
        }catch( PricevaModuleException $e ){
            ++$this->info[ 'module_errors' ];
            CommonHelpers::write_to_log($e);
        }catch( PricevaException $e ){
            ++$this->info[ 'priceva_errors' ];
            CommonHelpers::write_to_log($e);
        }catch( Throwable $e ){
            ++$this->info[ 'module_errors' ];
            CommonHelpers::write_to_log($e);
        }
    }

    /**
     * @param string $api_key
     * @param bool   $sync_only_active
     *
     * @throws PricevaException
     * @throws Exception
     */
    private function sync( $api_key, $sync_only_active )
    {
        $id_type_of_price = Options::type_price_ID();
        $price_recalc     = Options::price_recalc();
        $currency         = Options::currency();
        $sync_field       = Options::sync_field();
        $sync_dominance   = Options::sync_dominance();

        switch( $sync_dominance ){
            case "priceva":
                {
                    $this->sync_priceva_to_bitrix($api_key, $id_type_of_price, $price_recalc, $currency, $sync_field, $sync_only_active);
                    break;
                }
            case "bitrix":
                {
                    $this->sync_bitrix_to_priceva($api_key, $id_type_of_price, $price_recalc, $currency, $sync_field, $sync_only_active);
                    break;
                }
            default:
                throw new Exception("Wrong sync dominance type in module " . CommonHelpers::MODULE_ID);
        }

        $info     = $this->get_last_info_msg();
        $info_str =
            "Errors: " . implode(', ', $info[ 'errors' ]) . "; " .
            "Warnings: " . implode(', ', $info[ 'warnings' ]) . "; " .
            "Success: " . implode(', ', $info[ 'success' ]) . ".";

        CommonHelpers::write_to_log($info_str, 'PRICEVA_SYNC');
    }

    /**
     * @noinspection PhpUndefinedClassInspection
     *
     * @param string $api_key
     * @param int    $id_type_of_price
     * @param bool   $price_recalc
     * @param string $currency
     * @param string $sync_field
     * @param bool   $sync_only_active
     *
     * @throws PricevaException
     * @throws PricevaModuleException
     */
    private function sync_priceva_to_bitrix(
        $api_key,
        $id_type_of_price,
        $price_recalc,
        $currency,
        $sync_field,
        $sync_only_active
    ){
        $priceva_products = $this->get_priceva_products($api_key, $sync_only_active);

        foreach( $priceva_products as $priceva_product ){
            $this->process_priceva_product($sync_field, $sync_only_active, $priceva_product, $currency, $id_type_of_price, $price_recalc);
        }
    }

    /**
     * @param string   $sync_field
     * @param bool     $sync_only_active
     * @param stdClass $priceva_product
     * @param string   $currency
     * @param int      $id_type_of_price
     * @param bool     $price_recalc
     *
     * @throws PricevaModuleException
     */
    private function process_priceva_product(
        $sync_field,
        $sync_only_active,
        $priceva_product,
        $currency,
        $id_type_of_price,
        $price_recalc
    ){
        if( $bitrix_product = $this->get_bitrix_product($sync_field, $sync_only_active, $priceva_product) ){
            if( 0 < $price = $this->get_recommend_price($priceva_product) ){
                $this->set_price($bitrix_product[ 'ID' ], $price, $currency, $id_type_of_price, $price_recalc);
            }
        }else{
            ++$this->info[ 'product_not_found_bitrix' ];
        }
    }

    /**
     * @noinspection PhpUndefinedClassInspection
     *
     * @param string   $sync_field
     * @param bool     $sync_only_active
     * @param stdClass $priceva_product
     *
     * @return array|bool
     * @throws PricevaModuleException
     */
    private function get_bitrix_product( $sync_field, $sync_only_active, $priceva_product )
    {
        $arFilter = $this->prepare_filter_product($sync_only_active);

        if( $sync_field === "articul" ){
            $articul = $priceva_product->articul;

            if( !$articul ){
                ++$this->info[ 'articul_priceva_is_empty' ];

                return [];
            }

            $arFilter = array_merge($arFilter, [
                '=PROPERTY_ARTNUMBER' => $articul,
            ]);
        }else{
            $client_code             = $priceva_product->client_code;
            $what_use_as_client_code = Options::client_code();

            $arFilter = array_merge($arFilter, [
                $what_use_as_client_code => $client_code,
            ]);
        }

        $products = CIBlockElement::GetList([], $arFilter);

        if( $products->SelectedRowsCount() > 1 ){
            ++$this->info[ 'product_duplicate' ];

            return false;
        }

        return $products->getNext();
    }

    /**
     * @param $sync_only_active
     *
     * @return CIBlockResult|int
     * @throws PricevaModuleException
     */
    private function get_bitrix_products( $sync_only_active )
    {
        $arFilter = $this->prepare_filter_product($sync_only_active);

        return CIBlockElement::GetList([], $arFilter);
    }

    /**
     * @param string $api_key
     * @param int    $id_type_of_price
     * @param bool   $price_recalc
     * @param string $currency
     * @param string $sync_field
     * @param bool   $sync_only_active
     *
     * @throws PricevaException
     * @throws PricevaModuleException
     */
    private function sync_bitrix_to_priceva(
        $api_key,
        $id_type_of_price,
        $price_recalc,
        $currency,
        $sync_field,
        $sync_only_active
    ){
        $priceva_products = $this->get_priceva_products($api_key, $sync_only_active);

        $bitrix_products = $this->get_bitrix_products($sync_only_active);

        while( $bitrix_product = $bitrix_products->Fetch() ){
            $this->process_bitrix_product($sync_field, $bitrix_product, $priceva_products, $currency, $id_type_of_price, $price_recalc);
        }
    }

    /**
     * @return array
     * @throws PricevaModuleException
     */
    private function prepare_iblock_ids()
    {
        $iblock_ids = [];

        $simple_product_enable = $this->options->SIMPLE_PRODUCT_ENABLE;

        if( $simple_product_enable ){
            $iblock_mode = $this->options->IBLOCK_MODE;

            switch( $iblock_mode ){
                case 'ALL':
                    {
                        $iblocks    = CommonHelpers::get_iblocks($this->options->IBLOCK_TYPE_ID);
                        $iblock_ids = $iblock_ids + array_keys($iblocks);

                        break;
                    }
                case 'ONE':
                    {
                        $iblock_ids[] = $this->options->IBLOCK_ID;

                        break;
                    }
                default:
                    {
                        throw new PricevaModuleException('Wrong value of IBLOCK_MODE option.');

                        break;
                    }
            }
        }

        $trade_offers_enable = $this->options->TRADE_OFFERS_ENABLE;

        if( $trade_offers_enable ){
            $trade_offers_iblock_mode = $this->options->TRADE_OFFERS_IBLOCK_MODE;

            switch( $trade_offers_iblock_mode ){
                case 'ALL':
                    {
                        $trade_offers_iblocks = CommonHelpers::get_iblocks($this->options->TRADE_OFFERS_IBLOCK_TYPE_ID);
                        $iblock_ids           = $iblock_ids + array_keys($trade_offers_iblocks);

                        break;
                    }
                case 'ONE':
                    {
                        $iblock_ids[] = $this->options->TRADE_OFFERS_IBLOCK_ID;

                        break;
                    }
                default:
                    {
                        throw new PricevaModuleException('Wrong value of TRADE_OFFERS_IBLOCK_MODE option.');

                        break;
                    }
            }
        }

        return $iblock_ids;
    }

    /**
     * @param bool $sync_only_active
     *
     * @return array
     * @throws PricevaModuleException
     */
    private function prepare_filter_product( $sync_only_active )
    {
        $iblock_ids = $this->prepare_iblock_ids();

        // start to generate filters
        // infoblocks
        $cnt_iblocks = count($iblock_ids);

        switch( $cnt_iblocks ){
            case 1:
                {
                    $arFilter = [
                        'IBLOCK_ID' => array_shift($iblock_ids),
                    ];
                    break;
                }
            default:
                {
                    $iblocks_filter = [ "LOGIC" => "OR" ];

                    foreach( $iblock_ids as $iblock_id ){
                        $iblocks_filter[] = [ 'IBLOCK_ID' => $iblock_id ];
                    }

                    $arFilter = [ $iblocks_filter ];

                    break;
                }
            case 0:
                {
                    throw new PricevaModuleException('You must select at least one version of the iblock in the module settings.');
                    break;
                }
        }
        // active / not active products
        if( $sync_only_active ){
            $arFilter = array_merge($arFilter, [
                'ACTIVE'      => 'Y',
                'ACTIVE_DATE' => 'Y',
            ]);
        }

        return $arFilter;
    }

    /**
     * @param string $sync_field
     * @param array  $product
     *
     * @return bool|mixed
     * @throws PricevaModuleException
     */

    private function get_bitrix_sync_code( $sync_field, $product )
    {
        if( $sync_field === "articul" ){
            $bitrix_articul = $this->get_bitrix_articul($product[ 'ID' ]);
            if( $bitrix_articul ){
                return $bitrix_articul;
            }else{
                ++$this->info[ 'articul_bitrix_is_empty' ];

                return false;
            }
        }else{
            switch( Options::client_code() ){
                case 'ID':
                    {
                        return $product[ 'ID' ];
                        break;
                    }
                case 'CODE':
                    {
                        if( $product[ 'CODE' ] ){
                            return $product[ 'CODE' ];
                        }else{
                            ++$this->info[ 'symbol_bitrix_is_empty' ];

                            return false;
                        }
                        break;
                    }
                default:
                    {
                        throw new PricevaModuleException('Wrong CLIENT_CODE option value');
                    }
            }
        }
    }

    /**
     * @param string $sync_field
     * @param array  $bitrix_product
     * @param array  $reports
     * @param string $currency
     * @param int    $id_type_of_price
     * @param bool   $price_recalc
     *
     * @return bool
     * @throws PricevaModuleException
     */
    private function process_bitrix_product(
        $sync_field,
        $bitrix_product,
        $reports,
        $currency,
        $id_type_of_price,
        $price_recalc
    ){
        if( $priceva_product = $this->get_priceva_product($reports, $bitrix_product, $sync_field) ){
            if( 0 < $price = $this->get_recommend_price($priceva_product) ){
                $this->set_price($bitrix_product[ 'ID' ], $price, $currency, $id_type_of_price, $price_recalc);

                return true;
            }
        }

        return false;
    }

    private function get_bitrix_articul( $id )
    {
        $ar_res = CCatalogProduct::GetByIDEx($id);

        return isset($ar_res[ 'PROPERTIES' ][ 'ARTNUMBER' ][ 'VALUE' ]) ? $ar_res[ 'PROPERTIES' ][ 'ARTNUMBER' ][ 'VALUE' ] : false;
    }

    public function get_last_info_msg()
    {
        return [
            "errors"   => [
                Loc::getMessage("PRICEVA_BC_INFO_TEXT10") . ": {$this->info['product_duplicate']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT9") . ": {$this->info['priceva_errors']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT8") . ": {$this->info['module_errors']}",
            ],
            "warnings" => [
                Loc::getMessage("PRICEVA_BC_INFO_TEXT1") . ": {$this->info['product_not_found_priceva']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT2") . ": {$this->info['product_not_found_bitrix']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT3") . ": {$this->info['price_is_null_priceva']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT5") . ": {$this->info['product_not_synced']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT6") . ": {$this->info['articul_priceva_is_empty']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT7") . ": {$this->info['articul_bitrix_is_empty']}",
                Loc::getMessage("PRICEVA_BC_INFO_TEXT11") . ": {$this->info['symbol_bitrix_is_empty']}",
            ],
            "success"  => [
                Loc::getMessage("PRICEVA_BC_INFO_TEXT4") . ": {$this->info['product_synced']}",
            ],
        ];
    }

    /**
     * @param string $api_key
     * @param bool   $sync_only_active
     *
     * @return array
     * @throws PricevaException
     */
    private function get_priceva_products( $api_key, $sync_only_active )
    {
        $api = new PricevaAPI($api_key);

        $filters        = new PricevaFilters();
        $product_fields = new PricevaProductFields();

        $filters[ 'limit' ] = Options::download_at_time();
        $filters[ 'page' ]  = 1;

        if( $sync_only_active ){
            $filters[ 'active' ] = 1;
        }

        $product_fields[] = 'client_code';
        $product_fields[] = 'articul';

        $reports = $api->report_list($filters, $product_fields);

        $pages_cnt = (int)$reports->get_result()->pagination->pages_cnt;

        $objects = $reports->get_result()->objects;

        while( $pages_cnt > 1 ){
            $filters[ 'page' ] = $pages_cnt--;

            $reports = $api->report_list($filters, $product_fields);

            $objects = array_merge($objects, $reports->get_result()->objects);
        }

        return $objects;
    }

    /**
     * @param array  $priceva_products
     * @param array  $bitrix_product
     * @param string $sync_field
     *
     * @return int
     * @throws PricevaModuleException
     */
    private function get_priceva_product( $priceva_products, $bitrix_product, $sync_field )
    {
        $sync_code = $bitrix_sync_code = $this->get_bitrix_sync_code($sync_field, $bitrix_product);
        $key       = array_search($sync_code, array_column($priceva_products, $sync_field));

        if( $key === false ){
            ++$this->info[ 'product_not_found_priceva' ];

            return 0;
        }else{
            return $priceva_products[ $key ];
        }
    }

    private function get_recommend_price( $product )
    {
        if( $product->recommended_price == 0 ){
            ++$this->info[ 'price_is_null_priceva' ];
        }

        return $product->recommended_price;
    }

    /**
     * @param int $id_type_of_price
     *
     * @return bool
     */
    private function type_price_is_base( $id_type_of_price )
    {
        return CCatalogGroup::GetByID($id_type_of_price)[ 'BASE' ] === 'Y';
    }

    /**
     * @param int    $product_id
     * @param float  $price
     * @param string $currency
     * @param int    $price_type_id
     * @param bool   $price_recalc
     */
    private function set_price( $product_id, $price, $currency, $price_type_id, $price_recalc )
    {
        $arFields = [
            'PRODUCT_ID'       => $product_id,
            'CATALOG_GROUP_ID' => $price_type_id,
            'PRICE'            => $price,
            'CURRENCY'         => $currency,
            'RECALC'           => $price_recalc,

        ];

        if( $this->type_price_is_base($price_type_id) ){
            $result = CPrice::SetBasePrice($product_id, $price, $currency);
        }else{
            # workdnk check if exists
            $arExistsPrice = CPrice::GetList(
                [],
                [
                    'PRODUCT_ID'       => $product_id,
                    'CATALOG_GROUP_ID' => $price_type_id,
                ],
                false,
                false,
                [
                    'ID',
                ]
            )->Fetch();
            if ( $arExistsPrice ) {
                // $result = Price::update($product_id, $arFields);
                $result = CPrice::Update($arExistsPrice[ 'ID' ], $arFields);
            } else {
                $result = CPrice::Add($arFields);
            }
        }

        if( $result ){
            ++$this->info[ 'product_synced' ];
        }else{
            ++$this->info[ 'product_not_synced' ];
        }
    }
}
