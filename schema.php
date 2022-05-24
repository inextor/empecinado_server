<?php
namespace APP;
use \akou\DBTable;
class address extends \akou\DBTable
{
	var $id;
	var $name;
	var $phone;
	var $business_name;
	var $email;
	var $rfc;
	var $user_id;
	var $address;
	var $zipcode;
	var $country;
	var $state;
	var $city;
	var $suburb;
	var $note;
	var $created;
	var $updated;
}
class attachment extends \akou\DBTable
{
	var $id;
	var $uploader_user_id;
	var $file_type_id;
	var $filename;
	var $original_filename;
	var $content_type;
	var $size;
	var $width;
	var $height;
	var $status;
	var $created;
	var $updated;
}
class bank_account extends \akou\DBTable
{
	var $id;
	var $organization_id;
	var $user_id;
	var $name;
	var $email;
	var $bank;
	var $account;
	var $currency;
	var $alias;
	var $created;
	var $updated;
}
class bank_movement extends \akou\DBTable
{
	var $id;
	var $organization_id;
	var $provider_user_id;
	var $amount;
	var $balance;
	var $paid_date;
	var $reference;
	var $note;
	var $receipt_attachment_id;
	var $bank_account_id;
	var $payment_method_id;
	var $type;
	var $created_by_user_id;
	var $created;
	var $updated;
}
class bank_movement_bill extends \akou\DBTable
{
	var $id;
	var $bank_movement_id;
	var $bill_id;
	var $amount;
	var $created;
	var $updated;
}
class bill extends \akou\DBTable
{
	var $id;
	var $folio;
	var $accepted_status;
	var $organization_id;
	var $aproved_by_user_id;
	var $paid_by_user_id;
	var $bank_account_id;
	var $paid_to_bank_account_id;
	var $provider_user_id;
	var $purchase_order_id;
	var $invoice_attachment_id;
	var $pdf_attachment_id;
	var $receipt_attachment_id;
	var $note;
	var $due_date;
	var $paid_date;
	var $total;
	var $currency;
	var $amount_paid;
	var $status;
	var $paid_status;
	var $name;
	var $created;
	var $updated;
}
class box extends \akou\DBTable
{
	var $id;
	var $status;
	var $production_item_id;
	var $type_item_id;
	var $serial_number_range_start;
	var $serial_number_range_end;
	var $store_id;
	var $created;
	var $updated;
}
class box_content extends \akou\DBTable
{
	var $id;
	var $box_id;
	var $item_id;
	var $initial_qty;
	var $qty;
	var $serial_number_range_start;
	var $serial_number_range_end;
}
class category extends \akou\DBTable
{
	var $id;
	var $type;
	var $image_id;
	var $name;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $codigo;
	var $description;
	var $presentacion;
	var $created;
	var $updated;
}
class file_type extends \akou\DBTable
{
	var $id;
	var $preferences_id;
	var $name;
	var $content_type;
	var $extension;
	var $is_image;
	var $image_id;
	var $created;
	var $updated;
}
class image extends \akou\DBTable
{
	var $id;
	var $uploader_user_id;
	var $is_private;
	var $filename;
	var $original_filename;
	var $content_type;
	var $size;
	var $width;
	var $height;
	var $created;
}
class item extends \akou\DBTable
{
	var $id;
	var $category_id;
	var $image_id;
	var $name;
	var $on_sale;
	var $content;
	var $content_measure_type;
	var $description;
	var $reference_price;
	var $code;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class keyboard_shortcut extends \akou\DBTable
{
	var $id;
	var $name;
	var $key_combination;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class merma extends \akou\DBTable
{
	var $id;
	var $shipping_id;
	var $stocktake_id;
	var $box_id;
	var $store_id;
	var $item_id;
	var $qty;
	var $note;
	var $created;
	var $created_by_user_id;
	var $updated;
}
class notification_token extends \akou\DBTable
{
	var $id;
	var $user_id;
	var $provider;
	var $token;
	var $created;
	var $updated;
	var $status;
}
class order extends \akou\DBTable
{
	var $id;
	var $client_user_id;
	var $cashier_user_id;
	var $store_id;
	var $shipping_address_id;
	var $billing_address_id;
	var $discount;
	var $price_type_id;
	var $tax_percent;
	var $authorized_by;
	var $tag;
	var $status;
	var $delivery_status;
	var $type;
	var $client_name;
	var $total;
	var $shipping_cost;
	var $pending_amount;
	var $subtotal;
	var $tax;
	var $payment_type;
	var $amount_paid;
	var $address;
	var $suburb;
	var $city;
	var $state;
	var $zipcode;
	var $name;
	var $note;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class order_item extends \akou\DBTable
{
	var $id;
	var $status;
	var $order_id;
	var $item_id;
	var $qty;
	var $unitary_price;
	var $subtotal;
	var $total;
	var $is_free_of_charge;
	var $note;
	var $tax;
	var $delivery_status;
	var $return_required;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class order_item_fullfillment extends \akou\DBTable
{
	var $id;
	var $box_id;
	var $box_content_id;
	var $order_item_id;
	var $item_id;
	var $is_free_of_charge;
	var $qty;
	var $order_id;
	var $csv_serial_number_codes;
}
class organization extends \akou\DBTable
{
	var $id;
	var $name;
	var $rfc;
	var $razon_social;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class pallet extends \akou\DBTable
{
	var $id;
	var $store_id;
	var $production_item_id;
	var $created;
	var $updated;
	var $created_by_user_id;
}
class pallet_content extends \akou\DBTable
{
	var $id;
	var $pallet_id;
	var $box_id;
	var $status;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class preferences extends \akou\DBTable
{
	var $id;
	var $default_price_type_id;
	var $name;
	var $user_image_id;
	var $logo_image_id;
	var $login_background_image_id;
	var $background_image_id;
	var $background_color;
	var $menu_background_color;
	var $default_file_logo_image_id;
	var $etiqueta_image_id;
	var $client_image_id;
	var $login_logo_image_id;
}
class price extends \akou\DBTable
{
	var $id;
	var $store_id;
	var $currency_id;
	var $item_id;
	var $price_type_id;
	var $price;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class price_type extends \akou\DBTable
{
	var $id;
	var $name;
	var $sort_priority;
	var $status;
	var $created;
	var $updated;
}
class production extends \akou\DBTable
{
	var $id;
	var $caldo_item_id;
	var $codigo;
	var $date;
	var $litros;
	var $status;
	var $store_id;
	var $note;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class production_item extends \akou\DBTable
{
	var $id;
	var $box_type_item_id;
	var $item_id;
	var $items_per_box;
	var $boxes_per_pallet;
	var $qty;
	var $production_id;
	var $created;
	var $updated;
}
class push_notification extends \akou\DBTable
{
	var $id;
	var $user_id;
	var $object_type;
	var $object_id;
	var $priority;
	var $push_notification_id;
	var $sent_status;
	var $title;
	var $body;
	var $link;
	var $app_path;
	var $icon_image_id;
	var $read_status;
	var $response;
	var $created;
	var $updated;
}
class requisition extends \akou\DBTable
{
	var $id;
	var $status;
	var $required_by_store_id;
	var $requested_to_store_id;
	var $date;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class requisition_item extends \akou\DBTable
{
	var $id;
	var $requisition_id;
	var $item_id;
	var $status;
	var $aproved_status;
	var $qty;
	var $created;
	var $updated;
}
class returned_bottles extends \akou\DBTable
{
	var $id;
	var $item_id;
	var $from_user_id;
	var $qty;
	var $note;
	var $created;
	var $updated;
	var $returned;
}
class serial_number extends \akou\DBTable
{
	var $id;
	var $serial_number_record_id;
	var $image_id;
	var $evidence_image_id;
	var $type_item_id;
	var $item_id;
	var $box_id;
	var $store_id;
	var $order_id;
	var $assigned_to_user_id;
	var $code;
	var $status;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class serial_number_record extends \akou\DBTable
{
	var $id;
	var $type_item_id;
	var $order_item_id;
	var $assigned_to_user_id;
	var $store_id;
	var $date;
	var $start_code;
	var $qty;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class session extends \akou\DBTable
{
	var $id;
	var $user_id;
	var $status;
	var $created;
	var $updated;
}
class shipping extends \akou\DBTable
{
	var $id;
	var $shipping_guide;
	var $shiping_company;
	var $requisition_id;
	var $status;
	var $from_store_id;
	var $to_store_id;
	var $date;
	var $received_by_user_id;
	var $delivery_datetime;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class shipping_item extends \akou\DBTable
{
	var $id;
	var $shipping_id;
	var $requisition_item_id;
	var $item_id;
	var $box_id;
	var $pallet_id;
	var $qty;
	var $received_qty;
	var $shrinkage_qty;
	var $created;
	var $updated;
}
class stock_record extends \akou\DBTable
{
	var $id;
	var $pallet_id;
	var $box_content_id;
	var $box_id;
	var $item_id;
	var $order_item_id;
	var $store_id;
	var $shipping_item_id;
	var $production_item_id;
	var $serial_number_record_id;
	var $previous_qty;
	var $movement_qty;
	var $qty;
	var $description;
	var $movement_type;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class stocktake extends \akou\DBTable
{
	var $id;
	var $store_id;
	var $name;
	var $status;
	var $created;
	var $updated;
	var $created_by_user_id;
	var $updated_by_user_id;
}
class stocktake_item extends \akou\DBTable
{
	var $id;
	var $stocktake_id;
	var $box_id;
	var $box_content_id;
	var $pallet_id;
	var $item_id;
	var $creation_qty;
	var $current_qty;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class stocktake_scan extends \akou\DBTable
{
	var $id;
	var $stocktake_id;
	var $pallet_id;
	var $box_id;
	var $box_content_id;
	var $item_id;
	var $qty;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class store extends \akou\DBTable
{
	var $id;
	var $person_in_charge_user_id;
	var $organization_id;
	var $name;
	var $description;
	var $type;
	var $tax_percent;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class user extends \akou\DBTable
{
	var $id;
	var $status;
	var $default_billing_address_id;
	var $organization_id;
	var $default_shipping_address_id;
	var $price_type_id;
	var $store_id;
	var $credit_limit;
	var $credit_days;
	var $name;
	var $username;
	var $contact;
	var $business_name;
	var $email;
	var $phone;
	var $type;
	var $password;
	var $image_id;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $created;
	var $updated;
}
class user_permission extends \akou\DBTable
{
	var $user_id;
	var $add_providers;
	var $global_bills;
	var $add_bills;
	var $approve_bill_payments;
	var $pay_bills;
	var $add_items;
	var $send_shipping;
	var $global_shipping;
	var $receive_shipping;
	var $add_user;
	var $pos;
	var $global_pos;
	var $preferences;
	var $caldos;
	var $store_prices;
	var $global_prices;
	var $global_stats;
	var $add_stock;
	var $price_types;
	var $production;
	var $add_marbetes;
	var $asign_marbetes;
	var $fullfill_orders;
	var $global_fullfill_orders;
	var $add_requisition;
	var $created_by_user_id;
	var $updated_by_user_id;
	var $is_provider;
	var $stocktake;
	var $created;
	var $updated;
}
