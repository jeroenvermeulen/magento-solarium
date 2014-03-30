<?php

class JeroenVermeulen_Solarium_Model_Observer_Autoloader extends Varien_Event_Observer {

    function controllerFrontInitBefore( $event ) {
        spl_autoload_register(array($this, 'load'));
    }

    public static function load($class)
    {
        if ( preg_match( '#^(Solarium|Symfony\\\\Component\\\\EventDispatcher)#', $class ) ) {
            require( str_replace( '\\', '/', $class ) . '.php');
        }
    }

}