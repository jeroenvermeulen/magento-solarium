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

class JeroenVermeulen_Solarium_Model_CatalogSearch_Indexer_Fulltext extends Mage_CatalogSearch_Model_Indexer_Fulltext
{

    public function getDescription() {
        $result = parent::getDescription();
        if ( JeroenVermeulen_Solarium_Model_Engine::isEnabled() ) {
            $result .= ' - ' . Mage::helper( 'jeroenvermeulen_solarium' )->__( 'POWERED BY SOLARIUM' );
        }
        return $result;
    }

}