## Klump BNPL - Magento 2 Module

Klump BNPL payment gateway Magento2 module

### Manual Installation

*  Click the Download Zip button and save to your system.
*  Unzip the archived file.
*  Create a __Klump/Bnpl__ folder in your Magento project's __app/code__ directory.
*  Move the unzipped files into your project's __app/code/Klump/Bnpl__ directory.

### To enable the module:
From your command line, in your magento root directory, run
```bash
php bin/magento module:enable Klump_Payment --clear-static-content
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```
When enabled, navigate to *Magento Admin* under `Stores/Configuration/Sales/PaymentMethods` for settings

### Development:
```bash
bin/magento setup:di:compile
bin/magento cache:clean
bin/magento cache:flush
bin/magento indexer:reindex
```

To Disable the module
```bash
bin/magento module:disable Klump_Payment --clear-static-content
```

## Community

If you are a developer, please join our Developer Community on [Slack](https://slack.klumpdevelopers.com).
