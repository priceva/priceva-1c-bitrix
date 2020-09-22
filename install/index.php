<?php
/**
 * Created by PhpStorm.
 * User: S.Belichenko, email: stanislav@priceva.com
 * Date: 17.01.2019
 * Time: 18:10
 */

use Bitrix\Main\ArgumentException;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Priceva\Connector\Bitrix\Helpers\CommonHelpers;
use Priceva\Connector\Bitrix\Helpers\OptionsHelpers;
use Priceva\Connector\Bitrix\Options;
use Priceva\Connector\Bitrix\OptionsPage;
use Priceva\Connector\Bitrix\PricevaModuleException;

Class priceva_connector extends CModule
{
    var $MODULE_ID = 'priceva.connector';

    var $PARTNER_NAME = '';
    var $PARTNER_URI  = '';

    var $MODULE_VERSION      = '';
    var $MODULE_VERSION_DATE = '';
    var $MODULE_NAME         = '';
    var $MODULE_DESCRIPTION  = '';

    /**
     * @var $common_helpers  Priceva\Connector\Bitrix\Helpers\CommonHelpers
     */
    private $common_helpers;
    /**
     * @var $options_helpers Priceva\Connector\Bitrix\Helpers\OptionsHelpers
     */
    private $options_helpers;
    /**
     * @var $options Priceva\Connector\Bitrix\Options
     */
    private $options;

    private $need_save_unroll = false;

    private $unroll_methods = [];
    private $errors         = [];
    private $info           = [];

    private $delete_options            = false;
    private $delete_price_type         = false;
    private $delete_price_type_priceva = false;

    function __construct()
    {
        Loc::loadMessages(__FILE__);

        $arModuleVersion = [];

        include( __DIR__ . "/version.php" );

        $this->MODULE_VERSION      = $arModuleVersion[ "VERSION" ];
        $this->MODULE_VERSION_DATE = $arModuleVersion[ "VERSION_DATE" ];
        $this->MODULE_NAME         = Loc::getMessage("PRICEVA_BC_MODULE_NAME");
        $this->MODULE_DESCRIPTION  = Loc::getMessage("PRICEVA_BC_MODULE_DESC");

        $this->PARTNER_NAME = "Priceva";
        $this->PARTNER_URI  = "https://priceva.ru";
    }

    static public function get_current_path( $notDocumentRoot = false )
    {
        if( $notDocumentRoot )
            return str_ireplace($_SERVER[ "DOCUMENT_ROOT" ], '', dirname(__DIR__));
        else
            return dirname(__DIR__);
    }

    /**
     * @param bool $uninstall
     *
     * @throws PricevaModuleException
     * @throws LoaderException
     */
    private function check_system( $uninstall = false )
    {
        $common_helpers = $this->common_helpers;

        if( !$uninstall ){
            if( !$common_helpers::check_php_ext() ){
                throw new PricevaModuleException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_PHP_EXT"));
            }

            if( IsModuleInstalled($common_helpers::MODULE_ID) ){
                throw new PricevaModuleException(Loc::getMessage("PRICEVA_BC_INSTALL_INSTALL"));
            }
        }
        if( !$common_helpers::check_php_ver() ){
            throw new PricevaModuleException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_PHP_VER"), 777);
        }

        if( !$common_helpers::bitrix_d7() ){
            throw new PricevaModuleException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_VERSION") . $common_helpers::NEEDED_BITRIX_VER);
        }

        if( !Loader::includeModule('catalog') ){
            throw new PricevaModuleException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_MODULE_CATALOG_NOT_INSTALLED"));
        }
    }

    /**
     * @param int  $step
     * @param bool $is_full_business
     *
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws PricevaModuleException
     */
    private function install( $step, $is_full_business )
    {
        if( $step < 2 ){
            $this->install_step_1($is_full_business);
        }elseif( $step == 2 ){
            $this->install_step_2($is_full_business);
        }
    }

    /**
     * @return bool
     */
    function DoInstall()
    {
        global $step;

        try{
            $this->autoloader();
            $common_helpers = $this->common_helpers;
            $this->check_system();

            $step = IntVal($step);

            $is_full_business = $common_helpers::bitrix_full_business();

            $this->install($step, $is_full_business);

        }catch( Exception $e ){
            $common_helpers = $this->common_helpers;
            // 777 - php ver error, 778 - priceva autoloader error
            if( in_array($e->getCode(), [ 777, 778 ]) ){
                $common_helpers::write_to_log($e);
            }
            $this->common_helpers->APPLICATION->ThrowException($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param bool $full
     *
     * @throws PricevaModuleException
     */
    private function install_step_1( $full )
    {
        $this->InstallFiles();

        if( $full ){
            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::get_current_path() . "/install/step1_full_business.php"
            );
        }else{
            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::get_current_path() . "/install/step1_small_business.php"
            );
        }
    }

    /**
     * @param $full
     *
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws PricevaModuleException
     */
    private function install_step_2( $full )
    {
        $common_helpers = $this->common_helpers;

        $this->need_save_unroll = true;

        $this->InstallFiles();
        $this->InstallTasks();
        $this->InstallEvents();

        $id_type_price = $this->InstallDB();

        if( $full ){
            COption::SetOptionString($common_helpers::MODULE_ID, 'ID_TYPE_PRICE_PRICEVA', $id_type_price);
        }else{
            COption::SetOptionString($common_helpers::MODULE_ID, 'ID_TYPE_PRICE', $id_type_price);
        }

        $id_agent = $this->InstallAgents();
        COption::SetOptionString($common_helpers::MODULE_ID, 'ID_AGENT', $id_agent);

        $this->need_save_unroll = false;

        $aTabs = OptionsPage::get_main_options(true);
        OptionsPage::process_save_form(false, [ [ 'OPTIONS' => $aTabs ] ], $id_type_price, true);

        if( $this->errors ){

            foreach( $this->unroll_methods as $method ){
                $this->$method();
            }

            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::get_current_path() . "/install/errors.php"
            );
        }else{
            ModuleManager::registerModule($common_helpers::MODULE_ID);

            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::get_current_path() . "/install/step2.php"
            );
        }
    }

    private function uninstall_step_1( $full )
    {
        if( $full ){
            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::get_current_path() . "/install/unstep1_full_business.php"
            );
        }else{
            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::get_current_path() . "/install/unstep1_small_business.php"
            );
        }
    }

    /**
     * @param bool $full
     */
    private function uninstall_step_2( $full )
    {
        $common_helpers = $this->common_helpers;

        $request = $common_helpers::getInstance()->app->getContext()->getRequest();

        $this->delete_options = $common_helpers::convert_to_bool($request->get('options'));
        if( $full ){
            $this->delete_price_type         = $common_helpers::convert_to_bool($request->get('type_price'));
            $this->delete_price_type_priceva = $common_helpers::convert_to_bool($request->get('price_type_priceva'));
        }

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

            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_UNINSTALL_TITLE_1"),
                self::get_current_path() . "/install/errors.php"
            );
        }else{
            $this->info[ 'module_id' ] = $common_helpers::MODULE_ID;
            $this->common_helpers->APPLICATION->IncludeAdminFile(
                Loc::getMessage("PRICEVA_BC_INSTALL_TITLE_1"),
                self::get_current_path() . "/install/unstep2.php"
            );
        }
    }

    private function uninstall( $step, $is_full_business )
    {
        if( $step < 2 ){
            $this->uninstall_step_1($is_full_business);
        }elseif( $step == 2 ){
            $this->uninstall_step_2($is_full_business);
        }
    }

    /**
     * @return bool
     */
    function DoUninstall()
    {
        global $step;

        try{
            $this->autoloader();
            $common_helpers = $this->common_helpers;
            $this->check_system(true);

            $step = IntVal($step);

            $is_full_business = $common_helpers::bitrix_full_business();

            $this->uninstall($step, $is_full_business);

        }catch( Throwable $e ){
            $common_helpers = $this->common_helpers;
            // 777 - php ver error, 778 - priceva autoloader error
            if( in_array($e->getCode(), [ 777, 778 ]) ){
                $common_helpers::write_to_log($e);
            }
            $this->common_helpers->APPLICATION->ThrowException($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @return bool|int
     */
    function InstallDB()
    {
        parent::InstallDB();

        $common_helpers  = $this->common_helpers;
        $options_helpers = $this->options_helpers;

        $is_full_business = $common_helpers::bitrix_full_business();

        if( !$is_full_business ){
            return $options_helpers::get_base_price_type();
        }

        if( $price_type_priceva_id = $options_helpers::find_price_type_priceva_id() ){
            return $price_type_priceva_id;
        }

        $r = $this->add_price_type();

        $this->save_unroll($r, "UnInstallDB");

        return $r;
    }

    function UnInstallDB()
    {
        parent::UnInstallDB();

        $common_helpers = $this->common_helpers;
        $options        = $this->options;

        $save_unroll = true;

        if( $this->delete_price_type ){
            $type_price_ID = $options::type_price_ID();

            $type_price = $this->delete_price_type($type_price_ID);

            $this->info[ 'deleted_price' ] = $type_price;

            $save_unroll = $type_price;
        }

        if( $this->delete_price_type_priceva ){
            $type_price_priceva_ID = $options::type_price_priceva_ID();

            $deleted_price_priceva = $this->delete_price_type($type_price_priceva_ID);

            $this->info[ 'deleted_price_priceva' ] = $deleted_price_priceva;

            $save_unroll = $deleted_price_priceva && $save_unroll;
        }

        if( $this->delete_options ){
            COption::RemoveOption($common_helpers::MODULE_ID);
        }

        $this->save_unroll($save_unroll, "InstallDB");
    }

    /**
     * @param $from
     * @param $to
     *
     * @return bool
     * @throws PricevaModuleException
     */
    private function copy_dir_or_files( $from, $to )
    {
        $result = false;

        if( strpos($to . "/", $from . "/") === 0 || realpath($to) === realpath($from) ){
            return true;
        }

        if( is_dir($from) ){
            // если копируем папку
            if( false === $result = CopyDirFiles($from, $to, true, true) ){
                // не получилось скопировать папку - сообщим об этом ошибкой
                throw new PricevaModuleException("Cant copy directory from $from to $to");
            }
        }elseif( is_file($from) ){
            // если копируем файл
            if( false === $result = CopyDirFiles($from, $to, true) ){
                // не получилось создать файл - сообщим об этом ошибкой
                throw new PricevaModuleException("Cant copy file from $from to $to");
            }
        }

        return $result;
    }

    /**
     * @throws PricevaModuleException
     */
    function InstallFiles()
    {
        parent::InstallFiles();

        $common_helpers = $this->common_helpers;

        $bitrix_root         = $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix";
        $module_root         = $bitrix_root . "/modules/" . $common_helpers::MODULE_ID;
        $current_dir         = self::get_current_path();
        $current_install_dir = self::get_current_path() . "/install";

        $res = true;

        $res = $res && $this->copy_dir_or_files($current_install_dir . "/admin", $bitrix_root . "/admin");
        $res = $res && $this->copy_dir_or_files($current_dir . "/default_option.php", $module_root . "/default_option.php");
        $res = $res && $this->copy_dir_or_files($current_dir . "/options.php", $module_root . "/options.php");
        $res = $res && $this->copy_dir_or_files($current_dir . "/prolog.php", $module_root . "/prolog.php");
        $res = $res && $this->copy_dir_or_files($current_dir . "/lang", $module_root . "/lang");
        $res = $res && $this->copy_dir_or_files($current_dir . "/admin", $module_root . "/admin");
        $res = $res && $this->copy_dir_or_files($current_dir . "/assets/js/", $bitrix_root . "/js/" . $common_helpers::MODULE_ID);

        $this->save_unroll($res, "UnInstallFiles");
    }

    function UnInstallFiles()
    {
        parent::UnInstallFiles();

        Bitrix\Main\IO\Directory::deleteDirectory($_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/admin/priceva_bc.php");

        $this->save_unroll(true, "InstallFiles");
    }

    function InstallEvents()
    {
        parent::InstallEvents();

        $common_helpers = $this->common_helpers;

        EventManager::getInstance()->registerEventHandler('main', 'OnBuildGlobalMenu', $common_helpers::MODULE_ID, 'Priceva\Connector\Bitrix\PricevaConnector', 'AddGlobalMenuItem');

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

        $common_helpers = $this->common_helpers;

        EventManager::getInstance()->unRegisterEventHandler('main', 'OnBuildGlobalMenu', $common_helpers::MODULE_ID, 'Priceva\Connector\Bitrix\PricevaConnector', 'AddGlobalMenuItem');

        $this->save_unroll(true, "InstallEvents");
    }

    /**
     * todo Проверять, что агент еще не создан, и только тогда выдавать ошибку
     * @return int|bool
     */
    private function InstallAgents()
    {
        $this->common_helpers->APPLICATION->ResetException();

        $date = \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime('+1 day'));

        $id = CAgent::AddAgent(
            "\Priceva\Connector\Bitrix\PricevaConnector::agent();",
            "priceva.connector",
            "N",
            86400,
            '',
            "Y",
            $date->toString(),
            30
        );

        if( $id === false ){
            $this->errors[] = Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_ADD_AGENT") . ": " . $this->common_helpers->APPLICATION->GetException();

            return false;
        }else{
            $this->save_unroll(true, "UnInstallAgents");

            return $id;
        }
    }

    private function UnInstallAgents()
    {
        CAgent::RemoveAgent(
            "\Priceva\Connector\Bitrix\PricevaConnector::run();",
            "priceva.connector"
        );

        $this->save_unroll(true, "InstallAgents");
    }

    private function price_type_exist()
    {
        try{
            $common_helpers = $this->common_helpers;

            $dbPriceType = CCatalogGroup::GetList([], [ "NAME" => $common_helpers::NAME_PRICE_TYPE ]);
            while( $arPriceType = $dbPriceType->Fetch() ){
                return $arPriceType[ 'ID' ];
            }

            return false;

        }catch( Throwable $e ){
            $this->add_error($e);

            return false;
        }
    }

    private function delete_price_type( $type_price_ID )
    {
        try{
            $this->common_helpers->APPLICATION->ResetException();

            if( false == $type_price_name = CCatalogGroup::GetByID($type_price_ID)[ 'NAME' ] ){
                if( $error = $this->common_helpers->APPLICATION->GetException() ){
                    throw new Exception(Loc::getMessage("PRICEVA_BC_ERROR_DELETE_PRICE_TYPE") . " " . $error);
                }else{
                    return Loc::getMessage("PRICEVA_BC_INSTALL_PRICE_TYPE_DELETED_EARLIER") . $type_price_name . "(id=" . $type_price_ID . ")";
                }
            }

            if( false === ( new CCatalogGroup )->Delete($type_price_ID) ){
                if( $error = $this->common_helpers->APPLICATION->GetException() ){
                    throw new Exception(Loc::getMessage("PRICEVA_BC_ERROR_DELETE_PRICE_TYPE") . " " . $error);
                }else{
                    throw new Exception(Loc::getMessage("PRICEVA_BC_INSTALL_UNEXPECTED_SITUATION"));
                }
            }

            return Loc::getMessage("PRICEVA_BC_INSTALL_SUCCESS_DELETE_PRICE_TYPE") . $type_price_name . "(id=" . $type_price_ID . ")";
        }catch( Throwable $e ){
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
            $common_helpers = $this->common_helpers;

            Loader::includeModule('catalog');

            if( false !== $id = self::price_type_exist() ){
                throw new Exception(Loc::getMessage("PRICEVA_BC_INSTALL_PRICE_TYPE_EXIST") . $common_helpers::NAME_PRICE_TYPE . " (id=" . $id . ")");
            }
            $arFields = [
                "NAME"           => $common_helpers::NAME_PRICE_TYPE,
                "BASE"           => "N",
                "SORT"           => 100,
                "USER_GROUP"     => [ 1 ],   // видят Администраторы
                "USER_GROUP_BUY" => [ 1 ],  // покупают по этой цене Администраторы
                // только члены группы 2
                "USER_LANG"      => [
                    "ru" => $common_helpers::NAME_PRICE_TYPE,
                    "en" => $common_helpers::NAME_PRICE_TYPE,
                ],
            ];

            $ID = ( new CCatalogGroup )->Add($arFields);
            if( $ID <= 0 ){
                throw new SystemException(Loc::getMessage("PRICEVA_BC_INSTALL_ERROR_ADD_PRICE_TYPE"));
            }else{
                return $ID;
            }
        }catch( Throwable $e ){
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

    /**
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * @param string $param
     *
     * @return array|string
     */
    public function get_info( $param = '' )
    {
        if( $param ){
            return $this->info[ $param ];
        }else{
            return $this->info;
        }
    }

    /**
     * @param Throwable $error
     */
    private function add_error( $error )
    {
        $this->errors[] = $error->getMessage();
    }

    /**
     * @throws Exception
     */
    private function autoloader()
    {
        try{
            if(
                !class_exists('\Priceva\Connector\Bitrix\Helpers\CommonHelpers') ||
                !class_exists('\Priceva\Connector\Bitrix\Helpers\OptionsHelpers') ||
                !class_exists('\Priceva\Connector\Bitrix\Options') ||
                !class_exists('\Priceva\Connector\Bitrix\OptionsPage') ||
                !class_exists('\Priceva\Connector\Bitrix\Ajax') ||
                !class_exists('\Priceva\Connector\Bitrix\PricevaModuleException')
            ){
                CopyDirFiles(self::get_current_path() . "/lib/bitrix/", $_SERVER[ "DOCUMENT_ROOT" ] . "/bitrix/modules/$this->MODULE_ID/lib/bitrix/", true, true);

                /** @noinspection PhpIncludeInspection */
                require_once( self::get_current_path() . "/include.php" );
            }

            $this->common_helpers  = CommonHelpers::getInstance();
            $this->options_helpers = OptionsHelpers::getInstance();
            $this->options         = Options::class;
        }catch( Exception $e ){
            throw new Exception('Priceva autoloader error.', 778);
        }
    }
}
