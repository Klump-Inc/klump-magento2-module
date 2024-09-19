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
php bin/magento module:enable Klump_Bnpl --clear-static-content
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```
When enabled, navigate to *Magento Admin* under `Stores/Configuration/Sales/PaymentMethods` for settings

### Development:
```bash
php bin/magento setup:di:compile
php bin/magento cache:clean
php bin/magento cache:flush
```

## Community

If you are a developer, please join our Developer Community on [Slack](https://slack.klumpdevelopers.com).
