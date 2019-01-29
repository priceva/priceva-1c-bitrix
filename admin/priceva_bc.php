<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 21.01.2019
 * Time: 14:55
 */

$MODULE_ID = "priceva.connector";

require_once( $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/main/include/prolog_admin.php" );
require_once( $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/" . $MODULE_ID . "/prolog.php" );

CModule::IncludeModule($MODULE_ID);


$common_helpers = \Priceva\Connector\Bitrix\Helpers\CommonHelpers::getInstance();

$FORM_RIGHT = $common_helpers->APPLICATION->GetGroupRight($MODULE_ID);
if( $FORM_RIGHT <= "D" ) $common_helpers->APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

require( $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/main/include/prolog_admin_after.php" );

if( "POST" === $common_helpers->request_method() ){
    if( $common_helpers->app->getContext()->getRequest()->getPost('run') ){
        $connector = new \Priceva\Connector\Bitrix\PricevaConnector();
        $connector->run();
        $errors = $connector->get_last_info_msg();
    }
}

?>
<?=$errors?>
    <form method="post" action="<? echo $common_helpers->APPLICATION->GetCurPage() ?>?lang=<?=LANGUAGE_ID?>"
          id="priceva_bc">
    <input type="submit" name="run" value="Запустить синхронизацию" title="Запустить синхронизацию ">
</form>

<?php
require_once( $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/main/include/epilog_admin.php" );
?>