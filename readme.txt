=== Posti Shipping ===
Contributors: joosev, ottok, leotoikka, serter, k1sul1
Tags: woocommerce, shipping, toimitustavat, smartship, posti
Requires at least: 5.0
Tested up to: 6.7.2
Requires PHP: 7.1
Stable tag: trunk
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin enables WooCommerce orders to ship using Posti.

== Description ==

[Posti](https://www.posti.fi/) is a shipping service provider in Finland. This plugin integrates Posti’s parcel shipping services into WooCommerce. To start shipping, all your WooCommerce needs is this plugin and API credentials of your account registered with Posti. Order API credentials to use the plugin. (https://www.posti.fi/fi/yrityksille/tehosta-logistiikkaa/digitaaliset-palvelut-ja-rajapinnat/verkkokaupan-lisaosat)

This plugin requires at least WooCommerce version 4.7.0.

== Features ==

* Integrates Posti parcel shipping services with WooCommerce.
* Supports WooCommerce shipping zones and classes.
* Customers can choose to ship products to an address or to any pickup point.
* Store owners can add pickup points to any shipping zone’s shipping method.
* Store owners can use whatever shipping pricing plugin
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
= 3.10.6 =
* Bug fix for pallet type in parcel shipments

= 3.10.5 =
* Bug fix for Express-parcel return

= 3.10.4 =
* Fix for possible Cross Site Request Forgery (CSRF) vulnerability

= 3.10.3 =
* Fix for possible Full Path Disclosure (FPD) vulnerability

= 3.10.2 =
* New feature: adding product quantities to address label
* New feature: order notes can be added to address label
* New feature: new order list column to show shipment tracking link
* Pickup points optional for Express-parcel
* Bug fix for duplicate shipments
* Bug fix for bulk order status update

= 3.10.1 =
* Quick fix for Legacy order storage backwards compatibility

= 3.10.0 =
* New feature: WooCommerce block editor support
* Return product mappings fixed
* Tested against Wordpress 6.6.1 and WooCommerce 9.1.4

= 3.9.4 =
* New feature: allow ignoring product weights
* New feature: HPOS support
* Various small fixes

= 3.9.3 =
* Fix missing variable preventing list type of pickup points showing at checkout
* Fix PHP 8.2 related fixes

= 3.9.2 =
* WP and Woo compatibility update
* Update to latest API library
* Various small fixes

= 3.9.1 =
* WP compatibility update
* Bug fix: Attach tracking info to email

= 3.9.0 =
* New feature: Support for order pickups
* New feature: Pickup point filtering
* New feature: Email info as template
* New feature: Custom bulk create order
* New feature: Option to create return labels automatically
* Bug fix: Weight and volume information to shipping labels if variable product

= 3.8.0 =
* Support for shortcodes
* Support for variable products SKU on shipping labels

= 3.7.2 =
* Fix small pickup point search bug

= 3.7.1 =
* Fix for pickup point searches
* Fix for pakettikauppa_fetch_tracking_codes -action
* Various small fixes

= 3.7.0 =
* New feature: Support for different label sizes
* New feature: Shipping phone number optional / mandatory settings
* New feature: LV translations
* Refactoring: Arranging settings regrading checkout settings

= 3.6.2 =
* Bug fix: load product class when not in admin view

= 3.6.1 =
* Bug fix

= 3.6.0 =
* New feature: LQ-shipments
* New feature: If shipping postcode is missing, use billing address
* New feature: LT translation
* Tested against woocommerce 4.6.1 and wordpress 5.9.2
* Various bug fixes

= 3.5.2 =
* Diagnostic tool bug fix when using Posti Shipping version
* Add error message if mass action operation fails
* Update automated tests and PHP 8.x tests
* Convert API secret as password field for added security
* Update Pakettikauppa API library to latest version

= 3.5.1 =
* Bug fix

= 3.5.0 =
* Customer facing templates (checkout & view order) can now be replaced in own theme dir
* Minumum woocommerce version is now 4.7.0 and tested against woocommerce 6.1.0

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
