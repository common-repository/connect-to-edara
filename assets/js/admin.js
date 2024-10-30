jQuery(document).ready(function($){
    let activeRequest = false;
    let syncProductTaxRate = setInterval(function(){
        if(activeRequest)
        {
            return;
        }
        activeRequest = true;
        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: "/wp-admin/admin-ajax.php",
            data : {action: "sync_products_tax_rate"},
            success: function(result){
                let value;
                if (result.status === 'done') {
                    clearInterval(syncProductTaxRate);
                    window.location = window.location.href + "&sync_products_tax_rate=0"
                } else {
                    value = result.offset / result.total_count * 100
                    $("#sync_products_tax_rate_progress_bar").animate({width: parseInt(value) + "%"});
                    $("#sync_products_tax_rate_progress_bar_span").html(
                        result.offset + ' / ' + result.total_count + ' (' + parseInt(value) + '%)'
                    );
                }
                activeRequest = false;
            }

        });
    }, 500);
});

