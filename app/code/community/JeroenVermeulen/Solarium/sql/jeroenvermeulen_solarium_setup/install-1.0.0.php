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

/**
 * @TODO Somehow translations are not working here. Maybe it's not possible?
 */

/* @var $this Mage_Core_Model_Resource_Setup */
$this->startSetup();
$this->endSetup();

/** @var $helper JeroenVermeulen_Solarium_Helper_Data */
$helper      = Mage::helper( 'jeroenvermeulen_solarium' );
$configSteps = array(
    $helper->__( 'System' ),
    $helper->__( 'Configuration' ),
    $helper->__( 'CATALOG' ),
    $helper->__( 'Solarium Search' )
);

$notice .= $helper->__('Please follow these steps:').'<br />';
$notice .= '&nbsp; &nbsp; &#8226; '.$helper->__("Flush Magento's Cache Storage").'<br />';
$notice .= '&nbsp; &nbsp; &#8226; '.$helper->__("Log out").'<br />';
$notice .= '&nbsp; &nbsp; &#8226; '.$helper->__("Log in").'<br />';
$notice .= '&nbsp; &nbsp; &#8226; '.$helper->__("Configure via:") . '&nbsp;' .
           htmlentities( implode( ' > ', $configSteps ) ).'<br />';

$title = $helper->__( 'The extension JeroenVermeulen_Solarium has been installed - Setup Instructions' );

$inboxRecord = array(
    'severity'    => Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE,
    'title'       => $title,
    'description' => $notice,
    'internal'    => true
);
// Not using "Mage::getModel('adminnotification/inbox')->add()" because it does not work in Magento 1.6
Mage::getModel( 'adminnotification/inbox' )->parse( array( $inboxRecord ) );