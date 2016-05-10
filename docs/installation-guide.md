Prerequisite
------------

Php extension:

-   Soap

Installation
------------

The modules work on prestashop version 1.5.6.2 and 1.6. We can only gurantee that the module works with the standard checkout of Prestashop.

For general questions regarding installations please contact <support.solutions@payex.com> .

First you need to unzip the modules from the original zip file to your computer.

Now you should have the modules in their own zip file like this:

![](https://payex.github.io/PayEx.PrestaShop/media/image1.png)

Step 1:

First you need to know how to sign in as admin on your prestashop site.

Log on to your site using FTP/SFTP and go to the root folder where prestashop is located(this is usually public\_html). Look for a folder called admin(plus something more). Ex admin2545

Step 2:

Now go to your web browser and enter <http://yoursite.com/admin2545> as in this example and sign in as administrator.

![](https://payex.github.io/PayEx.PrestaShop/media/image2.png)

Step 3:

Find the menu bar item called “Modules” and click the first link in the drop down menu also called “Modules”

![](https://payex.github.io/PayEx.PrestaShop/media/image3.png)

Step 4:

Click the top right button ”Add a new module”

![](https://payex.github.io/PayEx.PrestaShop/media/image4.png)

Step 5:

Choose the module you want to upload to your site(must be a zip file or a tarball) and click upload

![](https://payex.github.io/PayEx.PrestaShop/media/image5.png)

Step 6:

Find the module in the list below(it usually scrolls down to your module when you have uploaded it) and click install

![](https://payex.github.io/PayEx.PrestaShop/media/image6.png)

Installation complete

Updating modules
----------------

When you have updated a module it is important that you go in to that module and click the “update settings” button. Otherwise the new settings will not take place.

Configuration
-------------

Payex General
-------------

![](https://payex.github.io/PayEx.PrestaShop/media/image7.png)

Account number: You can collect the account number in Payex Merchant Admin; for production mode: <https://secure.payex.com/Admin/Logon.aspx> and for test mode: <http://test-secure.payex.com/Admin/Logon.aspx> Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: To generate an encryption key click [here](#_How_to_generate)

Mode: Choose between test or live

Transaction type: Authorization is the standard transaction type, it requires a capture of the order. With Sale the amount ordered is processed immediately and withdrawn from the customers card. For more info contact PayEx support <support.solutions@payex.com>

Payment view: Which type of payment model you would like to use

When you’re finished click “update settings” and you’re done.

Payex factoring and part payment
--------------------------------

![](https://payex.github.io/PayEx.PrestaShop/media/image8.png)

Account number: You can collect the account number in Payex Merchant Admin; for production mode: <https://secure.payex.com/Admin/Logon.aspx> and for test mode: <http://test-secure.payex.com/Admin/Logon.aspx> Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: To generate an encryption key click [here](#_How_to_generate)

Mode: Choose between test or live

Payment type: You can choose between factoring, part payment or user select. On user select the customer will choose either factoring or part payment in the checkout.

Factoring fee: Fee for the invoice

Factoring Fee tax rule: Set tax for the factoring fee

Get address
-----------

![](https://payex.github.io/PayEx.PrestaShop/media/image9.png)

Account number: You can collect the account number in Payex Merchant Admin; for production mode: <https://secure.payex.com/Admin/Logon.aspx> and for test mode: <http://test-secure.payex.com/Admin/Logon.aspx> Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: To generate an encryption key click [here](#_How_to_generate)

Mode: Choose between test or live

Payex bank debit
----------------

![](https://payex.github.io/PayEx.PrestaShop/media/image10.png)

Account number: You can collect the account number in Payex Merchant Admin; for production mode: <https://secure.payex.com/Admin/Logon.aspx> and for test mode: <http://test-secure.payex.com/Admin/Logon.aspx> Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: To generate an encryption key click [here](#_How_to_generate)

Mode: Choose between test or live

Banks: Choose which banks to display

Payex one click
---------------

![](https://payex.github.io/PayEx.PrestaShop/media/image11.png)

Account number: You can collect the account number in Payex Merchant Admin; for production mode: <https://secure.payex.com/Admin/Logon.aspx> and for test mode: <http://test-secure.payex.com/Admin/Logon.aspx> Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: To generate an encryption key click [here](#_How_to_generate)

Mode: Choose between test or live

Transaction type: Authorization is the standard transaction type, it requires a capture of the order. With Sale the amount ordered is processed immediately and withdrawn from the customers card. For more info contact PayEx support <support.solutions@payex.com>

Agreement url: That’s the url with information to the customer on what kind of agreement you have made.

Max amount of single transaction: That’s the maximum amount to be withdrawn from customers account for every autopay.

Paypal via Payex
----------------

![](https://payex.github.io/PayEx.PrestaShop/media/image12.png)

Account number: You can collect the account number in Payex Merchant Admin; for production mode: <https://secure.payex.com/Admin/Logon.aspx> and for test mode: <http://test-secure.payex.com/Admin/Logon.aspx> Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: To generate an encryption key click [here](#_How_to_generate)

Mode: Choose between test or live

Transaction type: Authorization is the standard transaction type, it requires a capture of the order. With Sale the amount ordered is processed immediately and withdrawn from the customers card. For more info contact PayEx support <support.solutions@payex.com>

<span id="_How_to_generate" class="anchor"><span id="_Toc393283459" class="anchor"></span></span>How to generate an encryption key
----------------------------------------------------------------------------------------------------------------------------------

The encryption key for testing and production mode are different, so make sure you generate the one you need.

Step 1:

you must go to <http://www.payexpim.com/> and choose admin for either test or production environment. ![](https://payex.github.io/PayEx.PrestaShop/media/image13.png)

Step 2:

Sign in with the information you have been given by payex

![](https://payex.github.io/PayEx.PrestaShop/media/image14.png)

Step 3: In the margin on the left, find “Merchant” and click on “Merchant profile”

![](https://payex.github.io/PayEx.PrestaShop/media/image15.png)

Step 4:

Click on “new encryption key”

![](https://payex.github.io/PayEx.PrestaShop/media/image16.png)

Complete

How to activate Transaction Callback
------------------------------------

Transaction callback is an extra process used by PayEx to verify that the webshop is informed of the result of the payment processing. It is useful if your server goes down during payment or if customer close the webbrowser or lose connection just after payment. Callback is a required functionality.

![](https://payex.github.io/PayEx.PrestaShop/media/image17.jpg)

Use the following URL

http://www.shopsite.com/index.php?fc=module&module=payex&controller=transaction

Change www.shopsite.com for your shop's url

How to translate the modules
----------------------------

Sign in to your shop as admin and click the Localization menu and translation in the bottom of the drop down menu.

![](https://payex.github.io/PayEx.PrestaShop/media/image18.png)

In the first menu on screen “Modify translations” select “Installed modules translations” in the drop down menu and then click the flag of the country you want to translate to.

![](https://payex.github.io/PayEx.PrestaShop/media/image19.png)

If your language is not there then underneath there is a section where you can add a language or import a language pack.

Now you just have to open the correct fieldset and start translating. To easily find the correct module click expand all fieldsets and ctr+f and search for the module you want to translate.

The modules are called:

Factoring

Payex

Bankdebit

Pxoneclick

pxpaypal
