import { registerPlugin } from '@wordpress/plugins';

const render = () => {};

registerPlugin('wc-pakettikauppa', {
    render,
    scope: 'woocommerce-checkout',
});
