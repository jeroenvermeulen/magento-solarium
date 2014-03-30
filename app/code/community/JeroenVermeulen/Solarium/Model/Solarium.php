<?php

class JeroenVermeulen_Solarium_Model_Solarium {

    /** @var \Solarium\Client */
    protected $client;

    public function __construct() {
        $config = array(
            'endpoint' => array(
                'localhost' => array(
                    'host' => '54.186.239.231',  // TODO
                    'port' => 8983,              // TODO
                    'path' => '/solr/',          // TODO
                )
            )
        );
        $this->client = new Solarium\Client($config);
    }

    public function ping() {
        $result = false;
        try {
            $queryResult = $this->client->ping(  $this->client->createPing() );
            $resultData = $queryResult->getData();
            if ( !empty($resultData['status']) && 'OK' === $resultData['status'] ) {
                $result = true;
            }
        } catch ( Exception $e ) {
            Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::DEBUG );
        }
        return $result;
    }

    public function update( Solarium\QueryType\Update\Query\Query $updateQuery, $actionText='action' ) {
        $updateResult = $this->client->update($updateQuery);
        if ( 0 ==  $updateResult->getStatus() ) {
            Mage::getSingleton('adminhtml/session')->addSuccess( sprintf( 'Solr %s successful, query time: %d'
                                                                        , $actionText
                                                                        , $updateResult->getQueryTime()
                                                                        )
                                                               );
        } else {
            Mage::getSingleton('adminhtml/session')->addError( sprintf( 'Solr %s error, status: %d, query time: %d'
                                                                      , $actionText
                                                                      , $updateResult->getStatus()
                                                                      , $updateResult->getQueryTime()
                                                                      )
                                                             );
        }
        return ( 0 === $updateResult->getStatus() );
    }

    public function rebuildIndex( $storeId, $productIds ) { // TODO Use $storeId

        $coreResource = Mage::getSingleton('core/resource');
        $readAdapter = $coreResource->getConnection('core_read');

        $query = 'SELECT * FROM '.$coreResource->getTableName('catalogsearch/fulltext');
        if($productIds) {
            if( is_numeric($productIds) ) {
                $query .= ' WHERE product_id = '.$productIds;
            }
            else if(is_array($productIds)) {
                $query .= ' WHERE product_id IN('.implode(',',$productIds).')';
            }
        }
        $products = $readAdapter->query( $query );

        $solrUpdate = $this->client->createUpdate();

        while($product = $products->fetch()) {
            $document = $solrUpdate->createDocument();
            $document->id         = $product['fulltext_id'];
            $document->product_id = $product['product_id'];
            $document->store_id   = $product['store_id'];
            $document->fulltext   = $product['data_index'];
            $solrUpdate->addDocument($document);
        }

        $solrUpdate->addCommit();
        $solrUpdate->addOptimize( true, false, 5 ); // TODO check params
        $this->update( $solrUpdate, 'rebuild' ); // TODO: error if fails?

        return true;
    }

    public function cleanIndex( $storeId, $productIds = null)  // TODO Use $storeId
    {
        $solrUpdate = $this->client->createUpdate();

        if(is_numeric($productIds)) {
            $solrUpdate->addDeleteById( $productIds );
        } else if(is_array($productIds)) {
            $solrUpdate->addDeleteByIds( $productIds );
        } else {
            $solrUpdate->addDeleteQuery('*:*');
        }

        $solrUpdate->addCommit();
        $this->update( $solrUpdate, 'clean' ); // TODO: error if fails?
        return true;
    }

    public function query( $queryString, $storeId=0 ) {
        $query = $this->client->createSelect();
        $query->setQuery( $queryString );
        $query->setRows( 100 ); // TODO make configurable
        $query->setFields( array('product_id','score') ); // ,'store_id','fulltext'
        if ( $storeId ) {
            $query->createFilterQuery('store_id')->setQuery('store_id:'.$storeId);
        }
        $query->addSort( 'score', $query::SORT_DESC );
        $resultset = $this->client->select( $query );
        return $resultset;
    }

}