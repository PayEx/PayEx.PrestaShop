{*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author AAIT <info@aait.se>
*  @copyright  2007-2016 AAIT
*  @license    http://shop.aait.se/license.txt  EULA
*  International Registered Trademark & Property of PrestaShop SA
*}
{literal}
    <script type="application/javascript">
        $(document).ready(function () {
            // Fetch Info Click
            $('#process_fetch').on('click', function(e) {
                $('#process_fetch').attr('disabled', 'disabled');
                $.ajax({
                    url: document.URL,
                    type: 'POST',
                    cache: false,
                    async: true,
                    dataType: 'json',
                    data: {
                        ajax: true,
                        process_fetch: true,
                        payex_order_id: $('#payex_order_id').val(),
                        payex_transaction_id: $('#payex_transaction_id').val()
                    },
                    success: function (response) {
                        if (response.status !== 'ok') {
                            alert('Error: ' + response.message);
                            $('#process_fetch').removeAttr('disabled');
                            return false;
                        }
                        self.location.href = document.URL;
                    }
                });
            });

            // Capture Click
            $('#process_capture').on('click', function (e) {
                if (confirm('Capture payment and create invoice?')) {
                    $('#process_capture').attr('disabled', 'disabled');
                    $.ajax({
                        url: document.URL,
                        type: 'POST',
                        cache: false,
                        async: true,
                        dataType: 'json',
                        data: {
                            ajax: true,
                            process_capture: true,
                            payex_order_id: $('#payex_order_id').val(),
                            payex_transaction_id: $('#payex_transaction_id').val()
                        },
                        success: function (response) {
                            if (response.status !== 'ok') {
                                alert('Error: ' + response.message);
                                $('#process_capture').removeAttr('disabled');
                                return false;
                            }
                            alert(response.message);
                            self.location.href = document.URL;
                        }
                    });
                }
            });

            // Cancel Click
            $('#process_cancel').on('click', function (e) {
                if (confirm('Cancel order?')) {
                    $('#process_cancel').attr('disabled', 'disabled');
                    $.ajax({
                        url: document.URL,
                        type: 'POST',
                        cache: false,
                        async: true,
                        dataType: 'json',
                        data: {
                            ajax: true,
                            process_cancel: true,
                            payex_order_id: $('#payex_order_id').val(),
                            payex_transaction_id: $('#payex_transaction_id').val()
                        },
                        success: function (response) {
                            if (response.status !== 'ok') {
                                alert('Error: ' + response.message);
                                $('#process_cancel').removeAttr('disabled');
                                return false;
                            }
                            alert(response.message);
                            self.location.href = document.URL;
                        }
                    });
                }
            });

            // Refund Click
            $('#process_refund').on('click', function (e) {
                if (confirm('You want to perform an "Refund" operation?')) {
                    $('#process_refund').attr('disabled', 'disabled');
                    $.ajax({
                        url: document.URL,
                        type: 'POST',
                        cache: false,
                        async: true,
                        dataType: 'json',
                        data: {
                            ajax: true,
                            process_refund: true,
                            payex_order_id: $('#payex_order_id').val(),
                            payex_transaction_id: $('#payex_transaction_id').val(),
                            refund_amount: $('#refund_amount').val()
                        },
                        success: function (response) {
                            if (response.status !== 'ok') {
                                alert('Error: ' + response.message);
                                $('#process_refund').removeAttr('disabled');
                                return false;
                            }
                            alert(response.message);
                            self.location.href = document.URL;
                        }
                    });
                }
            });
        });
    </script>
{/literal}

<br/>
<fieldset>
    <legend><img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/actions.gif" alt=""/> {l s='PayEx Payment actions' mod='mobilepay'}</legend>
    <input type="hidden" id="payex_order_id" name="payex_order_id" value="{$order_id|escape:'htmlall':'UTF-8'}"/>
    <input type="hidden" id="payex_transaction_id" name="payex_transaction_id" value="{$transaction_id|escape:'htmlall':'UTF-8'}"/>
    {if $transaction_status == '3'}
        <input type="button" id="process_capture" name="process_capture" value="{l s='Capture' mod='mobilepay'}"
               class="button"/>
        <input type="button" id="process_cancel" name="process_cancel" value="{l s='Cancel' mod='mobilepay'}"
               class="button"/>
    {/if}
    {if $transaction_status == '0' || $transaction_status == '6'}
        {l s='Refund' mod='mobilepay'}: <input type="text" id="refund_amount" name="refund_amount" value="{($order_amount)|floatval}" />
        <input type="button" id="process_refund" name="process_refund" value="{l s='Refund' mod='mobilepay'}"
               class="button"/>
    {/if}
    <br/>
</fieldset>

<br/>
<fieldset>
    <legend><img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/information.png" alt=""/> {l s='PayEx transaction details' mod='mobilepay'}</legend>
    <table class="table" cellpadding="0" cellspacing="0">
        {foreach from=$transaction_data key=k item=v}
            <tr>
                <td>{$k|escape:'htmlall':'UTF-8'} </td>
                <td>{$v|escape:'htmlall':'UTF-8'} </td>
            </tr>
        {/foreach}
    </table>
    <input type="button" id="process_fetch" name="process_fetch" value="{l s='Fetch Info' mod='mobilepay'}"
           class="button"/>
    <br/>
</fieldset>
