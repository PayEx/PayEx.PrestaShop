{capture name=path}{l s='PayPal via PayEx' mod='pxpaypal'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Order summary' mod='pxpaypal'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.' mod='pxpaypal'}</p>
{else}

    <h3>{l s='PayPal via PayEx' mod='pxpaypal'}</h3>
    <form action="{$link->getModuleLink('pxpaypal', 'confirm', [], true)|escape:'html'}" method="post">
        <p>
            <img src="{$this_path_px}logo.gif" alt="{l s='PayEx' mod='pxpaypal'}" width="86" height="49" style="float:left; margin: 0px 10px 5px 0px;" />
            {l s='You have chosen to pay by PayPal via PayEx.' mod='pxpaypal'}
            <br/><br />
            {l s='Here is a short summary of your order:' mod='pxpaypal'}
        </p>
        <p style="margin-top:20px;">
            - {l s='The total amount of your order is' mod='pxpaypal'}
            <span id="amount" class="price">{displayPrice price=$total}</span>
            {if $use_taxes == 1}
                {l s='(tax incl.)' mod='pxpaypal'}
            {/if}
        </p>
        <p>
            -
            {if $currencies|@count > 1}
                {l s='We allow several currencies to be sent via PayEx.' mod='pxpaypal'}
                <br /><br />
                {l s='Choose one of the following:' mod='pxpaypal'}
                <select id="currency_payement" name="currency_payement" onchange="setCurrency($('#currency_payement').val());">
                    {foreach from=$currencies item=currency}
                        <option value="{$currency.id_currency}" {if $currency.id_currency == $cust_currency}selected="selected"{/if}>{$currency.name}</option>
                    {/foreach}
                </select>
            {else}
                {l s='We allow the following currency to be sent via PayEx:' mod='pxpaypal'}&nbsp;<b>{$currencies.0.name}</b>
                <input type="hidden" name="currency_payement" value="{$currencies.0.id_currency}" />
            {/if}
        </p>
        <p>
            {l s='You will be redirected to PayEx website when you place an order. ' mod='pxpaypal'}
            <br /><br />
            <b>{l s='Please confirm your order by clicking "Place my order."' mod='pxpaypal'}.</b>
        </p>
        <p class="cart_navigation" id="cart_navigation">
            <input type="submit" value="{l s='Place my order' mod='pxpaypal'}" class="exclusive_large" />
            <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}" class="button_large">{l s='Other payment methods' mod='pxpaypal'}</a>
        </p>
    </form>
{/if}
