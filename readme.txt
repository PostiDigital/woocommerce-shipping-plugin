=== Posti Shipping ===
Contributors: joosev, ottok, leotoikka, serter, k1sul1
Tags: woocommerce, shipping, toimitustavat, smartship, posti, smartpost, prinetti
Requires at least: 4.6
Tested up to: 5.5
Requires PHP: 7.1
Stable tag: trunk
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin enables WooCommerce orders to ship using Posti.

== Description ==

[Posti](https://www.posti.fi/) is a shipping service provider in Finland. This plugin integrates Posti’s parcel shipping services into WooCommerce. To start shipping, all your WooCommerce needs is this plugin and API credentials of your account registered with Posti. Order API credentials to use the plugin. (https://www.posti.fi/fi/yrityksille/tehosta-logistiikkaa/digitaaliset-palvelut-ja-rajapinnat/verkkokaupan-lisaosat)

This plugin requires at least WooCommerce version 3.4.

== Features ==

* Integrates Posti parcel shipping services with WooCommerce.
* Supports WooCommerce shipping zones and classes.
* Customers can choose to ship products to an address or to any pickup point.
* Store owners can add pickup points to any shipping zone’s shipping method.
* Store owners can specify themselves any fixed rate for a shipping or have free shipping if the order value is above a certain limit.
* Store owners can generate the shipping label by one click.
* Store owners can generate shipping labels as mass action from orders view.
* Store owners and customers get tracking code links and status information.
* Support for Cash-On-Delivery.

== Installation ==

1. Install the plugin through the WordPress plugins screen directly or upload the plugin files to the `/wp-content/plugins/posti-shipping` directory.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Use the Settings->WooCommerce Posti screen to configure the plugin
1. The plugin requires WooCommerce to be installed, with shipping zones configured and this plugin activated and settings set.

This plugin can also be installed directly from Github.

== Frequently Asked Questions ==

= Is this ready for production use? =

Yes! If you encounter any issues related to this plugin, please report at https://github.com/Seravo/woo-pakettikauppa/issues or [Posti customer service](https://www.posti.fi/verkkokauppiaat/)

== Screenshots ==

1. Order screen in admin
2. Checkout in twentynineteen theme
3. Checkout in twentynineteen theme
4. Settings screen in admin

== Changelog ==

= 1.1.0 =
* Small tweaks to UX in settings
* Ability to choose pickup point when creating custom shipment
* Removed obsolete Posti shipping method
* Various bug fixes

= 1.0.2 =
* Various small fixes to functionality and translations

= 1.0.1 =
* Tracking URL change

= 1.0.0 =
* Initial release for General Availability.
