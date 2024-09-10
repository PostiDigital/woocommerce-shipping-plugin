import { __ } from '@wordpress/i18n';

export const txt = wcSettings["posti-blocks_data"].txt; //Temporary solution while not clear how use @wordpress/i18n

export const txt_i18n = {
    block_options: __('Block options', 'woo-pakettikauppa'),
    pickup_block_title: __('Pickup point', 'woo-pakettikauppa'),
    pickup_select_field_default: __('Select a pickup point', 'woo-pakettikauppa'),
    pickup_select_field_optional: __('No pickup point: Send to the street address', 'woo-pakettikauppa'),
    pickup_select_other: __('Other', 'woo-pakettikauppa'),
    pickup_error: __('Please choose a pickup point', 'woo-pakettikauppa'),
    pickup_not_found: __('No pickup points were found. Check the address.', 'woo-pakettikauppa'),
    cart_pickup_info: __('You can choose the pickup point on the Checkout page', 'woo-pakettikauppa'),
    checkout_pickup_info: __('Choose one of pickup points close to the address you entered', 'woo-pakettikauppa'),
    custom_pickup_title: __('Custom pickup address', 'woo-pakettikauppa'),
    custom_pickup_description: __('If none of your preferred pickup points are listed, fill in a custom address above and select another pickup point', 'woo-pakettikauppa'),
    custom_pickup_help: __('After entering, please wait for a while for the results to be received', 'woo-pakettikauppa'),
    custom_pickup_error_too_short: __('The value is too short', 'woo-pakettikauppa'),
    custom_pickup_error_bad_char: __('Invalid character entered', 'woo-pakettikauppa'),
    custom_pickup_address: __('The selection of pickup points has been changed based on the address %s', 'woo-pakettikauppa')
};
