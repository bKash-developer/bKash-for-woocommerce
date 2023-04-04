# Wordpress (WooCommerce) Plugin For PGW
```
- User Story and Features
- Prepared By: Prabal Mallick (Senior Officer, Product Delivery, Merchant Products, Product & Technology)
- Updated: 04th April 2023
- Version: 1.0.9
```
### Introduction
Using this plugin, merchant can setup bKash payment gateway with selected product. Then merchant can start collecting payment from bKash customer for any requested service from merchant website.

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
* Checkout Sale (Regular Checkout)
* Checkout Authorised and Capture Payment
* Tokenized - Without Agreement
* Tokenized - With Agreement Only
* Tokenized - Agreement and Without Agreement

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

### Actions for Merchant:
   * ##### For Checkout:
         - Can setup bKash payment gateway.
         - Can manage credentials for bKash payment gateway.
         - Can set intent of payment modes. (Sale or Authorize)
         - Can view all transactions - online and offline (using webhook integration).
         - Can transfer money within wallet parts (Collection, Disbursement).
         - Can refund a transaction.
         - Can disburse money to bKash customer wallet.
         - Can search a transaction from it's merchant wallet.
   * ##### For Tokenization:
         - Can setup bKash payment gateway.
         - Can manage credentials for bKash payment gateway.
         - Can set intent of payment modes. (Sale or Authorize)
         - Can view all transactions - online and offline (using webhook integration).
         - Can refund a transaction.
         - Can search a transaction from it's merchant wallet.
         - Can view and delete all agreements from customers.

## Guids:
### Steps to enable

* Download and Setup Wordpress
* From plugin menu → add Plugin, one can install WooCommerce Plugin for Wordpress
* Activate WooCommerce Plugin and Set up WooCommerce related settings.
* Install WooCommerce bKash plugin from zip file by uploading it on Wordpress plugin menu.
* Activate the plugin, and go to WooCommerce Setting → Payments, find bKash PGW there and set it up with relevant information.
* Now bKash PGW should be available for use.
* Important! Change Permalink from Wordpress Settings → Reading to Post Name (etc).
* Align .htaccess file accordingly with the guidance of Wordpress on permalink setting page.


## Video Tutorials:


* **Download & Install bKash WooCommerce Payment Plugin**
  * Visit this link for the tutorial - https://drive.google.com/file/d/1ED5f1pfvAXrk2dZShUvvIIHEIUcIQ0Sg/view?usp=sharing
* **Configure bKash Checkout WooCommerce Part-1**
  * Visit this link for the tutorial - https://drive.google.com/file/d/15MlWEjkqus6k2LHv01nfUyCgsg5VSVWp/view?usp=sharing
* **Process of bKash Checkout API Log File Creation Part 2.1**
  * Visit this link for the tutorial - https://drive.google.com/file/d/1K0LbRzGWLSZfnieuCWmG5dAbRc-gicCD/view?usp=sharing
* **Process of bKash Checkout-Refund API Log Part-2.2**
  * Visit this link for the tutorial - https://drive.google.com/file/d/1TIqD24xIG9YvyjME0Y4sdJYYFPVXvDmy/view?usp=sharing
* **Process of bKash Checkout-Search Transaction API Log Part-2.3**
  * Visit this link for the tutorial - https://drive.google.com/file/d/1kFX45QwvxHwZJ9MAUJPXTYpTRQGBg5RF/view?usp=sharing
* **Process of bKash Checkout-Error Case Implementation Part-2.4**
  * Visit this link for the tutorial - https://drive.google.com/file/d/11DLX8KrstzHJj3w2UX89lMbTUQzQ5R7x/view?usp=sharing
* **Process of bKash Checkout-Generate Final Log File (Checkout, Search, Error Case & Refund) Part-2.5**
  * Visit this link for the tutorial - https://drive.google.com/file/d/15hXTl-KG2RwI94JX3yBBUURQq9X5G2g8/view?usp=sharing


### Webhook configuration process:
   Share webhook URL to bKash by collecting from WooCommerce settings for bKash payment gateway.
   
### Authorisation (Capture/Void) process: 
   To capture a payment collected from customer, merchant has to change order status from ON-HOLD to COMPLETED.
   To void a payment initiate by merchant, merchant has to change order status from ON-HOLD to CANCELLED.
   
   If merchant wants to handle Capture/Void scenario programatically, use standard WooCommerce API/Hooks to change the status.

### Additional Features

* Logging of request and response traces, so that file can be prepared for SO validation. (In WooCommerce Status Page you can find logs tab and Search for bKash_PGW_API_LOG_<current date> file.
* Refund can also be initiated from WooCommerce Orders actions.
* Authorised and Capture action can be performed by changing order status On Hold → Completed.
* All transactions and history list are made using pagination, so on each page 10 entries can be viewed.
