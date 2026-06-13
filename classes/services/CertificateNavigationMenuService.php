<?php
/**
 * @file plugins/generic/academicCertificate/classes/services/CertificateNavigationMenuService.php
 *
 * Registers a navigation menu item type so journal managers can add
 * "My Certificates / Belgelerim" to any menu via Settings > Website > Navigation Menus.
 */

namespace APP\plugins\generic\academicCertificate\classes\services;

use APP\core\Application;
use PKP\plugins\Hook;

class CertificateNavigationMenuService {

    const MENU_ITEM_TYPE = 'NMI_TYPE_ACM_MY_CERTIFICATES';

    /**
     * Register OJS navigation menu hooks (OJS 3.3–3.5).
     */
    public static function registerHooks() {
        if (class_exists('PKP\plugins\Hook')) {
            Hook::register('NavigationMenus::itemTypes', array(__CLASS__, 'addNavigationMenuItemTypes'));
            Hook::register('NavigationMenus::displaySettings', array(__CLASS__, 'displayNavigationMenuItem'));
        } elseif (class_exists('HookRegistry', false)) {
            \HookRegistry::register('NavigationMenus::itemTypes', array(__CLASS__, 'addNavigationMenuItemTypes'));
            \HookRegistry::register('NavigationMenus::displaySettings', array(__CLASS__, 'displayNavigationMenuItem'));
        }
    }

    /**
     * @param string $hookName
     * @param array $types
     */
    public static function addNavigationMenuItemTypes($hookName, &$types) {
        $types[self::MENU_ITEM_TYPE] = array(
            'title' => __('plugins.generic.academicCertificate.navigation.myCertificates'),
            'description' => __('plugins.generic.academicCertificate.navigation.myCertificates.description'),
            'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
        );
    }

    /**
     * @param string $hookName
     * @param array $params
     */
    public static function displayNavigationMenuItem($hookName, $params) {
        $navigationMenuItem =& $params[0];
        if ($navigationMenuItem->getType() !== self::MENU_ITEM_TYPE) {
            return;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $isLoggedIn = class_exists('Validation', false) && \Validation::isLoggedIn();

        $navigationMenuItem->setIsDisplayed($isLoggedIn && $context);
        if (!$navigationMenuItem->getIsDisplayed()) {
            return;
        }

        $dispatcher = $request->getDispatcher();
        $navigationMenuItem->setUrl($dispatcher->url(
            $request,
            defined('ROUTE_PAGE') ? ROUTE_PAGE : 'page',
            null,
            'certificate',
            'myCertificates',
            null
        ));
    }
}
