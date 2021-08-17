![CardGate](https://cdn.curopayments.net/thumb/200/logos/cardgate.png)

# CardGate module for Shopware 6.4+

[![Build Status](https://travis-ci.org/cardgate/drupal-ubercart.svg?branch=master)](https://travis-ci.org/cardgate/drupal-ubercart)

## Support

This module supports Ubercart version **6.4+**

## Preparation

The usage of this module requires that you have obtained CardGate security credentials.  
Please visit [**My CardGate**](https://my.cardgate.com/) and retrieve your **site ID** and **hash key**, or contact your accountmanager.

## Installation

1. Download and unzip the most recent [**cardgate.zip**](https://github.com/cardgate/drupal-ubercart/releases) file on your desktop.

2. Upload the **CuroCardGate** folder to your **Shopware plugins** folder, which you can find here:  
   **http://mywebshop.com/htdocs/custom/plugins/**  
   (Replace **http://mywebshop.com** with the URL of your webshop, so the **CuroCardGate** folder will end up in the **plugins folder**.)


## Configuration

1. Go to the **Admin, Modules** section of your webshop.

2. Scroll to the **Ubercart – Payment** section.

3. Checkmark the **CardGate Payment Gateways module**.
   Scroll down and click **Save configuration**.

4. Go to the **admin** section of your webshop and select **Admin, Store, Payment methods**.

5. Click on the **CardGate settings** link.

6. Now enter the **site ID**, and the **hash key** which you can find at **Sites** on [**My CardGate**](https://my.cardgate.com/).

7. Enter the **default language** used by your webshop, and click **Save configuration**.

8. At **Payment methods** checkmark all the payment methods that you wish to activate.  
   Attention: Do **not** checkmark the **CardGate** payment method, this is only used for the settings.

9. Click **Save configuration**.

10. Go to [**My CardGate**](https://my.cardgate.com/), choose **Sites** and select the appropriate site.

11. Go to **Connection to the website** and enter the **Callback URL**, for example:  
    **http://mywebshop.com/?q=cart/cgp_response**
    (Replace **http://mywebshop.com** with the URL of your webshop.)

12. When you are **finished testing** make sure that you switch from **Test Mode** to **Live mode** at the **CardGate settings** and save it (**Save**).

## Requirements

No further requirements. 