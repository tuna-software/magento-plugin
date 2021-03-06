# Magento 2 Tuna Module
This is the official magento 2 module for the [tuna payment gateway](https://tuna.uy).

    tuna/module-tunagateway

## Installation
This module is available through [*Packagist*](https://packagist.org/packages/tuna/module-tunagateway), below follow instructions to install either through a zip file.

\* = in production please use the `--keep-generated` option

### Downloaded Zip file

 - Unzip the zip file in `app/code/Tuna` like `app/code/Tuna/TunaGateway/*`
 - Enable the module by running `php bin/magento module:enable Tuna_TunaGateway`
 - Apply database updates by running `php bin/magento setup:upgrade`
 - force rebuild by running `php bin/magento setup:di:compile`
 - Refresh a static content by running `php bin/magento setup:static-content:deploy -f en_US pt_BR`
 - Flush the cache by running `php bin/magento cache:flush`
 - Execute the command 2 times to remove all content on genarated folder: `rm -rf generated/*`