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

class JeroenVermeulen_Solarium_Model_Observer extends Varien_Event_Observer
{
    /**
     * This an observer function for the event 'controller_front_init_before'.
     * It prepends our autoloader, so we can load the extra libraries.
     *
     * @param Varien_Event_Observer $observer
     */
    public function controllerFrontInitBefore( /** @noinspection PhpUnusedParameterInspection */ $observer ) {
        Mage::helper( 'jeroenvermeulen_solarium/autoloader' )->register();
    }

    /**
     * This an observer function for the event 'shell_reindex_init_process'.
     * It prepends our autoloader, so we can load the extra libraries.
     * When the shell script indexer.php is used, the "controller_front_init_before" event is not dispatched.
     *
     * @param Varien_Event_Observer $observer
     */
    public function shellReindexInitProcess( /** @noinspection PhpUnusedParameterInspection */ $observer ) {
        Mage::helper( 'jeroenvermeulen_solarium/autoloader' )->register();
    }

}