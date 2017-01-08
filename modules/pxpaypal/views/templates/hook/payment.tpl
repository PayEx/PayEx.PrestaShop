{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}
<p class="payment_module">
    <a href="{$link->getModuleLink('pxpaypal', 'payment', [], true)|escape:'htmlall':'UTF-8'}" title="{l s='Pay with PayEx' mod='pxpaypal'}" rel="nofollow">
        <img src="{$this_path_px|escape:'htmlall':'UTF-8'}logo.gif" alt="{l s='Pay with PayPal via PayEx' mod='pxpaypal'}" style="float:left;" />
        <br />{l s='Pay with PayPal via PayEx' mod='pxpaypal'}
        <br style="clear:both;" />
    </a>
</p>