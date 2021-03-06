<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 21.01.2019
 * Time: 14:41
 */

use Bitrix\Main\Localization\Loc;
use Priceva\Connector\Bitrix\Helpers\CommonHelpers;
use Priceva\Connector\Bitrix\OptionsPage;

global $APPLICATION, $Update, $Apply;
$MODULE_ID = "priceva.connector";

// for correct working with file /bitrix/modules/main/admin/group_rights.php where used $module_id but not $MODULE_ID
$module_id = "priceva.connector";

try{
    CModule::IncludeModule($MODULE_ID);

    $common_helpers = CommonHelpers::getInstance();

    Loc::LoadMessages($_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/main/options.php");
    Loc::loadMessages(__FILE__);

    CUtil::InitJSCore([ $MODULE_ID ]);

    $RIGHT = $common_helpers->APPLICATION->GetGroupRight($MODULE_ID);

    if( $RIGHT >= "R" ){
        $bVarsFromForm = false;

        $aTabs = OptionsPage::generate_options_tabs();

        $tabControl = new CAdminTabControl("tabControl", $aTabs);

        if( $common_helpers->is_post() && check_bitrix_sessid() ){
            if( OptionsPage::is_save_method() ){ // save / restore / defaults action on form
                OptionsPage::process_save_form($bVarsFromForm, $aTabs);

                ob_start();
                $Update = $Update . $Apply;
                require_once( $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/main/admin/group_rights.php" );
                ob_end_clean();
            }else{ // delete debug log
                $common_helpers::delete_debug_log();
            }
        }

        $tabControl->Begin();
        $form_action = $common_helpers->APPLICATION->GetCurPage() . "?mid=" . urlencode($mid) . "&lang=" . LANGUAGE_ID;
        ?>
        <form method="post" action="<?=$form_action?>" id="options">
            <?
            foreach( $aTabs as $caTab => $aTab ){
                $tabControl->BeginNextTab();

                if( $aTab[ "DIV" ] != "rights" ){
                    OptionsPage::generate_table($aTab, $bVarsFromForm);
                }elseif( $aTab[ "DIV" ] == "rights" ){
                    require( $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/main/admin/group_rights.php" );
                }
            }
            $tabControl->Buttons();
            OptionsPage::generate_buttons();
            echo bitrix_sessid_post();
            $tabControl->End(); ?>
        </form>
        <script>
            <?= OptionsPage::generate_js_script()?>
        </script>
    <? }
}catch( Exception $e ){
    CommonHelpers::write_to_log($e);
    CommonHelpers::getInstance()->APPLICATION->ThrowException($e->getMessage());
}