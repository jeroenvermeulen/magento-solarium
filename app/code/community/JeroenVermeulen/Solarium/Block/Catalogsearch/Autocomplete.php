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

    protected function _toHtml()
    {
        if (!Mage::getStoreConfig('jeroenvermeulen_solarium/results/autocomplete_product_suggestions')) {
            return parent::_toHtml();
        }

        $html = '<ul><li style="display:none"></li>';
        $suggestData = $this->getSuggestData();
        $productCollection = $products = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('entity_id', array('in' => $suggestData))
            ->addAttributeToSelect(array('name', 'thumbnail', 'special_price'));

        foreach ($productCollection as $product) {
            $html .= '<li title="' . $product->getName() . '" class="odd">'
                . '<span class="suggestions-productimage"><img src="' . Mage::helper('catalog/image')->init($product, 'thumbnail')->resize("50") . '"/></span>
                   <span class="suggestions-productname"><strong>' . Mage::helper('core/string')->truncate($product->getName() ,30) . '</strong></span>';
        }
        $html .= '</ul>';

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
            $query = $this->helper('catalogsearch')->getQueryText();
            $counter = 0;
            $storeId = Mage::app()->getStore()->getId();
            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            $engine = Mage::getSingleton('jeroenvermeulen_solarium/engine');
            $suggestions = $engine->getAutoSuggestions($storeId, $query);


            if (Mage::getStoreConfig('jeroenvermeulen_solarium/results/autocomplete_product_suggestions')) {
                return $suggestions;
            }
            $this->_suggestData = array();
            foreach ($suggestions as $value => $count) {
                $this->_suggestData[] = array(
                    'title' => $value,
                    'row_class' => (++$counter) % 2 ? 'odd' : 'even',
                    'num_of_results' => $count
                );
            }
        }

        return $this->_suggestData;
    }
}