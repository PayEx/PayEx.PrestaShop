{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}
{if $status == 'ok'}
    <p>{l s='Your order on %s is complete.' sprintf=$shop_name mod='pxoneclick'}
        <br /><br /><span class="bold">{l s='Your order will be shipped as soon as possible.' mod='pxoneclick'}</span>
        <br /><br />{l s='For any questions or for further information, please contact our' mod='pxoneclick'} <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='customer support' mod='pxoneclick'}</a>.
    </p>
{else}
    {if $status == 'pending'}
        <p>{l s='Your order on %s is pending.' sprintf=$shop_name mod='pxoneclick'}
            <br /><br /><span class="bold">{l s='Your order will be shipped as soon as we receive your payment.' mod='pxoneclick'}</span>
            <br /><br />{l s='For any questions or for further information, please contact our' mod='pxoneclick'} <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='customer support' mod='pxoneclick'}</a>.
        </p>
    {else}
        <p class="warning">
            {l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='pxoneclick'}
            <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='customer support' mod='pxoneclick'}</a>.
            <br />
            {l s='Details: %s.' sprintf=$message mod='pxoneclick'}
        </p>
    {/if}
{/if}