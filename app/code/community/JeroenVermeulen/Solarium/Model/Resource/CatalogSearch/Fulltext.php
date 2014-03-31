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

class JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext {

    /**
     * @param int            $storeId    - Store View Id
     * @param int|array|null $productIds - Product Entity Id(s)
     * @return JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
     */
    public function cleanIndex( $storeId = null, $productIds = null ) {
        parent::cleanIndex($storeId, $productIds);
        if ( JeroenVermeulen_Solarium_Model_Engine::isEnabled() ) {
            Mage::getSingleton('jeroenvermeulen_solarium/engine')->cleanIndex( $storeId, $productIds );
        }
        return $this;
    }

    /**
     * @param  int|null       $storeId    - Store View Id
     * @param  int|array|null $productIds - Product Entity Id(s)
     * @return JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
     */
    public function rebuildIndex( $storeId = null, $productIds = null ) {
        parent::rebuildIndex($storeId,$productIds);
        if ( JeroenVermeulen_Solarium_Model_Engine::isEnabled() ) {
            $engine = Mage::getSingleton('jeroenvermeulen_solarium/engine');
            $ok = $engine->rebuildIndex( $storeId, $productIds );
            if ( !$ok ) {
                Mage::getSingleton('adminhtml/session')->addError( sprintf('Error reindexing Solr: %s', $engine->getLastError()) );
            }
        }
        return $this;
    }

    /**
     * @param Mage_CatalogSearch_Model_Fulltext $object
     * @param string                            $queryText
     * @param Mage_CatalogSearch_Model_Query    $query
     * @return JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
     */
    public function prepareResult($object, $queryText, $query)
    {
        if (!$query->getIsProcessed()) {
            if ( JeroenVermeulen_Solarium_Model_Engine::isEnabled() ) {
                try {
                    $adapter           = $this->_getWriteAdapter();
                    $searchResultTable = $this->getTable('catalogsearch/result');
                    $searchResultSet = Mage::getSingleton('jeroenvermeulen_solarium/engine')->query( $queryText
                                                                                                   , $query->getStoreId() );
                    if( $searchResultSet->getNumFound() ) {
                        /** @var Solarium\QueryType\Select\Result\Document $document */
                        foreach ($searchResultSet as $document) {
                            $documentFields = $document->getFields();
                            $data = array( 'query_id'   => $query->getId(),
                                           'product_id' => $documentFields['product_id'],
                                           'relevance'  => $documentFields['score'] );
                            $adapter->insertOnDuplicate( $searchResultTable, $data, array('relevance') );
                        }
                    }
                    $query->setIsProcessed(1);
                } catch (Exception $e) {
                    Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::WARN );
                }
            }
            if ( !$query->getIsProcessed() ) {
                // Something went wrong
                return parent::prepareResult($object, $queryText, $query);
            }
        }
        return $this;
    }

}