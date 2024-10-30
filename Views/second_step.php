<?php
declare(strict_types=1);

namespace Edara\Menu;

use Edara\Includes\ProductTaxRate;
use Views\ProductsTable;

class EdaraIntegrationMenu {

    public function init() {
        add_action('admin_menu', [$this, 'EdaraAdminMenu']);
    }

    public function ProductList() {
        // var_dump("xx");die();/edara/wp-content/plugins/edara_erp_integration/Includes/data.php
        echo '<form method="post" action="/wp-content/plugins/connect-to-edara/Includes/data.php" id="first_step">
        <h2>Welcome To Edara Connect</h2>
        <h3>Lets Verify Your Edasssssssssssssssssssssssssra Account</h3>
        <table class="form-table" role="presentation">
        <tbody><tr class="example-class"><th scope="row"><label for="edara_email">Edara Email</label></th><td><input type="email" class="regular-text" name="edara_email" placeholder="Enter Edara Email" required="required"></td></tr><tr class="example-class"><th scope="row"><label for="edara_password">Edara Password</label></th><td><input type="password" class="regular-text" name="edara_password" placeholder="Enter Edara password" required="required"></td></tr><tr class="example-class"><th scope="row"><label for="edara_domain">Edara Domain</label></th><td><input type="text" class="regular-text" name="edara_domain" placeholder="Enter Edara Domain" required="required"></td></tr>
        </tbody>
        </table>
        <div id="error_area" style="display:none;color:red;"></div>
        <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Send"></p>
        </form>';
        echo "<script src='http://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.2.1.js'></script>
        <script>
      $(function () {

        $('form').on('submit', function (e) {

          e.preventDefault();

          $.ajax({
            type: 'post',
            url: '/wp-content/plugins/connect-to-edara/Includes/data.php',
            data: $('form').serialize(),
            success: function (message) {
                if(!message.success){
                    var error_area = document.getElementById('error_area');
                    error_area.style.display = 'block';
                    error_area.innerHTML = message.error;
                }else{
                    window.location = '/wp-content/plugins/connect-to-edara/Includes/data.php'
                }
                
              // alert('form was submitted');
            }
          });

        });

      });
    </script>";
        // $productList = new ProductsTable();
        // $productList->views();

        // $productList->prepare_items();
        // echo "<a class='button' href='".esc_url( add_query_arg( ProductTaxRate::SYNC_PRODUCT_TAX_RATE, 1, get_permalink() ) )."'><span aria-hidden='true'>Sync Products tax rate</span></a>";
        // echo "<a class='button' href='".admin_url( 'admin-post.php' )."'><span aria-hidden='true'>test</span></a>";

        // if ( true == ($transient_data = get_transient( ProductTaxRate::SYNC_PRODUCT_TAX_RATE ) )) {
        //     $total_count = intval(array_get($transient_data, 'total_count'));
        //     $offset = intval(array_get($transient_data, 'offset'));
        //     $value = $offset / $total_count * 100;
        //     echo '
        //         <div style="border:1px solid #ccc!important">
        //             <span id="sync_products_tax_rate_progress_bar_span" style="position: absolute; left: 50%;">
        //             '.$offset.' / '.$total_count.'
        //             ('.intval($value).'%)</span>
        //             <div id="sync_products_tax_rate_progress_bar" style="color:#000!important;background-color:#00B937!important; height:24px;width:'.$value.'%"></div>
        //         </div>
        //     ';
        // }


        // echo "<form method='post' name='frm_search_post' action='".$_SERVER['PHP_SELF']."?page=edara_integration_products'>";
        // $productList->search_box('Search Product(s)', 'search_post_id');
        // echo "</form";
        // $productList->display();
    }

    public function EdaraAdminMenu() {
        add_menu_page(
            'Edara Integration',
            'Edara Integration',
            'manage_options',
            'edara_integration_products',
            [$this, 'ProductList'],
            'dashicons-layout',
            null
        );
        add_menu_page( $page['page_title'], $page['menu_title'], $page['capability'], $page['menu_slug'], $page['callback'], $page['icon_url'], $page['position'] );
        // $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '', $position = null 
        add_submenu_page( 'manage_options', 'innnnn', 'innnnnnnn', false, 'sssssss', [$this, 'ProductList'], null );
    }

}