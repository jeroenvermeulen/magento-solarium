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

class JeroenVermeulen_Solarium_Block_Adminhtml_System_Config_Form_Fieldset_Info
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

    /**
     * Show version info
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderCommentHtml( $element ) {
        $helper = Mage::helper('jeroenvermeulen_solarium');
        $engine = Mage::getSingleton('jeroenvermeulen_solarium/engine');
        $versions = $engine->getVersionInfo();
        ob_start();
?>
            <table cellspacing="0" class="form-list">
                <colgroup class="label"></colgroup>
                <colgroup class="value"></colgroup>
                <colgroup class="scope-label"></colgroup>
                <colgroup class=""></colgroup>
                <tbody>
                <?php foreach ( $versions as $label => $value ): ?>
                    <tr>
                        <td class="label"><?php echo $helper->__($label); ?></td>
                        <td class="value"><?php echo $value; ?></td>
                        <td class="scope-label"></td>
                        <td class=""></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
<?php
        return ob_get_clean();
    }

}