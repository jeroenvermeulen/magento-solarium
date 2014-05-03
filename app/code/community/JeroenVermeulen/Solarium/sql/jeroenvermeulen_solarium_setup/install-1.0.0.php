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

/* @var $this Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();
$notice = 'The extension <strong>JeroenVermeulen_Solarium</strong> has been installed.<br />';
$notice .= 'Please log out and log in, then configure via: <em>System &gt; Configuration &gt; CATALOG &gt; Solarium Search</em><br />';
Mage::getSingleton( 'adminhtml/session' )->addNotice( $notice );
$installer->endSetup();