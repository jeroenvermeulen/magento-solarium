<?php

class JeroenVermeulen_Solarium_Admin_SolariumController extends Mage_Adminhtml_Controller_Action
{

    // URL:  http://[MAGROOT]/admin/solarium/ajax/key/###########/
    public function ajaxAction() {
        $result = array('test' => 1);
        $this->getResponse()->setBody( Mage::helper('core')->jsonEncode( $result ) );
    }

}