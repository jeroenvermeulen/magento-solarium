<?php

class JeroenVermeulen_Solarium_TestController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        echo 'Solarium library version: ' . Solarium\Client::VERSION . ' - ';

        $result = Mage::getSingleton('jeroenvermeulen_solarium/solarium')->ping();
        echo sprintf( 'Ping: <pre>%s</pre>', var_export($result,1) );

        $storeId = Mage::app()->getStore()->getId();
        $result = Mage::getSingleton('jeroenvermeulen_solarium/solarium')->query( 'camera', $storeId );
        echo sprintf( 'Camera: <pre>%s</pre>', var_export($result,1) );

    }

}
