<?php
/**
 * JeroenVermeulen_Solarium
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this Module to
 * newer versions in the future.
 *
 * @category    JeroenVermeulen
 * @package     JeroenVermeulen_Solarium
 * @copyright   Copyright (c) 2014 Jeroen Vermeulen (http://www.jeroenvermeulen.eu)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class JeroenVermeulen_Solarium_Model_Observer_Autoloader extends Varien_Event_Observer
{

    /**
     * This an observer function for the event 'controller_front_init_before'.
     * It prepends our autoloader, so we can load the extra libraries.
     *
     * @param Varien_Event_Observer $observer
     */
    public function controllerFrontInitBefore( /** @noinspection PhpUnusedParameterInspection */ $observer ) {
        $this->_registerAutoLoader();
    }

    /**
     * This an observer function for the event 'shell_reindex_init_process'.
     * It prepends our autoloader, so we can load the extra libraries.
     * When the shell script indexer.php is used, the "controller_front_init_before" event is not dispatched.
     *
     * @param Varien_Event_Observer $observer
     */
    public function shellReindexInitProcess( /** @noinspection PhpUnusedParameterInspection */ $observer ) {
        $this->_registerAutoLoader();
    }

    /**
     * This function can autoload classes starting with:
     * - Solarium
     * - Symfony\Component\EventDispatcher
     *
     * @param string $class
     */
    public static function load( $class ) {
        if ( preg_match( '#^(Solarium|Symfony\\\\Component\\\\EventDispatcher)\b#', $class ) ) {
            $phpFile = Mage::getBaseDir( 'lib' ) . DIRECTORY_SEPARATOR
                       . str_replace( '\\', DIRECTORY_SEPARATOR, $class ) . '.php';
            /** @noinspection PhpIncludeInspection */
            require_once( $phpFile );
        }
    }

    /**
     * Prepends our autoloader, so we can load the extra libraries.
     */
    private function _registerAutoLoader() {
        static $registered;
        if ( empty( $registered ) ) {
            $registered = true;
            spl_autoload_register( array( $this, 'load' ), true, true );
        }
    }

}