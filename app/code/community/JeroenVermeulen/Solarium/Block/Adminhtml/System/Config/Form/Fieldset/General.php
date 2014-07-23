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
 * Class JeroenVermeulen_Solarium_Block_Adminhtml_System_Config_Form_Fieldset_General
 */
class JeroenVermeulen_Solarium_Block_Adminhtml_System_Config_Form_Fieldset_General
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    /**
     * Show version info
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected
    function _getHeaderHtml(
        $element
    ) {
        /** @var JeroenVermeulen_Solarium_Helper_Data $helper */
        $helper               = Mage::helper( 'jeroenvermeulen_solarium' );
        $searchIndexerProcess = $helper->getSearchIndexer();
        $urlPath              = '';
        if (!empty( $searchIndexerProcess )) {
            $urlPath = 'adminhtml/process/reindexProcess/process/' . $searchIndexerProcess->getData( 'process_id' );
        }
        $indexUrl = Mage::helper( "adminhtml" )->getUrl( $urlPath );
        $howTo    = '<ul>';

        $howTo .= '<li>' . $helper->__( 'Step %d', 1 ) . ': ';
        $text = $helper->__( 'Configure and test your [Solr Server].' );
        $text = str_replace( '[', '<!--suppress HtmlUnknownAnchorTarget -->
                                   <a onclick="$(\'jeroenvermeulen_solarium_server-state\').value=0;
                                               Fieldset.toggleCollapse(\'jeroenvermeulen_solarium_server\');"
                                  href="#jeroenvermeulen_solarium_server-head">', $text );
        $text = str_replace( ']', '</a>', $text );
        $howTo .= $text;
        $howTo .= '</li>';

        $howTo .= '<li>' . $helper->__( 'Step %d', 2 ) . ': ';
        $text = $helper->__( 'Enable Solarium Search in [General Settings] below, Save Config.' );
        $text = str_replace( '[', '<!--suppress HtmlUnknownAnchorTarget -->
                                   <a onclick="$(\'jeroenvermeulen_solarium_general-state\').value=0;
                                               Fieldset.toggleCollapse(\'jeroenvermeulen_solarium_general\');"
                                  href="#jeroenvermeulen_solarium_general-head">', $text );
        $text = str_replace( ']', '</a>', $text );
        $howTo .= $text;
        $howTo .= '</li>';

        $howTo .= '<li>' . $helper->__( 'Step %d', 3 ) . ': ';
        $howTo .= '<a href="' . $indexUrl . '">';
        $howTo .= $helper->__( 'Reindex the Catalog Search Index' );
        $howTo .= '</a>.</li>';

        $howTo .= '<li>' . $helper->__( 'Step %d', 4 ) . ': ';
        $howTo .= $helper->__( 'Test searching via the frontend.' );
        $howTo .= $helper->__( 'If searching with a small typo works, Solarium search is active.' );
        $howTo .= '</ul><br />';
        return $howTo . parent::_getHeaderHtml( $element );
    }

}