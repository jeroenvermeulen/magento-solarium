Solr search extension for Magento
================
[![Solr](https://lucene.apache.org/images/solr.png)](https://lucene.apache.org/solr/)
[![Solarium](http://www.raspberry.nl/wp-content/uploads/2011/12/solarium.gif)](http://www.solarium-project.org/)
[![Magento](http://21inspired.com/wp-content/uploads/2010/01/magento-logo-1.jpg)](http://magento.com/)

The default MySQL Fulltext search is not performing very well on more serious shops. It is slow and the results aren't very relevant. Apache's Solr does a much better job in delivering fast and relevant results. I have used the Solarium PHP library to build Solr into Magento CE. If you have some funds available for improvements, pleas contact me.

This extension is also on [Magento Connect](http://www.magentocommerce.com/magento-connect/solr-search-based-on-solarium.html).

## Features

  * Free and Open Source
  * Fast results
  * Can handle high number of products
  * Autocomplete search query while typing
  * Autocorrect typos in search terms
  * Solr 3 & 4 support
  * Solr HTTP authentication support

## Requirements

  * Magento Community Edition 1.6+
  * A working Solr 3.x or 4.x server

## Installation & Usage

  * Inside this repository you can find the [solrconfig.xml](https://github.com/jeroenvermeulen/magento-solarium/blob/master/app/code/community/JeroenVermeulen/Solarium/docs/solrconfig.xml) and [schema.xml](https://github.com/jeroenvermeulen/magento-solarium/blob/master/app/code/community/JeroenVermeulen/Solarium/docs/schema.xml)
  * After installing this extension:
    * Clear the Configuration cache
    * Log out of the Admin
    * Log in on the Admin again
    * Configure and enable it via: *System > Configuration > CATALOG > Solarium Search*
    * Reindex the *Catalog Search Index*, this will update Solr (if enabled)

## Support

A lot of info can be found in our [Wiki Pages](https://github.com/jeroenvermeulen/magento-solarium/wiki)

If you have an issue, you can open a bug report in [GitHub's issue tracker](https://github.com/jeroenvermeulen/magento-solarium/issues).
