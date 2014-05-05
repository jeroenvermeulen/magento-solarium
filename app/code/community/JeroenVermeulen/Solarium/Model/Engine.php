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
class JeroenVermeulen_Solarium_Model_Engine
{
    /** @var \Solarium\Client */
    protected $_client;
    /** @var bool */
    protected $_working = false;
    /** @var Exception|Solarium\Exception\HttpException|string */
    protected $_lastError = '';
    /** @var int - in milliseconds */
    protected $_lastQueryTime = 0;
    /** @var null|int[] $_enabledStores - Used for caching */
    protected $_enabledStoreIds = null;

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Check if Solr search is enabled.
     * If no storeId specified we consider it enabled when it is for one store view, because then we need to
     * build the index.
     *
     * @param int $storeId - Store View Id
     * @return bool
     */
    public static function isEnabled( $storeId = null ) {
        $result = false;
        if ( empty( $storeId ) ) {
            // Can't use $this->getEnabledStoreIds() here because this is a static method.
            $stores = Mage::app()->getStores( false );
            /** @var Mage_Core_Model_Store $store */
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
     * Get an array of store Id's where Solarium Search is enabled for
     *
     * @return int[]
     */
    public function getEnabledStoreIds() {
        if ( is_null( $this->_enabledStoreIds ) ) {
            $this->_enabledStoreIds = array();
            $stores                 = Mage::app()->getStores( false );
            /** @var Mage_Core_Model_Store $store */
            foreach ( $stores as $store ) {
                if ( self::getConf( 'general/enabled', $store->getId() ) ) {
                    array_push( $this->_enabledStoreIds, $store->getId() );
                }
            }
        }
        return $this->_enabledStoreIds;
    }

    /**
     * Shorthand to get a Magento Configuration setting of this extension.
     *
     * @param string $setting - Path inside the "jeroenvermeulen_solarium" config section
     * @param int $storeId - Store View Id
     * @return mixed
     */
    public static function getConf( $setting, $storeId = null ) {
        return Mage::getStoreConfig( 'jeroenvermeulen_solarium/' . $setting, $storeId );
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Constructor, initialize Solarium client and check if it is working.
     *
     * The 'admin' core is a workaround used to execute 'non core specific' admin queries.
     * @see https://github.com/basdenooijer/solarium/issues/254
     */
    public function __construct() {
        // Make sure the autoloader is registered, because in Magento < 1.8 some events are missing.
        Mage::helper( 'jeroenvermeulen_solarium/autoloader' )->register();
        $helper = Mage::helper( 'jeroenvermeulen_solarium' );
        if ( self::isEnabled() ) {
            $this->_client  = new Solarium\Client( $this->_getSolariumClientConfig() );
            $this->_working = $this->ping();
        } else {
            // This should not happen, you should not construct this class when it is disabled.
            $this->_lastError = new Exception( $helper->__( 'Solarium Search is not enabled via System Configuration.' ) );
        }
    }

    /**
     * @param $queryString
     * @return null|string
     */
    public function getAutoSuggestions($queryString){
        //create basic query with wildcard
        $query = $this->_client->createSelect();
        $query->setFields('text');
        $query->setQuery($queryString . '*');
        $query->setRows(0);

        $groupComponent = $query->getGrouping();
        $groupComponent->addField('product_id');
        $groupComponent->setFacet(true);
        $groupComponent->setLimit(1);

        //add facet for completion
        $facetSet = $query->getFacetSet();
        $facet = $facetSet->createFacetField('text')->setField('text')->setMincount(1)->setPrefix($queryString);
        $solariumResult = $this->_client->select($query);
        return $solariumResult->getFacetSet()->getFacet('text');
    }

    /**
     * @param \Solarium\Client $client
     */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * @return \Solarium\Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * @param boolean $working
     */
    public function setWorking($working)
    {
        $this->_working = $working;
    }

    /**
     * @return boolean
     */
    public function getWorking()
    {
        return $this->_working;
    }

    /**
     * This function returns the cached status if Solr is working.
     *
     * @return bool - True if engine is working
     */
    public function isWorking() {
        return (boolean) $this->_working;
    }

    /**
     * Detect if we are running as shell script or via web server
     *
     * @return bool - True = shell script
     */
    public function isShellScript() {
        return ( null === Mage::app()->getRequest()->getControllerName() );
    }

    /**
     * Returns the last occurred error.
     *
     * @return string - Last occurred error
     */
    public function getLastError() {
        $result = '';
        if ( is_a( $this->_lastError, 'Solarium\\Exception\\HttpException' ) ) {
            if ( $this->_lastError->getBody() ) {
                $data = json_decode( $this->_lastError->getBody(), true );
                if ( !empty( $data[ 'error' ][ 'msg' ] ) ) {
                    $result = $data[ 'error' ][ 'msg' ];
                }
            }
        }
        if ( empty( $result ) && is_a( $this->_lastError, 'Exception' ) ) {
            $result = $this->_lastError->getMessage();
        }
        if ( empty( $result ) && is_string( $this->_lastError ) ) {
            $result = $this->_lastError;
        }
        if ( !empty( $result ) && preg_match( '#<pre>(.+)</pre>#', $result, $matches ) ) {
            $result = trim( $matches[ 1 ] );
        }
        return strval( $result );
    }

    /**
     * Returns the time in milliseconds the last Solr query took to execute.
     *
     * @return float - in in milliseconds
     */
    public function getLastQueryTime() {
        return intval( $this->_lastQueryTime ) / 1000;
    }

    /**
     * Return an array with version info, to show in backend.
     *
     * @return array
     */
    public function getVersionInfo() {
        $helper                         = Mage::helper( 'jeroenvermeulen_solarium' );
        $versions                       = array();
        $versions[ 'Operating System' ] = php_uname();
        $versions[ 'PHP' ]              = phpversion();
        $versions[ 'Magento' ]          = Mage::getVersion();
        $versions[ 'Extension' ]        = $helper->getExtensionVersion();
        $versions[ 'Solarium Library' ] = Solarium\Client::VERSION;
        $versions[ 'Solr' ]             = $helper->__( 'unknown' );
        $versions[ 'Java' ]             = $helper->__( 'unknown' );
        if ( $this->isWorking() ) {
            try {
                /**
                 * Abusing ping query to get system info
                 * @see https://github.com/basdenooijer/solarium/issues/254
                 */
                $query = $this->_client->createPing();
                $query->setHandler( 'system' );
                $data = $this->_client->ping( $query, 'admin' )->getData();
                if ( !empty( $data[ 'lucene' ][ 'solr-impl-version' ] ) ) {
                    $versions[ 'Solr' ] = $data[ 'lucene' ][ 'solr-impl-version' ];
                }
                if ( !empty( $data[ 'jvm' ][ 'name' ] ) && !empty( $data[ 'jvm' ][ 'version' ] ) ) {
                    $versions[ 'Java' ] = $data[ 'jvm' ][ 'name' ] . ' ' . $data[ 'jvm' ][ 'version' ];
                }
            } catch ( Exception $e ) {
                Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
            }
        }
        return $versions;
    }

    /**
     * Ping Solr to test if it is working.
     *
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
            $query->setTimeAllowed( intval( self::getConf( 'server/search_timeout' ) ) ); // Not 100% sure if this works.
            $solariumResult = $this->_client->ping( $query );
            $resultData     = $solariumResult->getData();
            if ( !empty( $resultData[ 'status' ] ) && 'OK' === $resultData[ 'status' ] ) {
                $result = true;
            }
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * Clean the Solr index. If a Store ID or Product IDs are specified it is only cleared for those.
     *
     * @param int $storeId - Store View Id
     * @param null|array|int $productIds - Product Entity Id(s)
     * @return bool                      - True on success
     */
    public function cleanIndex( $storeId, $productIds = null ) {
        if ( !$this->_working ) {
            return false;
        }
        $result = false;
        try {
            $queryText = array();

            if ( !empty( $storeId ) ) {
                $queryText[ ] .= 'store_id:' . $storeId;
            }

            if ( is_numeric( $productIds ) ) {
                $queryText[ ] .= 'product_id:' . $productIds;
            }

            if ( is_array( $productIds ) ) {
                $or = array();
                foreach ( $productIds as $id ) {
                    $or[ ] = 'product_id:' . $id;
                }
                $queryText[ ] .= '(' . implode( ' OR ', $or ) . ')';
            }

            if ( empty( $queryText ) ) {
                $queryText[ ] = '*:*'; // Delete all
            }

            $query = $this->_client->createUpdate();
            $query->addDeleteQuery( implode( ' ', $queryText ) );
            $query->addCommit();

            $solariumResult = $this->_client->update( $query, 'update' );
            $result         = $this->_processResult( $solariumResult, 'clean' );
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * Rebuild the index. If a Store ID or Product IDs are specified it is only rebuilt for those.
     *
     * @param int|null $storeId - Store View Id
     * @param int[]|null $productIds - Product Entity Id(s)
     * @return bool                  - True on success
     */
    public function rebuildIndex( $storeId = null, $productIds = null ) {
        if ( !$this->_working ) {
            return false;
        }
        $result = false;
        try {
            $coreResource = Mage::getSingleton( 'core/resource' );
            $readAdapter  = $coreResource->getConnection( 'core_read' );

            $select = $readAdapter->select();
            $select->from( $coreResource->getTableName( 'catalogsearch/fulltext' ), array( 'product_id', 'store_id', 'data_index', 'fulltext_id' ) );

            if ( empty( $storeId ) ) {
                $select->where( 'store_id IN (?)', $this->getEnabledStoreIds() );
            } else {
                $select->where( 'store_id', $storeId );
            }
            if ( !empty( $productIds ) ) {
                if ( is_numeric( $productIds ) ) {
                    $select->where( 'product_id = ?', $productIds );
                } else {
                    if ( is_array( $productIds ) ) {
                        $select->where( 'product_id IN (?)', $productIds );
                    }
                }
            }
            $products = $readAdapter->query( $select );

            if ( !$products->rowCount() ) {
                // No matching products, nothing to update, consider OK.
                $result = true;
            } else {
                /** @var Solarium\Plugin\BufferedAdd\BufferedAdd $buffer */
                $buffer = $this->_client->getPlugin( 'bufferedadd' );
                $buffer->setBufferSize( max( 1, self::getConf( 'reindexing/buffersize', $storeId ) ) );
                $buffer->setEndpoint( 'update' );
                $this->_client->getEventDispatcher()->addListener( Solarium\Plugin\BufferedAdd\Event\Events::PRE_FLUSH, array( $this, 'flushListener' ) );
                /** @noinspection PhpAssignmentInConditionInspection */
                while ( $product = $products->fetch() ) {
                    $data = array( 'id' => intval( $product[ 'fulltext_id' ] ),
                                   'product_id' => intval( $product[ 'product_id' ] ),
                                   'store_id' => intval( $product[ 'store_id' ] ),
                                   'text' => $this->_filterString( $product[ 'data_index' ] ) );
                    $buffer->createDocument( $data );
                }
                $solariumResult = $buffer->flush();
                $this->optimize(); // ignore result
                $result = $this->_processResult( $solariumResult, 'flushing buffered add' );
            }
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * Optimize the Solr index.
     *
     * @return bool - True on success
     */
    public function optimize() {
        if ( !$this->_working ) {
            return false;
        }
        // get an update query instance
        $update = $this->_client->createUpdate();
        $update->addOptimize();
        $solariumResult = $this->_client->update( $update, 'update' );
        return $this->_processResult( $solariumResult, 'optimize' );
    }

    /**
     * Query the Solr server to search for a string.
     *
     * @param int $storeId - Store View Id
     * @param string $queryString - Text to search for
     * @param int $try - Times tried to find result
     * @return array
     */
    public function query( $storeId, $queryString, $try = 1 ) {
        if ( !$this->_working ) {
            return false;
        }
        $result = false;
        try {
            $query = $this->_client->createSelect();
            // Default field, needed when it is not specified in solrconfig.xml
            $query->addParam( 'df', 'text' );
            $query->setQuery( $this->_filterString( $queryString ) );
            $query->setRows( $this::getConf( 'results/max' ) );
            $query->setFields( array( 'product_id', 'score' ) );
            if ( is_numeric( $storeId ) ) {
                $query->createFilterQuery( 'store_id' )->setQuery( 'store_id:' . intval( $storeId ) );
            }
            $query->addSort( 'score', $query::SORT_DESC );
            $doAutoCorrect = ( 1 == $try && self::getConf( 'results/autocorrect' ) );
            if ( $doAutoCorrect ) {
                $spellCheck = $query->getSpellcheck();
                $spellCheck->setQuery( $queryString );
                // You need Solr >= 4.0 for this to improve spell correct results.
                $query->addParam( 'spellcheck.alternativeTermCount', 1 );
            }
            $query->setTimeAllowed( intval( self::getConf( 'server/search_timeout' ) ) );
            $solrResultSet        = $this->_client->select( $query );
            $this->_lastQueryTime = $solrResultSet->getQueryTime();
            $result               = array();
            foreach ( $solrResultSet as $solrResult ) {
                $result[ ] = array( 'relevance' => $solrResult[ 'score' ], 'product_id' => $solrResult[ 'product_id' ] );
            }

            $correctedQueryString = false;
            if ( $doAutoCorrect ) {
                $spellCheckResult = $solrResultSet->getSpellcheck();
                if ( $spellCheckResult && !$spellCheckResult->getCorrectlySpelled() ) {
                    $collation = $spellCheckResult->getCollation();
                    if ( $collation ) {
                        $correctedQueryString = $collation->getQuery();
                    }
                    if ( empty( $correctedQueryString ) ) {
                        $suggestions = $spellCheckResult->getSuggestions();
                        if ( !empty( $suggestions ) ) {
                            $words = array();
                            /** @var Solarium\QueryType\Select\Result\Spellcheck\Suggestion $suggestion */
                            foreach ( $suggestions as $suggestion ) {
                                $words[] = $suggestion->getWord();
                            }
                            $correctedQueryString = implode( ' ', $words );
                        }
                    }
                    if ( !empty( $correctedQueryString ) ) {
                        // Add results from auto correct
                        $result = array_merge( $result, $this->query( $storeId, $correctedQueryString, $try + 1 ) );
                    }
                }
            }
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * Callback function for the BufferedAdd Solarium plugin.
     * Later we can use this function to show progress to the user.
     *
     * @param \Solarium\Plugin\BufferedAdd\Event\PreFlush $event
     */
    public function flushListener( Solarium\Plugin\BufferedAdd\Event\PreFlush $event ) {
        Mage::Log( sprintf( '%s - Flushing buffer, %d docs', __CLASS__, count( $event->getBuffer() ) ), Zend_Log::DEBUG );
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Get configuration for Solarium, based on this extension's settings in the Magento Configuration
     *
     * @return array
     */
    protected function _getSolariumClientConfig() {
        $host           = trim( self::getConf( 'server/host' ) );
        // If the user pasted a URL as hostname, clean it.
        $host           = str_replace( array( 'http://', '/' ), array( '', '' ), $host );
        $endPointConfig = array();
        $endPointConfig['host'] = $host;
        $endPointConfig['port'] = intval( self::getConf( 'server/port' ) );
        $endPointConfig['path'] = trim( self::getConf( 'server/path' ) );
        $endPointConfig['core'] = trim( self::getConf( 'server/core' ) );
        if( self::getConf('server/requires_authentication') ) {
            $endPointConfig['username'] = trim( self::getConf('server/username') );
            $endPointConfig['password'] = Mage::helper('core')->decrypt( self::getConf( 'server/password' ) );
        }
        $config = array();
        $config['endpoint'] = array();
        $config['endpoint']['default'] = $endPointConfig;
        $config['endpoint']['update']  = $endPointConfig;
        $config['endpoint']['admin']   = $endPointConfig;
        $config['endpoint']['default']['timeout'] = intval( self::getConf( 'server/search_timeout' ) );
        $config['endpoint']['update']['timeout']  = intval( self::getConf( 'server/search_timeout' ) );
        $config['endpoint']['admin']['timeout']   = intval( self::getConf( 'server/search_timeout' ) );
        $config['endpoint']['admin']['core']      = 'admin';
        return $config;
    }

    /**
     * Process the result of a Solarium update.
     *
     * @param Solarium\QueryType\Update\Result|bool $solariumResult
     * @param string $actionText
     * @return bool
     */
    protected function _processResult( $solariumResult, $actionText = 'query' ) {
        $result = false;
        $helper = Mage::helper( 'jeroenvermeulen_solarium' );
        if ( is_a( $solariumResult, 'Solarium\QueryType\Update\Result' ) ) {
            $this->_lastQueryTime = $solariumResult->getQueryTime();
            if ( 0 !== $solariumResult->getStatus() ) {
                $this->_lastError = $solariumResult->getStatus();
                Mage::getSingleton( 'adminhtml/session' )->addError( $helper->__( 'Solr %s error, status: %d, query time: %d', $helper->__( $actionText ), $solariumResult->getStatus(), $solariumResult->getQueryTime() ) );
            }
            $result = ( 0 === $solariumResult->getStatus() );
        } else {
            $this->_lastError = $helper->__( 'Solr %s error, incorrect object received.', $actionText );
        }
        return $result;
    }

    /**
     * Filter a string to be safe to add to Solr.
     *
     * @param string $str
     * @return string
     */
    protected function _filterString( $str ) {
        static $badChars; // This variable is saved inside this function.
        if ( !isset($badChars) ) {
            for ( $ord = 0; $ord < 32; $ord++ ) {
                $badChars .= chr( $ord );
            }
        }
        return preg_replace( '/[' . preg_quote( $badChars, '/' ) . ']+/', ' ', $str );
    }

}