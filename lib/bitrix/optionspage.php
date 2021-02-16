<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 24.04.2019
 * Time: 17:01
 */

namespace Priceva\Connector\Bitrix;


use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CAdminMessage;
use COption;
use CUtil;
use Priceva\Connector\Bitrix\Helpers\CommonHelpers;
use Priceva\Connector\Bitrix\Helpers\OptionsHelpers;

class OptionsPage
{
    /**
     * @param array $select_values
     *
     * @return array
     */
    public static function add_not_selected( $select_values )
    {
        return [ '0' => Loc::getMessage("PRICEVA_BC_COMMON_HELPERS_NOT_SELECTED") ] + $select_values;
    }

    /**
     * @return bool
     */
    public static function is_save_method()
    {
        global $Update, $Apply, $RestoreDefaults;

        return isset($Update) || isset($Apply) || isset($RestoreDefaults);
    }

    /**
     * @return bool
     */
    public static function is_restore_method()
    {
        global $RestoreDefaults;

        return isset($RestoreDefaults);
    }

    /**
     * @param array $filter
     *
     * @return string
     */
    public static function generate_js_script( $filter = [] )
    {
        $functions = [
            'showDownloadsIfPriceva',
            'check_showDownloadsIfPriceva',

            'showClientCodeIfClientCode',
            'check_showClientCodeIfClientCode',

            'showIblocksIfSimpleProductEnable',
            'check_showIblocksIfSimpleProductEnable',
            'showIBlocksIfOneIBlock',
            'check_showIBlocksIfOneIBlock',
            'loadTypesInfoblocks',

            'showIblocksIfTradeOffersEnable',
            'check_showIblocksIfTradeOffersEnable',
            'showTradeOffersIBlocksIfOneIBlock',
            'check_showTradeOffersIBlocksIfOneIBlock',
            'loadTradeOffersTypesInfoblocks',

        ];

        $needed = array_diff($functions, $filter);

        return 'window.priceva_func_filter = ' . json_encode($needed) . ";";
    }

    /**
     * @param string $name
     */
    private static function generate_tr_heading( $name )
    {
        ?>
        <tr class="heading">
            <td colspan="2"><b><?=$name?></b></td>
        </tr>
        <?php
    }

    /**
     * @param $element
     * @param $element_text
     * @param $option_name
     * @param $option_val
     * @param $tooltip
     */
    private static function generate_tr( $element, $element_text, $option_name, $option_val, $tooltip )
    {
        ?>
        <tr>
            <!--suppress HtmlDeprecatedAttribute -->
            <td class="adm-detail-content-cell-l"
                width="40%" <? if( $element[ 0 ] == "textarea" ) echo 'class="adm-detail-valign-top"' ?>>
                <label for="<?=htmlspecialcharsbx($option_name)?>"><?=$element_text?>:</label>
            </td>
            <!--suppress HtmlDeprecatedAttribute -->
            <td class="adm-detail-content-cell-r" width="60%">
                <?php
                switch( $element[ 0 ] ){
                    case 'textarea':
                        {
                            ?>
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <textarea rows="<?
                            echo $element[ 1 ] ?>" name="<?
                            echo htmlspecialcharsbx($option_name) ?>" style="width:100%"><?
                                echo htmlspecialcharsbx($option_val) ?></textarea>
                            <?
                            break;
                        }
                    case 'checkbox':
                        {
                            ?>
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <input type="checkbox"
                                   name="<?=htmlspecialcharsbx($option_name)?>"
                                   id="<?=htmlspecialcharsbx($option_name)?>"
                                   value="Y"
                                <? if( $option_val == "Y" ) echo " checked"; ?>
                            >
                            <?
                            break;
                        }
                    case 'text':
                        {
                            ?>
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <input type="text"
                                   size="<?=$element[ 1 ]?>"
                                   maxlength="255" value="<?=htmlspecialcharsbx($option_val)?>"
                                   name="<?=htmlspecialcharsbx($option_name)?>"
                            >
                            <?
                            break;
                        }
                    case 'select':
                        {
                            if( count($element[ 1 ]) ){
                                ?>
                                <!--suppress HtmlFormInputWithoutLabel -->
                                <select name="<?=htmlspecialcharsbx($option_name)?>">
                                    <?
                                    foreach( $element[ 1 ] as $key => $value ){
                                        ?>
                                        <option
                                                value="<?=htmlspecialcharsbx($key)?>"
                                            <? if( $option_val == $key ) echo 'selected="selected"' ?>
                                        >
                                            <?=htmlspecialcharsEx($value)?>
                                        </option>
                                        <?
                                    } ?>
                                </select>
                                <?
                            }
                            break;
                        }
                    case 'note':
                        {
                            echo BeginNote(), $element[ 1 ], EndNote();
                            break;
                        }
                } ?>
                <?=$tooltip ? "  <a class='priceva-tooltip' title='$tooltip' href='#'>?</a>" : ''?>
            </td>
        </tr>
        <?php
    }

    /**
     * @param $aTab
     * @param $bVarsFromForm
     *
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function generate_table( $aTab, $bVarsFromForm )
    {
        ?>
        <style>
            .priceva-tooltip {
                color: #fff;
                background-color: #feb22a;
                width: 12px;
                height: 12px;
                display: inline-block;
                border-radius: 100%;
                font-size: 10px;
                text-align: center;
                text-decoration: none;
                -webkit-box-shadow: inset -1px -1px 1px 0 rgba(0, 0, 0, 0.25);
                -moz-box-shadow: inset -1px -1px 1px 0 rgba(0, 0, 0, 0.25);
                box-shadow: inset -1px -1px 1px 0 rgba(0, 0, 0, 0.25);
            }
        </style>
        <span><?=Loc::getMessage("PRICEVA_BC_OPTIONS_LINK_KB_1")?><a target="_blank"
                                                                     href="https://priceva.ru/knowledge/nastrojka-i-rabota-s-modulem-1c-bitrix/"><?=Loc::getMessage("PRICEVA_BC_OPTIONS_LINK_KB_2")?></a>.</span>
        <?php
        foreach( $aTab[ "OPTIONS" ] as $option_name => $option ){
            if( $bVarsFromForm ){
                $option_val = $_POST[ $option_name ];
            }else{
                $option_val = Option::get(CommonHelpers::MODULE_ID, $option_name);
            }
            $element_text = $option[ 0 ];
            $element      = $option[ 1 ];
            $tooltip      = Loc::getMessage("TOOLTIP_$option_name");

            if( $element === 'heading' ){
                static::generate_tr_heading($element_text);
            }else{
                static::generate_tr($element, $element_text, $option_name, $option_val, $tooltip);
            }
        }
    }

    /**
     *
     */
    public static function generate_buttons()
    {
        ?>
        <input
                class="adm-btn-save"
                type="submit"
                name="Update"
                value="<?=Loc::getMessage("MAIN_SAVE")?>"
                title="<?=Loc::getMessage("MAIN_OPT_SAVE_TITLE")?>"
        >
        <input
                type="submit"
                name="Apply"
                value="<?=Loc::getMessage("MAIN_OPT_APPLY")?>"
                title="<?=Loc::getMessage("MAIN_OPT_APPLY_TITLE")?>"
        >
        <? if( strlen($_REQUEST[ "back_url_settings" ]) > 0 ): ?>
        <input
                type="button"
                name="Cancel"
                value="<?=Loc::getMessage("MAIN_OPT_CANCEL")?>"
                title="<?=Loc::getMessage("MAIN_OPT_CANCEL_TITLE")?>"
                onclick="window.location='<?=htmlspecialcharsbx(CUtil::addslashes($_REQUEST[ "back_url_settings" ]))?>'"
        >
        <input
                type="hidden"
                name="back_url_settings"
                value="<?=htmlspecialcharsbx($_REQUEST[ "back_url_settings" ])?>"
        >
    <? endif ?>
        <input
                type="submit"
                name="RestoreDefaults"
                title="<?=Loc::getMessage("MAIN_HINT_RESTORE_DEFAULTS")?>"
                OnClick="return confirm('<?=CUtil::addslashes(Loc::getMessage("MAIN_HINT_RESTORE_DEFAULTS_WARNING"))?>')"
                value="<?=Loc::getMessage("MAIN_RESTORE_DEFAULTS")?>"
        >
        <input
                type="submit"
                name="deleteDebugLog"
                title="<?=Loc::getMessage("PRICEVA_BC_OPTIONS_BUTTON_DELETE_DEBUG_LOG_TITLE")?>"
                value="<?=Loc::getMessage("PRICEVA_BC_OPTIONS_BUTTON_DELETE_DEBUG_LOG")?>"
        >
        <input
                type="button"
                name="DownloadDebugLog"
                title="<?=Loc::getMessage("PRICEVA_BC_OPTIONS_BUTTON_DOWNLOAD_DEBUG_LOG_TITLE")?>"
                value="<?=Loc::getMessage("PRICEVA_BC_OPTIONS_BUTTON_DOWNLOAD_DEBUG_LOG")?>"
                onclick="window.open('/priceva.log', '_blank');"
        >
        <?
    }

    /**
     * @param bool $install
     *
     * @return array
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function generate_options_tabs( $install = false )
    {
        return [
            [
                "DIV"     => "index",
                "TAB"     => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_MAIN"),
                "ICON"    => "",
                "TITLE"   => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_MAIN_TITLE"),
                "OPTIONS" => self::get_main_options($install),
            ],
            [
                "DIV"     => "rights",
                "TAB"     => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_RIGHTS"),
                "ICON"    => "",
                "TITLE"   => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_OPTIONS_RIGHTS"),
                "OPTIONS" => [],
            ],
        ];
    }

    /**
     *
     * @param bool $install
     *
     * @return array
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function get_main_options( $install = false )
    {
        $filter = [];

        if( !CommonHelpers::bitrix_full_business() ){
            $filter = [
                'ID_TYPE_PRICE',
                'PRICE_RECALC',
            ];
        }

        if( $install ){
            $filter = array_merge($filter, [
                'DEBUG',
                'HEADING0',
                'HEADING1',
                'HEADING2',
                'HEADING3',
                'HEADING4',
                'SIMPLE_PRODUCT_ENABLE',
                'IBLOCK_TYPE_ID',
                'IBLOCK_MODE',
                'IBLOCK_ID',
                'TRADE_OFFERS_ENABLE',
                'TRADE_OFFERS_IBLOCK_TYPE_ID',
                'TRADE_OFFERS_IBLOCK_MODE',
                'TRADE_OFFERS_IBLOCK_ID',
            ]);
        }

        $types_of_price       = CommonHelpers::get_types_of_price();
        $currencies           = CommonHelpers::get_currencies();
        $types_iblocks        = CommonHelpers::get_types_iblocks();
        $iblocks              = CommonHelpers::get_iblocks(Option::get(CommonHelpers::MODULE_ID, 'IBLOCK_TYPE_ID'));
        $trade_offers_iblocks = CommonHelpers::get_iblocks(Option::get(CommonHelpers::MODULE_ID, 'TRADE_OFFERS_IBLOCK_TYPE_ID'));

        if( $filter ){
            if( $price_type_priceva_id = OptionsHelpers::find_price_type_priceva_id() ){
                $types_of_price[ $price_type_priceva_id ] = 'PRICEVA';
            }else{
                $types_of_price[ 'need_create_priceva' ] = 'PRICEVA';
            }
        }

        // $agent_id = OptionsHelpers::option_agent_id();

        $options = [
            "HEADING0"                    => [ Loc::getMessage("PRICEVA_BC_OPTIONS_HEADING_MAIN_PARAMS"), "heading" ],
            "API_KEY"                     => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_API_KEY"), [ "text", 32 ] ],
            "DEBUG"                       => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_DEBUG"), [
                    "select", OptionsPage::add_not_selected([
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_ON"),
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_OFF"),
                    ]),
                ],
            ],
            "HEADING1"                    => [ Loc::getMessage("PRICEVA_BC_OPTIONS_HEADING_IBLOCK"), "heading" ],
            "SIMPLE_PRODUCT_ENABLE"       => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_SIMPLE_PRODUCT_ENABLE"), [
                    "select", OptionsPage::add_not_selected([
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_NO"),
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_YES"),
                    ]),
                ],
            ],
            "IBLOCK_TYPE_ID"              => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_IBLOCK_TYPE_ID"), [ "select", OptionsPage::add_not_selected($types_iblocks) ] ],
            "IBLOCK_MODE"                 => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_IBLOCK_MODE"), [
                    "select", OptionsPage::add_not_selected([
                        "ALL" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_IBLOCK_MODE_TEXT_ALL"),
                        "ONE" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_IBLOCK_MODE_TEXT_ONE"),
                    ]),
                ],
            ],
            "IBLOCK_ID"                   => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_IBLOCK_ID"), [ "select", OptionsPage::add_not_selected($iblocks) ] ],
            "HEADING2"                    => [ Loc::getMessage("PRICEVA_BC_OPTIONS_HEADING_TRADE_OFFERS_IBLOCK"), "heading" ],
            "TRADE_OFFERS_ENABLE"         => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_TRADE_OFFERS_ENABLE"), [
                    "select", OptionsPage::add_not_selected([
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_NO"),
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_YES"),
                    ]),
                ],
            ],
            "TRADE_OFFERS_IBLOCK_TYPE_ID" => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_TRADE_OFFERS_IBLOCK_TYPE_ID"), [ "select", OptionsPage::add_not_selected($types_iblocks) ] ],
            "TRADE_OFFERS_IBLOCK_MODE"    => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_TRADE_OFFERS_IBLOCK_MODE"), [
                    "select", OptionsPage::add_not_selected([
                        "ALL" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_IBLOCK_MODE_TEXT_ALL"),
                        "ONE" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_IBLOCK_MODE_TEXT_ONE"),
                    ]),
                ],
            ],
            "TRADE_OFFERS_IBLOCK_ID"      => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_TRADE_OFFERS_IBLOCK_ID"), [ "select", OptionsPage::add_not_selected($trade_offers_iblocks) ] ],
            "HEADING3"                    => [ Loc::getMessage("PRICEVA_BC_OPTIONS_HEADING_SYNC"), "heading" ],
            "SYNC_FIELD"                  => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_SYNC_FIELD"), [
                    "select", OptionsPage::add_not_selected([
                        "client_code" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_CLIENT_CODE"),
                        "articul"     => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_ARTICUL"),
                    ]),
                ],
            ],
            "CLIENT_CODE"                 => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_CLIENT_CODE"), [
                    "select", OptionsPage::add_not_selected([
                        "ID"   => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_PRODUCT_ID"),
                        "CODE" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_PRODUCT_CODE"),
                    ]),
                ],
            ],
            "SYNC_ONLY_ACTIVE"            => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_SYNC_ONLY_ACTIVE"), [
                    "select", OptionsPage::add_not_selected([
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_NO"),
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_YES"),
                    ]),
                ],
            ],
            "SYNC_DOMINANCE"              => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_SYNC_DOMINANCE"), [
                    "select", OptionsPage::add_not_selected([
                        "bitrix"  => "Bitrix",
                        "priceva" => "Priceva",
                    ]),
                ],
            ],
            "DOWNLOAD_AT_TIME"            => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_DOWNLOAD_AT_TIME"), [
                    "select", OptionsPage::add_not_selected([
                        "10"   => "10",
                        "100"  => "100",
                        "1000" => "1000",
                    ]),
                ],
            ],
            "HEADING4"                    => [ Loc::getMessage("PRICEVA_BC_OPTIONS_HEADING_WORK_PRICE"), "heading" ],
            "ID_TYPE_PRICE"               => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_ID_TYPE_PRICE"), [ "select", OptionsPage::add_not_selected($types_of_price) ] ],
            "PRICE_RECALC"                => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_PRICE_RECALC"), [
                    "select", OptionsPage::add_not_selected([
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_NO"),
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_YES"),
                    ]),
                ],
            ],
            "CURRENCY"                    => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_CURRENCY"), [ "select", OptionsPage::add_not_selected($currencies) ] ],
        ];

        return array_diff_key($options, array_flip($filter));
    }

    /**
     * @param bool  $bVarsFromForm
     * @param array $aTabs
     * @param int   $id_type_price_priceva
     * @param bool  $install
     */
    public static function process_save_form( $bVarsFromForm, $aTabs, $id_type_price_priceva = 0, $install = false )
    {
        if( self::is_restore_method() ) // если было выбрано "по умолчанию", то сбрасывает все option'ы
        {
            COption::RemoveOption(CommonHelpers::MODULE_ID);
        }else{
            // только если у нас не восстановление параметров, запишем текущие значения в БД,
            // иначе мы сначала удалим значения опций (если без else просто добавить код ниже к коду выше),
            // и потом их сразу же ставим нулевые все, код ниже не возьмет значения по умолчанию
            if( !$bVarsFromForm ){
                foreach( $aTabs as $aTab ){
                    foreach( $aTab[ "OPTIONS" ] as $option_name => $option ){
                        $request = CommonHelpers::getInstance()->app->getContext()->getRequest();
                        $val     = $request->get($option_name);

                        if( false !== strripos($option_name, 'HEADING') ){
                            continue;
                        }

                        if( $id_type_price_priceva && $val === 'need_create_priceva' ){
                            $val = $id_type_price_priceva;
                        }

                        if( $option[ 1 ][ 0 ] == "checkbox" && $val != "Y" ){
                            $val = "N";
                        }

                        if( !$install ){
                            self::check_option($option_name, $val);
                        }

                        COption::SetOptionString(CommonHelpers::MODULE_ID, $option_name, $val, $option[ 0 ]);
                    }
                }
            }
        }
    }

    /**
     * @param string $name
     * @param mixed  $val
     *
     * @return bool
     */
    public static function check_option( $name, $val )
    {
        $options_dependencies = [
            'IBLOCK_TYPE_ID' => [
                'SIMPLE_PRODUCT_ENABLE' => [ '0', false ],
            ],
            'IBLOCK_MODE'    => [
                'IBLOCK_TYPE_ID'        => [ '0' ],
                'SIMPLE_PRODUCT_ENABLE' => [ '0', false ],
            ],
            'IBLOCK_ID'      => [
                'IBLOCK_MODE'           => [ '0', 'ALL' ],
                'SIMPLE_PRODUCT_ENABLE' => [ '0', false, true ],
            ],

            'TRADE_OFFERS_IBLOCK_TYPE_ID' => [
                'TRADE_OFFERS_ENABLE' => [ '0', false ],
            ],
            'TRADE_OFFERS_IBLOCK_MODE'    => [
                'TRADE_OFFERS_IBLOCK_TYPE_ID' => [ '0', '' ],
                'TRADE_OFFERS_ENABLE'         => [ '0', false ],
            ],
            'TRADE_OFFERS_IBLOCK_ID'      => [
                'TRADE_OFFERS_IBLOCK_MODE' => [ '0', '', 'ALL' ],
                'TRADE_OFFERS_ENABLE'      => [ '0', false, true ],
            ],

            'CLIENT_CODE' => [ 'SYNC_FIELD' => [ '0', 'articul' ], ],
        ];

        $options_inverse_dependencies = [
            'TRADE_OFFERS_ENABLE' => [ 'IBLOCK_TYPE_ID' => [ 'catalog' ], ],
        ];

        $options = Options::getInstance();

        $result = true;
        // если значени пустое
        if( empty($val) ){
            // если есть какая-то зависимость для текущей опции
            if( $options_dependencies[ $name ] || $options_inverse_dependencies[ $name ] ){
                // переберем все прямые зависимости (их может быть больше одной)
                if( key_exists($name, $options_dependencies) ){
                    foreach( $options_dependencies[ $name ] as $key => $dependency ){
                        // проверим,  есть ли в массиве прямых зависимостей значение текущей зависимости,
                        // разрешающее для текущего проверяемого поля пустое значение
                        $val_cur_dep = $options->$key;
                        if( !in_array($val_cur_dep, $dependency, true) ){
                            $result = false;
                            break;
                        }
                    }
                }
                if( key_exists($name, $options_inverse_dependencies) ){
                    // переберем все обратные зависимости (их может быть больше одной)
                    foreach( $options_inverse_dependencies[ $name ] as $key => $dependency ){
                        // проверим,  есть ли в массиве обратных зависимостей значение текущей зависимости,
                        // запрещающее для текущего проверяемого поля пустое значение
                        $val_cur_dep = $options->$key;
                        if( in_array($val_cur_dep, $dependency, true) ){
                            $result = false;
                            break;
                        }
                    }
                }
            }else{
                // если никакой зависимости(ей) нет и поле просто пустое, то это не ОК
                $result = false;
            }

            if( !$result ){
                echo ( new CAdminMessage(Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_FILL_FIELD") . Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_" . $name)) )->Show();
            }
        }

        return $result;
    }
}