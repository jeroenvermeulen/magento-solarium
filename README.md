magento-solarium
================

The default MySQL Fulltext search is not performing very well on more serious shops. It is slow and the results aren't very relevant. Apache's Solr does a much better job in delivering fast and relevant results. I have used the Solarium PHP library to build Solr into Magento CE. In the future I plan to build autocorrect of search terms and other futures. If you have some funds available for these improvements, pleas contact me.

## Requirements

  * Magento Community Edition 1.6+
  * Solr 4.3+

## Installation & Usage

  * Inside this repository you can find the [solrconfig.xml](https://github.com/jeroenvermeulen/magento-solarium/blob/master/app/code/community/JeroenVermeulen/Solarium/docs/solrconfig.xml) and [schema.xml](https://github.com/jeroenvermeulen/magento-solarium/blob/master/app/code/community/JeroenVermeulen/Solarium/docs/schema.xml)
  * After installing this extension you can configure it via: *System > Configuration > CATALOG > Solarium Search.*

## Support

If you have an issue, you can open a bug report in [GitHub's issue tracker](https://github.com/jeroenvermeulen/magento-solarium/issues).