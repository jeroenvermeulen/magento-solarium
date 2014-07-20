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
 * Class JeroenVermeulen_Solarium_Model_SelfTest
 */
class JeroenVermeulen_Solarium_Model_SelfTest
{
    protected $message;
    const WIKI_URL     = 'https://github.com/jeroenvermeulen/magento-solarium/wiki';
    const TEST_STOREID = 999999;

    /**
     * @param array $param - Connection parameters
     * @return string - Result HTML to show in admin.
     */
    function test( $param )
    {
        $this->message = '';
        $ok            = true;
        $allOk         = true;
        $insertOk      = true;
        $helper        = Mage::helper( 'jeroenvermeulen_solarium' );
        try {
            $testProductId    = intval( time() . getmypid() );
            $testProduct      = 'SELF TEST ENTRY ' . $testProductId;
            $testAutoComplete = substr( $testProductId, 0, -3 );
            $testAutoCorrect  = 'SELF T3ST ENTRY ' . $testProductId;
            $defaultParam     = array(
                'host'     => '',
                'port'     => '',
                'path'     => '',
                'core'     => '',
                'auth'     => false,
                'username' => '',
            );
            $param         = array_merge( $defaultParam, $param );
            $config        = array(
                'general/enabled'                  => true,
                'server/host'                      => $param[ 'host' ],
                'server/port'                      => $param[ 'port' ],
                'server/path'                      => $param[ 'path' ],
                'server/core'                      => $param[ 'core' ],
                'server/requires_authentication'   => $param[ 'auth' ],
                'server/username'                  => $param[ 'username' ],
                'server/search_timeout'            => $param[ 'timeout' ],
                'results/autocomplete_suggestions' => 25,
                'results/autocorrect'              => 1,
                'results/did_you_mean'             => 1,
                'results/did_you_mean_suggestions' => 5,
                'results/max'                      => 100
            );
            if (!preg_match( '|^\*+$|', $param['password'] )) {
                $config[ 'server/password' ] = Mage::helper( 'core' )->encrypt( $param['password'] );
            }
            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            if ($ok) {
                $engine = Mage::getModel( 'jeroenvermeulen_solarium/engine', $config );
                $ok     = $engine->isWorking();
                $allOk  = $allOk and $ok;
                $this->addMessage( 'Connection to Solr', $ok, 'Please check the connection settings.' );
            }
            if ($ok) {
                $ok     = $engine->ping();
                $allOk  = $allOk and $ok;
                $this->addMessage( 'Ping Solr', $ok, 'Please check the connection settings.' );
            }
            if ($ok) {
                $ok     = $engine->getClient()->checkMinimal( '3.0' );
                $allOk  = $allOk and $ok;
                $this->addMessage( 'Check Solr version', $ok, 'Solr server version must be 3.0 or greater.' );
            }
            if ($ok) {
                /** @var Solarium\Plugin\BufferedAdd\BufferedAdd $buffer */
                $buffer = $engine->getClient()->getPlugin( 'bufferedadd' );
                $buffer->setEndpoint( 'update' );
                $data = array(
                    'id'         => 'test' . $testProductId,
                    'product_id' => $testProductId,
                    'store_id'   => $this::TEST_STOREID,
                    'text'       => $testProduct
                );
                $buffer->createDocument( $data );
                $solariumResult = $buffer->commit();
                $engine->optimize(); // ignore result
                $insertOk = $engine->processResult( $solariumResult, 'flushing buffered add' );
                $allOk    = $insertOk and $ok;
                $this->addMessage(
                     'Inserting test entry in Solr',
                         $insertOk,
                         'Make sure you install the "schema.xml" and "solrconfig.xml"
                          provided by this extension, and restart Solr.'
                );
            }
            if ($insertOk) {
                $searchResult = $engine->search( $this::TEST_STOREID, $testProduct, $engine::SEARCH_TYPE_LITERAL );
                $resultDocs   = $searchResult->getResultProducts();
                $ok           = false;
                $allOk        = $allOk and $ok;
                foreach ($resultDocs as $resultDoc) {
                    if ($testProductId == $resultDoc[ 'product_id' ]) {
                        $ok = true;
                    }
                }
                $this->addMessage( 'Search for test entry', $ok );
                $autoSuggest = $engine->getAutoSuggestions( $this::TEST_STOREID, $testAutoComplete );
                $ok          = false;
                $allOk       = $allOk and $ok;
                foreach ($autoSuggest as $term => $count) {
                    if ( $testProductId == $term && 0 < $count ) {
                        $ok = true;
                    }
                }
                $this->addMessage( 'Test Autocomplete', $ok );

                $searchResult = $engine->search( $this::TEST_STOREID, $testAutoCorrect, $engine::SEARCH_TYPE_LITERAL );
                $resultDocs   = $searchResult->getResultProducts();
                $ok           = false;
                $allOk        = $allOk and $ok;
                foreach ($resultDocs as $resultDoc) {
                    if ($testProductId == $resultDoc[ 'product_id' ]) {
                        $ok = true;
                    }
                }
                $this->addMessage( 'Test Suggest + Correct Typos', $ok );

                $ok     = $engine->cleanIndex( $this::TEST_STOREID, array( $testProductId ) );
                $allOk  = $allOk and $ok;
                $this->addMessage( 'Deleting test entry from Solr', $ok );
            }
        } catch ( Exception $e ) {
            $allOk = false;
            $this->message .= '<tr>';
            $this->message .= '<td class="label error">ERROR</td>';
            $this->message .= '<td class="value error">' . $e->getMessage() . '</td>';
            $this->message .= '</tr>';
        }
        if (!$allOk) {
            $this->message .= '<tr>';
            $wikiText = $helper->__( 'You can find the Installation Instructions and FAQ in [our Wiki on GitHub].' );
            $wikiText = str_replace( '[', '<a href="' . $this::WIKI_URL . '" target="_blank">', $wikiText );
            $wikiText = str_replace( ']', '</a>', $wikiText );
            $this->message .= '<td class="value" colspan="3"><strong>' . $wikiText . '</strong></td>';
            $this->message .= '</tr>';
        }
        return $this->message;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Adds a test result to the result HTML message.
     *
     * @param string $action
     * @param bool $success
     * @param string $solveInfo
     */
    protected
    function addMessage(
        $action,
        $success,
        $solveInfo = ''
    ) {
        $helper = Mage::helper( 'jeroenvermeulen_solarium' );
        $this->message .= '<tr>';
        $this->message .= '<td class="label">' . htmlspecialchars( $helper->__( $action ) ) . '</td>';
        if ($success) {
            $this->message .= '<td class="value available">' . $helper->__( 'Success' ) . '</td>';
            $this->message .= '<td>&nbsp;</td>';
        } else {
            $this->message .= '<td class="value error">' . $helper->__( 'FAILED' ) . '</td>';
            $this->message .= '<td><strong>' . ( empty( $solveInfo ) ? '&nbsp;' : $helper->__(
                                                                                         $solveInfo
                ) ) . '</strong></td>';
        }
        $this->message .= '</tr>' . "\n";
    }

}