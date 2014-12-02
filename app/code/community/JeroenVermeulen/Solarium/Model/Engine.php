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
 * Class JeroenVermeulen_Solarium_Model_Engine
 */
class JeroenVermeulen_Solarium_Model_Engine
{
    const SEARCH_TYPE_USE_CONFIG        = 0;
    const SEARCH_TYPE_LITERAL           = 1;
    const SEARCH_TYPE_STRING_COMPLETION = 2;
    const ENDPOINT_FUNCTION_UPDATE      = 'update';
    const ENDPOINT_FUNCTION_QUERY       = 'query';
    const ENDPOINT_FUNCTION_ADMIN       = 'admin';
    const TEST_STOREID                  = 999999;

    /** @var \Solarium\Client[] */
    protected $_clients;
    /** @var bool[] */
    protected $_working = array();
    /** @var Exception|Solarium\Exception\HttpException|string */
    protected $_lastError = '';
    /** @var int - in milliseconds */
    protected $_lastQueryTime = 0;
    /** @var null|int[] $_enabledStores - Used for caching */
    protected $_enabledStoreIds = null;
    /** @var array - Override Magento Config */
    protected $_overrideConfig = array();

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Check if Solr search is enabled.
     * If no storeId specified we consider it enabled when it is for one store view, because then we need to
     * build the index.
     *
     * @param int $storeId - Store View Id
     * @return bool
     */
    public static
    function isEnabled(
        $storeId = null
    ) {
        $result = false;
        if (empty( $storeId )) {
            // Can't use $this->getEnabledStoreIds() or $this->getConf() here because this is a static method.
            $stores = Mage::app()->getStores( false );
            /** @var Mage_Core_Model_Store $store */
            foreach ($stores as $store) {
                $result = Mage::getStoreConfigFlag( 'jeroenvermeulen_solarium/general/enabled', $store->getId() );
                if ($result) {
                    break;
                }
            }
        } else {
            $result = Mage::getStoreConfigFlag( 'jeroenvermeulen_solarium/general/enabled', $storeId );
        }
        return $result;
    }

    /**
     * Get an array of store Id's where Solarium Search is enabled for
     *
     * @return int[]
     */
    public
    function getEnabledStoreIds()
    {
        if (is_null( $this->_enabledStoreIds )) {
            $this->_enabledStoreIds = array();
            $stores                 = Mage::app()->getStores( false );
            /** @var Mage_Core_Model_Store $store */
            foreach ($stores as $store) {
                if ($this->getConf( 'general/enabled', $store->getId() )) {
                    array_push( $this->_enabledStoreIds, $store->getId() );
                }
            }
            if ( $this->getConf('general/enabled', self::TEST_STOREID) ) {
                array_push( $this->_enabledStoreIds, self::TEST_STOREID );
            }
        }
        return $this->_enabledStoreIds;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Constructor, initialize Solarium client and check if it is working.
     *
     * @param array $overrideConfig
     */
    public
    function __construct(
        $overrideConfig = array()
    ) {
        // Make sure the autoloader is registered, because in Magento < 1.8 some events are missing.
        /** @var JeroenVermeulen_Solarium_Helper_Autoloader $autoLoader */
        $autoLoader = Mage::helper( 'jeroenvermeulen_solarium/autoloader' );
        $autoLoader->register();
        if (!empty( $overrideConfig )) {
            $this->_overrideConfig = $overrideConfig;
        }
        /** @var JeroenVermeulen_Solarium_Helper_Data $helper */
        $helper          = Mage::helper( 'jeroenvermeulen_solarium' );
        $enabledStoreIds = $this->getEnabledStoreIds();
        if (!empty( $enabledStoreIds )) {
            $this->_client = new Solarium\Client( $this->_getSolariumClientConfig() );
            $this->setWorking( Mage_Core_Model_App::ADMIN_STORE_ID, $this->ping(Mage_Core_Model_App::ADMIN_STORE_ID) );
            foreach ( $enabledStoreIds as $storeId ) {
                $this->setWorking( $storeId, $this->ping($storeId) );
            }
        } else {
            // This should not happen, you should not construct this class when it is disabled.
            $this->_lastError =
                new Exception( $helper->__( 'Solarium Search is not enabled via System Configuration.' ) );
        }
    }

    /**
     * Get a the key used to tell the Solarium library which endpoint to use.
     *
     * @param int|Mage_Core_Model_Store $store
     * @param string $endpointFunction
     * @return string
     * @throws Exception
     */
    public
    function getEndpointKey( $store, $endpointFunction ) {
        if ( $store instanceof Mage_Core_Model_Store ) {
            $store = $store->getId();
        }
        if ( !is_numeric($store) ) {
            throw new Exception( sprintf("getEndpointKey: invalid store received: '%s'", $store) );
        }
        $storeEndpointFunctions = array(self::ENDPOINT_FUNCTION_QUERY, self::ENDPOINT_FUNCTION_UPDATE);
        if ( Mage_Core_Model_App::ADMIN_STORE_ID == $store || self::ENDPOINT_FUNCTION_ADMIN == $endpointFunction ) {
            return 'admin';
        } else {
            if ( in_array($endpointFunction, $storeEndpointFunctions) ) {
                return sprintf( "%d_%s", $store, $endpointFunction );
            } else {
                throw new Exception( sprintf("getEndpointKey: invalid endpoint function received: '%s'", $endpointFunction) );
            }
        }
    }

    /**
     * Get a Magento Configuration setting of this extension.
     * Possibly this setting can be overridden by supplying a configuration array to this class constructor.
     *
     * @param string $setting - Path inside the "jeroenvermeulen_solarium" config section
     * @param int $storeId - Store View Id
     * @return mixed
     */
    public
    function getConf(
        $setting,
        $storeId
    ) {
        $storeId = intval($storeId);
        if (isset( $this->_overrideConfig[ $storeId ][ $setting ] )) {
            return $this->_overrideConfig[ $storeId ][ $setting ];
        } elseif( self::TEST_STOREID == $storeId ) {
            return null;
        } else {
            return Mage::getStoreConfig( 'jeroenvermeulen_solarium/' . $setting, $storeId );
        }
    }

    /**
     * @param \Solarium\Client $client
     */
    public
    function setClient(
        $client
    ) {
        $this->_client = $client;
    }

    /**
     * @return \Solarium\Client
     */
    public
    function getClient()
    {
        return $this->_client;
    }

    /**
     * @param int $storeId
     * @param boolean $working
     */
    public
    function setWorking(
        $storeId, $working
    ) {
        $this->_working[$storeId] = $working;
    }

    /**
     * This function returns the cached status if Solr is working for a specific store id.
     *
     * @param int $storeId
     * @return bool - True if engine is working
     */
    public
    function isWorking( $storeId )
    {
        return !empty( $this->_working[$storeId] );
    }

    /**
     * Detect if we are running as shell script or via web server
     *
     * @return bool - True = shell script
     */
    public
    function isShellScript()
    {
        return ( null === Mage::app()->getRequest()->getControllerName() );
    }

    /**
     * Returns the last occurred error.
     *
     * @return string - Last occurred error
     */
    public
    function getLastError()
    {
        $result = '';
        if (is_a( $this->_lastError, 'Solarium\\Exception\\HttpException' )) {
            if ($this->_lastError->getBody()) {
                $data = json_decode( $this->_lastError->getBody(), true );
                if (!empty( $data[ 'error' ][ 'msg' ] )) {
                    $result = $data[ 'error' ][ 'msg' ];
                }
            }
        }
        if (empty( $result ) && is_a( $this->_lastError, 'Exception' )) {
            $result = $this->_lastError->getMessage();
        }
        if (empty( $result ) && is_string( $this->_lastError )) {
            $result = $this->_lastError;
        }
        if (!empty( $result ) && preg_match( '#<pre>(.+)</pre>#', $result, $matches )) {
            $result = trim( $matches[ 1 ] );
        }
        return strval( $result );
    }

    /**
     * Returns the time in milliseconds the last Solr query took to execute.
     *
     * @return float - in in milliseconds
     */
    public
    function getLastQueryTime()
    {
        return intval( $this->_lastQueryTime ) / 1000;
    }

    /**
     * Return an array with version info, to show in backend.
     *
     * @param int $storeId
     * @param bool $extended - When true we will output more information
     * @return array
     */
    public
    function getVersionInfo(
        $storeId,
        $extended = false
    ) {
        /** @var JeroenVermeulen_Solarium_Helper_Data $helper */
        $helper   = Mage::helper( 'jeroenvermeulen_solarium' );
        $versions = array();
        if ($extended) {
            $versions[ 'Operating System' ] = php_uname();
            $versions[ 'PHP' ]              = phpversion();
            $versions[ 'Magento' ]          = Mage::getVersion();
            $versions[ 'Extension' ]        = $helper->getExtensionVersion();
            $versions[ 'Solarium Library' ] = Solarium\Client::VERSION;
        }
        $versions[ 'Solr version' ] = $helper->__( 'unknown' );
        $versions[ 'Java version' ] = $helper->__( 'unknown' );
        if ( $this->isWorking( $storeId ) ) {
            try {
                /**
                 * Abusing ping query to get system info
                 * @see https://github.com/basdenooijer/solarium/issues/254
                 */
                $query = $this->_client->createPing();
                $query->setHandler( 'admin/system' );
                $data = $this->_client->ping( $query, $this->getEndpointKey( Mage_Core_Model_App::ADMIN_STORE_ID, self::ENDPOINT_FUNCTION_ADMIN ) )->getData();
                $this->debugQuery( $query );
                if (!empty( $data[ 'lucene' ][ 'solr-impl-version' ] )) {
                    $versions[ 'Solr version' ] = $data[ 'lucene' ][ 'solr-impl-version' ];
                }
                if (!empty( $data[ 'jvm' ][ 'name' ] ) && !empty( $data[ 'jvm' ][ 'version' ] )) {
                    $versions[ 'Java version' ] = $data[ 'jvm' ][ 'name' ] . ' ' . $data[ 'jvm' ][ 'version' ];
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
     * @param int $storeId
     * @return bool - True on success
     */
    public
    function ping( $storeId )
    {
        /**
         * This function should not check the $this->_working variable,
         * because it is used to check if everything is working.
         */
        $result = false;
        try {
            $query = $this->_client->createPing();
            // Not 100% sure if this setTimeAllowed works.
            $query->setTimeAllowed( intval( $this->getConf( 'server/search_timeout', $storeId ) ) );
            $solariumResult = $this->_client->ping( $query, $this->getEndpointKey($storeId,self::ENDPOINT_FUNCTION_QUERY) );
            $this->debugQuery( $query );
            $resultData     = $solariumResult->getData();
            if (!empty( $resultData[ 'status' ] ) && 'OK' === $resultData[ 'status' ]) {
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
     * @param int $storeId - Store View Id, null or 0 means all storeViews
     * @param null|array|int $productIds - Product Entity Id(s)
     * @return bool                      - True on success
     */
    public
    function cleanIndex(
        $storeId,
        $productIds = null
    ) {
        if (!$this->_working) {
            return false;
        }
        $result = false;
        try {
            $query = $this->_client->createUpdate();
            $query->addDeleteQuery( $this->_getDeleteQueryText( $storeId, $productIds ) );
            $query->addCommit();

            $solariumResult = $this->_client->update( $query, self::getEndpointKey($storeId,self::ENDPOINT_FUNCTION_UPDATE) );
            $this->debugQuery( $query );
            $result         = $this->processResult( $solariumResult, 'clean' );
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * Rebuild the index. If a Store ID or Product IDs are specified it is only rebuilt for those.
     *
     * @param int $storeId - Store View Id, null or 0 means all storeViews
     * @param int[]|null $productIds - Product Entity Id(s)
     * @param bool $cleanFirst - If set to true the index will be cleared first
     * @return bool                  - True on success
     */
    public
    function rebuildIndex(
        $storeId,
        $productIds = null,
        $cleanFirst = false
    ) {
        if (!$this->_working) {
            return false;
        }
        $result = false;
        try {
            $storeId = intval( $storeId );
            $coreResource = Mage::getSingleton( 'core/resource' );
            $readAdapter  = $coreResource->getConnection( 'core_read' );

            $select = $readAdapter->select();
            $select->from(
                   $coreResource->getTableName( 'catalogsearch/fulltext' ),
                       array( 'product_id', 'data_index', 'fulltext_id' )
            );
            $select->where( 'store_id = ?', $storeId );
            if (!empty( $productIds )) {
                if (is_numeric( $productIds )) {
                    $select->where( 'product_id = ?', $productIds );
                } else {
                    if (is_array( $productIds )) {
                        $select->where( 'product_id IN (?)', $productIds );
                    }
                }
            }
            $products = $readAdapter->query( $select );

            if (!$products->rowCount()) {
                // No matching products, nothing to update, consider OK.
                $result = true;
            } else {
                if ($cleanFirst) {
                    $deleteQuery = $this->_client->createUpdate();
                    $deleteQuery->addDeleteQuery( $this->_getDeleteQueryText( $storeId, $productIds ) );
                    // No commit yet, will be done after BufferedAdd
                    $this->_client->update( $deleteQuery, $this->getEndpointKey($storeId, self::ENDPOINT_FUNCTION_UPDATE) );
                    $this->debugQuery( $deleteQuery );
                }
                /** @var Solarium\Plugin\BufferedAdd\BufferedAdd $buffer */
                $buffer = $this->_client->getPlugin( 'bufferedadd' );
                $buffer->setBufferSize( max( 1, $this->getConf( 'reindexing/buffersize', $storeId ) ) );
                $buffer->setEndpoint( $this->getEndpointKey($storeId, self::ENDPOINT_FUNCTION_UPDATE) );
                /** @noinspection PhpAssignmentInConditionInspection */
                while ($product = $products->fetch()) {
                    $text = $product[ 'data_index' ];
                    $text = preg_replace( '/\s*\,\s*+/', ' ', $text ); // Replace comma separation by spaces
                    $text = $this->_filterString( $text );
                    $data = array(
                        'id'         => intval( $product[ 'fulltext_id' ] ),
                        'product_id' => intval( $product[ 'product_id' ] ),
                        'store_id'   => $storeId,
                        'text'       => $text
                    );
                    $buffer->createDocument( $data );
                }
                $solariumResult = $buffer->commit();
                $this->optimize( $storeId ); // ignore result
                $result = $this->processResult( $solariumResult, 'flushing buffered add' );
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
     * @param int $storeId
     * @return bool - True on success
     */
    public
    function optimize( $storeId )
    {
        if (!$this->_working) {
            return false;
        }
        // get an update query instance
        $update = $this->_client->createUpdate();
        $update->addOptimize();
        $solariumResult = $this->_client->update( $update, $this->getEndpointKey($storeId,self::ENDPOINT_FUNCTION_UPDATE) );
        $this->debugQuery( $update );
        return $this->processResult( $solariumResult, 'optimize' );
    }

    /**
     * Check if Solr index is empty.
     * If storeId is supplied it is used as a filter.
     *
     * @param null|int $storeId
     * @return int
     */
    public
    function isEmpty(
        $storeId = null
    ) {
        return ( 0 == $this->getDocumentCount( $storeId ) );
    }

    /**
     * Get number of documents in Solr index for a specific store id.
     *
     * @param int $storeId
     * @return int
     */
    public
    function getDocumentCount(
        $storeId
    ) {
        $query = $this->_client->createSelect();
        $query->setQueryDefaultField( 'text' );
        $query->setRows( 0 );
        $query->setFields( array( 'product_id' ) );
        $query->createFilterQuery( 'store_id' )->setQuery( 'store_id:' . intval( $storeId ) );
        $query->setTimeAllowed( intval( $this->getConf( 'server/search_timeout', $storeId ) ) );
        $solrResultSet = $this->_client->select( $query, $this->getEndpointKey($storeId,self::ENDPOINT_FUNCTION_QUERY) );
        $this->debugQuery( $query );
        return ( $solrResultSet ) ? $solrResultSet->getNumFound() : 0;
    }

    /**
     * Query the Solr server to search for a string.
     *
     * @param int $storeId            - Store View Id
     * @param string $queryString     - Text to search for
     * @param int $searchType         - 0 = use config, 1 = Literal2 = Search completion
     * @param null|bool $doDidYouMean - null = use config
     * @param int|null $maxResults    - null = use config
     * @return JeroenVermeulen_Solarium_Model_SearchResult
     */
    public
    function search(
        $storeId,
        $queryString,
        $searchType = JeroenVermeulen_Solarium_Model_Engine::SEARCH_TYPE_USE_CONFIG,
        $doDidYouMean = null,
        $maxResults = null
    ) {
        if ( is_null($doDidYouMean) ) {
            $doDidYouMean = $this->getConf( 'results/did_you_mean', $storeId );
        }
        if ( is_null($maxResults) ) {
            $maxResults = $this->getConf( 'results/max', $storeId );
        }
        $result = Mage::getModel( 'jeroenvermeulen_solarium/searchResult' );
        $result->setStoreId( $storeId );
        $result->setResultQuery( $queryString );
        $result->setEngine( $this );
        if ( $this::SEARCH_TYPE_USE_CONFIG == $searchType ) {
            $searchType = $this->getConf( 'results/search_type', $storeId );
        }
        if (!$this->_working) {
            return $result;
        }
        try {
            $query              = $this->_client->createSelect();
            $queryHelper        = $query->getHelper();
            $escapedQueryString = $queryHelper->escapeTerm( $queryString );
            if ( $this::SEARCH_TYPE_STRING_COMPLETION == $searchType ) {
                $escapedQueryString = $escapedQueryString . '*';
            }
            $query->setQueryDefaultField( array( 'text' ) );
            $query->setQuery( $escapedQueryString );
            $query->setRows( $maxResults );
            $query->setFields( array( 'product_id', 'score' ) );
            if (is_numeric( $storeId )) {
                $query->createFilterQuery( 'store_id' )->setQuery( 'store_id:' . intval( $storeId ) );
            }
            $query->addSort( 'score', $query::SORT_DESC );
            // Group by product_id to prevent double results.
            $groupComponent = $query->getGrouping();
            $groupComponent->addField( 'product_id' );
            $groupComponent->setLimit( 1 );

            if ( $doDidYouMean ) {
                // We do one extra because one may get removed because of auto correct.
                $numSuggestions = 1 + $this->getConf( 'results/did_you_mean_suggestions', $storeId );
                $spellCheck = $query->getSpellcheck();
                $spellCheck->setQuery( $queryString );
                $spellCheck->setCount( 10 * $numSuggestions );
                $spellCheck->setExtendedResults( true );
                $spellCheck->setOnlyMorePopular( true );
                $spellCheck->setAccuracy( $this->getConf( 'results/suggestions_accuracy', $storeId ) / 100 );
                // You need Solr >= 4.0 for this to improve spell correct results.
                $query->addParam( 'spellcheck.alternativeTermCount', 10 * $numSuggestions );
            }

            $query->setTimeAllowed( intval( $this->getConf( 'server/search_timeout', $storeId ) ) );
            $solrResultSet = $this->_client->select( $query, $this->getEndpointKey($storeId,self::ENDPOINT_FUNCTION_QUERY) );
            $this->debugQuery( $query );

            $this->_lastQueryTime = $solrResultSet->getQueryTime();
            $resultProducts       = array();
            foreach ($solrResultSet->getGrouping()->getGroup( 'product_id' ) as $valueGroup) {
                foreach ($valueGroup as $solrResult) {
                    $key                    = 'prd' . $solrResult[ 'product_id' ];
                    $resultProducts[ $key ] = array(
                        'relevance'  => $solrResult[ 'score' ],
                        'product_id' => $solrResult[ 'product_id' ]
                    );
                }
            }
            $result->setResultProducts( $resultProducts );

            if ( $doDidYouMean) {
                $suggest          = array();
                $spellCheckResult = $solrResultSet->getSpellcheck();
                if ( $spellCheckResult ) {
                    $suggestions = $spellCheckResult->getSuggestions();
                    foreach ( $suggestions as $suggestion ) {
                        foreach ($suggestion->getWords() as $word) {
                            if ($word[ 'freq' ] > $suggestion->getOriginalFrequency()) {
                                $suggest[ $word[ 'word' ] ] = $word[ 'freq' ];
                            }
                        }
                    }
                    arsort( $suggest, SORT_NUMERIC );
                    $result->setSuggestions( $suggest );
                }
            }
        } catch ( Exception $e ) {
            $this->_lastError = $e;
            Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
        }
        return $result;
    }

    /**
     * @param int $storeId
     * @param string $queryString
     * @return string|null
     */
    public function autoCorrect( $storeId, $queryString ) {
        $result = null;

        $query = $this->_client->createSelect();
        $query->setRows(0);

        $spellcheck = $query->getSpellcheck();
        $spellcheck->setQuery( $queryString );
        $spellcheck->setCount(1);
        $spellcheck->setMaxCollations(1);
        $spellcheck->setCollate(true);
        $query->addParam( 'spellcheck.alternativeTermCount', 1 );

        $resultSet = $this->_client->select( $query, $this->getEndpointKey($storeId,self::ENDPOINT_FUNCTION_QUERY) );
        $this->debugQuery( $query );
        $spellCheckResult = $resultSet->getSpellcheck();

        if ( $spellCheckResult ) {
            $result = $spellCheckResult->getCollation()->getQuery();
            $result = trim( $result, '()' );
            if ( $result == $queryString ) {
                $result = null;
            }
        }

        return $result;
    }

    /**
     * @param integer $storeId - Store View Id
     * @param string $queryString - What the user is typing
     * @return array              - key = suggested term,  value = result count
     */
    public
    function getAutoSuggestions(
        $storeId,
        $queryString
    ) {
        $result = null;
        // Create basic query with wildcard
        $query              = $this->_client->createSelect();
        $queryHelper        = $query->getHelper();
        $escapedQueryString = $queryHelper->escapeTerm( strtolower( $queryString ) );

        $query->setQueryDefaultField( 'text' );
        $query->setQuery( $escapedQueryString . '*' );
        $query->setRows( 0 );

        if (!empty( $storeId )) {
            $query->createFilterQuery( 'store_id' )->setQuery( 'store_id:' . intval( $storeId ) );
        }

        $groupComponent = $query->getGrouping();
        $groupComponent->addField( 'product_id' );
        $groupComponent->setFacet( true );
        $groupComponent->setLimit( 1 );

        // Add facet for completion
        $facetSet   = $query->getFacetSet();
        $facetField = $facetSet->createFacetField( 'auto_complete' );
        $facetField->setField( 'text' );
        $facetField->setMincount( 1 );
        $facetField->setLimit( $this->getConf( 'results/autocomplete_suggestions', $storeId ) );
        $facetField->setPrefix( $escapedQueryString );

        try {
            $solariumResult = $this->_client->select( $query, $this->getEndpointKey($storeId,self::ENDPOINT_FUNCTION_QUERY) );
            $this->debugQuery( $query );
            if ($solariumResult) {
                $result = array();
                foreach ($solariumResult->getFacetSet()->getFacet( 'auto_complete' ) as $term => $matches) {
                    if ($matches) {
                        $result[ $term ] = $matches;
                    }
                };
            }
        }
        catch ( Exception $e ) {
            Mage::log( sprintf( '%s->%s: %s', __CLASS__, __FUNCTION__, $e->getMessage() ), Zend_Log::ERR );
            $this->debugQuery( $query );
        }

        return $result;
    }

    /**
     * Process the result of a Solarium update.
     *
     * @param Solarium\QueryType\Update\Result|bool $solariumResult
     * @param string $actionText
     * @return bool
     */
    public
    function processResult(
        $solariumResult,
        $actionText = 'query'
    ) {
        $result = false;
        /** @var JeroenVermeulen_Solarium_Helper_Data $helper */
        $helper = Mage::helper( 'jeroenvermeulen_solarium' );
        if (is_a( $solariumResult, 'Solarium\QueryType\Update\Result' )) {
            $this->_lastQueryTime = $solariumResult->getQueryTime();
            if (0 !== $solariumResult->getStatus()) {
                $this->_lastError = $solariumResult->getStatus();
                Mage::getSingleton( 'adminhtml/session' )->addError(
                    $helper->__(
                           'Solr %s error, status: %d, query time: %d',
                               $helper->__( $actionText ),
                               $solariumResult->getStatus(),
                               $solariumResult->getQueryTime()
                    )
                );
            }
            $result = ( 0 === $solariumResult->getStatus() );
        } else {
            $this->_lastError = $helper->__( 'Solr %s error, incorrect object received.', $actionText );
        }
        return $result;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Get configuration for Solarium, based on this extension's settings in the Magento Configuration
     *
     * @return array
     */
    protected
    function _getSolariumClientConfig()
    {
        $config = array();
        $config[ 'endpoint' ] = array();

        /**
         * The 'admin' core is a workaround used to execute 'non core specific' admin queries.
         * @see https://github.com/basdenooijer/solarium/issues/254
         */
        $storeId = Mage_Core_Model_App::ADMIN_STORE_ID;
        $endpointFunction = self::ENDPOINT_FUNCTION_ADMIN;
        $endpointKey = $this->getEndpointKey( $storeId, $endpointFunction );
        $config[ 'endpoint' ][ $endpointKey ] = $this->_getSolariumEndpointConfig( $storeId, self::ENDPOINT_FUNCTION_ADMIN );

        /**
         * Now we create the endpoints for each store
         */
        $enabledStoreIds = $this->getEnabledStoreIds();
        $endpointFunctions = array(self::ENDPOINT_FUNCTION_QUERY, self::ENDPOINT_FUNCTION_UPDATE);
        foreach ( $enabledStoreIds as $storeId ) {
            foreach ( $endpointFunctions as $endpointFunction ) {
                $endpointKey = $this->getEndpointKey( $storeId, $endpointFunction );
                $config[ 'endpoint' ][ $endpointKey ] = $this->_getSolariumEndpointConfig( $storeId, $endpointFunction );
            }
        }

        return $config;
    }

    protected
    function _getSolariumEndpointConfig( $storeId, $endpointFunction ) {
        $host = trim( $this->getConf( 'server/host', $storeId ) );
        // If the user pasted a URL as hostname, clean it.
        $host             = str_replace( array( 'http://', '/' ), array( '', '' ), $host );
        $result           = array();
        $result[ 'host' ] = $host;
        $result[ 'port' ] = intval( $this->getConf( 'server/port', $storeId ) );
        $result[ 'path' ] = trim( $this->getConf( 'server/path', $storeId ) );
        $result[ 'core' ] = trim( $this->getConf( 'server/core', $storeId ) );
        if ($this->getConf( 'server/requires_authentication', $storeId )) {
            $result[ 'username' ] = trim( $this->getConf( 'server/username', $storeId ) );
            $result[ 'password' ] = Mage::helper( 'core' )->decrypt( $this->getConf( 'server/password', $storeId ) );
        }
        if ( self::ENDPOINT_FUNCTION_ADMIN == $endpointFunction ) {
            $result[ 'timeout' ] = intval( $this->getConf( 'server/timeout', $storeId ) );
            $result[ 'core' ]      = '';
        } elseif ( self::ENDPOINT_FUNCTION_QUERY == $endpointFunction ) {
            $result[ 'timeout' ] = intval( $this->getConf( 'server/search_timeout', $storeId ) );
        } elseif ( self::ENDPOINT_FUNCTION_UPDATE == $endpointFunction ) {
            $result[ 'timeout' ] = intval( $this->getConf( 'server/timeout', $storeId ) );
        } else {
            throw new Exception( sprintf("_getSolariumEndpointConfig: Unknown endpoint function: '%s'.", $endpointFunction) );
        }
        return $result;
    }

    /**
     * Filter a string to be safe to add to Solr.
     *
     * @param string $str
     * @return string
     */
    protected
    function _filterString(
        $str
    ) {
        static $badChars; // This variable is saved inside this function.
        if (!isset( $badChars )) {
            for ($ord = 0; $ord < 32; $ord++) {
                $badChars .= chr( $ord );
            }
        }
        return preg_replace( '/[' . preg_quote( $badChars, '/' ) . ']+/', ' ', strval( $str ) );
    }

    /**
     * Get delete query string for Solr
     *
     * @param int $storeId - Store View Id, null or 0 means all storeViews
     * @param null|array|int $productIds - Product Entity Id(s)
     * @return string
     */
    protected
    function _getDeleteQueryText(
        $storeId = null,
        $productIds = null
    ) {
        $queryText = array();
        if (!empty( $storeId )) {
            $queryText[ ] .= 'store_id:' . $storeId;
        }
        if (is_numeric( $productIds )) {
            $queryText[ ] .= 'product_id:' . $productIds;
        }
        if (is_array( $productIds )) {
            $or = array();
            foreach ($productIds as $id) {
                $or[ ] = 'product_id:' . $id;
            }
            $queryText[ ] .= '(' . implode( ' OR ', $or ) . ')';
        }
        if (empty( $queryText )) {
            $queryText[ ] = '*:*'; // Delete all
        }
        return implode( ' ', $queryText );
    }

    /**
     * @param Solarium\Core\Query\Query $query
     */
    protected
    function debugQuery( $query ) {
        if ( !empty($query) && Mage::getIsDeveloperMode() ) {
            $url = $this->getClient()->getEndpoint()->getBaseUri();
            $url .= $query->getRequestBuilder()->build( $query )->getUri();
            // Modify URL for easier debugging
            $url = str_replace( 'omitHeader=true&', '', $url );
            $url .= '&indent=true&echoParams=all';
            Mage::log( 'Solr request: ' . $url , Zend_Log::DEBUG );
        }
    }
}