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
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    /**
     * Show version info
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderCommentHtml( $element ) {
        $helper   = Mage::helper( 'jeroenvermeulen_solarium' );
        $engine   = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
        $versions = $engine->getVersionInfo();
        ob_start();
        ?>
        <table class="form-list">
            <tbody>
            <?php foreach ( $versions as $label => $value ): ?>
                <tr>
                    <td class="label"><?php echo $helper->__( $label ); ?></td>
                    <td><?php echo $value; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

<a href="#" onclick="solariumTestConnection(); return false;">Test Connection</a>
<script type="text/javascript">
    function solariumTestConnection() {
        params = {
            host: $('jeroenvermeulen_solarium_server_host').value,
            port: $('jeroenvermeulen_solarium_server_port').value,
            path: $('jeroenvermeulen_solarium_server_path').value,
            core: $('jeroenvermeulen_solarium_server_core').value,
            auth: $('jeroenvermeulen_solarium_server_requires_authentication').value,
            username: $('jeroenvermeulen_solarium_server_username').value,
            password: $('jeroenvermeulen_solarium_server_password').value,
            timeout: $('jeroenvermeulen_solarium_server_search_timeout').value
        };

        new Ajax.Request('<?php echo Mage::helper("adminhtml")->getUrl("adminhtml/solarium/ajax"); ?>', {
            parameters: params,
            onSuccess: function( $response ) {
                $('solarium_test_connection_result').update( $response.responseText );
            },
            onFailure: function( $response ) {
                $('solarium_test_connection_result').update( 'ERROR ' + $response.responseText );
            }
        });
    }
</script>
<span id="solarium_test_connection_result">&nbsp;</span>
        <?php
        return ob_get_clean();
    }

}