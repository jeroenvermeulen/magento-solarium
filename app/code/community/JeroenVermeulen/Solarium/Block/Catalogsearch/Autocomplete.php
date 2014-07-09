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

class JeroenVermeulen_Solarium_Block_Catalogsearch_Autocomplete extends Mage_CatalogSearch_Block_Autocomplete
{

    /**
     * Sanitize result to standard core functionality
     * @return array|null
     */
    public function getSuggestData()
    {
        if ( ! $this->_suggestData ) {
            $query = $this->helper('catalogsearch')->getQueryText();
            $counter = 0;
            $data = array();
            $storeId = Mage::app()->getStore()->getId();
            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            $engine = Mage::getSingleton('jeroenvermeulen_solarium/engine');
            $facet = $engine->getAutoSuggestions( $storeId, $query );

            foreach ( $facet as $value => $count ) {
                $_data = array(
                    'title' => $value,
                    'row_class' => ( ++$counter ) % 2 ? 'odd' : 'even',
                    'num_of_results' => $count
                );
                $data[] = $_data;
            }
            $this->_suggestData = $data;
        }
        return $this->_suggestData;
    }
}