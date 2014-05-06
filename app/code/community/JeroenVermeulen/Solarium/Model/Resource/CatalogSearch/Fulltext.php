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

class JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{

    /**
     * @param int $storeId - Store View Id
     * @param int|array|null $productIds - Product Entity Id(s)
     * @return JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
     */
    public function cleanIndex( $storeId = null, $productIds = null ) {
        parent::cleanIndex( $storeId, $productIds );
        /**
         * If it is enabled for one store, clean for the current store.
         * This is needed to clean up when you switch Solarium Search from enable to disable for a store.
         */
        if ( JeroenVermeulen_Solarium_Model_Engine::isEnabled() ) {
            Mage::getSingleton( 'jeroenvermeulen_solarium/engine' )->cleanIndex( $storeId, $productIds );
        }
        return $this;
    }

    /**
     * @param  int|null $storeId - Store View Id
     * @param  int|array|null $productIds - Product Entity Id(s)
     * @return JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
     */
    public function rebuildIndex( $storeId = null, $productIds = null ) {
        parent::rebuildIndex( $storeId, $productIds );
        if ( JeroenVermeulen_Solarium_Model_Engine::isEnabled( $storeId ) ) {
            $helper       = Mage::helper( 'jeroenvermeulen_solarium' );
            $engine       = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            $startTime    = microtime( true );
            $ok           = $engine->rebuildIndex( $storeId, $productIds );
            $timeUsed     = microtime( true ) - $startTime;
            // When product IDs are supplied, it is an automatic update, and we should not show messages.
            if ( null == $productIds ) {
                if ( $ok ) {
                    $message = $helper->__( 'Solr Index was rebuilt in %s seconds.', sprintf( '%.02f', $timeUsed ) );
                    if ( $engine->isShellScript() ) {
                        echo $message . "\n";
                    } else {
                        Mage::getSingleton( 'adminhtml/session' )->addSuccess( $message );
                    }
                } else {
                    $message = $helper->__( 'Error reindexing Solr: %s', $engine->getLastError() );
                    if ( $engine->isShellScript() ) {
                        echo $message . "\n";
                    } else {
                        Mage::getSingleton( 'adminhtml/session' )->addError( $message );
                    }
                }
            }
        }
        return $this;
    }

    /**
     * @param Mage_CatalogSearch_Model_Fulltext $object
     * @param string $queryText
     * @param Mage_CatalogSearch_Model_Query $query
     * @return JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
     */
    public function prepareResult( $object, $queryText, $query ) {
        if ( !$query->getIsProcessed() ) {
            if ( JeroenVermeulen_Solarium_Model_Engine::isEnabled( $query->getStoreId() ) ) {
                $adapter           = $this->_getWriteAdapter();
                $searchResultTable = $this->getTable( 'catalogsearch/result' );
                $engine            = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
                if ( $engine->isWorking() ) {
                    $searchResult = $engine->query( $query->getStoreId(), $queryText );
                    if ( false !== $searchResult ) {
                        if ( 0 == count($searchResult) ) {
                            // No results, we need to check if the index is empty.
                            if ( ! $engine->isEmpty( $query->getStoreId() ) ) {
                                $query->setIsProcessed( 1 );
                            } else {
                                Mage::Log( sprintf('%s - Warning: index is empty', __CLASS__), Zend_Log::WARN );
                            }
                        } else {
                            foreach ( $searchResult as $data ) {
                                $data[ 'query_id' ] = $query->getId();
                                $adapter->insertOnDuplicate( $searchResultTable, $data, array( 'relevance' ) );
                            }
                            $query->setIsProcessed( 1 );
                        }
                    }
                }
            }
            if ( !$query->getIsProcessed() ) {
                Mage::log( 'Solr disabled or something went wrong, fallback to Magento Fulltext Search', Zend_Log::WARN );
                return parent::prepareResult( $object, $queryText, $query );
            }
        }
        return $this;
    }

}