<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 12.02.2019
 * Time: 11:55
 */

namespace Priceva\Connector\Bitrix;


use Bitrix\Main\Localization\Loc;
use Priceva\Connector\Bitrix\Helpers\{CommonHelpers, OptionsHelpers};

class PricevaOptions
{
    private $install;

    public $common_helpers;

    public function __construct( $install = false )
    {
        $this->install        = $install;
        $this->common_helpers = CommonHelpers::getInstance();
    }

    /**
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    public function generate_options_tabs()
    {
        return [
            [
                "DIV"     => "index",
                "TAB"     => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_MAIN"),
                "ICON"    => "testmodule_settings",
                "TITLE"   => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_MAIN_TITLE"),
                "OPTIONS" => $this->get_main_options(),
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
     * @param array $filter
     *
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function get_main_options( $filter = [] )
    {
        $types_of_price = $this->common_helpers::get_types_of_price();
        $currencies     = $this->common_helpers::get_currencies();

        // $agent_id       = OptionsHelpers::get_agent_id();

        $options = [
            "API_KEY"          => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_APIKEY"), [ "text", 32 ] ],
            "ID_TYPE_PRICE"    => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_PRICE_TYPE"), [ "select", $this->common_helpers->add_not_selected($types_of_price) ] ],
            "SYNC_FIELD"       => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_SYNC_FIELD"), [
                    "select", $this->common_helpers->add_not_selected([
                        "client_code" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_CLIENT_CODE"),
                        "articul"     => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_ARTICUL"),
                    ]),
                ],
            ],
            "CLIENT_CODE"      => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_CLIENT_CODE_ANALOG"), [
                    "select", $this->common_helpers->add_not_selected([
                        "ID"   => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_PRODUCT_ID"),
                        "CODE" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_PRODUCT_CODE"),
                    ]),
                ],
            ],
            "SYNC_ONLY_ACTIVE" => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_ACTIVE_PRODUCT"), [
                    "select", $this->common_helpers->add_not_selected([
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_NO"),
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_YES"),
                    ]),
                ],
            ],
            "SYNC_DOMINANCE"   => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_SYNC_DOMINANCE"), [
                    "select", $this->common_helpers->add_not_selected([
                        "bitrix"  => "Bitrix",
                        "priceva" => "Priceva",
                    ]),
                ],
            ],
            "DOWNLOAD_AT_TIME" => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_DOWNLOAD_AT_TIME"), [
                    "select", $this->common_helpers->add_not_selected([
                        "10"   => "10",
                        "100"  => "100",
                        "1000" => "1000",
                    ]),
                ],
            ],
            "PRICE_RECALC"     => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_PRICE_RECALC"), [
                    "select", $this->common_helpers->add_not_selected([
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_NO"),
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_YES"),
                    ]),
                ],
            ],
            "CURRENCY"         => [ Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_CURRENCY"), [ "select", $this->common_helpers->add_not_selected($currencies) ] ],
            "DEBUG"            => [
                Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_DEBUG"), [
                    "select", $this->common_helpers->add_not_selected([
                        "YES" => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_ON"),
                        "NO"  => Loc::getMessage("PRICEVA_BC_OPTIONS_TEXT_OFF"),
                    ]),
                ],
            ],
        ];

        return array_diff_assoc($options, $filter);
    }

    public function generate_buttons()
    {
        ?>
        <input type="submit" name="Update" value="<?=GetMessage("MAIN_SAVE")?>"
               title="<?=GetMessage("MAIN_OPT_SAVE_TITLE")?>" class="adm-btn-save">
        <input type="submit" name="Apply" value="<?=GetMessage("MAIN_OPT_APPLY")?>"
               title="<?=GetMessage("MAIN_OPT_APPLY_TITLE")?>">
        <? if( strlen($_REQUEST[ "back_url_settings" ]) > 0 ): ?>
        <input type="button" name="Cancel" value="<?=GetMessage("MAIN_OPT_CANCEL")?>"
               title="<?=GetMessage("MAIN_OPT_CANCEL_TITLE")?>"
               onclick="window.location='<? echo htmlspecialcharsbx(\CUtil::addslashes($_REQUEST[ "back_url_settings" ])) ?>'">
        <input type="hidden" name="back_url_settings"
               value="<?=htmlspecialcharsbx($_REQUEST[ "back_url_settings" ])?>">
    <? endif ?>
        <input type="submit" name="RestoreDefaults" title="<? echo GetMessage("MAIN_HINT_RESTORE_DEFAULTS") ?>"
               OnClick="return confirm('<? echo AddSlashes(GetMessage("MAIN_HINT_RESTORE_DEFAULTS_WARNING")) ?>')"
               value="<? echo GetMessage("MAIN_RESTORE_DEFAULTS") ?>">
        <?
    }

    /**
     * @param $aTab
     * @param $bVarsFromForm
     *
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public function generate_table( $aTab, $bVarsFromForm )
    {
        foreach( $aTab[ "OPTIONS" ] as $name => $arOption ){
            if( $bVarsFromForm ){
                $val = $_POST[ $name ];
            }else{
                $m   = $this->common_helpers::MODULE_ID;
                $val = \Bitrix\Main\Config\Option::get($this->common_helpers::MODULE_ID, $name);
            }
            $type     = $arOption[ 1 ];
            $disabled = array_key_exists("disabled", $arOption) ? $arOption[ "disabled" ] : "";
            ?>
            <tr <?
            if( isset($arOption[ 2 ]) && strlen($arOption[ 2 ]) ) echo 'style="display:none" class="show-for-' . htmlspecialcharsbx($arOption[ 2 ]) . '"' ?>>
                <td width="40%" <?
                if( $type[ 0 ] == "textarea" ) echo 'class="adm-detail-valign-top"' ?>>
                    <label for="<?
                    echo htmlspecialcharsbx($name) ?>"><?
                        echo $arOption[ 0 ] ?>:</label>
                <td width="30%">
                    <?
                    if( $type[ 0 ] == "checkbox" ){
                        ?>
                        <!--suppress HtmlFormInputWithoutLabel -->
                        <input type="checkbox" name="<?
                        echo htmlspecialcharsbx($name) ?>" id="<?
                        echo htmlspecialcharsbx($name) ?>" value="Y"<?
                        if( $val == "Y" ) echo " checked"; ?><?
                        if( $disabled ) echo ' disabled="disabled"'; ?>><?
                        if( $disabled ) echo '<br>' . $disabled; ?>
                        <?
                    }elseif( $type[ 0 ] == "text" ){
                        ?>
                        <!--suppress HtmlFormInputWithoutLabel -->
                        <input type="text" size="<?
                        echo $type[ 1 ] ?>" maxlength="255" value="<?
                        echo htmlspecialcharsbx($val) ?>" name="<?
                        echo htmlspecialcharsbx($name) ?>">
                        <?
                    }elseif( $type[ 0 ] == "textarea" ){
                        ?>
                        <!--suppress HtmlFormInputWithoutLabel -->
                        <textarea rows="<?
                        echo $type[ 1 ] ?>" name="<?
                        echo htmlspecialcharsbx($name) ?>" style=
                                  "width:100%"><?
                            echo htmlspecialcharsbx($val) ?></textarea>
                        <?
                    }elseif( $type[ 0 ] == "select" ){
                        ?>
                        <?
                        if( count($type[ 1 ]) ){
                            ?>
                            <!--suppress HtmlFormInputWithoutLabel -->
                            <select name="<?
                            echo htmlspecialcharsbx($name) ?>" onchange="doShowAndHide()">
                                <?
                                foreach( $type[ 1 ] as $key => $value ){
                                    ?>
                                    <option value="<?
                                    echo htmlspecialcharsbx($key) ?>" <?
                                    if( $val == $key ) echo 'selected="selected"' ?>><?
                                        echo htmlspecialcharsEx($value) ?></option>
                                    <?
                                } ?>
                            </select>
                            <?
                        }else{
                            ?>
                            <?
                            echo GetMessage("ZERO_ELEMENT_ERROR"); ?>
                            <?
                        } ?>
                        <?
                    }elseif( $type[ 0 ] == "note" ){
                        ?>
                        <?
                        echo BeginNote(), $type[ 1 ], EndNote(); ?>
                        <?
                    } ?>
                </td>
                <td width="30%">
                    <?
                    if( $arOption[ 3 ] ){
                        ?>
                        <p><?
                            echo $arOption[ 3 ]; ?></p>
                        <?
                    } ?>
                </td>
            </tr>
            <?
        }
    }

    /**
     * @param bool  $bVarsFromForm
     * @param array $aTabs
     */
    public function process_save_form( $bVarsFromForm, $aTabs )
    {
        if( OptionsHelpers::is_restore_method() ) // если было выбрано "по умолчанию", то сбрасывает все option'ы
        {
            \COption::RemoveOption($this->common_helpers::MODULE_ID);
        }else{
            // только если у нас не восстановление параметров, запишем текущие значения в БД,
            // иначе мы сначала удалим значения опций (если без else просто добавить код ниже к коду выше),
            // и потом их сразу же ставим нулевые все, код ниже не возьмет значения по умолчанию
            if( !$bVarsFromForm ){
                foreach( $aTabs as $i => $aTab ){
                    foreach( $aTab[ "OPTIONS" ] as $name => $arOption ){
                        $disabled = array_key_exists("disabled", $arOption) ? $arOption[ "disabled" ] : "";
                        if( $disabled )
                            continue;

                        $val = $_POST[ $name ];
                        if( $arOption[ 1 ][ 0 ] == "checkbox" && $val != "Y" ){
                            $val = "N";
                        }

                        \COption::SetOptionString($this->common_helpers::MODULE_ID, $name, $val, $arOption[ 0 ]);
                    }
                }
            }
        }
    }

    public function generate_js_script( $filter = [] )
    {
        $functions = [
            'doShowAndHide'                    => "function doShowAndHide() {
                var form = BX('options');
                var selects = BX.findChildren(form, {tag: 'select'}, true);
                for (var i = 0; i < selects.length; i++) {
                    var selectedValue = selects[i].value;
                    var trs = BX.findChildren(form, {tag: 'tr'}, true);
                    for (var j = 0; j < trs.length; j++) {
                        if (/show-for-/.test(trs[j].className)) {
                            if (trs[j].className.indexOf(selectedValue) >= 0)
                                trs[j].style.display = 'table-row';
                            else
                                trs[j].style.display = 'none';
                        }
                    }
                }
            }",
            'showDownloadsIfPriceva'           => "function showDownloadsIfPriceva() {
                var form = BX('options');
                var select_SYNC_DOMINANCE = BX.findChildren(form, {
                    tag: 'select',
                    attribute: {name: 'SYNC_DOMINANCE'}
                }, true);
                BX.bind(select_SYNC_DOMINANCE[0], 'bxchange', check_showDownloadsIfPriceva)
            }",
            'check_showDownloadsIfPriceva'     => "function check_showDownloadsIfPriceva() {
                var form = BX('options'),
                    select_SYNC_DOMINANCE = BX.findChildren(form, {
                        tag: 'select',
                        attribute: {name: 'SYNC_DOMINANCE'}
                    }, true),
                    select_DOWNLOAD_AT_TIME = BX.findChildren(form, {
                        tag: 'select',
                        attribute: {name: 'DOWNLOAD_AT_TIME'}
                    }, true);

                var s = select_DOWNLOAD_AT_TIME[0],
                    d = select_SYNC_DOMINANCE[0];

                if (d.value === 'priceva') {
                    BX.adjust(s, {props: {disabled: false}});
                } else {
                    BX.adjust(s, {props: {disabled: true}});
                    s.value = 0;
                }
            }",
            'showClientCodeIfClientCode'       => "function showClientCodeIfClientCode() {
                var form = BX('options');
                var select_SYNC_FIELD = BX.findChildren(form, {
                    tag: 'select',
                    attribute: {name: 'SYNC_FIELD'}
                }, true);
                BX.bind(select_SYNC_FIELD[0], 'bxchange', check_showClientCodeIfClientCode)
            }",
            'check_showClientCodeIfClientCode' => "function check_showClientCodeIfClientCode() {
                var form = BX('options'),
                    select_SYNC_FIELD = BX.findChildren(form, {
                        tag: 'select',
                        attribute: {name: 'SYNC_FIELD'}
                    }, true),
                    select_CLIENT_CODE = BX.findChildren(form, {
                        tag: 'select',
                        attribute: {name: 'CLIENT_CODE'}
                    }, true);

                var s = select_CLIENT_CODE[0],
                    d = select_SYNC_FIELD[0];

                if (d.value === 'client_code') {
                    BX.adjust(s, {props: {disabled: false}});
                } else {
                    BX.adjust(s, {props: {disabled: true}});
                    s.value = 0;
                }
            }",
        ];

        $needed = array_diff_assoc($functions, $filter);

        $script = '';

        foreach( $needed as $func_name => $function ){
            $script .= $function . " BX.ready($func_name);\n";
        }

        return $script;
    }

    public function get_default_options()
    {
        return [
            "API_KEY"          => "",
            "ID_TYPE_PRICE"    => "0",
            "SYNC_FIELD"       => "articul",
            "CLIENT_CODE"      => "ID",
            "SYNC_DOMINANCE"   => "priceva",
            "SYNC_ONLY_ACTIVE" => "YES",
            "DOWNLOAD_AT_TIME" => "10",
            "PRICE_RECALC"     => "NO",
            "CURRENCY"         => "RUB",
            "AGENT_ID"         => "",
            "ID_ARICUL_IBLOCK" => "",
            "DEBUG"            => "NO",
        ];
    }
}