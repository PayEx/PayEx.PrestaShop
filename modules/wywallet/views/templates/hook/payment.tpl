{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}
<p class="payment_module">
    <a href="{$link->getModuleLink('wywallet', 'payment', [], true)|escape:'htmlall':'UTF-8'}" title="{l s='Pay with WyWallet' mod='wywallet'}" rel="nofollow">
        <img src="{$this_path_px|escape:'htmlall':'UTF-8'}logo.gif" alt="{l s='Pay with WyWallet' mod='wywallet'}" style="float:left;" />
        <br />{l s='Pay with WyWallet' mod='wywallet'}
        <br style="clear:both;" />
    </a>
</p>