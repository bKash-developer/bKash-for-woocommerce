=== bKash For WooCommerce ===
Contributors: bkashpayment
Tags: bKash,bKashPayment,bKashForWooCommerce,online payment,ecommerce,woocommerce
Requires at least: 4.0
Tested up to: 5.9.3
Stable tag: 1.0.5
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

bKash for WooCommerce plugin is a Wordpress based WooCommerce plugin. bKash merchants can install it as a plugin at their online Wordpress store.

== Description ==

bKash Payment Gateway provides range of payment solutions to merchants of the online sphere. This is the official bKash PGW plugin, by installing this plugin you’ll be able to integrate the bKash PGW, into your WooCommerce Webshop. The bKash PGW plugin extends WooCommerce allowing you to take payments directly on your store via bKash’s API. This will only take a few minutes. If the plugin is successfully installed your customers will then be able to checkout with bKash payment gateway.
bKash has different payment option available, by installing this plugin merchant will be able to integrate bKash “Regular Checkout and Tokenized Checkout”.
Regular Checkout: On the checkout page, the plugin loads an iFrame which is provided by bKash, customer will complete the payment by entering their bKash account credentials (Account Number, Verification Code, PIN).
Tokenized Checkout: bKash's tokenized checkout provides the customers a more convenient way of payment. Using this product, the customers can create an agreement in merchant websites/apps that for further payment using bKash, they will only use bKash wallet PIN. In this case the merchant system needs to store these agreements against different user accounts. This provides a faster and convenient payment opportunity for both the merchant and the customer.
bKash PGW plugin is available for Merchants in Bangladesh, to accepts payments from customer you need to be a bKash merchant first.


### Technical Requirements:
* Wordpress (4.0 or above).
* WooCommerce (2.0 or above).
* PHP (7.0 or above)
* MySQL (5.6 or above)
* Change in Permalink so that .htaccess can be rewritable. (https://wpengine.com/resources/wordpress-permalinks/)
* File write permission for wp-content directory.

### Non-Technical Requirements:
* Active bKash Merchant Wallet.
* bKash payment gateway credentials (Sandbox and Production)


### Available Environments
This plugin supports below environments of bKash payment gateway.
    * Sandbox
    * Production

### Available Payment Methods for bKash Payment Gateway in this plugin
* Checkout - Sale (Regular Checkout)
* Checkout - Authorised and Capture Payment
* Tokenized - Without Agreement
* Tokenized - With Agreement Only
* Tokenized - Agreement and Without Agreement
* Tokenized -  Authorised and Capture Payment

### Additional Features of different bKash payment gateway products.
* Merchant Wallet Balance Check (In Checkout Only)
* B2C Payout (In Checkout Only)
* Intra Account Transfer
* Web-hooks
* Refund
* Search Transaction

### Available Menus for Merchant (Based on selected product, Checkout or Tokenized)
* Transaction List
* Search a transaction
* Check Balances
* Intra account transfer
* Disburse Money
* Transfer History
* Refund a Transaction
* Agreements
* Web-hooks
* Payment Settings

### Actions for Merchant:
   *  For Checkout:
         - Can make a payment.
         - Can setup bKash payment gateway.
         - Can manage credentials for bKash payment gateway.
         - Can set intent of payment modes. (Sale or Authorise)
         - Can view all transactions - online and offline (using webhook integration).
         - Can transfer money within wallet parts (Collection, Disbursement).
         - Can refund a transaction.
         - Can disburse money to bKash customer wallet.
         - Can search a transaction from it's merchant wallet.
   * For Tokenization:
         - Can make a payment
         - Can setup bKash payment gateway.
         - Can manage credentials for bKash payment gateway.
         - Can set intent of payment modes. (Sale or Authorise)
         - Can view all transactions - online and offline (using webhook integration).
         - Can refund a transaction.
         - Can search a transaction from it's merchant wallet.
         - Can view and delete all agreements from customers.

== Installation ==

This section describes how to install the plugin and get it working.

* Manual Installation
    1. Upload `bkash-for-wooCommerce-main` folder to the `/wp-content/plugins/` directory
    1. Activate the plugin through the 'Plugins' menu in WordPress
    1. Go to WooCommerce Settings -> Payment -> Select bKash Payment and process for setup.
    1. Set your desired setup and set bKash provided credentials (App Key, App Secret, Username and Password).

* Install by Searching
    1. Go to Plugins
    1. Click Add new button
    1. Search for bKash For WooCommerce
    1. Click install and activate.

### Configuration
1. Log into your WordPress admin and activate the bKash plugin in WordPress Plugin Manager.
1. Log into your WooCommerce Webstore account, navigate to Settings and click the Payment tab.
1. Scroll down to the Payment page and go to the manage option of bKash Payment Gateway under Gateway Display.
1. Click on bKash Payment Gateway to edit the settings. If you do not see bKash Payment Gateway in the list, make sure you have activated the plugin in the WordPress Plugin Manager.


* Fill in the following credentials.
    - Enable - Enable bKash PGW
    - Title – bKash Payment Gateway
    - Description - Pay with bKash PGW.
    - Integration Type – Checkout/Checkout URL/Tokenized (With Agreement)/Tokenized (Without Agreement)
    - Intent – Sale/Authorized (Intent of the payment. For checkout the value should be "sale".)
    - API Version -  v1.2.0-beta
    - B2C Payout – Enable B2C Payout (Using this solution a specific amount of fund can be transferred to a receiver's personal bKash account)
    - Debug Log – Enable logging (If you need API response)
    - Webhook – Enable Webhook listener (You will need to share this webhook link with bKash team)
    - Sandbox – Enable if you need to do sandbox testing, otherwise live credentials field will be available there.
    - Application Key - Sandbox/Production Key
    - Application Secrete - Sandbox/Production Secrete
    - UserName – Sandbox/Production username shared by bKash
    - Password - Sandbox/Production Password shared by bKash
    - Your bKash payment gateway is enabled. Now you can accept payment through bKash.

### Sandbox Result:
For sandbox validation, you may need to share sandbox responses with bKash integration team.
To do that navigate to Woo-commerce and click Status Tab. Scroll down and go to Log tab,
there you will find a log file called bKash sandbox log. Copy sandbox results which you required from that log
and share it through a Microsoft docs file.



### Change Permalink
* Important! Change Permalink from Wordpress Settings → Reading to Post Name (etc).
* Align .htaccess file accordingly with the guidance of Wordpress on permalink setting page.

### Webhook configuration process:
Share webhook URL to bKash team by collecting from WooCommerce settings for bKash payment gateway.

### Authorisation (Capture/Void) process:
To capture a payment collected from customer, merchant has to change order status from ON-HOLD to COMPLETED.
To void a payment initiate by merchant, merchant has to change order status from ON-HOLD to CANCELLED.

If merchant wants to handle Capture/Void scenario programmatically, use standard WooCommerce API/Hooks to change the status.

### Additional Features

* Logging of request and response traces, so that file can be prepared for SO validation.
    (In WooCommerce Status Page you can find logs tab and Search for bKash_PGW_API_LOG_<current date> file.
* Refund can also be initiated from WooCommerce Orders actions.
* Authorised and Capture action can be performed by changing order status On Hold → Completed.
* All transactions and history list are made using pagination, so on each page 10 entries can be viewed.

== Frequently Asked Questions ==

= How can I get a bKash Merchant Account? =
Ans: You can register here – https://www.bkash.com/i-want-register/send-registration-request , Except this interested merchants can call bKash customer care for this. Then CS forwards the request to business team for acquisition.
= What bKash Payment methods are available through this plugin? =
Ans: Regular checkout and tokenized checkout and besides Refund, webhooks, search transaction will be available for every merchant.
= What does plugin cost? =
Ans: bKash PGW plugin has no setup fees, no monthly fees, no hidden costs: you only get charged when you will make the bank settlement!
= Does this plugin need SSL certificate? =
Ans: Yes! Whenever you go live, a SSL certificate must be installed on your site to use bKash plugin.
= Does this support sandbox mode for testing? =
Ans: Yes, we have sandbox environment for testing.
= Is there any manual available for this plugin? =
Ans: Yes, we have a detailed manual for this plugin, you can download it from here

== Screenshots ==
1. bKash payment method in Woocommerce payment method list
2. Settings page of bKash payment method
3. User paying with bKash Payment method
4. Transaction list for admin to check

== Changelog ==
= 1.0.5 =
* Escaped variables, added cancel payment, colored transaction list status.
= 1.0.4 =
* Minor bug fix of unwanted message during capturing a non-bKash payment.
= 1.0.3 =
* Added Search and Filter functionalities in list tables.
= 1.0.2 =
* Wordpress recommended optimisation finalisation
= 1.0.1 =
* Wordpress recommended optimisation
= 1.0.0 =
* First release with all features