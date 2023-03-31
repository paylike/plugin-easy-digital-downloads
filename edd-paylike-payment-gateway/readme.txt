=== Payment gateway via Paylike for Easy Digital Downloads  ===
Contributors: ionut.calara
Tags: credit card, gateway, paylike, easy-digital-downloads, edd
Requires at least: 4.4
Tested up to: 6.1.2
Stable tag: 1.7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Take payments in your Easy Digital Downloads store using the Paylike Gateway

== Description ==

Accept payments with Visa and MasterCard instantly. Paylike is the modern full-stack payment platform for which you have been waiting.

Without a doubt, Easy Digital Downloads is a complete eCommerce solution for WordPress. Right out of the box, it is prepared to power your online business without the need of any other dependencies. Together with our payment plugin for Easy Digital Downloads you will have a strong and lean setup to take your business to the next level.

= Countries =

Countries supported by Paylike.

Notice that this has nothing to do with accepting cards. Cards from all over the world is accepted.

You will need a registered company or citizenship from one of these countries:

- Austria
- Belgium
- Bulgaria
- Croatia
- Cyprus
- Czech Republic
- Denmark
- Estonia
- Finland
- France
- Germany
- Greece
- Hungary
- Iceland
- Ireland
- Italy
- Latvia
- Lichtenstein
- Lithuania
- Luxembourg
- Malta
- Netherlands
- Norway
- Poland
- Portugal
- Romania
- Slovakia
- Slovenia
- Spain
- Sweden
- United Kingdom

== Installation ==

Once you have installed Easy Digital Downloads on your Wordpress setup, follow these simple steps:
Signup at (paylike.io) [https://paylike.io] (it’s free)

1. Create a live account
1. Create an app key for your website store
1. Upload the plugin files to the `/wp-content/plugins/edd-paylike` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Insert the app key and your public key in the Gateway settings for the Paylike payment plugin

Start earning revenue on your website store!
When you have the first transaction the team at Paylike will reach out to you for some additional details (required by financial regulations) so we can payout your profits.
If you expect to have a volume higher than EUR 40.000 / month, reach out for volume pricing.


== Frequently Asked Questions ==

= Does the plugin support test mode? =

Yes, the plugin supports test mode.


= How do i capture a payment if i have set the option to not capture the money on checkout? =

To capture a preapproved payment use the buttons you will find in the payment history for all approval pending orders. The buttons are located in the "Preapproval" column.

== Screenshots ==

1. The settings panel for the Paylike gateway
2. Checkout screen
3. Payment screen via embedded form
4. Payment screen via popup

== Changelog ==

= 1.7.0 =
Update compatibility

= 1.6.2 =
Fix pay possibility from edd embedded form

= 1.6.1 =
Fix display plugin version in plugins page

= 1.6.0 =
Upgraded js SDK version from v3 to v10
Modified logic to work with v10

= 1.5.1 =
Remove placeholders from card form

= 1.5 =
Add tds support

= 1.4 =
Update php client
Add verbose logs
Add automated testing
Update wp tested up to tag

= 1.3 =
Minor fix
Update data format for paylike dashboard

= 1.2 =
* Updated api wrapper
* Updated tested up to tag
* Added settings link
* Added currencies multiplier crosscheck
* Fixed javascript being hooked when gateway was not valid for frontend

= 1.1 =
* Updated error message handler

= 1.0 =
* Initial release
