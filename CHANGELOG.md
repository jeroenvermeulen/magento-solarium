# Change Log

### v1.0.1-beta

  * Initial Magento Connect release
  * Solarium version: 3.2.0
  * Symfony EventDispatcher version: 2.4.2

### v1.1.0-beta

  * Fix for issue #1: Solr xml illegal character issue on Magento indexing
  * Added auto correct for search terms
  * Improved error catching
  * Added configurable timeout
  * Added confirmation after rebuilding Solr index
  * Improved error catching
  * Removed some unnecessary settings from solrconfig.xml

### v1.2.0-beta

  * Improved Configuration Settings
  * Enable/Disable for separate storeviews is fixed
  * Added version info to Configuration
  * Added licence info to Solarium + explanation about dependencies
  * Added 'df' param to ping and query
  * Improved error messages
  * Separate timeout for search
  * Updated to work from Solr 3.1.0
  * Removed some unnecessary stuff from solrconfig.xml
  * Improved index rebuild to send 100 products at once.

### v1.2.1-beta

  * Small change to composer.json: the licence code

### v1.3.0-beta

  * Improved indexing based on Solarium's BufferedAdd Plugin. Thanks @basdenooijer.
  * Extended version info
  * Improved timeouts when Solr is not responding
  * Fix for enabling/disabling Solr search per storeview
  * Added spellcheck.alternativeTermCount=1. Thanks @toonvd.
  * Improved error handling, thanks @MikeYV
  * If no products found, don't generate error but consider OK. Thanks @MikeYV

### v1.3.1-beta

  * Fix for issue #8: Fatal error when using shell/indexer.php

### v1.3.2-beta

  * Fix for issue #9: Class not found when using shell/indexer.php in Magento 1.7

### v1.4.0-beta

  * Added possibility to use http authentication in Solr connection. Thanks @toonvd.
  * Added Solr based auto suggestion. Thanks @toonvd.
  * Issue #11 Detect empty index, fallback to Magento Fulltext Search.

### v1.4.1-beta

  * Fixed issue #13: Autocomplete should filter on store