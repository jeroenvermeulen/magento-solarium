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
 * Class JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
 */
class JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
	
	
    /**
     * @var array
     */
    protected $_foundData = array();

    /**
     * Flag for verbose logging
     */
    protected $verboseLogging = false;


    public function __construct() {
        parent::__construct();
        $this->verboseLogging = Mage::getStoreConfigFlag('jeroenvermeulen_solarium/general/verboseLogging');
    }

    /**
     * This function is called when a visitor searches
     *
     * @param Mage_CatalogSearch_Model_Fulltext $object
     * @param string $queryText
     * @param Mage_CatalogSearch_Model_Query $query
     * @return JeroenVermeulen_Solarium_Model_Resource_CatalogSearch_Fulltext
     */
    public
    function prepareResult(
        $object,
        $queryText,
        $query
    ) {
        if (JeroenVermeulen_Solarium_Model_Engine::isEnabled( $query->getStoreId() )) {
            $helper            = Mage::helper('jeroenvermeulen_solarium');
            $adapter           = $this->_getWriteAdapter();
            $searchResultTable = $this->getTable( 'catalogsearch/result' );
            $catSearchHelper   = Mage::helper('catalogsearch');
		    $stringHelper      = Mage::helper('core/string');
            $thisQueryLength = $stringHelper->strlen($queryText);
            $wordsFull = $stringHelper->splitWords($queryText, true);
            $wordsLike = $stringHelper->splitWords($queryText, true, $catSearchHelper->getMaxQueryWords());

            /* Validate strings and return normal search to handle messages */
            if($catSearchHelper->getMaxQueryLength() < $thisQueryLength ||
                $catSearchHelper->getMinQueryLength() > $thisQueryLength ||
                count($wordsFull) > count($wordsLike)){
                return parent::prepareResult($object, $queryText, $query);
            }

            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            $engine = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            if ($engine->isWorking()) {
                $searchResult = $engine->search( $query->getStoreId(), $queryText );
                $searchResult->setUserQuery( $queryText );
                Mage::register( 'solarium_search_result', $searchResult );
                if ( ! $searchResult->getResultCount() ) {
                    // Autocorrect
                    if ( $engine->getConf( 'results/autocorrect', $query->getStoreId() ) ) {
                        $searchResult->autoCorrect();
                    }
                }
                $resultProducts = $searchResult->getResultProducts();
                if ( ! $searchResult->getResultCount() ) {
                    // No results, we need to check if the index is empty.
                    if ($engine->isEmpty( $query->getStoreId() )) {
                        Mage::Log( sprintf( '%s - Warning: index is empty', __CLASS__ ), Zend_Log::WARN );
                    } else {
                        $query->setIsProcessed( 1 );
                    }
                } else {
                    $columns    = array( 'query_id', 'product_id', 'relevance' );
                    $insertRows = array();
		    $foundData  = array();
                    $queryId    = $query->getId();
                    foreach ($resultProducts as $data) {
                        $insertRows[ ] = array( $queryId, $data[ 'product_id' ], $data[ 'relevance' ] );
			$foundData [$data['product_id']] =  $data[ 'relevance' ];
                    }
                    $adapter->beginTransaction();
                    $adapter->delete( $searchResultTable, 'query_id = ' . $queryId );
                    $adapter->insertArray( $searchResultTable, $columns, $insertRows );
                    $adapter->commit();
                    $query->setIsProcessed( 1 );
		    $this->_foundData = $foundData;
                }
                // Autocorrect notification
                if ( $searchResult->didAutoCorrect() ) {
                    $catSearchHelper->addNoteMessage(
                        $helper->__( "Showing results for '%s' instead.", $searchResult->getResultQuery() ) );
                }
                // "Did you mean" suggestions
                $suggestions = $searchResult->getBetterSuggestions();
                if ( $suggestions ) {
                    $suggestHtml = '';
                    foreach ($suggestions as $searchTerm => $result_count)  {
                        $title = $helper->__('Results').': '.$result_count;
                        $href = Mage::getUrl('catalogsearch/result', array('q' => $searchTerm));
                        $suggestHtml .= sprintf('&nbsp; <a title="%s" href="%s">%s</a>', $title, $href, $searchTerm);
                    }
                    $catSearchHelper->addNoteMessage( $helper->__('Did you mean:') . $suggestHtml );
                }
                /** @deprecated The registry key 'solarium_suggest' is deprecated, it was used in 1.6.0 till 1.6.2 */
                Mage::register( 'solarium_suggest', $searchResult->getBetterSuggestions() );
            }
        }
        if (!$query->getIsProcessed()) {
            Mage::log( 'Solr disabled or something went wrong, fallback to Magento Fulltext Search', Zend_Log::WARN );
            return parent::prepareResult( $object, $queryText, $query );
        }
        return $this;
    }

    /**
     * Override to prevent table locking during cleanup of previous search results by Magento
     * - Update `catalogsearch_query`.`is_processed` in steps of 20
     * - Clean `catalogsearch_result` in steps of 20
     *
     * @return Mage_CatalogSearch_Model_Resource_Fulltext
     */
    public function resetSearchResults()
    {
        $this->log( 'Solarium resetSearchResults: start', Zend_Log::DEBUG );

        $read = $this->_getReadAdapter();
        $write = $this->_getWriteAdapter();

        $pageSize    = 20;

        $queryTable  = $this->getTable('catalogsearch/search_query');
        $querySelect = $read->select()->from( $queryTable, 'COUNT(*)' )->where( 'is_processed' );
        $queryCount  = intval( $read->fetchOne( $querySelect ) );
        if ( $queryCount ) {
            $this->log( sprintf('Solarium resetSearchResults: clearing %d search queries', $queryCount), Zend_Log::DEBUG );
            $queryPages  = ceil( $queryCount / $pageSize );
            $querySql    = sprintf( 'UPDATE %s SET %s=0 WHERE %s=1 LIMIT %d',
                                    $read->quoteIdentifier( $queryTable ),
                                    $read->quoteIdentifier( 'is_processed' ),
                                    $read->quoteIdentifier( 'is_processed' ),
                                    $pageSize );
            for ( $page=0; $page < $queryPages; $page++ ) {
                try {
                    // It would be better to do this using a Varien or Zend object, but they don't support LIMIT on update.
                    $write->query( $querySql );
                } catch ( Exception $e ) {
                    // This happens on busy sites because of deadlock
                    Mage::log( sprintf('Solarium resetSearchResults: Update error during search reindex, but we can continue: %s', $e->getMessage()),
                               Zend_Log::ERR );
                    sleep(1);
                    $page--;
                }
            }
        }
        $resultTable  = $this->getTable('catalogsearch/result');
        $resultSelect = $read->select()->from( $resultTable, 'COUNT(*)' );
        $resultCount  = intval( $read->fetchOne( $resultSelect ) );
        if ( $resultCount ) {
            $this->log( sprintf('Solarium resetSearchResults: clearing %d search results', $queryCount), Zend_Log::DEBUG );
            $resultPages  = ceil( $resultCount / $pageSize );
            $resultSql    = sprintf( 'DELETE FROM %s LIMIT %d',
                                     $read->quoteIdentifier( $resultTable ),
                                     $pageSize );
            for ( $page=0; $page < $resultPages; $page++ ) {
                try {
                    // It would be better to do this using a Varien or Zend object, but they don't support LIMIT on delete.
                    $write->query( $resultSql );
                    break;
                } catch ( Exception $e ) {
                    // This happens on busy sites because of deadlock
                    Mage::log( sprintf('Solarium resetSearchResults: Delete error during search reindex, but we can continue: %s', $e->getMessage()),
                               Zend_Log::ERR );
                    sleep(1);
                    $page--;
                }
            }
        }

        $this->log( 'Solarium resetSearchResults: done.', Zend_Log::DEBUG );

        Mage::dispatchEvent('catalogsearch_reset_search_result');

        return $this;
    }

    /**
     * Log message to system log when verbose logging is enabled
     *
     * @param string $message
     * @param integer $level
     * @param string $file
     * @param bool $forceLog
     */
    public function log($message, $level = null, $file = '', $forceLog = false) {
        if ($this->verboseLogging) {
            Mage::log($message, $level, $file, $forceLog);
        }
    }

}
