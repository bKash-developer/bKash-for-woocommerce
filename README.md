# Wordpress (WooCommerce) Plugin For PGW

User Story and Features
Prepared By: Md. Shahnawaz Ahmed (Asst. Lead Engineer, Solution Engineering, P&T)
Dated: 13th April 2021
Version: 1.0.0

### User Story

##### User:

As an User I can pay using my bKash account on merchant website. They can remember my account number.

##### Merchant:

As a merchant I can set up my payment option in WooCommerce platform with bKash. Also I can choose the payment integration type and intent so that customer can pay using one of the methods. I need a dashboard where I can see all my transactions, agreements, balances. Also I can do transfer money internally and externally. On my website customer can do the refund.

### Features

* ###### Engaged with User Type
    * Merchant Admin (Settings/Admin Interface)
    * Customer (Payment Interface)
* ###### Actions for User Type
    * Customer - Can Pay, Can request to remember his/her account, Can unbind that remember.
    * Merchant - Can setup WooCommerce Payment facility, Can see/do/delete all allowed PGW products activities.

### Requirements
* Merchant Mobile Number should be registered with bKash as merchant account, also onboarded on PGW.
* four Credentials will be required, App Key, App Secret, Username, Password.
* Merchant has to enable debug in Wordpress in order to view logs.
* Compatibility
* WooCommerce 2.2 or above version is required
* Wordpress 4.0 or above version is required

### Environments
    This plugin supports below environments
        * Sandbox
        * Production

### Steps to enable

* Download and Setup Wordpress
* From plugin menu → add Plugin, one can install WooCommerce Plugin for Wordpress
* Activate WooCommerce Plugin and Set up WooCommerce related settings.
* Install WooCommerce bKash plugin from zip file by uploading it on Wordpress plugin menu.
* Activate the plugin, and go to WooCommerce Setting → Payments, find bKash PGW there and set it up with relevant information.
* Now bKash PGW should be available for use.

### Available Payment Methods in this plugin
* Checkout Sale (Regular Checkout)
* Checkout Authorised and Capture Payment
* Tokenised Non-Agreement
* Tokenised Agreement Based
* Tokenised Both
* B2C Payout
* Intra Account Transfer
* Web-hooks
* Refund
* Search Transaction

### Available Menus to Merchant
* Transaction List
* Search a transaction
* Check Balances
* Intra account transfer
* Disburse Money
* Transfer History
* Refund a Transaction
* Agreements
* Web-hooks

### Additional Features

* Logging of request and response traces, so that file can be prepared for SO validation.
* Refund can also be initiated from WooCommerce Orders actions.
* Authorised and Capture action can be performed by changing order status On Hold → Completed.
* All transactions and history list are made using pagination, so on each page 10 entries can be viewed.
### ScreenShots

### Summary

Using this plugin the pain point of merchant will be eased. Currently merchant has to hire developers and share request and response result by coding and collecting from script. Also they has to maintain both environment (Sandbox and Production) to sync with integration process. This plugin will minimise these steps and can onboard merchant on PGW on the fly.