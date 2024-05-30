<?php
defined('ABSPATH') || exit;

$customerUser = isset($param['customer_user']) ? $param['customer_user'] : 0;

$wc_address_book = WC_Address_Book::get_instance();
$woo_address_book_billing_address_book = $wc_address_book->get_address_book($customerUser, 'billing');

$customerShipping = [];

foreach ($woo_address_book_billing_address_book as $key => $address_item) {
  if ($key == $param['shippingOrderId']) {
    $customerShipping = [
      'first_name' => $address_item[$key . '_first_name'],
      'last_name'  => $address_item[$key . '_last_name'],
      'country'    => $address_item[$key . '_country'],
      'address_1'  => $address_item[$key . '_address_1'],
      'address_2'  => $address_item[$key . '_address_2'],
      'city'       => $address_item[$key . '_city'],
      'state'      => $address_item[$key . '_state'],
      'postcode'   => $address_item[$key . '_postcode'],
      'phone'      => $address_item[$key . '_phone'],
      'email'      => $address_item[$key . '_email'],
      'company'    => $address_item[$key . '_company'],
    ];
  }
}
?>

<div class="row">
  <div class="col-md-6 mb-3">
    <label for="_shipping_first_name">First name</label>
    <input type="text" class="form-control" id="_shipping_first_name"
           name="_shipping_first_name"
           placeholder="* First name"
           value="<?php echo isset($customerShipping['first_name']) ? $customerShipping['first_name'] : ''; ?>"
           required>
  </div>
  <div class="col-md-6 mb-3">
    <label for="_shipping_last_name">Last name</label>
    <input type="text" class="form-control" id="_shipping_last_name" name="_shipping_last_name"
           placeholder="* Last name"
           value="<?php echo isset($customerShipping['last_name']) ? $customerShipping['last_name'] : ''; ?>"
           required>
  </div>
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <label for="_shipping_email">Email address</label>
    <input type="text" class="form-control" id="_shipping_email" name="_shipping_email"
           placeholder="* Email address"
           value="<?php echo isset($customerShipping['email']) ? $customerShipping['email'] : ''; ?>"
           required>
  </div>
  <div class="col-md-6 mb-3">
    <label for="_shipping_phone">Phone</label>
    <input type="text" class="form-control" id="_shipping_phone" name="_shipping_phone"
           placeholder="* Phone"
           value="<?php echo isset($customerShipping['phone']) ? $customerShipping['phone'] : ''; ?>"
           required>
  </div>
</div>

<div class="mb-3">
  <label for="_shipping_company">Company</label>
  <input type="text" class="form-control" id="_shipping_company" name="_shipping_company"
         value="<?php echo isset($customerShipping['company']) ? $customerShipping['company'] : ''; ?>"
         placeholder="Company" required="">
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <label for="_shipping_address_1">Address 1</label>
    <input type="text" class="form-control" id="_shipping_address_1" name="_shipping_address_1"
           placeholder="* Address 1"
           value="<?php echo isset($customerShipping['address_1']) ? $customerShipping['address_1'] : ''; ?>"
           required>
  </div>
  <div class="col-md-6 mb-3">
    <label for="_shipping_address_2">Address 2</label>
    <input type="text" class="form-control" id="_shipping_address_2" name="_shipping_address_2"
           placeholder="Address 2"
           value="<?php echo isset($customerShipping['address_2']) ? $customerShipping['address_2'] : ''; ?>"
           required>
  </div>
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <label for="_shipping_country">Country / Region</label>
    <select class="custom-select d-block w-100 change-address-shipping" id="_shipping_country" name="_shipping_country"
            required="">
      <option value="US" <?php echo isset($customerShipping['country']) && $customerShipping['country'] == 'US' ? 'selected' : ''; ?>>
        United States(+1)
      </option>
      <option value="MX" <?php echo isset($customerShipping['country']) && $customerShipping['country'] == 'MX' ? 'selected' : ''; ?>>
        Mexico(+52)
      </option>
      <option value="CA" <?php echo isset($customerShipping['country']) && $customerShipping['country'] == 'CA' ? 'selected' : ''; ?>>
        Canada(+1)
      </option>
      <option value="VN" <?php echo isset($customerShipping['country']) && $customerShipping['country'] == 'VN' ? 'selected' : ''; ?>>
        Viet Nam(+84)
      </option>
      <option value="KR" <?php echo isset($customerShipping['country']) && $customerShipping['country'] == 'KR' ? 'selected' : ''; ?>>
        Korea(+82)
      </option>
    </select>
  </div>
  <div class="col-md-6 mb-3">
    <label for="_shipping_city">City</label>
    <input class="form-control change-address-shipping" id="_shipping_city" name="_shipping_city" placeholder="* City"
           value="<?php echo isset($customerShipping['city']) && $customerShipping['city'] == '' ? 'selected' : ''; ?>"
           required="">
  </div>
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <label for="_shipping_state">State / County</label>
    <select class="custom-select d-block w-100 change-address-shipping" id="_shipping_state" name="_shipping_state"
            required="">
      <?php if (isset($customerShipping['state'])) { ?>
        <option value="<?php echo $customerShipping['state']; ?>"><?php echo $customerShipping['state']; ?></option>
      <?php } else { ?>
        <option value="">Choose...</option>
      <?php } ?>
    </select>
  </div>
  <div class="col-md-6 mb-3">
    <label for="_shipping_postcode">Postcode / ZIP</label>
    <input class="form-control change-address-shipping" id="_shipping_postcode" name="_shipping_postcode"
           value="<?php echo isset($customerShipping['postcode']) ? $customerShipping['postcode'] : ''; ?>"
           placeholder="* Postcode / ZIP" required="">
  </div>
</div>
