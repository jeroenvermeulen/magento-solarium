<?php

class JeroenVermeulen_Solarium_Admin_SolariumController extends Mage_Adminhtml_Controller_Action
{

    // URL:  http://[MAGROOT]/admin/solarium/ajax/key/###########/
    public function ajaxAction() {
        $request = $this->getRequest();
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
        $result = $engine->isWorking() ? 'Success' : 'ERROR';
        $this->getResponse()->setBody( $result );
    }

}