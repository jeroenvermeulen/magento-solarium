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
 * Class JeroenVermeulen_Solarium_Model_Adminhtml_System_Config_Source_Searchtype
 */
class JeroenVermeulen_Solarium_Model_Adminhtml_System_Config_Source_Searchtype
{
    /**
     * Options getter
     *
     * @return array
     */
    public
    function toOptionArray()
    {
        return array(
            array( 'value' => JeroenVermeulen_Solarium_Model_Engine::SEARCH_TYPE_LITERAL,
                   'label' => Mage::helper( 'jeroenvermeulen_solarium' )->__( 'Literal Search' ) ),
            array( 'value' => JeroenVermeulen_Solarium_Model_Engine::SEARCH_TYPE_STRING_COMPLETION,
                   'label' => Mage::helper( 'jeroenvermeulen_solarium' )->__( 'String Completion (starts with)' ) ),
            array( 'value' => JeroenVermeulen_Solarium_Model_Engine::SEARCH_TYPE_WILDCARD,
                   'label' => Mage::helper( 'jeroenvermeulen_solarium' )->__( 'Wildcard (contains string)' ) ),
        );
    }

}