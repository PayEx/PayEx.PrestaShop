{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}
<p class="payment_module">
    <a href="{$link->getModuleLink('pxswish', 'payment', [], true)|escape:'htmlall':'UTF-8'}" title="{l s='Pay with Swish' mod='pxswish'}" style="line-height:14px;padding-left: 15px;" rel="nofollow">
        <img src="{$this_path_px|escape:'htmlall':'UTF-8'}/views/img/pxswish.png" alt="{l s='Pay with PayEx' mod='pxswish'}" style="float:left;margin-right:4px;" />
        <br />{l s='Pay with Swish' mod='pxswish'}
        <br style="clear:both;" />
    </a>
</p>