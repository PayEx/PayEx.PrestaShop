{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}
<p class="payment_module">
    <a href="{$link->getModuleLink('factoring', 'payment', [], true)|escape:'htmlall':'UTF-8'}" title="{l s='Pay using PayEx Factoring' mod='factoring'}" rel="nofollow">
        <img src="{$this_path_px|escape:'htmlall':'UTF-8'}logo.gif" alt="{l s='Pay using PayEx Factoring' mod='factoring'}" style="float:left;" />
        <br />{l s='Pay using PayEx Factoring' mod='factoring'}
        <br style="clear:both;" />
    </a>
</p>