<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 17.01.2019
 * Time: 18:10
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

Class priceva_connector extends CModule
{
    var $MODULE_ID = '';

    var $MODULE_VERSION      = '';
    var $MODULE_VERSION_DATE = '';
    var $MODULE_NAME         = '';
    var $MODULE_DESCRIPTION  = '';

    /**
     * @var $common_helpers \Priceva\Connector\Bitrix\Helpers\CommonHelpers
     */
    private $common_helpers;

    /**
     * @var bool
     */
    private $need_save_unroll = false;
    /**
     * @var array
     */
    private $unroll_methods = [];

    private $errors = [];

    function __construct()
    {
        $this->common_helpers = self::autoload_helpers();

        $this->MODULE_ID = $this->common_helpers::MODULE_ID;

        $arModuleVersion = [];

        include( __DIR__ . "/version.php" );

        $this->MODULE_VERSION      = $arModuleVersion[ "VERSION" ];
        $this->MODULE_VERSION_DATE = $arModuleVersion[ "VERSION_DATE" ];
        $this->MODULE_NAME         = Loc::getMessage("PRICEVA_BC_MODULE_NAME");
        $this->MODULE_DESCRIPTION  = Loc::getMessage("PRICEVA_BC_MODULE_DESC");

        $this->PARTNER_NAME = Loc::getMessage("PRICEVA_BC_PARTNER_NAME");
        $this->PARTNER_URI  = Loc::getMessage("PRICEVA_BC_PARTNER_URI");
    }

    /**
     * @return bool
     */
    static public function isVersionD7()
    {
        return CheckVersion(SM_VERSION, '14.00.00');
    }

    static public function GetPatch( $notDocumentRoot = false )
    {
        if( $notDocumentRoot )
            return str_ireplace($_SERVER[ "DOCUMENT_ROOT" ], '', dirname(__DIR__));
        else
            return dirname(__DIR__);
    }

    function DoInstall()
    {
        global $APPLICATION;

        if( self::isVersionD7() ){

            if( IsModuleInstalled($this->common_helpers::MODULE_ID) ){
                $APPLICATION->ThrowException(Loc::getMessage("PRICEVA_BC_INSTALL_INSTALL"));
            }

            $this->need_save_unroll = true;

            $this->InstallFiles();
            $this->InstallTasks();
            $this->InstallEvents();
            $id_type_price = $this->InstallDB();
            $this->InstallAgents();

            $this->need_save_unroll = false;

            if( $this->errors ){

                foreach( $this->unroll_methods as $method ){
                    $this->$method;
                }

                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                    self::GetPatch() . "/install/errors.php"
                );
            }else{
                ModuleManager::registerModule($this->common_helpers::MODULE_ID);

                COption::SetOptionString($this->common_helpers::MODULE_ID, 'ID_TYPE_PRICE', $id_type_price);

                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                    self::GetPatch() . "/install/step1.php"
                );
            }
        }else{
            $APPLICATION->ThrowException(Loc::getMessage("ACADEMY_OOP_INSTALL_ERROR_VERSION"));
        }
    }

    function DoUninstall()
    {
        global $APPLICATION;

        $this->need_save_unroll = true;

        $this->UnInstallAgents();
        $this->UnInstallEvents();
        $this->UnInstallTasks();
        $this->UnInstallDB();
        $this->UnInstallFiles();

        $this->need_save_unroll = false;

        if( $this->errors ){

            foreach( $this->unroll_methods as $method ){
                $this->$method();
            }

            $APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_UNINSTALL_TITLE_1"),
                self::GetPatch() . "/install/errors.php"
            );
        }else{
            ModuleManager::unRegisterModule($this->common_helpers::MODULE_ID);

            $APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::GetPatch() . "/install/unstep1.php"
            );
        }
    }

    /**
     * @return bool|int
     */
    function InstallDB()
    {
        parent::InstallDB();

        $r = $this->add_price_type();

        $this->save_unroll($r, "UnInstallDB");

        return $r;
    }

    function UnInstallDB()
    {
        parent::UnInstallDB();

        $r = $this->delete_price_type();

        $this->save_unroll($r, "InstallDB");
    }

    function InstallFiles()
    {
        parent::InstallFiles();

        $r1 = CopyDirFiles(self::GetPatch() . "/lib/", $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/" . $this->common_helpers::MODULE_ID . "/lib", true, true);
        $r2 = CopyDirFiles(self::GetPatch() . "/admin/", $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/" . $this->common_helpers::MODULE_ID . "/admin", true, true);
        $r3 = CopyDirFiles(self::GetPatch() . "/include.php", $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/" . $this->common_helpers::MODULE_ID . "/include.php", true, true);
        $r4 = CopyDirFiles(self::GetPatch() . "/install/admin/", $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/admin/", true, true);
        $r5 = CopyDirFiles(self::GetPatch() . "/install/module/", $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/" . $this->common_helpers::MODULE_ID, true, true);

        $this->save_unroll($r1 && $r2 && $r3 && $r4 && $r5, "UnInstallFiles");
    }

    function UnInstallFiles()
    {
        parent::UnInstallFiles();

        Bitrix\Main\IO\Directory::deleteDirectory($_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/" . $this->common_helpers::MODULE_ID);
        Bitrix\Main\IO\Directory::deleteDirectory($_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/admin/priceva_bc.php");

        $this->save_unroll(true, "InstallFiles");
    }

    function InstallEvents()
    {
        parent::InstallEvents();

        EventManager::getInstance()->registerEventHandler('main', 'OnBuildGlobalMenu', $this->common_helpers::MODULE_ID, 'priceva_connector', 'AddGlobalMenuItem');

        $this->save_unroll(true, "UnInstallEvents");
    }

    function InstallTasks()
    {
        parent::InstallTasks();

        $this->save_unroll(true, "UnInstallTasks");
    }

    function UnInstallTasks()
    {
        parent::UnInstallTasks();

        $this->save_unroll(true, "InstallTasks");
    }

    function UnInstallEvents()
    {
        parent::UnInstallEvents();

        EventManager::getInstance()->unRegisterEventHandler('main', 'OnBuildGlobalMenu', $this->common_helpers::MODULE_ID, 'priceva_bitrix_connector', 'AddGlobalMenuItem');

        $this->save_unroll(true, "InstallEvents");
    }

    private function InstallAgents()
    {
        $r = CAgent::AddAgent(
            "\Priceva\Connector\Bitrix\PricevaConnector::run();",
            "priceva.connector",
            "Y",
            86400,
            "01.01.2019 00:00:00",
            "Y",
            "01.01.2019 00:00:00",
            30
        );

        $this->save_unroll($r, "UnInstallAgents");
    }

    private function UnInstallAgents()
    {
        CAgent::RemoveAgent(
            "\Priceva\Connector\Bitrix\PricevaConnector::run();",
            "priceva.connector"
        );

        $this->save_unroll(true, "InstallAgents");
    }

    public function AddGlobalMenuItem( &$aGlobalMenu, &$aModuleMenu )
    {
        $aModuleMenu[] = [
            "parent_menu" => "global_menu_custom",
            "icon"        => "default_menu_icon",
            "page_icon"   => "default_page_icon",
            "sort"        => 100,
            "text"        => "Ручное управление",
            "title"       => "Ручное управление",
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
                "sort"         => 400,
                "items_id"     => "global_menu_priceva",
                "help_section" => "custom",
                "items"        => [],
            ],
        ];

        return $arRes;
    }

    private function check_price_type()
    {
        try{
            $dbPriceType = \CCatalogGroup::GetList([], [ "NAME" => $this->common_helpers::NAME_PRICE_TYPE ]);
            while( $arPriceType = $dbPriceType->Fetch() ){
                return true;
            }

            return false;

        }catch( \Throwable $e ){
            $this->add_error($e);

            return false;
        }
    }

    private function delete_price_type()
    {
        global $APPLICATION;
        try{
            if( !\Bitrix\Main\Loader::includeModule('catalog') ){
                throw new \Bitrix\Main\LoaderException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_CATALOG_NOT_INSTALLED"));
            }

            $type_price_ID = $this->common_helpers::get_type_price_ID();

            $APPLICATION->ResetException();

            if( false === $ID = \CCatalogGroup::Delete($type_price_ID) ){
                if( $APPLICATION->GetException() ){
                    throw new Exception("Произошла ошибка при попытке удаления типа цен");
                }
            }else{
                return true;
            }
        }catch( \Throwable $e ){
            $this->add_error($e);
        }

        return false;
    }

    /**
     * @return bool|int
     */
    private function add_price_type()
    {
        try{
            \Bitrix\Main\Loader::includeModule('catalog');

            if( self::check_price_type() ){
                throw new \Bitrix\Main\SystemException("Тип цен " . $this->common_helpers::NAME_PRICE_TYPE . " уже создан.");
            }
            $arFields = [
                "NAME"           => $this->common_helpers::NAME_PRICE_TYPE,
                "BASE"           => "N",
                "SORT"           => 100,
                "USER_GROUP"     => [ 1 ],   // видят Администраторы
                "USER_GROUP_BUY" => [ 1 ],  // покупают по этой цене Администраторы
                // только члены группы 2
                "USER_LANG"      => [
                    "ru" => $this->common_helpers::NAME_PRICE_TYPE,
                    "en" => $this->common_helpers::NAME_PRICE_TYPE,
                ],
            ];

            $ID = \CCatalogGroup::Add($arFields);
            if( $ID <= 0 ){
                throw new \Bitrix\Main\SystemException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_ADD_PRICE_TYPE"));
            }else{
                return $ID;
            }
        }catch( \Throwable $e ){
            $this->add_error($e);
        }

        return false;
    }

    /**
     * @param bool   $condition
     * @param string $func
     */
    private function save_unroll( $condition, $func )
    {
        if( ( $this->need_save_unroll ) && ( $condition !== false ) ){
            $this->unroll_methods[] = $func;
        }
    }

    public function get_errors()
    {
        return $this->errors;
    }


    /**
     * @param Throwable $error
     */
    private function add_error( $error )
    {
        $this->errors[] = $error->getMessage();
    }

    private static function autoload_helpers()
    {
        $module_id = "priceva.connector";

        if( !class_exists('\Priceva\Connector\Bitrix\Helpers\CommonHelpers') ){
            if( file_exists($_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/priceva.connector/include.php") ){
                require_once( $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/$module_id/include.php" );
            }else{
                CopyDirFiles(self::GetPatch() . "/lib/", $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/priceva.connector/lib", true, true);
                require_once( $_SERVER[ "DOCUMENT_ROOT" ] . "/local/modules/$module_id/include.php" );
            }
        }

        return \Priceva\Connector\Bitrix\Helpers\CommonHelpers::class;
    }
}