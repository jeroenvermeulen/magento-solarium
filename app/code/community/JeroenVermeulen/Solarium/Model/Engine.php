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
    /** @var Exception|string */
    protected $_lastError = '';
    /** @var int - in milliseconds */
    protected $_lastQueryTime = 0;

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Check if Solr search is enabled.
     * If no storeId specified we consider it enabled when it is for one store view, because then we need to
     * build the index.
     * @param int $storeId - Store View Id
     * @return bool
     */
    public static function isEnabled( $storeId=null ) {
        $result = false;
        if ( empty($storeId) ) {
            $stores = Mage::app()->getStores( true );
            foreach ( $stores as $store ) {
                $result = (boolean) self::getConf( 'general/enabled', $store->getId() );
                if ( $result ) {
                    break;
                }
            }
        } else {
            $result = (boolean) self::getConf( 'general/enabled', $storeId );
        }
        return $result;
    }

    /**
     * @param string $setting - Path inside the "jeroenvermeulen_solarium" config section
     * @param int    $storeId - Store View Id
     * @return mixed
     */
    public static function getConf( $setting, $storeId=null ) {
        return Mage::getStoreConfig( 'jeroenvermeulen_solarium/'.$setting, $storeId );
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Constructor
     */
    public function __construct() {
        $helper = Mage::helper('jeroenvermeulen_solarium');
        if ( self::isEnabled() ) {
            $host = trim( self::getConf('server/host') );
            $host = str_replace( array('http://','/'), array('',''), $host );
            $config = array(
                'endpoint' => array(
                    'default' => array(
                        'host'    => $host,
                        'port'    => intval( self::getConf('server/port') ),
                        'path'    => trim( self::getConf('server/path') ),
                        'core'    => trim( self::getConf('server/core') ),
                        'timeout' => intval( self::getConf('server/timeout') )
                    )
                )
            );
            $this->_client = new Solarium\Client($config);
            $this->_working = $this->ping();
        } else {
            // This should not happen, you should not construct this class when it is disabled.
            $this->_lastError = new Exception( $helper->__('Solarium Search is not enabled via System Configuration.') );
        }
    }

    /**
     * @return bool - True if engine is working
     */
    public function isWorking() {
        return (boolean) $this->_working;
    }

    /**
     * @return string - Last occurred error
     */
    public function getLastError() {
        $result = '';
        if ( is_a( $this->_lastError, 'Solarium\\Exception\\HttpException' ) ) {
            if ( $this->_lastError->getBody() ) {
                $data = json_decode( $this->_lastError->getBody(), true );
                if ( !empty($data['error']['msg']) ) {
                    $result = $data['error']['msg'];
                }
            }
        }
        if ( empty($result) && is_a( $this->_lastError, 'Exception' ) ) {
            $result = $this->_lastError->getMessage();
        }
        if ( empty($result) && is_string( $this->_lastError ) ) {
            $result = $this->_lastError;
        }
        if ( !empty($result) && preg_match( '#<pre>(.+)</pre>#', $result, $matches ) ) {
            $result = trim( $matches[1] );
        }
        return strval( $result );
    }

    /**
     * @return float - in in milliseconds
     */
    public function getLastQueryTime() {
        return intval( $this->_lastQueryTime ) / 1000;
    }

    /**
     * Return an array with version info, to show in backend
     * @return array
     */
    public function getVersionInfo() {
        $helper = Mage::helper('jeroenvermeulen_solarium');
        $versions = array();
        $versions[ 'Extension version' ] = $helper->getExtensionVersion();
        $versions[ 'Solarium library version' ] = Solarium\Client::VERSION;
        return $versions;
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
            $query = $this->_client->createPing();
            // Default field, needed when it is not specified in solrconfig.xml
            $query->addParam( 'qt', '/select' ); // Needed for Solr < 3.6
            $query->addParam( 'df', 'text' );
            $query->setTimeAllowed( intval( self::getConf('server/search_timeout') ) ); // Not 100% sure if this works.
            $queryResult = $this->_client->ping( $query );
            $resultData = $queryResult->getData();
            if ( !empty($resultData['status']) && 'OK' === $resultData['status'] ) {
                $result = true;
            }
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * @param int            $storeId    - Store View Id
     * @param null|array|int $productIds - Product Entity Id(s)
     * @return bool                      - True on success
     */
    public function cleanIndex( $storeId, $productIds = null )
    {
        if ( !$this->_working ) {
            return false;
        }
        $result = false;
        try {
            $queryText = array();
            if ( !empty($storeId) ) {
                $queryText[] .= 'store_id:' . $storeId;
            }
            if ( is_numeric($productIds) ) {
                $queryText[] .= 'product_id:' . $productIds;
            }
            if ( is_array($productIds) ) {
                $or = array();
                foreach ( $productIds as $id ) {
                    $or[] = 'product_id:' . $id;
                }
                $queryText[] .= '('.implode( ' OR ', $or ).')';
            }

            if ( empty($queryText) ) {
                $queryText[] = '*:*'; // Delete all
            }

            $query = $this->_client->createUpdate();
            $query->addParam( 'qt', '/update' ); // Needed for Solr < 3.6
            $query->addDeleteQuery( implode( ' ', $queryText ) );
            $query->addCommit();

            $result = $this->_update( $query, 'clean' );
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * @param int $storeId     - Store View Id
     * @param null $productIds - $productIds - Product Entity Id(s)
     * @return bool            - True on success
     */
    public function rebuildIndex( $storeId, $productIds ) {
        if ( !$this->_working ) {
            return false;
        }
        $result = false;
        try {
            $coreResource = Mage::getSingleton('core/resource');
            $readAdapter = $coreResource->getConnection('core_read');

            $select = $readAdapter->select();
            $select->from( $coreResource->getTableName('catalogsearch/fulltext')
                         , array('product_id','store_id','data_index','fulltext_id') );

            if ( !empty($storeId) ) {
                $select->where( 'store_id', $storeId );
            }
            if ( !empty($productIds) ) {
                if( is_numeric($productIds) ) {
                    $select->where( 'product_id = ?', $productIds );
                }
                else if(is_array($productIds)) {
                    $select->where( 'product_id IN (?)', $productIds );
                }
            }
            $products = $readAdapter->query( $select );

            $query = $this->_client->createUpdate();
            $query->addParam( 'qt', '/update' ); // Needed for Solr < 3.6

            $documentSet = array();
            while( $product = $products->fetch() ) {
                if ( 100 == count($documentSet) ) {
                    $query->addDocuments( $documentSet );
                    $documentSet = array();
                }
                $document = $query->createDocument();
                $document->id         = $product['fulltext_id'];
                $document->product_id = $product['product_id'];
                $document->store_id   = $product['store_id'];
                $document->text       = $this->_filterString( $product['data_index'] );
                $documentSet[] = $document;
            }
            $query->addDocuments( $documentSet );

            $query->addCommit();
            $query->addOptimize();

            $result = $this->_update( $query, 'rebuild' );
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * @param int    $storeId     - Store View Id
     * @param string $queryString - Text to search for
     * @return array
     */
    public function query( $storeId, $queryString, $try=1 ) {
        if ( !$this->_working ) {
            return false;
        }
        $result = false;
        try {
            $query = $this->_client->createSelect();
            // Default field, needed when it is not specified in solrconfig.xml
            $query->addParam( 'df', 'text' );
            $query->setQuery( $this->_filterString($queryString) );
            $query->setRows( $this::getConf('results/max') );
            $query->setFields( array('product_id','score') );
            if ( is_numeric( $storeId ) ) {
                $query->createFilterQuery( 'store_id' )->setQuery( 'store_id:' . intval($storeId) );
            }
            $query->addSort( 'score', $query::SORT_DESC );
            $doAutoCorrect = ( 1 == $try && self::getConf('results/autocorrect') );
            if ( $doAutoCorrect ) {
                $spellCheck = $query->getSpellcheck();
                $spellCheck->setQuery( $queryString );
            }
            $query->setTimeAllowed( intval( self::getConf('server/search_timeout') ) );
            $solrResultSet = $this->_client->select( $query );
            $this->_lastQueryTime = $solrResultSet->getQueryTime();
            $result = array();
            foreach( $solrResultSet as $solrResult ) {
                $result[] = array( 'relevance' => $solrResult['score']
                                 , 'product_id' => $solrResult['product_id'] );
            }
    
            $correctedQueryString = false;
            if ( $doAutoCorrect ) {
                $spellCheckResult = $solrResultSet->getSpellcheck();
                if ( $spellCheckResult && ! $spellCheckResult->getCorrectlySpelled() ) {
                    $collation = $spellCheckResult->getCollation();
                    if ( $collation ) {
                        $correctedQueryString = $collation->getQuery();
                    }
                    if ( empty($correctedQueryString) ) {
                        $suggestions = $spellCheckResult->getSuggestions();
                        if ( !empty($suggestions) ) {
                            $words = array();
                            /** @var Solarium\QueryType\Select\Result\Spellcheck\Suggestion $suggestion */
                            foreach ( $suggestions as $suggestion ) {
                                $words[] = $suggestion->getWord();
                            }
                            $correctedQueryString = implode( ' ', $words );
                        }
                    }
                    if ( ! empty($correctedQueryString) ) {
                        // Add results from auto correct
                        $result = array_merge( $result, $this->query( $storeId, $correctedQueryString, $try+1 ) );
                    }
                }
            }
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( __CLASS__.'->'.__FUNCTION__.': '.$e->getMessage(), Zend_Log::ERR );
        }
        return $result;
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
        $helper = Mage::helper('jeroenvermeulen_solarium');
        $updateResult = $this->_client->update($updateQuery);
        $this->_lastQueryTime = $updateResult->getQueryTime();
        if ( 0 !==  $updateResult->getStatus() ) {
            $this->_lastError = $updateResult->getStatus();
            $adminSession = Mage::getSingleton('adminhtml/session');
            $adminSession->addError( $helper->__( 'Solr %s error, status: %d, query time: %d'
                                                , $actionText
                                                , $updateResult->getStatus()
                                                , $updateResult->getQueryTime() ) );
        }
        return ( 0 === $updateResult->getStatus() );
    }

    protected function _filterString( $str ) {
        $badChars = '';
        for ( $ord = 0; $ord < 32; $ord++ ) {
            $badChars .= chr($ord);
        }
        return preg_replace( '/['.preg_quote($badChars,'/').']+/', ' ' , $str );
    }

}