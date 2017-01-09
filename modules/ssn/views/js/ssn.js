/**
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*/

$(document).ready(function () {
    // Attach field to form
    if ($('#add_address').length) {
        var fields1 = '<div class="form-group text">';
        fields1 += '<label for="social_security_number">Social Security Number</label>';
        fields1 += '<input class="form-control" type="text" name="social_security_number" id="social_security_number" placeholder="Social Security Number">';
        fields1 += '<label for="check_postcode">Zip/Postal code</label>';
        fields1 += '<input type="text" class="text form-control" name="check_postcode" id="check_postcode" />';
        fields1 += '<label for="check_country">Country</label>';
        fields1 += '<select name="check_country" id="check_country" class="form-control"><option value="SE" selected="selected">Sweden</option><option value="NO">Norway</option></select>';
        fields1 += '<br/><input type="button" name="getAddress" value="Get Address" class="button"</div>';

        $(fields1).insertBefore(jQuery('#add_address input[name="firstname"]').closest('div'));
    }

    // One Page Checkout Mode
    if ($('#opc_account_form #email').length) {
        var fields2 = '<div class="form-group text">';
            fields2 += '<label for="social_security_number">Social Security Number</label>';
            fields2 += '<input class="form-control" type="text" name="social_security_number" id="social_security_number" placeholder="Social Security Number">';
            fields2 += '<label for="check_postcode">Zip/Postal code</label>';
            fields2 += '<input type="text" class="text form-control" name="check_postcode" id="check_postcode" />';
            fields2 += '<label for="check_country">Country</label>';
            fields2 += '<select name="check_country" id="check_country" class="form-control"><option value="SE" selected="selected">Sweden</option><option value="NO">Norway</option></select>';
            fields2 += '<br/><input type="button" name="getAddress-opc" value="Get Address" class="button"</div>';

        $(fields2).insertBefore(jQuery('#opc_account_form input[name="email"]').closest('div'));
    }

    $("input[name='getAddress']").on('click', function(e) {
        $.ajax({
            url: baseDir + 'index.php?fc=module&module=ssn&controller=address',
            type: 'POST',
            cache: false,
            async: true,
            dataType: 'json',
            data: {
                ajax: true,
                ssn: $("input[name='social_security_number']").val(),
                country_code: $("select[name='check_country']").val(),
                postcode: $("input[name='check_postcode']").val()
            },
            success: function (response) {
                if (!response.success) {
                    alert(response.message);
                    return false;
                }

                jQuery('input[name="firstname"]').val(response.first_name);
                jQuery('input[name="lastname"]').val(response.last_name);
                jQuery('input[name="address1"]').val(response.address_1);
                jQuery('input[name="address2"]').val(response.address_2);
                jQuery('input[name="postcode"]').val(response.postcode);
                jQuery('input[name="city"]').val(response.city);
                jQuery('select[name="id_country"]').val(response.country_id);
            }
        });
    });

    $("input[name='getAddress-opc']").on('click', function(e) {
        $.ajax({
            url: baseDir + 'index.php?fc=module&module=ssn&controller=address',
            type: 'POST',
            cache: false,
            async: true,
            dataType: 'json',
            data: {
                ajax: true,
                ssn: $("input[name='social_security_number']").val(),
                country_code: $("select[name='check_country']").val(),
                postcode: $("input[name='check_postcode']").val()
            },
            success: function (response) {
                if (!response.success) {
                    alert(response.message);
                    return false;
                }

                jQuery('#opc_account_form input[name="customer_firstname"]').val(response.first_name);
                jQuery('#opc_account_form input[name="customer_lastname"]').val(response.last_name);
                jQuery('#opc_account_form input[name="firstname"]').val(response.first_name);
                jQuery('#opc_account_form input[name="lastname"]').val(response.last_name);
                jQuery('#opc_account_form input[name="address1"]').val(response.address_1 + ' ' + response.address_2);
                jQuery('#opc_account_form input[name="postcode"]').val(response.postcode);
                jQuery('#opc_account_form input[name="city"]').val(response.city);
                jQuery('#opc_account_form select[name="id_country"]').val(response.country_id);
            }
        });
    });
});
