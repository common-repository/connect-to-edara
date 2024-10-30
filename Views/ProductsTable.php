<?php

namespace Views;

use Edara\Includes\ProductTaxRate;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}


class ProductsTable extends \WP_List_Table {

    /**
     * @var int Number of posts per page
     */
    private int $perPage = 20;

    /**
     * Get list of products
     *
     * @param $orderby
     * @param $order
     * @param $search_term
     * @return array
     */
    private function getProducts($orderby = '', $order = '', $search_term = ''): array {
        // var_dump("Ss");die();
        $products = [];
        $paged = isset($_GET['paged']) ? trim($_GET['paged']) : 1;
        $search_term = isset($_POST['s']) ? trim($_POST['s']) : "";

        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => $this->perPage,
            'paged' => $paged,
            'suppress_filters' => false,
            'orderby' => 'title',
            'order' => 'asc',
            's' => $search_term,
        ];

        $posts = get_posts($args);

        foreach ($posts as $product) {
            $externalId = get_post_meta($product->ID, 'external_id', true);
            $url = edara_url() . "/Warehouse/stockitems.aspx?stockItemId={$externalId}";
            $products[] = array(
                'id' => "<a target='_blank' href='".get_edit_post_link( $product->ID )."'>{$product->ID}</a>",
                'title' => "<a target='_blank' href='".esc_url( get_permalink($product->ID) )."'>{$product->post_title}</a>",
                'edara_product_id' => "<a target='_blank' href='{$url}'>{$externalId}</a>",
                'taxable' => get_post_meta($product->ID, ProductTaxRate::EXTERNAL_PRODUCT_TAX_RATE, true),
            );
        }

        return $products;
    }

    /**
     * @return void
     */
    public function prepare_items(): void {
        $orderby = isset($_GET['$orderby']) ? trim($_GET['$orderby']) : "";
        $order = isset($_GET['order']) ? trim($_GET['order']) : "";

        $search_term = isset($_POST['s']) ? trim($_POST['s']) : "";

        $data = $this->getProducts($orderby, $order, $search_term);

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'suppress_filters' => false,
            's' => $search_term,
            );
        $the_query = new \WP_Query($args);

        $this->set_pagination_args(array(
            "total_items" => $the_query->found_posts,
            "per_page" => $this->perPage
        ));

        $this->items = $data;

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
    }

    /**
     * @return string[]
     */
    public function get_columns(): array {
        $columns = array(
            'id' => 'ID',
            'title' => 'Name',
            'edara_product_id' => 'Edara',
            'taxable' => 'Taxable',
        );

        return $columns;
    }

    // columns_default
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'title':
            case 'taxable':
            case 'edara_product_id':
                return $item[$column_name];
            default:
                return "no value";

        }
    }

    //sortable columns
    public function get_sortable_columns() {
        return array(
            'title' => array('title', false),
        );
    }

    //hidden columns
    public function get_hidden_columns() {
        return [];
    }
}
