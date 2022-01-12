=== Posti Shipping ===
Contributors: joosev, ottok, leotoikka, serter, k1sul1
Tags: woocommerce, shipping, toimitustavat, smartship, posti, smartpost, prinetti
Requires at least: 5.0
Tested up to: 5.8.2
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
= 3.4.1 =
* Bug fix related to token caching

= 3.4.0 =
* New feature: Updated diagnostic tool to check versions and cached tokens
* New feature: Additional info to shipping labels in custom shipment
* New feature: Instuction if no configured shipping method
* Fine tuning: Wizard fixes in settings
* Fine tuning: Double check that cached token is really expired after TTL
* Bug fix: Automated test fixes
* Bug fix: Refactoring, small bug fixes

= 3.3.0 =
* New feature: Diagnostic tool to check everything is working correctly
* New feature: If using only one provider in pickup point searches, don't show the provider name
* Bug fix: Allow saving if more than 16 shipping methods
* Bug fix: Various small bug fixes

= 3.2.3 =
* Minor bugfix for token authentication

= 3.2.2 =
* Minor bugfix

= 3.2.1 =
* Fix timing issue preventing working from most installations

= 3.2.0 =
* Add support for additional info to shipping labels
* Various bug fixes

= 3.1.2.1 =
* Various compatibility fixes

= 3.1.2 =
* Backwards compatibility for bulk actions

= 3.1.1 =
* If product does not exists, display "unknown product"
* Change versioning to identical to Pakettikauppa plugin

= 3.1.0 =
* Create multiple shipping labels from order view
* Allow editing of order phone number and email address
* Make pickup point search optional
* Various bug fixes

= 3.0.1 =
* Fix bug in pickup point saving in checkout

= 3.0.0 =
* Remove Pakettikauppa shipping method option
* Add possibility to choose pickup point in order view
* Fix bug for pickup point not saving in checkout

= 2.4.0 =
* New pickup point chooser for checkout
* Fixing pickup point related bugs

= 2.3.3 =
* Some plugins brake because of forced validation of chosen pickup points. This update loosens the validation little bit

= 2.3.2 =
* Fix to validation that checks if pickup point is chosen
* Add checks that billing email and/or phone exists before adding those to shipping

= 2.3.1 =
* Fix to help to fill orders from previous versions when using pickup points as shipping method

= 2.3.0 =
* Many small changes: f.ex. adding new fields to checkout and to sender configuration
* Fixes compatibility with Klarna
* Fixes return shipments

= 2.2.0 =
* Properly catch exceptions so faulty orders don't cause a fatal error
* Allow supplying a different address for pickup point search
* Fix headers so files download properly

= 2.1.2 =
* Fixed a bug breaking the setup wizard

= 2.1.1 =
* Fixed a bug breaking shipping methods created the old way.

= 1.1.2 =
* Better error handling if API credentials are wrong

= 1.1.1 =
* Fix tracking url

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
