# Magento 2 Tuna Module

    ``tuna/module-tunagateway``

 - [Installation](#markdown-header-installation)

## Installation
\* = in production please use the `--keep-generated` option

### Type 1: Zip file

 - Unzip the zip file in `app/code/Tuna`
 - Enable the module by running `php bin/magento module:enable Tuna_TunaGateway`
 - Apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

### Type 2: Composer

 - Install the module composer by running `composer require tuna/module-tunagateway`
 - enable the module by running `php bin/magento module:enable Tuna_TunaGateway`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`


