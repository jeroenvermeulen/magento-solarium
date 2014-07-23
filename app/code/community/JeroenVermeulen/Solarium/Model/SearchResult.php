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
 * Class JeroenVermeulen_Solarium_Model_SearchResult
 *
 * Container for search result data from Solr.
 * When a search has been executed, this object is available via:
 *   Mage::registry( 'solarium_search_result' );
 *
 * @method setStoreId( int $storeId )
 * @method int getStoreId()
 * @method setUserQuery( string $query )
 * @method string getUserQuery()
 * @method setResultQuery( string $query )
 * @method string getResultQuery()
 * @method JeroenVermeulen_Solarium_Model_SearchResult setResultProducts($data)
 * @method array getResultProducts()
 * @method JeroenVermeulen_Solarium_Model_SearchResult setSuggestions($data)
 * @method array getSuggestions()
 */

/**
 * Most of the work is done by Magento's Mage_Core_Model_Abstract.
 *
 * Class JeroenVermeulen_Solarium_Model_SearchResult
 */
class JeroenVermeulen_Solarium_Model_SearchResult extends Mage_Core_Model_Abstract
{
    protected $betterSuggestions = null;

    /**
     * @return bool - True if autocorrect changed the the string to search for.
     */
    public function didAutoCorrect() {
        return( $this->getUserQuery() && $this->getResultQuery() && $this->getUserQuery() != $this->getResultQuery() );
    }

    /**
     * @return int[] - Array of product id's
     */
    public function getResultProductIds() {
        $result = array();
        $resultProducts = $this->getResultProducts();
        if ( is_array($resultProducts) ) {
            foreach ( $resultProducts as $resultProduct ) {
                $result[] = $resultProduct['product_id'];
            }
        }
        return $result;
    }

    /**
     * @return int
     */
    public function getResultCount() {
        $result = 0;
        $resultProducts = $this->getResultProducts();
        if ( is_array($resultProducts) ) {
            $result = count( $resultProducts );
        }
        return $result;
    }

    /**
     * @return array - with key = term, value = result count.
     */
    public function getBetterSuggestions() {
        if ( is_null($this->betterSuggestions) ) {
            $this->betterSuggestions = array();
            $suggestions = $this->getSuggestions();
            $resultCount = $this->getResultCount();
            $engine = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            if ( is_array($suggestions) ) {
                foreach ( $suggestions as $term => $freq ) {
                    $termResult = $engine->search( $this->getStoreId(), $term, JeroenVermeulen_Solarium_Model_Engine::SEARCH_TYPE_LITERAL, 2 );
                    if ( $termResult->getResultCount() > $resultCount ) {
                        $this->betterSuggestions[ $term ] = $termResult->getResultCount();
                    }
                }
            }
        }
        return $this->betterSuggestions;
    }
}