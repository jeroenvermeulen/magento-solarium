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


class JeroenVermeulen_Solarium_Model_Engine {

    /** @var \Solarium\Client */
    protected $_client;
    /** @var bool */
    protected $_working = false;
    /** @var string */
    protected $_lastError = '';

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @return bool
     */
    public static function isEnabled() {
        return boolval( self::getConf('general/enabled') );
    }

    /**
     * @param string $setting - Path inside the "jeroenvermeulen_solarium" config section
     * @return mixed
     */
    public static function getConf( $setting ) {
        return Mage::getStoreConfig('jeroenvermeulen_solarium/'.$setting);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Constructor
     */
    public function __construct() {
        if ( self::isEnabled() ) {
            $config = array(
                'endpoint' => array(
                    'default' => array(
                        'host' => self::getConf('server/host'),
                        'port' => self::getConf('server/port'),
                        'path' => self::getConf('server/path'),
                        'core' => self::getConf('server/core'),
                        'timeout' => 5
                    )
                )
            );
            $this->_client = new Solarium\Client($config);
            $this->_working = $this->ping();
        } else {
            // This should not happen, you should not construct this class when it is disabled.
            $this->_lastError = 'Solarium Search is not enabled via System Configuration.';
        }
    }

    /**
     * @return bool - True if engine is working
     */
    public function isWorking() {
        return boolval( $this->_working );
    }

    /**
     * @return string - Last occurred error
     */
    public function getLastError() {
        return strval( $this->_lastError );
    }

    /**
     * @return bool - True on success
     */
    public function ping() {
        /**
         * This function should not check the $this->_working variable,
         * because it is used to check if everything is working.
         */
        $result = false;
        try {
            $queryResult = $this->_client->ping(  $this->_client->createPing() );
            $resultData = $queryResult->getData();
            if ( !empty($resultData['status']) && 'OK' === $resultData['status'] ) {
                $result = true;
            }
        } catch ( Exception $e ) {
            $message = $e->getMessage();
            if ( is_a( $e, 'Solarium\\Exception\\HttpException' ) ) {
                $messageData = json_decode( $e->getBody(), true );
                if ( !empty($messageData['error']['msg']) ) {
                    $message = $messageData['error']['msg'];
                }
            }
            $this->_lastError = $message;
            Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::DEBUG );
        }
        return $result;
    }

    /**
     * @param int $storeId     - Store View Id
     * @param null $productIds - $productIds - Product Entity Id(s)
     * @return bool            - True on success
     */
    public function cleanIndex( $storeId, $productIds = null)  // TODO Use $storeId
    {
        if ( !$this->_working ) {
            return false;
        }
        $solrUpdate = $this->_client->createUpdate();

        if(is_numeric($productIds)) {
            $solrUpdate->addDeleteById( $productIds );
        } else if(is_array($productIds)) {
            $solrUpdate->addDeleteByIds( $productIds );
        } else {
            $solrUpdate->addDeleteQuery('*:*');
        }

        $solrUpdate->addCommit();

        return $this->_update( $solrUpdate, 'clean' );
    }

    /**
     * @param int $storeId     - Store View Id
     * @param null $productIds - $productIds - Product Entity Id(s)
     * @return bool            - True on success
     */
    public function rebuildIndex( $storeId, $productIds ) { // TODO Use $storeId
        if ( !$this->_working ) {
            return false;
        }
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

        $solrUpdate = $this->_client->createUpdate();

        while($product = $products->fetch()) {
            $document = $solrUpdate->createDocument();
            $document->id         = $product['fulltext_id'];
            $document->product_id = $product['product_id'];
            $document->store_id   = $product['store_id'];
            $document->text       = $product['data_index'];
            $solrUpdate->addDocument($document);
        }

        $solrUpdate->addCommit();
        $solrUpdate->addOptimize( true, false, 5 ); // TODO check params

        return $this->_update( $solrUpdate, 'rebuild' );
    }

    /**
     * @param string $queryString - Text to search for
     * @param int    $storeId     - Store View Id
     * @return bool|\Solarium\QueryType\Select\Result\Result
     */
    public function query( $queryString, $storeId=0 ) {
        if ( !$this->_working ) {
            return false;
        }
        $storeId = intval( $storeId );
        $query = $this->_client->createSelect();
        $query->setQuery( $queryString );
        $query->setRows( 100 ); // TODO make configurable
        $query->setFields( array('product_id','score') );
        if ( $storeId ) {
            $query->createFilterQuery('store_id')->setQuery('store_id:'.$storeId);
        }
        $query->addSort( 'score', $query::SORT_DESC );
        $resultset = $this->_client->select( $query );
        return $resultset;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @param \Solarium\QueryType\Update\Query\Query $updateQuery
     * @param string $actionText
     * @return bool
     */
    protected function _update( Solarium\QueryType\Update\Query\Query $updateQuery, $actionText='action' ) {
        if ( !$this->_working ) {
            return false;
        }
        $updateResult = $this->_client->update($updateQuery);
        if ( 0 ==  $updateResult->getStatus() ) {
            Mage::getSingleton('adminhtml/session')->addSuccess( sprintf( 'Solr %s successful, query time: %d'
                                                                     , $actionText
                                                                     , $updateResult->getQueryTime()
                                                                 )
            );
        } else {
            $this->_lastError = $updateResult->getStatus();
            Mage::getSingleton('adminhtml/session')->addError( sprintf( 'Solr %s error, status: %d, query time: %d'
                                                                   , $actionText
                                                                   , $updateResult->getStatus()
                                                                   , $updateResult->getQueryTime()
                                                               )
            );
        }
        return ( 0 === $updateResult->getStatus() );
    }

}