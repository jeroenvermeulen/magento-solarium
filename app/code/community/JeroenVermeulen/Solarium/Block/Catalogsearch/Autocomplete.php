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
 * Class JeroenVermeulen_Solarium_Block_Catalogsearch_Autocomplete
 */
class JeroenVermeulen_Solarium_Block_Catalogsearch_Autocomplete extends Mage_CatalogSearch_Block_Autocomplete
{
    protected $_suggestData;
    protected $_suggestProductIds;

    protected
    function _toHtml()
    {
        if ( ! Mage::getStoreConfig( 'jeroenvermeulen_solarium/results/autocomplete_product_suggestions' )) {
            return parent::_toHtml();
        }
        $productIds = $this->getSuggestProductIds();
        if (empty( $productIds )) {
            return parent::_toHtml();
        } else {
            $html              =
                '<ul class="product_suggest"><li style="display: none"></li>'; // Magento by default starts with a hidden <li>, don't know why.
            $productCollection = $products = Mage::getModel( 'catalog/product' )->getCollection()->addAttributeToFilter(
                                                 'entity_id',
                                                     array( 'in' => $productIds )
                )->addAttributeToSelect( array( 'name', 'thumbnail', 'product_url' ) );
            $counter           = 0;
            foreach ($productCollection as $product) {
                $rowClass = ( ++$counter ) % 2 ? 'odd' : 'even';
                $html .= sprintf( '<li title="%s" class="%s" data-url="%s">',
                                  htmlentities( $product->getName() ),
                                  $rowClass,
                                  htmlentities( $product->getProductUrl() ) );
                $html .= '<span class="suggestions-productimage">';
                $html .= sprintf(
                    '<img src="%s" />',
                    htmlentities( Mage::helper( 'catalog/image' )->init( $product, 'thumbnail' )->resize( '50' ) )
                );
                $html .= '</span>';
                $html .= '<span class="suggestions-productname">';
                $html .= htmlentities( Mage::helper( 'core/string' )->truncate( $product->getName(), 100 ) );
                $html .= '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        return $html;

    }

    /**
     * Sanitize result to standard core functionality
     * @return array|null
     */
    public
    function getSuggestData()
    {
        if (!$this->_suggestData) {
            $query   = $this->helper( 'catalogsearch' )->getQueryText();
            $counter = 0;
            $storeId = Mage::app()->getStore()->getId();
            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            $engine      = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            $suggestions = $engine->getAutoSuggestions( $storeId, $query );

            $this->_suggestData = array();
            foreach ($suggestions as $value => $count) {
                $this->_suggestData[ ] = array(
                    'title'          => $value,
                    'row_class'      => ( ++$counter ) % 2 ? 'odd' : 'even',
                    'num_of_results' => $count
                );
            }
        }
        return $this->_suggestData;
    }

    public
    function getSuggestProductIds()
    {
        /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
        $engine = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
        if (empty( $this->_suggestProductIds ) && $engine->isWorking()) {
            $query                    = $this->helper( 'catalogsearch' )->getQueryText();
            $storeId                  = Mage::app()->getStore()->getId();
            $searchResult             = $engine->search( $storeId,
                                                         $query,
                                                         $engine::SEARCH_TYPE_STRING_COMPLETION,
                                                         null,
                                                         $engine->getConf('results/autocomplete_suggestions') );
            $this->_suggestProductIds = $searchResult->getResultProductIds();
        }
        return $this->_suggestProductIds;
    }
}