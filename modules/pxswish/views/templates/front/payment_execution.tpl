{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}

{capture name=path}{l s='Swish' mod='pxswish'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Order summary' mod='pxswish'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.' mod='pxswish'}</p>
{else}

    <h3>{l s='Swish' mod='pxswish'}</h3>
    <form action="{$link->getModuleLink('pxswish', 'confirm', [], true)|escape:'htmlall':'UTF-8'}" method="post">
        <p>
            <img src="{$this_path_px|escape:'htmlall':'UTF-8'}/views/img/pxswish.png" alt="{l s='PayEx' mod='pxswish'}" width="80" height="80" style="float:left; margin: 0px 10px 5px 0px;" />
            {l s='You have chosen to pay by Swish.' mod='pxswish'}
            <br/><br />
            {l s='Here is a short summary of your order:' mod='pxswish'}
        </p>
        <p style="margin-top:20px;">
            - {l s='The total amount of your order is' mod='pxswish'}
            <span id="amount" class="price">{displayPrice price=$total}</span>
            {if $use_taxes == 1}
                {l s='(tax incl.)' mod='pxswish'}
            {/if}
        </p>
        <p>
            -
            {if $currencies|@count > 1}
                {l s='We allow several currencies to be sent via Swish.' mod='pxswish'}
                <br /><br />
                {l s='Choose one of the following:' mod='pxswish'}
                <select id="currency_payement" name="currency_payement" onchange="setCurrency($('#currency_payement').val());">
                    {foreach from=$currencies item=currency}
                        <option value="{$currency.id_currency|escape:'htmlall':'UTF-8'}" {if $currency.id_currency == $cust_currency}selected="selected"{/if}>{$currency.name|escape:'htmlall':'UTF-8'}</option>
                    {/foreach}
                </select>
            {else}
                {l s='We allow the following currency to be sent via Swish:' mod='pxswish'}&nbsp;<b>{$currencies.0.name|escape:'htmlall':'UTF-8'}</b>
                <input type="hidden" name="currency_payement" value="{$currencies.0.id_currency|escape:'htmlall':'UTF-8'}" />
            {/if}
        </p>
        <p>
            {l s='You will be redirected to PayEx website when you place an order. ' mod='pxswish'}
            <br /><br />
            <b>{l s='Please confirm your order by clicking "Place my order."' mod='pxswish'}.</b>
        </p>
        <p class="cart_navigation" id="cart_navigation">
            <input type="submit" value="{l s='Place my order' mod='pxswish'}" class="exclusive_large" />
            <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'htmlall':'UTF-8'}" class="button_large">{l s='Other payment methods' mod='pxswish'}</a>
        </p>
    </form>
{/if}
