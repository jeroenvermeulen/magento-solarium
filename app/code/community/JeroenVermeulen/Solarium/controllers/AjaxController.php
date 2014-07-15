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
 * Class JeroenVermeulen_Solarium_AjaxController
 */
class JeroenVermeulen_Solarium_AjaxController extends Mage_Core_Controller_Front_Action
{

    /**
     * Override default suggestAction to add our own block
     *
     * URL:  http://[MAGE-ROOT]/solarium/ajax/suggest/?q=comp -->
     */
    public
    function suggestAction()
    {
        if (!$this->getRequest()->getParam( 'q', false )) {
            // No query received
            $this->getResponse()->setRedirect( Mage::getSingleton( 'core/url' )->getBaseUrl() );
        }
        /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
        $engine    = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
        $blockType = 'catalogsearch/autocomplete';
        if ($engine->isWorking()) {
            $blockType = 'jeroenvermeulen_solarium/catalogsearch_autocomplete';
        }
        /** @var Mage_CatalogSearch_Block_Autocomplete $block */
        $block = $this->getLayout()->createBlock( $blockType );
        $this->getResponse()->setBody( $block->toHtml() );
    }

}

