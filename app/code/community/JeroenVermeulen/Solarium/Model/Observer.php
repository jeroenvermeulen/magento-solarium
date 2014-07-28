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
 * Class JeroenVermeulen_Solarium_Model_Observer
 */
class JeroenVermeulen_Solarium_Model_Observer extends Varien_Event_Observer
{
    protected $_reindexQueue = array();

    /**
     * This is an observer function for the event 'controller_front_init_before'.
     * It prepends our autoloader, so we can load the extra libraries.
     *
     * @param Varien_Event_Observer $observer
     */
    public
    function controllerFrontInitBefore(
        /** @noinspection PhpUnusedParameterInspection */
        $observer
    ) {
        /** @var JeroenVermeulen_Solarium_Helper_Autoloader $autoLoader */
        $autoLoader = Mage::helper( 'jeroenvermeulen_solarium/autoloader' );
        $autoLoader->register();
    }

    /**
     * This is an observer function for the event 'shell_reindex_init_process'.
     * It prepends our autoloader, so we can load the extra libraries.
     * When the shell script indexer.php is used, the "controller_front_init_before" event is not dispatched.
     *
     * @param Varien_Event_Observer $observer
     */
    public
    function shellReindexInitProcess(
        /** @noinspection PhpUnusedParameterInspection */
        $observer
    ) {
        /** @var JeroenVermeulen_Solarium_Helper_Autoloader $autoLoader */
        $autoLoader = Mage::helper( 'jeroenvermeulen_solarium/autoloader' );
        $autoLoader->register();
    }

    /**
     * This is an observer function for the event 'after_reindex_process_catalogsearch_fulltext'.
     * It starts a full Solr reindex.
     *
     * @param Varien_Event_Observer $observer
     */
    public
    function afterReindexProcessCatalogsearchFulltext(
        /** @noinspection PhpUnusedParameterInspection */
        $observer
    ) {
        if (JeroenVermeulen_Solarium_Model_Engine::isEnabled()) {
            /** @var JeroenVermeulen_Solarium_Helper_Data $helper */
            $helper = Mage::helper( 'jeroenvermeulen_solarium' );
            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            $engine    = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            $startTime = microtime( true );
            $ok        = $engine->rebuildIndex( null, null, true );
            $timeUsed  = microtime( true ) - $startTime;
            if ($ok) {
                $message = $helper->__( 'Solr Index was rebuilt in %s seconds.', sprintf( '%.02f', $timeUsed ) );
                if ($engine->isShellScript()) {
                    echo $message . "\n";
                } else {
                    Mage::getSingleton( 'adminhtml/session' )->addSuccess( $message );
                }
            } else {
                $message   = $helper->__( 'Error reindexing Solr: %s', $engine->getLastError() );
                $configUrl = Mage::helper( "adminhtml" )->getUrl(
                                 "adminhtml/system_config/edit/section/jeroenvermeulen_solarium/"
                );
                if ($engine->isShellScript()) {
                    $message .= "\nPlease check the Solr server configuration via the Admin:";
                    $message .= "\nSystem > Configuration > CATALOG > Solarium search";
                    echo $message . "\n";
                } else {
                    $notice = $helper->__( 'Please check the [Solr server configuration].');
                    $notice = str_replace( '[',
                                           sprintf('<!--suppress HtmlUnknownTarget --><a href="%s">', $configUrl),
                                           $notice );
                    $notice = str_replace( ']', '</a>', $notice );
                    $message .= '<br />' . $notice;
                    Mage::getSingleton( 'adminhtml/session' )->addError( $message );
                }
            }
        }
    }

    /**
     * This is an observer function for the event 'catalogsearch_index_process_start'.
     * If we get passed specific products to be re-indexed, we add them to the queue.
     *
     * @param Varien_Event_Observer $observer
     */
    public
    function catalogsearchIndexProcessStart(
        $observer
    ) {
        $storeId    = intval( $observer->getData( 'store_id' ) );
        $productIds = $observer->getData( 'product_ids' );
        if (!empty( $productIds )) {
            if (!isset( $this->_reindexQueue[ $storeId ] )) {
                $this->_reindexQueue[ $storeId ] = array();
            }
            $this->_reindexQueue[ $storeId ] = array_merge( $this->_reindexQueue[ $storeId ], $productIds );
        }
    }

    /**
     * This is an observer function for the event 'catalogsearch_index_process_complete'.
     * Magento has finished indexing, so we process the queue.
     *
     * @param Varien_Event_Observer $observer
     */
    public
    function catalogsearchIndexProcessComplete(
        /** @noinspection PhpUnusedParameterInspection */
        $observer
    ) {
        if (!empty( $this->_reindexQueue )) {
            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            $engine = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            foreach ($this->_reindexQueue as $storeId => $productIds) {
                // $storeId can be 0, which means all stores.
                $engine->cleanIndex( $storeId, $productIds );
                $engine->rebuildIndex( $storeId, $productIds );
            }
        }
    }

    /**
     * This is an observer function for the event 'adminhtml_block_html_before'.
     * If the block is the grid for the "Index Management" we update the description of the "Catalog Search Index"
     *
     * @param Varien_Event_Observer $observer
     */
    public
    function adminhtmlBlockHtmlBefore(
        $observer
    ) {
        $block = $observer->getData( 'block' );
        if (is_a( $block, 'Mage_Index_Block_Adminhtml_Process_Grid' )) {
            /** @var Mage_Index_Block_Adminhtml_Process_Grid $block */
            $engine = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            if ( $engine->isWorking() ) {
                $collection = $block->getCollection();
                foreach ($collection as $item) {
                    /** @var Mage_Index_Model_Process $item */
                    if ('catalogsearch_fulltext' == $item->getIndexerCode()) {
                        $item->setData( 'description',
                                        'Rebuild Catalog product fulltext search index - POWERED BY SOLARIUM' );
                    }
                }
            }
        }
    }

    /**
     * This is an observer function for the event 'start_index_events_catalog_product_delete'.
     * It gets called after one or more products have been deleted.
     *
     * @param Varien_Event_Observer $observer
     */
    public
    function startIndexEventsCatalogProductDelete(
        /** @noinspection PhpUnusedParameterInspection */
        $observer
    ) {
        $productIds = array();
        $events     = Mage::getSingleton( 'index/indexer' )->getProcessByCode(
                          'catalogsearch_fulltext'
        )->getUnprocessedEventsCollection();
        /** @var Mage_Index_Model_Event $event */
        foreach ($events as $event) {
            if ('catalog_product' == $event->getEntity() && 'delete' == $event->getType()) {
                $productId = $event->getEntityPk();
                if ($productId) {
                    $productIds[ ] = $productId;
                }
            }
        }
        if (!empty( $productIds ) && JeroenVermeulen_Solarium_Model_Engine::isEnabled()) {
            /** @var JeroenVermeulen_Solarium_Model_Engine $engine */
            $engine = Mage::getSingleton( 'jeroenvermeulen_solarium/engine' );
            $engine->cleanIndex( null, $productIds );
        }
    }

}