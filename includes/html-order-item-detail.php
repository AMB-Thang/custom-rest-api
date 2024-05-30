<?php
/**
 * Order items HTML for meta box.
 *
 * @package WooCommerce\Admin
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer;

defined('ABSPATH') || exit;

global $wpdb;

if (!$order) {
  $order = new \WC_Order();
  $data_sync = wc_get_container()->get(DataSynchronizer::class);

  $post_id = wp_insert_post(
    array(
      'post_type'   => 'shop_order',
      'post_status' => 'auto-draft',
      'post_author' => 1,
    )
  );

  if (!$post_id) {
    throw new \Exception(__('Could not create order in posts table.', 'woocommerce'));
  }

  $order->set_id($post_id);
}

/**
 * Allow plugins to determine whether refunds UI should be rendered in the template.
 *
 * @param bool $render_refunds If the refunds UI should be rendered.
 * @param int $order_id The Order ID.
 * @param WC_Order $order The Order object.
 * @since 6.4.0
 *
 */
$render_refunds = (bool)apply_filters('woocommerce_admin_order_should_render_refunds', 0 < $order->get_total() - $order->get_total_refunded() || 0 < absint($order->get_item_count() - $order->get_item_count_refunded()), $order->get_id(), $order);

$payment_gateway = wc_get_payment_gateway_by_order($order);
$line_items = $order->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
$discounts = $order->get_items('discount');
$line_items_fee = $order->get_items('fee');
$line_items_shipping = $order->get_items('shipping');

if (wc_tax_enabled()) {
  $order_taxes = $order->get_taxes();
  $tax_classes = WC_Tax::get_tax_classes();
  $classes_options = wc_get_product_tax_class_options();
  $show_tax_columns = count($order_taxes) === 1;
}

$clo_wp_list_item = 6;
?>

<form name="post" action="" id="save_order_wp">
  <div class="row mt-3 user-exit">
    <div class="col-md-12">
      <h4 class="mb-3 shipping-title">
        Shipping Address
      </h4>
    </div>

    <div class="col-md-12">
      <div id="form_create_order">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="_shipping_first_name">First name</label>
            <input type="text" class="form-control" class="form-control" disabled
                   value="<?php echo $order->get_billing_first_name(); ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label for="_shipping_last_name">Last name</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_shipping_last_name(); ?>">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="_shipping_email">Email address</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_email(); ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label for="_shipping_phone">Phone</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_phone(); ?>">
          </div>
        </div>

        <?php if ($order->get_billing_company()) { ?>
          <div class="mb-3">
            <label for="_shipping_company">Company</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_company(); ?>">
          </div>
        <?php } ?>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="_shipping_address_1">Address 1</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_address_1(); ?>">
          </div>
            <?php if ($order->get_billing_address_2()) { ?>
              <div class="col-md-6 mb-3">
                <label for="_shipping_address_2">Address 2</label>
                <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_address_2(); ?>">
              </div>
            <?php } ?>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="_shipping_country">Country / Region</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_country(); ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label for="_shipping_city">City</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_city(); ?>">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="_shipping_state">State / County</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_state(); ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label for="_shipping_postcode">Postcode / ZIP</label>
            <input type="text" class="form-control" disabled value="<?php echo $order->get_billing_postcode(); ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-12">
      <div id="add_item">
        <div class="row">
          <div class="col-md-12 mb-2">
            <h4 class="mb-3 shipping-title">
              Item list
            </h4>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12 order-data">
            <div class="woocommerce_order_items_wrapper wc-order-items-editable" style="border-top: 1px solid #ddd;">
              <table cellpadding="0" cellspacing="0" class="woocommerce_order_items"
                     order_id="<?php echo $order->get_id(); ?>"
                     width="100%">
                <thead>
                <tr>
                  <th class="item sortable order-items-table" colspan="2"
                      data-sort="string-ins"><?php esc_html_e('Item', 'woocommerce'); ?></th>
                  <?php do_action('woocommerce_admin_order_item_headers', $order); ?>
                  <th class="item_cost sortable order-items-table"
                      data-sort="float"><?php esc_html_e('Cost', 'woocommerce'); ?></th>
                  <th class="quantity sortable order-items-table"
                      data-sort="int"><?php esc_html_e('Qty', 'woocommerce'); ?></th>
                  <th class="line_cost sortable order-items-table"
                      data-sort="float"><?php esc_html_e('Total', 'woocommerce'); ?></th>
                  <?php
                  if (!empty($order_taxes)) :
                    foreach ($order_taxes as $tax_id => $tax_item) :
                      $tax_class = wc_get_tax_class_by_tax_id($tax_item['rate_id']);
                      $tax_class_name = isset($classes_options[$tax_class]) ? $classes_options[$tax_class] : __('Tax', 'woocommerce');
                      $column_label = !empty($tax_item['label']) ? $tax_item['label'] : __('Tax', 'woocommerce');
                      /* translators: %1$s: tax item name %2$s: tax class name  */
                      $column_tip = sprintf(esc_html__('%1$s (%2$s)', 'woocommerce'), $tax_item['name'], $tax_class_name);
                      ?>
                      <th class="line_tax tips order-items-table" data-tip="<?php echo esc_attr($column_tip); ?>">
                        <?php echo esc_attr($column_label); ?>
                        <input type="hidden" class="order-tax-id" name="order_taxes[<?php echo esc_attr($tax_id); ?>]"
                               value="<?php echo esc_attr($tax_item['rate_id']); ?>">
                        <?php if ($order->is_editable()) : ?>
                          <a class="delete-order-tax" href="#" data-rate_id="<?php echo esc_attr($tax_id); ?>"></a>
                        <?php endif; ?>
                      </th>
                      <?php
                      $clo_wp_list_item++;
                    endforeach;
                  endif;
                  ?>
                  <th class="wc-order-edit-line-item order-items-table" width="1%">&nbsp;</th>
                </tr>
                </thead>
                <tbody id="order_line_items">
                <?php
                if ($line_items) {
                  foreach ($line_items as $item_id => $item) {
                    do_action('woocommerce_before_order_item_' . $item->get_type() . '_html', $item_id, $item, $order);

                    include __DIR__ . '/html-order-item.php';

                    do_action('woocommerce_order_item_' . $item->get_type() . '_html', $item_id, $item, $order);
                  }
                  do_action('woocommerce_admin_order_items_after_line_items', $order->get_id());
                } else {
                  echo '<tr><td class="wp-list-item text-center" colspan="' . $clo_wp_list_item . '">Item is empty</td></tr>';
                }
                ?>
                </tbody>
                <tbody id="order_fee_line_items">
                <?php
                foreach ($line_items_fee as $item_id => $item) {
                  include __DIR__ . '/html-order-fee.php';
                }
                do_action('woocommerce_admin_order_items_after_fees', $order->get_id());
                ?>
                </tbody>
                <tbody id="order_shipping_line_items">
                <?php
                $shipping_methods = WC()->shipping() ? WC()->shipping()->load_shipping_methods() : array();
                foreach ($line_items_shipping as $item_id => $item) {
                  include __DIR__ . '/html-order-shipping.php';
                }
                do_action('woocommerce_admin_order_items_after_shipping', $order->get_id());
                ?>
                </tbody>
                <tbody id="order_refunds">
                <?php
                $refunds = $order->get_refunds();

                if ($refunds) {
                  foreach ($refunds as $refund) {
                    include __DIR__ . '/html-order-refund.php';
                  }
                  do_action('woocommerce_admin_order_items_after_refunds', $order->get_id());
                }
                ?>
                </tbody>
              </table>
            </div>
            <div class="wc-order-data-row wc-order-totals-items wc-order-items-editable">

              <?php
              $countRow = 2;
              if (0 < $order->get_total_discount()) {
                $countRow++;
              }

              if (0 < $order->get_total_fees()) {
                $countRow++;
              }

              if ($order->get_shipping_methods()) {
                $countRow++;
              }

              if (wc_tax_enabled()) {
                foreach ($order->get_tax_totals() as $code => $tax_total) {
                  $countRow++;
                }
              }
              ?>

              <table class="wc-order-totals">
                <tr>
                  <td class="label"><?php esc_html_e('Items Subtotal:', 'woocommerce'); ?></td>
                  <td width="1%"></td>
                  <td class="total">
                    <?php echo wc_price($order->get_subtotal(), array('currency' => $order->get_currency())); ?>
                  </td>
                </tr>
                <?php if (0 < $order->get_total_discount()) : ?>
                  <tr>
                    <td class="label"><?php esc_html_e('Coupon(s):', 'woocommerce'); ?></td>
                    <td width="1%"></td>
                    <td class="total">-
                      <?php echo wc_price($order->get_total_discount(), array('currency' => $order->get_currency())); ?>
                    </td>
                  </tr>
                <?php endif; ?>
                <?php if (0 < $order->get_total_fees()) : ?>
                  <tr>
                    <td class="label"><?php esc_html_e('Fees:', 'woocommerce'); ?></td>
                    <td width="1%"></td>
                    <td class="total">
                      <?php echo wc_price($order->get_total_fees(), array('currency' => $order->get_currency())); ?>
                    </td>
                  </tr>
                <?php endif; ?>

                <?php do_action('woocommerce_admin_order_totals_after_discount', $order->get_id()); ?>

                <?php if ($order->get_shipping_methods()) : ?>
                  <tr>
                    <td class="label"><?php esc_html_e('Shipping:', 'woocommerce'); ?></td>
                    <td width="1%"></td>
                    <td class="total">
                      <?php echo wc_price($order->get_shipping_total(), array('currency' => $order->get_currency())); ?>
                    </td>
                  </tr>
                <?php endif; ?>

                <?php do_action('woocommerce_admin_order_totals_after_shipping', $order->get_id()); ?>

                <?php if (wc_tax_enabled()) : ?>
                  <?php foreach ($order->get_tax_totals() as $code => $tax_total) : ?>
                    <tr>
                      <td class="label"><?php echo esc_html($tax_total->label); ?>:</td>
                      <td width="1%"></td>
                      <td class="total">
                        <?php
                        // We use wc_round_tax_total here because tax may need to be round up or round down depending upon settings, whereas wc_price alone will always round it down.
                        echo wc_price(wc_round_tax_total($tax_total->amount), array('currency' => $order->get_currency())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>

                <?php do_action('woocommerce_admin_order_totals_after_tax', $order->get_id()); ?>

                <tr>
                  <td class="label"><?php esc_html_e('Order Total', 'woocommerce'); ?>:</td>
                  <td width="1%"></td>
                  <td class="total">
                    <?php echo wc_price($order->get_total(), array('currency' => $order->get_currency())); ?>
                  </td>
                </tr>
              </table>

              <div class="clear"></div>

              <?php if (in_array($order->get_status(), array('processing', 'completed', 'refunded'), true) && !empty($order->get_date_paid())) : ?>

                <table class="wc-order-totals">
                  <tr>
                    <td class="<?php echo $order->get_total_refunded() ? 'label' : 'label label-highlight'; ?>"><?php esc_html_e('Paid', 'woocommerce'); ?>
                      : <br/></td>
                    <td width="1%"></td>
                    <td class="total">
                      <?php echo wc_price($order->get_total(), array('currency' => $order->get_currency())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </td>
                  </tr>
                  <tr>
                    <td>
					<span class="description">
					<?php
          if ($order->get_payment_method_title()) {
            /* translators: 1: payment date. 2: payment method */
            echo esc_html(sprintf(__('%1$s via %2$s', 'woocommerce'), $order->get_date_paid()->date_i18n(get_option('date_format')), $order->get_payment_method_title()));
          } else {
            echo esc_html($order->get_date_paid()->date_i18n(get_option('date_format')));
          }
          ?>
					</span>
                    </td>
                    <td colspan="2"></td>
                  </tr>
                </table>

                <div class="clear"></div>

              <?php endif; ?>

              <?php if ($order->get_total_refunded()) : ?>
                <table class="wc-order-totals">
                  <tr>
                    <td class="label refunded-total"><?php esc_html_e('Refunded', 'woocommerce'); ?>:</td>
                    <td width="1%"></td>
                    <td class="total refunded-total">
                      -<?php echo wc_price($order->get_total_refunded(), array('currency' => $order->get_currency())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                  </tr>

                  <?php do_action('woocommerce_admin_order_totals_after_refunded', $order->get_id()); ?>

                  <tr>
                    <td class="label label-highlight"><?php esc_html_e('Net Payment', 'woocommerce'); ?>:</td>
                    <td width="1%"></td>
                    <td class="total">
                      <?php echo wc_price($order->get_total() - $order->get_total_refunded(), array('currency' => $order->get_currency())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </td>
                  </tr>

                </table>
              <?php endif; ?>

              <div class="clear"></div>

              <table class="wc-order-totals">
                <?php do_action('woocommerce_admin_order_totals_after_total', $order->get_id()); ?>
              </table>

              <table style="width: 100%">
                <tr>
                  <td colspan="4" class="text-center">
                    <button type="button" class="btn btn-primary btn-back-list" style="width: 48%">Back to list</button>
                  </td>
                </tr>
              </table>

              <div class="clear"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>