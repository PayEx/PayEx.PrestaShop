$(document).ready(function () {
    // Attach field to form
    if ($('#add_address').length) {
        var fields = '<div class="form-group text"><label for="social_security_number">Social Security Number</label><input class="form-control" type="text" name="social_security_number" id="social_security_number" placeholder="Social Security Number"><input type="button" name="getAddress" value="Get Address" class="button" style="width: 100px; margin-left: 10px;"></div>';
        $(fields).insertBefore(jQuery('#add_address input[name="firstname"]').closest('div'));
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
                ssn: $("input[name='social_security_number']").val()
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
                jQuery('input[name="id_country"]').val(18); // SE supported only
            }
        });
    });
});
