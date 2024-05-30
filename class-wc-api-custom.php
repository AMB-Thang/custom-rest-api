<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer;
use Automattic\WooCommerce\Utilities\NumberUtil;
use SkyVerge\WooCommerce\PluginFramework\v5_10_14 as Framework;

class WC_API_Custom extends WC_API_Resource
{
    protected $base = '/custom';

    public function register_routes($routes)
    {
        $routes['/getuserdetail'] = array(
            array(array($this, 'getuserdetail'), WC_API_Server::READABLE)
        );
        $routes['/getuserlist'] = array(
            array(array($this, 'getuserlist'), WC_API_Server::READABLE)
        );
        $routes['/getlinkitem'] = array(
            array(array($this, 'getlinkitem'), WC_API_Server::READABLE)
        );
        $routes['/getitemlist'] = array(
            array(array($this, 'getItemList'), WC_API_Server::READABLE)
        );
        $routes['/createorderdraft'] = array(
            array(array($this, 'createorderdraft'), WC_API_Server::READABLE)
        );
        $routes['/addorderitem'] = array(
            array(array($this, 'addorderitem'), WC_API_Server::CREATABLE | WC_API_Server::ACCEPT_DATA)
        );
        $routes['/addtaxes'] = array(
            array(array($this, 'addtaxes'), WC_API_Server::READABLE)
        );
        $routes['/saveorder'] = array(
            array(array($this, 'saveorder'), WC_API_Server::CREATABLE | WC_API_Server::ACCEPT_DATA)
        );
        $routes['/getorderdetail'] = array(
            array(array($this, 'getorderdetail'), WC_API_Server::READABLE)
        );
        $routes['/calcuorder'] = array(
            array(array($this, 'calcuorder'), WC_API_Server::CREATABLE | WC_API_Server::ACCEPT_DATA)
        );
        $routes['/deleteitem'] = array(
            array(array($this, 'deleteitem'), WC_API_Server::READABLE)
        );
        $routes['/getorderlist'] = array(
            array(array($this, 'getorderlist'), WC_API_Server::READABLE)
        );
        $routes['/getshipping'] = array(
            array(array($this, 'getshipping'), WC_API_Server::READABLE)
        );
        $routes['/getshippingorder'] = array(
            array(array($this, 'getshippingorder'), WC_API_Server::READABLE)
        );
        $routes['/getstates'] = array(
            array(array($this, 'getstates'), WC_API_Server::READABLE)
        );

        return $routes;
    }

    public function getstates($param = array())
    {
        $countriesObj = new WC_Countries();
        $countyStates = $countriesObj->get_states($param['country']);

        return $countyStates;
    }

    public function getshippingorder($param = array())
    {
        try {
            ob_start();
            include __DIR__ . '/includes/html-order-shipping-detail.php';
            $res = ob_get_clean();

            return $res;
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    public function getshipping($param = array())
    {
//        if (!class_exists('WC_Address_Book')) {
//            return [];
//        }

        $customerUser = isset($param['customer_user']) ? $param['customer_user'] : 0;

        $wc_address_book = WC_Address_Book::get_instance();
        $woo_address_book_billing_address_book = $wc_address_book->get_address_book($customerUser, 'shipping');

        $customerShipping = [];

        foreach ($woo_address_book_billing_address_book as $key => $address_item) {
            $customerShipping[$key]['first_last_name'] = $address_item[$key . '_first_name'] . ' ' . $address_item[$key . '_last_name'];
            $customerShipping[$key]['country'] = $address_item[$key . '_country'] . ' ' . $address_item[$key . '_postcode'];
            $customerShipping[$key]['address'] = $address_item[$key . '_address_1'] . ' ' . $address_item[$key . '_address_2'] . ', ' . $address_item[$key . '_state'];
            $customerShipping[$key]['phone'] = $address_item[$key . '_phone'];
        }

        return $customerShipping;
    }

    public function getorderlist($param = array())
    {
        $customerUser = isset($param['customer_user']) ? $param['customer_user'] : 1;

        $limit = 12;
        $totalPages = 1;
        $page = isset($param['page']) && $param['page'] ? $param['page'] : 1;

        $args = array(
//        'customer_id' => 1,
            'limit' => -1
        );
        $orders = wc_get_orders($args);
        $totalOrders = count($orders) ?: 1;

        $offset = $limit * ($page - 1);
        $totalPages = ceil($totalOrders / $limit);

        $args = array(
//        'customer_id' => $customerUser,
            'offset' => $offset,
            'limit' => -1
        );
        $customerOrderArr = wc_get_orders($args);

        $res = $orders = [];
        foreach ($customerOrderArr as $order) {
            $orders[] = [
                "id" => $order->get_id(),
                "value" => $order->get_total(),
                "status" => $order->get_status(),
                "date" => $order->get_date_created()->date_i18n('Y-m-d'),
            ];
        }

        $res['orders'] = $orders;
        $res['currency'] = get_woocommerce_currency();
        $res['page'] = $page;
        $res['totalPages'] = $totalPages;

        return $res;
    }

    public function deleteitem($param = array())
    {
        wp_set_current_user(1);

        $order_id = absint($param['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            throw new Exception(__('Invalid order', 'woocommerce'));
        }

        if (!isset($param['order_item_ids'])) {
            throw new Exception(__('Invalid items', 'woocommerce'));
        }

        $order_item_ids = wp_unslash($param['order_item_ids']);
        if (is_numeric($order_item_ids)) {
            $order_item_ids = array($order_item_ids);
        }

        if (!empty($order_item_ids)) {
            foreach ($order_item_ids as $item_id) {
                $item_id = absint($item_id);
                $item = $order->get_item($item_id);

                if (!$item) {
                    continue;
                }

                $order->add_order_note(sprintf(__('Deleted %s', 'woocommerce'), $item->get_name()), false, true);
                wc_delete_order_item($item_id);
            }
        }

        $order->calculate_totals(false);
        $order->save();

        return $order;
    }

    public function calcuorder($data = array())
    {
        wp_set_current_user(1);

        $data['items'] = $data;

        $this->wp_add_order_fee($data);

        try {
            $order_id = isset($data['order_id']) ? absint($data['order_id']) : 0;
            $order = wc_get_order($order_id);

            ob_start();
            include __DIR__ . '/includes/html-order-items.php';
            $res = ob_get_clean();

            return $res;
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    public function getorderdetail($param = array())
    {
        try {
            $_POST = $param;

            $order_id = isset($param['order_id']) ? absint($param['order_id']) : 0;
            $order = wc_get_order($order_id);

            ob_start();
            include __DIR__ . '/includes/html-order-item-detail.php';
            $res = ob_get_clean();

            return $res;
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    public function saveorder($data = array())
    {
        wp_set_current_user(1);

//      $this->add_address_book($param);
        $this->wp_add_order_shipping($data);

        $order_id = (isset($data['order_id']) && $data['order_id']) ? absint($data['order_id']) : 0;
        $order = wc_get_order($order_id);
        $order->set_status('wc-processing');
        $order->save();

        try {
            ob_start();
            include __DIR__ . '/includes/html-order-items.php';
            $res = ob_get_clean();

            return $res;
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    public function addtaxes($param = array())
    {
        wp_set_current_user(1);

        if (function_exists('wc_avatax')) {
            $matched_tax_rates = $this->wp_matched_tax_rates($param);

            $this->wp_add_tax_item($param, $matched_tax_rates);
            $shippingTaxes = $this->wp_add_tax_shipping($param, $matched_tax_rates);
            $this->wp_add_tax_total($param, $matched_tax_rates, $shippingTaxes);
        }

        $order = wc_get_order($param['order_id']);
        $order->calculate_totals(false);

        $total = $order->get_total();
        foreach ($order->get_tax_totals() as $tax_total) {
            $total = wc_round_tax_total($total);
            $total += NumberUtil::round($tax_total->amount, wc_get_price_decimals());
        }

        $order->set_total($total);
        $order->save();

        try {
            ob_start();
            include __DIR__ . '/includes/html-order-items.php';
            $res = ob_get_clean();

            return $res;
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    public function addorderitem($data = array())
    {
        if (!isset($data['order_id'])) {
            throw new Exception(__('Invalid order', 'woocommerce'));
        }

        $order_id = absint(wp_unslash($data['order_id']));
        $items = (!empty($data['items'])) ? wp_unslash($data['items']) : '';
        $items_to_add = isset($data['data']) ? array_filter(wp_unslash((array)$data['data'])) : array();

        try {
            return $this->maybe_add_order_item($order_id, $items, $items_to_add);
        } catch (Exception $e) {
            throw new Exception(__('Create order error.', 'woocommerce'));
        }
    }

    public function createorderdraft($param = array())
    {
        $data_sync = wc_get_container()->get(DataSynchronizer::class);
        $order = new \WC_Order();
        $post_id = wp_insert_post(
            array(
                'post_type' => $data_sync->data_sync_is_enabled() ? $order->get_type() : $data_sync::PLACEHOLDER_ORDER_POST_TYPE,
                'post_status' => 'draft',
                'comment_status' => 'closed',
                'post_parent' => $order->get_changes()['parent_id'] ?? $order->get_data()['parent_id'] ?? 0,
                'post_author' => 1,
            )
        );
        $order->set_id($post_id);
        $order->save();

        try {
            ob_start();
            include __DIR__ . '/includes/html-order-items.php';
            $response = ob_get_clean();

            return $response;
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }

    public function getuserdetail($param = array())
    {
        $user_data = get_user_by('id', $param['id']);
        $shippingAddress = $this->get_formatted_shipping_name($param['id']);
        $billingAddress = $this->get_formatted_billing_name($param['id']);

        $user_firstname = get_user_meta($param['id'], 'first_name', true);
        $user_lastname = get_user_meta($param['id'], 'last_name', true);
        $name = $user_firstname . $user_lastname;

        $phone = get_user_meta($param['id'], 'billing_phone', true);
        if (!$phone) {
            $phone = get_user_meta($param['id'], 'shipping_phone', true);
        }

        return [
            'shipping' => $shippingAddress,
            'billing' => $billingAddress,
            'name' => $name,
            'nicename' => $user_data->display_name,
            'email' => $user_data->user_email,
            'phone' => $phone
        ];
    }

    public function getuserlist($param = array())
    {
        $response = [];
        $limit = 12;
        $totalPages = 1;

        $page = isset($param['page']) && $param['page'] ? $param['page'] : 1;
        $userCount = get_users([
                                   'search' => isset($param['name_mail_phone']) ? $param['name_mail_phone'] : '',
                                   'search_columns' => [isset($param['type']) ? $param['type'] : ''],
                                   'fields' => ['ID']
                               ]);
        $totalUsers = count($userCount) ?: 1;

        $offset = $limit * ($page - 1);
        $totalPages = ceil($totalUsers / $limit);

        $users = get_users([
                               'search' => isset($param['name_mail_phone']) ? $param['name_mail_phone'] : '',
                               'search_columns' => [isset($param['type']) ? $param['type'] : ''],
                               'fields' => ['ID', 'user_email', 'user_nicename'],
                               'offset' => $offset,
                               'number' => $limit,
                               'order' => 'ASC'
                           ]);

        foreach ($users as $key => $user) {
            $response[$key]['id'] = $user->id;
            $response[$key]['user_email'] = $user->user_email;
            $response[$key]['user_nicename'] = $user->user_nicename;

            $user_phone = get_user_meta($user->id, 'billing_phone', true);
            $response[$key]['billing_phone'] = $user_phone;
        }

        $res['users'] = $response;
        $res['page'] = $page;
        $res['totalPages'] = $totalPages;

        return $res;
    }

    public function getlinkitem($param = array())
    {
        $term = '';
        $include_variations = true;
        if (isset($param['term']) && $param['term']) {
            $term = (string)wc_clean(wp_unslash($param['term']));
        }

//  if (empty($term)) {
//    wp_die();
//  }

        if (!empty($param['limit'])) {
            $limit = absint($param['limit']);
        } else {
            $limit = absint(apply_filters('woocommerce_json_search_limit', 30));
        }

        $include_ids = !empty($param['include']) ? array_map('absint', (array)wp_unslash($param['include'])) : array();
        $exclude_ids = !empty($param['exclude']) ? array_map('absint', (array)wp_unslash($param['exclude'])) : array();

        $exclude_types = array();
        if (!empty($param['exclude_type'])) {
            $exclude_types = wp_unslash($param['exclude_type']);
            if (!is_array($exclude_types)) {
                $exclude_types = explode(',', $exclude_types);
            }

            foreach ($exclude_types as &$exclude_type) {
                $exclude_type = strtolower(trim($exclude_type));
            }

            $exclude_types = array_intersect(
                array_merge(array('variation'), array_keys(wc_get_product_types())),
                $exclude_types
            );
        }

        $data_store = WC_Data_Store::load('product');
        $ids = $data_store->search_products($term, '', (bool)$include_variations, true, $limit, $include_ids, $exclude_ids);

        $products = array();

        foreach ($ids as $id) {
            $product_object = wc_get_product($id);

            if (!($product_object && is_a($product_object, 'WC_Product'))) {
                continue;
            }

            $formatted_name = $product_object->get_formatted_name();

            if (in_array($product_object->get_type(), $exclude_types, true)) {
                continue;
            }

            if ($product_object->is_on_backorder()) {
                $stock_html = __('On backorder', 'woocommerce');
            } elseif ($product_object->is_in_stock()) {
                $stock_html = __('In stock', 'woocommerce');
            } else {
                $stock_html = __('Out of stock', 'woocommerce');
            }
            $formatted_name .= ' - ' . sprintf(__('%s', 'woocommerce'), $stock_html);

            $products[$product_object->get_permalink()] = rawurldecode(wp_strip_all_tags($formatted_name));
        }

        return $products;
    }

    public function getItemList($param = array())
    {
        $include_variations = true;
        if (empty($term) && isset($param['term'])) {
            $term = (string)wc_clean(wp_unslash($param['term']));
        }

//  if (empty($term)) {
//    wp_die();
//  }

        if (!empty($param['limit'])) {
            $limit = absint($param['limit']);
        } else {
            $limit = absint(apply_filters('woocommerce_json_search_limit', 30));
        }

        $include_ids = !empty($param['include']) ? array_map('absint', (array)wp_unslash($param['include'])) : array();
        $exclude_ids = !empty($param['exclude']) ? array_map('absint', (array)wp_unslash($param['exclude'])) : array();

        $exclude_types = array();
        if (!empty($param['exclude_type'])) {
            $exclude_types = wp_unslash($param['exclude_type']);
            if (!is_array($exclude_types)) {
                $exclude_types = explode(',', $exclude_types);
            }

            foreach ($exclude_types as &$exclude_type) {
                $exclude_type = strtolower(trim($exclude_type));
            }

            $exclude_types = array_intersect(
                array_merge(array('variation'), array_keys(wc_get_product_types())),
                $exclude_types
            );
        }

        $data_store = WC_Data_Store::load('product');
        $ids = $data_store->search_products($term, '', (bool)$include_variations, true, $limit, $include_ids, $exclude_ids);

        $products = array();

        foreach ($ids as $id) {
            $product_object = wc_get_product($id);

            if (!($product_object && is_a($product_object, 'WC_Product'))) {
                continue;
            }

            $formatted_name = $product_object->get_formatted_name();

            if (in_array($product_object->get_type(), $exclude_types, true)) {
                continue;
            }

            if ($product_object->is_on_backorder()) {
                $stock_html = __('On backorder', 'woocommerce');
            } elseif ($product_object->is_in_stock()) {
                $stock_html = __('In stock', 'woocommerce');
            } else {
                $stock_html = __('Out of stock', 'woocommerce');
            }
            $formatted_name .= ' - ' . sprintf(__('%s', 'woocommerce'), $stock_html);

            $products[$product_object->get_id()] = rawurldecode(wp_strip_all_tags($formatted_name));
        }

        return $products;
    }

    function get_formatted_shipping_name($user_id)
    {
        $shipping = '';
        $shipping .= get_user_meta($user_id, 'shipping_first_name', true) . ' ' . get_user_meta($user_id, 'shipping_last_name', true);

        $shipping_address_1 = get_user_meta($user_id, 'shipping_address_1', true);
        $shipping_address_2 = get_user_meta($user_id, 'shipping_address_2', true);
        $shipping_city = get_user_meta($user_id, 'shipping_city', true);
        $shipping_state = get_user_meta($user_id, 'shipping_state', true);
        $shipping_postcode = get_user_meta($user_id, 'shipping_postcode', true);
        $shipping_country = get_user_meta($user_id, 'shipping_country', true);

        if ($shipping_address_1) {
            $shipping .= " - ";
            $shipping .= $shipping_address_1;
        }

        if ($shipping_address_2) {
            $shipping .= " - ";
            $shipping .= $shipping_address_2;
        }

        if ($shipping_city) {
            $shipping .= " - ";
            $shipping .= $shipping_city;
        }

        if ($shipping_state) {
            $shipping .= " - ";
            $shipping .= $shipping_state;
        }

        if ($shipping_postcode) {
            $shipping .= " - ";
            $shipping .= $shipping_postcode;
        }

        if ($shipping_country) {
            $shipping .= " - ";
            $shipping .= $shipping_country;
        }

        return $shipping;
    }

    function get_formatted_billing_name($user_id)
    {
        $billing = '';
        $billing .= get_user_meta($user_id, 'billing_first_name', true) . ' ' . get_user_meta($user_id, 'billing_last_name', true);

        $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
        $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
        $billing_city = get_user_meta($user_id, 'billing_city', true);
        $billing_state = get_user_meta($user_id, 'billing_state', true);
        $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
        $billing_country = get_user_meta($user_id, 'billing_country', true);

        if ($billing_address_1) {
            $billing .= " - ";
            $billing .= $billing_address_1;
        }

        if ($billing_address_2) {
            $billing .= " - ";
            $billing .= $billing_address_2;
        }

        if ($billing_city) {
            $billing .= " - ";
            $billing .= $billing_city;
        }

        if ($billing_state) {
            $billing .= " - ";
            $billing .= $billing_state;
        }

        if ($billing_postcode) {
            $billing .= " - ";
            $billing .= $billing_postcode;
        }

        if ($billing_country) {
            $billing .= " - ";
            $billing .= $billing_country;
        }

        return $billing;
    }




    function add_address_book($param)
    {
        $wc_address_book = WC_Address_Book::get_instance();
        $address_names = $wc_address_book->get_address_names($param['customer_user'], 'shipping');
        $shipping_name = $wc_address_book->set_new_address_name($address_names, 'shipping');
        $this->add_address_name($wc_address_book, $_POST['customer_user'], $shipping_name, 'shipping');

        $customer = new WC_Customer($param['customer_user']);

        if (is_email($customer->get_display_name())) {
            $customer->set_display_name($customer->get_first_name() . ' ' . $customer->get_last_name());
        }

        $key = [
            '_shipping_first_name', '_shipping_last_name', '_shipping_company', '_shipping_email', '_shipping_phone', '_shipping_address_1'
            , '_shipping_address_2', '_shipping_city', '_shipping_state', '_shipping_postcode', '_shipping_country'
        ];

        foreach ($key as $val) {
            $key = str_replace('_shipping_', $shipping_name . '_', $val);
            $customer->update_meta_data($key, $param[$val]);
        }

        return $customer->save();
    }

    function add_address_name($wc_address_book, $user_id, $name, $type)
    {
        $address_names = $wc_address_book->get_address_names($user_id, $type);

        if (!is_array($address_names) || empty($address_names)) {
            $address_names = array();
        }

        if (!in_array($name, $address_names, true)) {
            array_push($address_names, $name);
            $wc_address_book->save_address_names($user_id, $address_names, $type);
        }
    }

    function maybe_add_order_item($order_id, $items, $items_to_add)
    {
        try {
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new Exception(__('Invalid order', 'woocommerce'));
            }

            if (!empty($items)) {
                $save_items = array();
                parse_str($items, $save_items);
                wc_save_order_items($order->get_id(), $save_items);
            }

            $order_notes = array();
            $added_items = array();

            foreach ($items_to_add as $item) {
                if (!isset($item['id'], $item['qty']) || empty($item['id'])) {
                    continue;
                }

                $product_id = absint($item['id']);
                $qty = wc_stock_amount($item['qty']);
                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }

                if ('variable' === $product->get_type()) {
                    continue;
                }

                $validation_error = new WP_Error();
                $validation_error = apply_filters('woocommerce_ajax_add_order_item_validation', $validation_error, $product, $order, $qty);

                if ($validation_error->get_error_code()) {
                    continue;
                }

                $item_id = $order->add_product($product, $qty, array('order' => $order));
                $item = apply_filters('woocommerce_ajax_order_item', $order->get_item($item_id), $item_id, $order, $product);
                $added_items[$item_id] = $item;
                $order_notes[$item_id] = $product->get_formatted_name();

                do_action('woocommerce_ajax_add_order_item_meta', $item_id, $item, $order);
            }

            $order->add_order_note(sprintf(__('Added line items: %s', 'woocommerce'), implode(', ', $order_notes)), false, true);
            do_action('woocommerce_ajax_order_items_added', $added_items, $order);

            $data = get_post_meta($order_id);

            ob_start();
            include __DIR__ . '/includes/html-order-items.php';
            $items_html = ob_get_clean();

            return $items_html;
        } catch (Exception $e) {
            throw $e;
        }
    }

    function wp_add_tax_item($param, $matched_tax_rates)
    {
        $order = wc_get_order($param['order_id']);

        foreach ($order->get_items() as $item_id => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            $taxTotalPrice = 0;
            $productTaxes = WC_Tax::calc_shipping_tax($product->get_price(), $matched_tax_rates);

            foreach ($productTaxes as $val) {
                $val = wc_round_tax_total($val);
                $taxTotalPrice += NumberUtil::round($val, wc_get_price_decimals());
            }

            wc_update_order_item_meta($item_id, '_line_tax', $taxTotalPrice);
            wc_update_order_item_meta($item_id, '_line_subtotal_tax', $taxTotalPrice);

            $taxes = ['total' => $productTaxes, 'subtotal' => $productTaxes];
            wc_update_order_item_meta($item_id, '_line_tax_data', $taxes);
        }

        return $order->save();
    }

    function wp_add_tax_shipping($param, $matched_tax_rates)
    {
        $order = wc_get_order($param['order_id']);

        foreach ($order->get_items('shipping') as $item_id => $item) {
            wc_delete_order_item($item_id);
        }

        $zoneId = $this->getShippingZone($param['country']);
        $shipping_zone = new WC_Shipping_Zone($zoneId);
        $shipping_methods = $shipping_zone->get_shipping_methods(true, 'values');

        $shippingMethodArr = [
            'name' => '',
            'method_id' => '',
            'instance_id' => '',
            'cost' => '',
            'total_tax' => '',
            'taxes' => '',
            'Items' => ''
        ];

        foreach ($shipping_methods as $instance_id => $shipping_method) {
            if ($shipping_method->instance_id == $param['fake_shipping_method']) {
                $shippingCost = $shipping_method->cost;

                $shippingMethodArr = [
                    'name' => $shipping_method->title,
                    'method_id' => $shipping_method->id,
                    'instance_id' => $shipping_method->instance_id,
                    'total' => wc_format_decimal($shipping_method->cost),
                ];
            }
        }

        $taxTotalShipping = 0;
        $shippingTaxes = WC_Tax::calc_shipping_tax($shippingCost, $matched_tax_rates);

        foreach ($shippingTaxes as $val) {
            $val = wc_round_tax_total($val);
            $taxTotalShipping += NumberUtil::round($val, wc_get_price_decimals());
        }

        $shippingMethodArr['total_tax'] = $taxTotalShipping;
        $shippingMethodArr['taxes'] = $shippingTaxes;

        $item = new \WC_Order_Item_Shipping();
        $item->set_props($shippingMethodArr);
        $item->save();

        $order->add_item($item);
        $order->save();

        return $shippingTaxes;
    }

    function wp_add_tax_total($param, $matched_tax_rates, $shippingTaxes)
    {
        $order = wc_get_order($param['order_id']);
        $subTotal = $order->get_subtotal();

        foreach ($order->get_items('tax') as $item_id => $item) {
            wc_delete_order_item($item_id);
        }

        $taxes = WC_Tax::calc_tax($subTotal, $matched_tax_rates);
        foreach ($taxes as $k => $v) {
            $taxes[$k] = wc_round_tax_total($v);
        }

        $taxTotal = $this->getTaxTotals($taxes, $shippingTaxes);

        if (function_exists('wc_avatax')) {
            foreach (array_keys($taxTotal) as $code) {
                if (Framework\SV_WC_Helper::str_starts_with($code, wc_avatax()->get_tax_handler()->get_rate_prefix()) && !empty($matched_tax_rates[$code]['label'])) {
                    $taxTotal[$code]->label = $matched_tax_rates[$code]['label'];
                }
            }
        }

        foreach ($taxTotal as $val) {
            $item = new WC_Order_Item_Tax();
            $tax_rate_id = $val->tax_rate_id;
            $item->set_props([
                                 'rate_id' => $tax_rate_id,
                                 'tax_total' => $val->amount,
                                 'rate_code' => WC_Tax::get_rate_code($tax_rate_id),
                                 'label' => WC_Tax::get_rate_label($tax_rate_id),
                                 'compound' => WC_Tax::is_compound($tax_rate_id),
                                 'rate_percent' => WC_Tax::get_rate_percent_value($tax_rate_id)
                             ]);
            $item->save();
            $order->add_item($item);
        }

        return $order->save();
    }

    function wp_matched_tax_rates($param)
    {
        $matched_tax_rates = [];
        if (function_exists('wc_avatax')) {
            wc_avatax()->set_tax_handler();

            $rates = wc_avatax()->get_tax_handler()->get_estimated_rates($param['country'], $param['state'], str_replace('-', '', $param['postcode']), $param['city']);
            foreach ($rates as $code => $rate) {
                $matched_tax_rates[$code] = array(
                    'rate' => $rate->get_rate() * 100,
                    'label' => $rate->get_label(),
                    'shipping' => 'yes',
                    'compound' => 'no',
                );
            }
        }

        return $matched_tax_rates;
    }

    function getTaxTotals($taxes, $shippingTaxes)
    {
        $tax_totals = array();

        foreach ($taxes as $key => $tax) {
            $code = WC_Tax::get_rate_code($key);

            if ($code || apply_filters('woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated') === $key) {
                if (!isset($tax_totals[$code])) {
                    $tax_totals[$code] = new stdClass();
                    $tax_totals[$code]->amount = 0;
                }

                $tax_totals[$code]->tax_rate_id = $key;
                $tax_totals[$code]->is_compound = WC_Tax::is_compound($key);
                $tax_totals[$code]->label = WC_Tax::get_rate_label($key);

                if (isset($shippingTaxes[$key])) {
                    //  $tax -= $shippingTaxes[$key];
                    $tax = wc_round_tax_total($tax);
                    $tax += NumberUtil::round($shippingTaxes[$key], wc_get_price_decimals());
                    unset($shippingTaxes[$key]);
                }

                $tax_totals[$code]->amount += wc_round_tax_total($tax);
                $tax_totals[$code]->formatted_amount = wc_price($tax_totals[$code]->amount);
            }
        }

        if (apply_filters('woocommerce_cart_hide_zero_taxes', true)) {
            $amounts = array_filter(wp_list_pluck($tax_totals, 'amount'));
            $tax_totals = array_intersect_key($tax_totals, $amounts);
        }

        return $tax_totals;
    }

    function getShippingZone($country)
    {
        $data_store = WC_Data_Store::load('shipping-zone');
        $raw_zones = $data_store->get_zones();

        foreach ($raw_zones as $raw_zone) {
            $zone = new WC_Shipping_Zone($raw_zone);
            foreach ($zone->get_zone_locations() as $val) {
                if ($val->code == $country) {
                    return $zone->get_id();
                }
            }
        }

        return null;
    }

    function wp_add_order_shipping($param)
    {
        $order = wc_get_order($param['order_id']);
        $cus_user_id = ((isset($param['customer_user']) && $param['customer_user']) ? $param['customer_user'] : 1);

        $address = array(
            'first_name' => $param['_shipping_first_name'],
            'last_name' => $param['_shipping_last_name'],
            'company' => $param['_shipping_company'],
            'email' => $param['_shipping_email'],
            'phone' => $param['_shipping_phone'],
            'address_1' => $param['_shipping_address_1'],
            'address_2' => $param['_shipping_address_2'],
            'city' => $param['_shipping_city'],
            'state' => $param['_shipping_state'],
            'postcode' => $param['_shipping_postcode'],
            'country' => $param['_shipping_country']
        );

        $order->set_address($address, 'shipping');
        $order->set_address($address);
        $order->set_customer_id($cus_user_id);

        $order->save();
    }

    function wp_add_order_fee($param)
    {
        $_POST = $param;

        try {
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new Exception(__('Invalid order', 'woocommerce'));
            }

            $amount = isset($_POST['amount']) ? wc_clean(wp_unslash($_POST['amount'])) : 0;

            $calculate_tax_args = array(
                'country' => isset($_POST['country']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['country']))) : '',
                'state' => isset($_POST['state']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['state']))) : '',
                'postcode' => isset($_POST['postcode']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['postcode']))) : '',
                'city' => isset($_POST['city']) ? wc_strtoupper(wc_clean(wp_unslash($_POST['city']))) : '',
            );

            if (strstr($amount, '%')) {
                $order->calculate_totals(false);
                $formatted_amount = $amount;
                $percent = floatval(trim($amount, '%'));
                $amount = $order->get_total() * ($percent / 100);
            } else {
                $amount = floatval($amount);
                $formatted_amount = wc_price($amount, array('currency' => $order->get_currency()));
            }

            $fee = new WC_Order_Item_Fee();
            $fee->set_amount($amount);
            $fee->set_total($amount);
            $fee->set_name(sprintf(__('%s fee', 'woocommerce'), wc_clean($formatted_amount)));

            $order->add_item($fee);
            $order->calculate_taxes($calculate_tax_args);
            $order->calculate_totals();
            $order->save();
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
    }
}