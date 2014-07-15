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
 * Class JeroenVermeulen_Solarium_Helper_Data
 *
 * Usage:   $helper = Mage::helper( 'jeroenvermeulen_solarium' );
 */
class JeroenVermeulen_Solarium_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_searchIndexerProcess = false;

    /**
     * @return string
     */
    public
    function getExtensionVersion()
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return strval( Mage::getConfig()->getNode()->modules->JeroenVermeulen_Solarium->version );
    }

    /**
     * @return false|Mage_Index_Model_Process
     */
    public
    function getSearchIndexer()
    {
        if (empty( $this->_searchIndexerProcess )) {
            /** @var Mage_Index_Model_Indexer $indexer */
            if (version_compare( Mage::getVersion(), '1.8.0.0', '<' )) {
                $indexer = Mage::getSingleton( 'index/indexer' );
            } else {
                $factory = Mage::getSingleton( 'core/factory' );
                $indexer = $factory->getSingleton( $factory->getIndexClassAlias() );
            }
            $this->_searchIndexerProcess = $indexer->getProcessByCode( 'catalogsearch_fulltext' );
        }
        return $this->_searchIndexerProcess;
    }
}