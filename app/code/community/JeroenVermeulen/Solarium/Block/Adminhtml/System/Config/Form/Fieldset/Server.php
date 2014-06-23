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

class JeroenVermeulen_Solarium_Block_Adminhtml_System_Config_Form_Fieldset_Server
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    /**
     * Show version info
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getFooterHtml( $element ) {
        /** @var JeroenVermeulen_Solarium_Helper_Data $helper */
        $helper   = Mage::helper( 'jeroenvermeulen_solarium' );
        ob_start();
        ?>
        <table class="form-list">
            <tbody>
                <tr>
                    <td class="label">&nbsp;</td>
                    <td>
                        <button type="button" onclick="solariumTestConnection();">
                            <?php echo htmlspecialchars( $helper->__('Test Connection') ); ?>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="form-list">
            <tbody id="solarium_test_connection_result" style="display: none;" >
                <tr >
                    <td class="label">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
        <script type="text/javascript">
            function solariumTestConnection() {
                function solariumTestMessage( message, setClass ) {
                    if ( !setClass ) {
                        setClass = '';
                    }
                    var resultRow = '';
                    resultRow += '<tr>';
                    resultRow += '<td class="label"><?php echo htmlspecialchars( $helper->__('Connection Test') ); ?></td>';
                    resultRow += '<td class="' + setClass + '">' + message + '</td>';
                    resultRow += '</tr>';
                    $('solarium_test_connection_result').update( resultRow ).show();
                }
                var params = {
                    host: $('jeroenvermeulen_solarium_server_host').value,
                    port: $('jeroenvermeulen_solarium_server_port').value,
                    path: $('jeroenvermeulen_solarium_server_path').value,
                    core: $('jeroenvermeulen_solarium_server_core').value,
                    auth: $('jeroenvermeulen_solarium_server_requires_authentication').value,
                    username: $('jeroenvermeulen_solarium_server_username').value,
                    password: $('jeroenvermeulen_solarium_server_password').value,
                    timeout: $('jeroenvermeulen_solarium_server_search_timeout').value
                };
                solariumTestMessage( <?php echo json_encode( $helper->__('Connecting...') ); ?> );
                new Ajax.Request( <?php echo json_encode( Mage::helper("adminhtml")->getUrl("adminhtml/solarium/testConnection") ); ?>, {
                    'parameters': params,
                    'onComplete': function( $response ) {
                        $('solarium_test_connection_result').update( $response.responseText ).show();
                    },
                    'onFailure': function( $response ) {
                        solariumTestMessage( <?php echo json_encode( $helper->__('ERROR') ); ?> + ' ' + response.status + ': ' + $response.responseText, 'not-available' );
                    }
                });
            }
        </script>
        <?php
        return ob_get_clean() . parent::_getFooterHtml( $element );
    }

}