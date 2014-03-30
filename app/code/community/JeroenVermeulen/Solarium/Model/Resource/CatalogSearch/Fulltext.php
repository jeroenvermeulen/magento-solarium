<?php
/**
 * Created by PhpStorm.
 * User: jeroen
 * Date: 30/03/14
 * Time: 23:06
 */ 
class JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext {

    /**
     * Overloaded method cleanIndex.
     * Delete search index data for store
     *
     * @param int $storeId Store View Id
     * @param int|array|null $productIds Product Entity Id
     * @return Mage_CatalogSearch_Model_Resource_Fulltext
     */
    public function cleanIndex($storeId = null, $productIds = null)
    {
        parent::cleanIndex($storeId, $productIds);

//        if(Mage::getStoreConfigFlag('solr/active/admin')) { // TODO: Enable
            Mage::getModel('jeroenvermeulen_solarium/solarium')->cleanIndex( $storeId, $productIds );
//        }

        return $this;
    }

    /**
     * Overloaded method rebuildIndex.
     * Regenerate search index for store(s)
     *
     * @param  int|null $storeId
     * @param  int|array|null $productIds
     * @return Magentix_Solr_Model_CatalogSearch_Resource_Fulltext
     */
    public function rebuildIndex($storeId = null, $productIds = null)
    {
        parent::rebuildIndex($storeId,$productIds);

        //if(Mage::getStoreConfigFlag('solr/active/admin')) {  // TODO ENABLE
            Mage::getSingleton('jeroenvermeulen_solarium/solarium')->rebuildIndex( $storeId, $productIds );
        //}

        return $this;
    }

    /**
     * Overloaded method prepareResult.
     * Prepare results for query.
     * Replaces the traditional fulltext search with a Solr Search (if active).
     *
     * @param Mage_CatalogSearch_Model_Fulltext $object
     * @param string $queryText
     * @param Mage_CatalogSearch_Model_Query $query
     * @return Magentix_Solr_Model_CatalogSearch_Resource_Fulltext
     */
    public function prepareResult($object, $queryText, $query, $try=1)
    {
        // TODO if(!Mage::getStoreConfigFlag('solr/active/frontend')) {
        //    return parent::prepareResult($object, $queryText, $query);
        //}

        $adapter = $this->_getWriteAdapter();
        if (!$query->getIsProcessed()) {

            try {
                $solarium = Mage::getSingleton('jeroenvermeulen_solarium/solarium');
                $searchResultSet = $solarium->query( $queryText, (int)$query->getStoreId() );
                if( $searchResultSet->getNumFound() ) {
                    $data = array();
                    /** @var Solarium\QueryType\Select\Result\Document $document */
                    foreach ($searchResultSet as $document) {
                        $documentFields = $document->getFields();
                        $data[] = array('query_id'   => $query->getId(),
                                        'product_id' => $documentFields['product_id'],
                                        'relevance'  => $documentFields['score']);
                    }
                    $adapter->insertMultiple($this->getTable('catalogsearch/result'),$data);
                }
                $query->setIsProcessed(1);

            } catch (Exception $e) {
                Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::WARN );
                return parent::prepareResult($object, $queryText, $query);
            }

        }

        return $this;
    }

}