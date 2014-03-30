<?php

class JeroenVermeulen_Solarium_TestController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        echo 'Solarium library version: ' . Solarium\Client::VERSION . ' - ';

        $config = array(
            'endpoint' => array(
                'localhost' => array(
                    'host' => '54.186.239.231',
                    'port' => 8983,
                    'path' => '/solr/',
                )
            )
        );

// create a client instance
        $client = new Solarium\Client($config);

// create a ping query
        $ping = $client->createPing();

// execute the ping query
        try {
            $result = $client->ping($ping);
            echo 'Ping query successful';
            echo '<br/><pre>';
            var_dump($result->getData());
        } catch (Solarium\Exception $e) {
            echo 'Ping query failed';
        }
    }

}
