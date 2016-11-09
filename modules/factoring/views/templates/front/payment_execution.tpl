{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}
{capture name=path}{l s='PayEx Factoring' mod='factoring'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Order summary' mod='factoring'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.' mod='factoring'}</p>
{else}
    {if $error}
        <p class="warning">{l s='Error:' mod='factoring'} {$error|escape:'htmlall':'UTF-8'}</p>
    {/if}
    <h3>{l s='PayEx Factoring' mod='factoring'}</h3>
    <form action="{$link->getModuleLink('factoring', 'confirm', [], true)|escape:'htmlall':'UTF-8'}" method="post">
        <p>
            <img src="{$this_path_px|escape:'htmlall':'UTF-8'}logo.gif" alt="{l s='PayEx' mod='factoring'}" width="86" height="49" style="float:left; margin: 0px 10px 5px 0px;" />
            {l s='You have chosen to pay by PayEx.' mod='factoring'}
            <br/><br />
            {l s='Here is a short summary of your order:' mod='factoring'}
        </p>
        <p style="margin-top:20px;">
            - {l s='The total amount of your order is' mod='factoring'}
            <span id="amount" class="price">{displayPrice price=$total}</span>
            {if $fee !== 0}<br/><span class="price">{l s='This includes the factoring fee' mod='factoring'} {displayPrice price=$fee}</span>{/if}
            {if $use_taxes == 1}
                {l s='(tax incl.)' mod='factoring'}
            {/if}
        </p>
        {if $type === 'SELECT'}
        <p>
            <label for="factoring-menu">{l s='Please select payment method:' mod='factoring'}</label>
            <select name="factoring-menu" id="factoring-menu" class="required-entry">
                <option selected value="FINANCING">{l s='Financing Invoice' mod='factoring'}</option>
                <option value="FACTORING">{l s='Invoice 2.0 (Factoring)' mod='factoring'}</option>
                <option value="CREDITACCOUNT">{l s='Part Payment' mod='factoring'}</option>
            </select>
        </p>
        {/if}
        <p>
            -
            {l s='* Enter Social Security Number:' mod='factoring'}
            <input name="social-security-number" type="text" placeholder="YYMMDD-XXXX" value="" class="required" />
        </p>
        <p>
            -
            {if $currencies|@count > 1}
                {l s='We allow several currencies to be sent via PayEx.' mod='factoring'}
                <br /><br />
                {l s='Choose one of the following:' mod='factoring'}
                <select id="currency_payement" name="currency_payement" onchange="setCurrency($('#currency_payement').val());">
                    {foreach from=$currencies item=currency}
                        <option value="{$currency.id_currency|escape:'htmlall':'UTF-8'}" {if $currency.id_currency == $cust_currency}selected="selected"{/if}>{$currency.name|escape:'htmlall':'UTF-8'}</option>
                    {/foreach}
                </select>
            {else}
                {l s='We allow the following currency to be sent via PayEx:' mod='factoring'}&nbsp;<b>{$currencies.0.name|escape:'htmlall':'UTF-8'}</b>
                <input type="hidden" name="currency_payement" value="{$currencies.0.id_currency|escape:'htmlall':'UTF-8'}" />
            {/if}
        </p>
        <p>
            {l s='You will be redirected to PayEx website when you place an order. ' mod='factoring'}
            <br /><br />
            <b>{l s='Please confirm your order by clicking "Place my order."' mod='factoring'}.</b>
        </p>
        <p class="cart_navigation" id="cart_navigation">
            <input type="submit" value="{l s='Place my order' mod='factoring'}" class="exclusive_large" />
            <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'htmlall':'UTF-8'}" class="button_large">{l s='Other payment methods' mod='factoring'}</a>
        </p>
    </form>
{/if}
