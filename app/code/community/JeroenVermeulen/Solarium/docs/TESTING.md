Testing Solarium Search
================

## Introduction

  * During all tests monitor the PHP error log, `var/log/system.log` and `var/log/exception.log`

## General flow

  * Admin: System > Configuration > CATALOG > Solarium search > Solr Server > Test Connection
  * Admin: System > Index Management > Catalog Search Index > Reindex Data
  * Frontend: Search with small typo
  * The search must return results, correcting the typo

## Auto Complete

  * Frontend: Type the first 3 letters of longer word from a product title
  * It must show a box suggesting the whole word

## Multi Word Auto Complete

  * Frontend: Type a whole word and then one letter from a product title
  * It must show a box suggesting multiple words

## Product Save

  * Change the title of a single product to some new unique string, save
  * Search for the unique string in the frontend
  * It must return the product

## Product Delete

  * Pick a single product
  * Remember the title
  * Search for the title in the frontend
  * The search must return the product
  * Delete the product in the backend
  * Search for the title in the frontend again
  * It must no longer return the product

## Searching for words which are apart in the text

  * Search for two words which are in a product title but not next to each other
  * The Auto Complete will not work, but searching must return results

