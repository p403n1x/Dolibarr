<?php

/*
 *  This file is part of SplashSync Project.
 *
 *  Copyright (C) 2015-2020 Splash Sync  <www.splashsync.com>
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Splash\Local;

use Splash\Core\SplashCore      as Splash;
use Splash\Local\Core\ExtraFieldsPhpUnitTrait;
use Splash\Local\Core\MultiCompanyTrait;
use Splash\Models\LocalClassInterface;
use User;

/**
 * Local Core Management Class
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Local implements LocalClassInterface
{
    use ExtraFieldsPhpUnitTrait;
    use MultiCompanyTrait;

    /**
     * @var string
     */
    const ROOT_INC = "master.inc.php";

    //====================================================================//
    // *******************************************************************//
    //  MANDATORY CORE MODULE LOCAL FUNCTIONS
    // *******************************************************************//
    //====================================================================//

    /**
     * {@inheritdoc}
     */
    public function parameters()
    {
        global $langs;

        $parameters = array();
        //====================================================================//
        // Server Identification Parameters
        $parameters["WsIdentifier"] = self::getParameter("SPLASH_WS_ID");
        $parameters["WsEncryptionKey"] = self::getParameter("SPLASH_WS_KEY");
        //====================================================================//
        // If Expert Mode => Allow Overide of Server Host Address
        if ((self::getParameter("SPLASH_WS_EXPERT")) && !empty(self::getParameter("SPLASH_WS_HOST"))) {
            $parameters["WsHost"] = self::getParameter("SPLASH_WS_HOST");
        }
        //====================================================================//
        // If Expert Mode => Allow Update of Communication Protocol
        if ((self::getParameter("SPLASH_WS_EXPERT")) && !empty(self::getParameter("SPLASH_WS_METHOD"))) {
            $parameters["WsMethod"] = self::getParameter("SPLASH_WS_METHOD");
        }
        //====================================================================//
        // Smart Notifications
        $parameters["SmartNotify"] = (bool) self::getParameter("SPLASH_SMART_NOTIFY");
        //====================================================================//
        // Strict Variants Mode
        $parameters["StrictVariantsMode"] = false;
        //====================================================================//
        // Overide Module Parameters with Local User Selected Lang
        if (self::getParameter("SPLASH_LANG")) {
            $parameters["DefaultLanguage"] = self::getParameter("SPLASH_LANG");
        //====================================================================//
        // Overide Module Parameters with Local Default System Lang
        } elseif (($langs) && $langs->getDefaultLang()) {
            $parameters["DefaultLanguage"] = $langs->getDefaultLang();
        }
        //====================================================================//
        // Overide Module Local Name in Logs
        $parameters["localname"] = self::getParameter("MAIN_INFO_SOCIETE_NOM");
        //====================================================================//
        // Overide Webserver Path if MultiCompany Module Is Active
        if ($this->isMultiCompanyChildEntity()) {
            $parameters["ServerPath"] = $this->getMultiCompanyServerPath();
        }
        //====================================================================//
        // Setup Custom Json Configuration Path to (../conf/splash.json)
        $parameters["ConfiguratorPath"] = $this->getDolibarrRoot()."/conf/splash.json";

        return $parameters;
    }

    /**
     * {@inheritdoc}
     *
     * @global User $user
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function includes()
    {
        //====================================================================//
        // When Library is called in server mode ONLY
        //====================================================================//
        if (!empty(SPLASH_SERVER_MODE)) {
            // This is Webservice Access. We must be able to go on it from outside.
            define('NOCSRFCHECK', 1);
        }

        //====================================================================//
        // When Library is called in client mode ONLY
        //====================================================================//

        // NOTHING TO DO

        //====================================================================//
        // When Library is called in both client & server mode
        //====================================================================//

        if (!defined("DOL_DOCUMENT_ROOT")) {
            /** @codingStandardsIgnoreStart */
            global $db, $langs, $conf, $user, $hookmanager, $dolibarr_main_url_root, $mysoc;
            /** @codingStandardsIgnoreEnd */
            //====================================================================//
            // Initiate Dolibarr Global Envirement Variables
            require_once($this->getDolibarrRoot()."/".self::ROOT_INC);

            //====================================================================//
            // Splash Modules Constant Definition
            dol_include_once("/splash/_conf/defines.inc.php");

            //====================================================================//
            // Load Default User
            $this->loadLocalUser();

            //====================================================================//
            // Load Default Language
            self::loadDefaultLanguage();

            //====================================================================//
            // Manage MultiCompany
            //====================================================================//
            $this->setupMultiCompany();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function selfTest()
    {
        global $langs;

        //====================================================================//
        //  Load Local Translation File
        Splash::translator()->load("main@local");
        $langs->load("errors");

        //====================================================================//
        //  Verify - Server Core Infos
        if (!self::selfTestCore()) {
            return false;
        }
        //====================================================================//
        //  Verify - User Config
        if (!self::selfTestConfig()) {
            return false;
        }

        Splash::log()->msg("MsgSelfTestOk");

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function informations($informations)
    {
        //====================================================================//
        // Init Response Object
        $response = $informations;

        //====================================================================//
        // Company Informations
        $response->company = self::getParameter("MAIN_INFO_SOCIETE_NOM", "...");
        $response->address = self::getParameter("MAIN_INFO_SOCIETE_ADDRESS", "...");
        $response->zip = self::getParameter("MAIN_INFO_SOCIETE_ZIP", "...");
        $response->town = self::getParameter("MAIN_INFO_SOCIETE_TOWN", "...");
        $response->country = self::getParameter("MAIN_INFO_SOCIETE_COUNTRY", "...");
        $response->www = self::getParameter("MAIN_INFO_SOCIETE_WEB", "...");
        $response->email = self::getParameter("MAIN_INFO_SOCIETE_MAIL", "...");
        $response->phone = self::getParameter("MAIN_INFO_SOCIETE_TEL", "...");

        //====================================================================//
        // Server Logo & Images
        $response->icoraw = Splash::file()->readFileContents(DOL_DOCUMENT_ROOT."/favicon.ico");
        $response->logourl = "https://www.dolibarr.org";
        $response->logourl .= "/medias/image/www.dolibarr.org/images/stories/dolibarr_256x256.png";

        //====================================================================//
        // Server Informations
        $response->servertype = "Dolibarr ERP";
        $response->serverurl = DOL_MAIN_URL_ROOT;

        //====================================================================//
        // Current Module Version
        $response->moduleversion = SPL_MOD_VERSION;

        return $response;
    }

    //====================================================================//
    // *******************************************************************//
    //  OPTIONNAl CORE MODULE LOCAL FUNCTIONS
    // *******************************************************************//
    //====================================================================//

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testSequences($name = null)
    {
        global $db, $conf;
        require_once(DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php");
        $ent = $conf->entity;

        //====================================================================//
        // Disable BackLog for Dolibarr Version below 9.0
        if (Local::dolVersionCmp("9.0.0") < 0) {
            $conf->blockedlog->enabled = 0;
        }

        switch ($name) {
            case "Monolangual":
                dolibarr_set_const($db, "MAIN_MULTILANGS", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "PRODUIT_MULTIPRICES", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_MULTICOMPANY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_DETECT_TAX_NAME", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_ALLOW", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_VARIANTS", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_CODECLIENT_ADDON", 'mod_codeclient_monkey', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_CODECOMPTA_ADDON", 'mod_codecompta_aquarium', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_EMAIL_MANDATORY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF1_MANDATORY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF2_MANDATORY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF3_MANDATORY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF4_MANDATORY", '0', 'chaine', 0, '', $ent);

                self::configurePhpUnitExtraFields("societe", false);
                self::configurePhpUnitExtraFields("socpeople", false);
                self::configurePhpUnitExtraFields("product", false);
                self::configurePhpUnitExtraFields("commande", false);
                self::configurePhpUnitExtraFields("facture", false);

                return array();
            case "Multilangual":
                dolibarr_set_const($db, "MAIN_MULTILANGS", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "PRODUIT_MULTIPRICES", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_MULTICOMPANY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_ALLOW", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_VARIANTS", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_EMAIL_MANDATORY", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF1_MANDATORY", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF2_MANDATORY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF3_MANDATORY", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF4_MANDATORY", '0', 'chaine', 0, '', $ent);

                return array();
            case "MultiPrices":
                dolibarr_set_const($db, "MAIN_MULTILANGS", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "PRODUIT_MULTIPRICES", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "PRODUIT_MULTIPRICES_LIMIT", '3', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_MULTIPRICE_LEVEL", (string) rand(1, 3), 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_MULTICOMPANY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_ALLOW", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_VARIANTS", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_EMAIL_MANDATORY", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF1_MANDATORY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF2_MANDATORY", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF3_MANDATORY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SOCIETE_IDPROF4_MANDATORY", '1', 'chaine', 0, '', $ent);

                self::configurePhpUnitExtraFields("societe", false);
                self::configurePhpUnitExtraFields("socpeople", false);
                self::configurePhpUnitExtraFields("product", false);
                self::configurePhpUnitExtraFields("commande", false);
                self::configurePhpUnitExtraFields("facture", false);

                return array();
            case "ExtraFields":
                dolibarr_set_const($db, "MAIN_MULTILANGS", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "PRODUIT_MULTIPRICES", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_MULTICOMPANY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_ALLOW", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_VARIANTS", '0', 'chaine', 0, '', $ent);

                self::configurePhpUnitExtraFields("societe", true);
                self::configurePhpUnitExtraFields("socpeople", true);
                self::configurePhpUnitExtraFields("product", true);
                self::configurePhpUnitExtraFields("commande", true);
                self::configurePhpUnitExtraFields("facture", true);

                return array();
            case "Variants":
                dolibarr_set_const($db, "MAIN_MULTILANGS", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "PRODUIT_MULTIPRICES", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_MULTICOMPANY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_ALLOW", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_VARIANTS", '1', 'chaine', 0, '', $ent);

                self::configurePhpUnitExtraFields("societe", false);
                self::configurePhpUnitExtraFields("socpeople", false);
                self::configurePhpUnitExtraFields("product", false);
                self::configurePhpUnitExtraFields("commande", false);
                self::configurePhpUnitExtraFields("facture", false);

                return array();
            case "GuestOrders":
                dolibarr_set_const($db, "MAIN_MULTILANGS", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "PRODUIT_MULTIPRICES", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_MULTICOMPANY", '0', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_ALLOW", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_CUSTOMER", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "SPLASH_GUEST_ORDERS_EMAIL", '1', 'chaine', 0, '', $ent);
                dolibarr_set_const($db, "MAIN_MODULE_VARIANTS", '0', 'chaine', 0, '', $ent);

                self::configurePhpUnitExtraFields("societe", false);
                self::configurePhpUnitExtraFields("socpeople", false);
                self::configurePhpUnitExtraFields("product", false);
                self::configurePhpUnitExtraFields("commande", false);
                self::configurePhpUnitExtraFields("facture", false);

                return array();
            default:
            case "List":
                return array("Monolangual", "Multilangual", "Variants", "MultiPrices", "GuestOrders", "ExtraFields" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function testParameters()
    {
        //====================================================================//
        // Init Parameters Array
        return array();
    }

    //====================================================================//
    //  Dolibarr Specific Tools
    //====================================================================//

    /**
     * Compare Dolibarr version is lower/greater then version given.
     *
     * @param string $version Dolibarr Version to compare (ie : 3.3.3)
     *
     * @return int -1 if given version is lower then current version
     *             0 if given version is egal to current version
     *             1 if given version is above current version
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public static function dolVersionCmp($version)
    {
        $current = explode('.', DOL_VERSION);
        $cmp = explode('.', $version);

        if (($current["0"] == $cmp ["0"]) && ($current["1"] == $cmp ["1"]) && ($current["2"] == $cmp ["2"])) {
            return 0;
        }
        if (($current["0"] > $cmp ["0"])) {
            return 1;
        }
        if (($current["0"] < $cmp ["0"])) {
            return -1;
        }
        if (($current["1"] > $cmp ["1"])) {
            return 1;
        }
        if (($current["1"] < $cmp ["1"])) {
            return -1;
        }
        if (($current["2"] > $cmp ["2"])) {
            return 1;
        }
        if (($current["2"] < $cmp ["2"])) {
            return -1;
        }

        return 0;
    }

    /**
     * Initiate Local Request User if not already defined
     *
     * @return void
     */
    public static function loadDefaultLanguage()
    {
        global $langs;
        //====================================================================//
        // Load Default Language
        //====================================================================//
        if (!empty(self::getParameter("SPLASH_LANG"))) {
            $langs->setDefaultLang(self::getParameter("SPLASH_LANG"));
        }

        //====================================================================//
        // Load Required Splash Translation Files
        Splash::translator()->load("main@local");
        Splash::translator()->load("objects@local");
    }

    /**
     * Safe Get of A Global Parameter
     *
     * @param string $key     Global Parameter Key
     * @param string $default Default Parameter Value
     *
     * @return string
     */
    public static function getParameter($key, $default = null)
    {
        global $conf;

        return isset($conf->global->{$key})  ? $conf->global->{$key} : $default;
    }

    //====================================================================//
    // *******************************************************************//
    // Place Here Any SPECIFIC or COMMON Local Functions
    // *******************************************************************//
    //====================================================================//

    /**
     * Execute Core Module SelfTest
     *
     * @global object $conf
     *
     * @return bool
     */
    private static function selfTestCore()
    {
        global $conf;

        //====================================================================//
        //  Verify - Server Identifier Given
        if (!isset($conf->global->SPLASH_WS_ID) || empty($conf->global->SPLASH_WS_ID)) {
            return Splash::log()->err("ErrSelfTestNoWsId");
        }

        //====================================================================//
        //  Verify - Server Encrypt Key Given
        if (!isset($conf->global->SPLASH_WS_KEY) || empty($conf->global->SPLASH_WS_KEY)) {
            return Splash::log()->err("ErrSelfTestNoWsKey");
        }

        return true;
    }

    /**
     * Execute Module Configuration SelfTest
     *
     * @return bool
     */
    private static function selfTestConfig()
    {
        global $conf,$langs;

        //====================================================================//
        //  Verify - User Selected
        if (!isset($conf->global->SPLASH_USER) || ($conf->global->SPLASH_USER <= 0)) {
            return Splash::log()->err("ErrSelfTestNoUser");
        }

        //====================================================================//
        //  Verify - Stock Selected
        if (!isset($conf->global->SPLASH_STOCK) || ($conf->global->SPLASH_STOCK <= 0)) {
            return Splash::log()->err("ErrSelfTestNoStock");
        }

        //====================================================================//
        // Check if company name is defined (first install)
        if (empty($conf->global->MAIN_INFO_SOCIETE_NOM) || empty($conf->global->MAIN_INFO_SOCIETE_COUNTRY)) {
            return Splash::log()->err($langs->trans("WarningMandatorySetupNotComplete"));
        }

        //====================================================================//
        // Check Version is Above 4.0
        if (self::dolVersionCmp("4.0.0") < 0) {
            return Splash::log()->err(
                "Splash Module for Dolibarr require Dolibarr Version Above 4.0. "
                    ."Please update your system before using Splash."
            );
        }

        return true;
    }

    /**
     * Initiate Local Request User if not already defined
     *
     * @return bool
     */
    private function loadLocalUser()
    {
        global $conf,$db,$user;

        //====================================================================//
        // CHECK USER ALREADY LOADED
        //====================================================================//
        if (isset($user->id) && !empty($user->id)) {
            return true;
        }

        //====================================================================//
        // LOAD USER FROM DATABASE
        //====================================================================//

        //====================================================================//
        // Include Object Dolibarr Class
        require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

        //====================================================================//
        // Read Local Configuration
        $userId = isset($conf->global->SPLASH_USER)?$conf->global->SPLASH_USER:null;
        if (empty($userId)) {
            return Splash::log()->err("Local - Dolibarr Error : No Local User Defined.");
        }
        //====================================================================//
        // Load Local User

        $user = new User($db);
        if (1 != $user->fetch($userId)) {
            Splash::log()->err("Local : Unable to Load Local User");

            return Splash::log()->err("Local - Dolibarr Error : ".$user->error);
        }

        //====================================================================//
        // Load Local User Rights
        if (!$user->all_permissions_are_loaded) {
            $user->getrights();
        }

        return true;
    }

    /**
     * Search for Dolibarr Root Folder in upper folders - Maximum 5 Levels
     *
     * @return string
     */
    private function getDolibarrRoot()
    {
        //====================================================================//
        // Search for Dolibarr Root Folder & Store Module Root URL - Maximum 5 Levels
        //====================================================================//

        //====================================================================//
        // Start From Folder Above this module
        $rootFolder = dirname(dirname(dirname(__FILE__)));
        for ($i = 0; $i < 5; $i++) {
            //====================================================================//
            // Check if main.inc.phpo file exist
            if (is_file($rootFolder."/main.inc.php")) {
                return $rootFolder;
            }

            //====================================================================//
            // Move one folder above
            $rootFolder = dirname($rootFolder);
        }

        return dirname(dirname(dirname(__FILE__)));
    }
}
