<?php

/**
 * Class JeroenVermeulen_Solarium_Admin_SolariumController
 *
 * Controller for actions from the Magento Admin interface
 */
class JeroenVermeulen_Solarium_Admin_SolariumController extends Mage_Adminhtml_Controller_Action
{

    /**
     *
     *
     * Example URL:  http://[MAGE-ROOT]/admin/solarium/testConnection/key/###########/
     */
    public function testConnectionAction() {
        $request = $this->getRequest();
        $helper   = Mage::helper( 'jeroenvermeulen_solarium' );
        $config = array( 'general/enabled' => true,
                         'server/host' => $request->getParam('host', false),
                         'server/port' => $request->getParam('port', false),
                         'server/path' => $request->getParam('path', false),
                         'server/core' => $request->getParam('core', false),
                         'server/requires_authentication' => $request->getParam('auth', false),
                         'server/username' => $request->getParam('username', false),
                         'server/search_timeout' => $request->getParam('timeout', false) );
        if ( !preg_match('|^\*+$|', $request->getParam('password', false) ) ) {
            $config['server/password'] =  Mage::helper('core')->encrypt( $request->getParam('password', false) );
        }
        $engine = Mage::getModel( 'jeroenvermeulen_solarium/engine', $config );
        $class  = 'error';
        $state  = 'FAILED';
        if ( $engine->isWorking() ) {
            $class = 'available';
            $state = 'Success';
        }
        $versions = $engine->getVersionInfo();
        ob_start();
?>
        <tr id="solarium_test_connection_result">
            <td class="label">
                <?php echo htmlspecialchars( $helper->__('Connection Test') ); ?>
            </td>
            <td class="<?php echo htmlspecialchars( $class ); ?>">
                <?php echo htmlspecialchars( $helper->__( $state ) ); ?>
            </td>
        </tr>
<?php
        foreach ( $versions as $label => $value ):
?>
        <tr>
            <td class="label">
                <?php echo htmlspecialchars( $helper->__( $label ) ); ?>
            </td>
            <td>
                <?php echo htmlspecialchars( $value ); ?>
            </td>
        </tr>
<?php
        endforeach;
        $result = ob_get_clean();
        $this->getResponse()->setBody( $result );
    }

}