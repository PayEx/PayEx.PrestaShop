{*
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*}
<script type="application/javascript">
    jQuery(document).ready(function($) {
        $('#bankdebit_pay').on('click', function(e) {
            e.preventDefault();
            $('#bankdebit_banks').slideDown();
        });
    });
</script>
<p class="payment_module">
    <a id="bankdebit_pay" href="#" title="{l s='Pay with Bank Debit' mod='bankdebit'}" rel="nofollow">
        <img src="{$this_path_px|escape:'htmlall':'UTF-8'}logo.gif" alt="{l s='Pay with Bank Debit' mod='bankdebit'}" style="float:left;" />
        <br />{l s='Pay with Bank Debit' mod='bankdebit'}
        <br style="clear:both;" />
    </a>
    <div id="bankdebit_banks" style="display: none">
        {l s='Choose one of the following banks:' mod='bankdebit'}
        <form id="bankdebit_form" class="form-inline" action="{$base_dir_ssl|escape:'htmlall':'UTF-8'}modules/bankdebit/redirect.php" method="post">
            <div class="form-group">
                <select id="banks" name="banks">
                    {foreach from=$banks key=bank_code item=bank_name}
                    <option value="{$bank_code|escape:'htmlall':'UTF-8'}">{$bank_name|escape:'htmlall':'UTF-8'}</option>
                    {/foreach}
                </select>

                <button type="submit" class="btn btn-default">{l s='Pay' mod='bankdebit'}</button>
            </div>
        </form>
    </div>
</p>
