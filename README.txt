=== Usermaven ===
Contributors: usermaven
Donate link: https://usermaven.com/
Tags: analytics, google analytics alternative, web analytics, stats, privacy, privacy friendly, privacy friendly analytics,
Requires at least: 3.0.1
Tested up to: 6.8.3
Requires PHP: 5.6
Stable tag: 1.2.7
License: Massachusetts Institute of Technology (MIT) license
License URI: https://opensource.org/licenses/MIT

Usermaven's web analytics product is a Google Analytics alternative that provides a real-time view of your website traffic metrics. Cookie-free, hosted in EU, and fully compliant with GDPR, CCPA and PECR.

== Description ==

Usermaven helps marketing and product teams turn more visitors into customers, get more people to use the product, and keep them coming back. No more guessing or relying on intuition – let data drive your success.

* Effortless, no-code event tracking: Unlike other tools, Usermaven eliminates dependence on developers for tracking key actions performed by users on your website or app, including comprehensive WooCommerce store analytics.
* Analyze your marketing channels to increase ROI. See which traffic sources or campaigns are bringing in the most conversions and sales.
* Track and compare the performance of your marketing campaigns with UTMs.
* Track individual user behavior to understand their interests. See what they're paying attention to, and make informed decisions.
* Get accurate stats with Adblocker bypassing and cookie-less tracking.

= WooCommerce Integration =

Usermaven automatically tracks all essential WooCommerce events to give you deep insights into your store's performance:

* Product Views: Track when customers view product pages
* Cart Actions: Monitor add-to-cart, remove-from-cart, and cart updates
* Checkout Process: Follow users through each step of your checkout funnel
* Purchase Events: Capture successful purchases with complete order details
* Product Categories: Understand which product categories drive the most interest
* Revenue Analytics: Get detailed revenue reports and purchase patterns

= Why Usermaven? =

Most firms try to use complex and expensive analytics platforms like Mixpanel or Amplitude but never get around to properly configuring them to get meaningful insights. You need a product analytics solution that's easy to setup and has ready-made templates to generate actionable insights for making data-backed growth decisions.

That's why we built Usermaven, the new data scientist in your team. We are making product analytics affordable, easy to setup and simple to maintain.

* Super Simple – Designed to be simple and intuitive in every way, without complexity or clutter to distract you. WooCommerce events are tracked automatically with zero configuration needed.
* Privacy Compliance – We've designed Usermaven to comply with GDPR and CCPA regulations from day one.
* System Security – We apply the latest security standards and take measures to ensure your data is safe with us.
== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Usermaven, log in to your WordPress dashboard, navigate to the Plugins menu and click "Add New".

In the search field type "Usermaven" and click Search Plugins. Once you have found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Upgrade Notice ==

Please make sure you make a backup of your database before updating any version to ensure that none of your data is lost.

== Frequently Asked Questions ==

= Contact Us =

For more information, visit the [Usermaven website](https://usermaven.com/). And for answers to any particular question, [contact us](https://usermaven.com/contact).

== Screenshots ==

1. Usermaven Analytics Dashboard
2. Usermaven WordPress Plugin Settings Page

== Changelog ==

= 1.2.7 - January 14, 2026 =
* Added separate form tracking toggle for better control over form submission tracking
* Enhanced tracking options with dedicated form tracking configuration
* Improved settings page with clearer tracking options

= 1.2.6 - December 19, 2025 =
* Events now include IP address and browser information.
* Cookieless tracking mode now enables full privacy protection.
* IP address is anonymized for privacy before any processing.
* This update maintains compatibility with previous versions of the plugin.

= 1.2.2 - March 26, 2025 =
- Fixed CSRF vulnerability in settings form
- Added nonce verification to improve security in admin settings

= 1.2.1 - March 25, 2025 =
- Fixed shareable dashboard documentation link

= 1.2.0 - March 25, 2025 =
- Added user identification feature for logged-in WordPress users (optional in admin panel)
- Introduced option to exclude specific user roles (e.g., Administrator)
- Fixed WordPress values unpack in old PHP versions

= 1.1.8 - February 17, 2025 =
- Improved WooCommerce cart init null check for early exit
- Fixed issue with tracking pixel not loading correctly on some WordPress themes
- Improved error handling for invalid API keys in settings page

= 1.1.7 - February 13, 2025 =
- Fixed WooCommerce initiate checkout event
- Improved initiate checkout for custom checkout page and order status update
- Added WooCommerce tracking class and events API custom events
- Added WordPress dashboard widget to display Usermaven stats directly in admin area

= 1.1.6 - October 18, 2024 =
- Added customer role tracking
- Enhanced tracking pixel with custom domain white-labeling for ad blocker bypassing
- Updated readme.txt to reflect latest features and compatibility with WordPress
- Fixed minor bug with user role exclusion not applying correctly

= 1.1.5 - February 12, 2024 =
- Optimized Performance and Consistency for WordPress UI
- Improved performance of event tracking by optimizing JavaScript injection
- Added support for tracking frontend events (clicks, form submissions) with toggle in settings

= 1.1.4 - December 12, 2023 =
- Added new WordPress plugin features
- Updated compatibility to WordPress 6.4.2 in readme.txt
- Minor bug fixes and stability improvements based on testing with latest WordPress version

= 1.1.0 - March 16, 2023 =
- Updated tracking snippet and version
- Enhanced readme.txt with detailed features
- Added support for automatic WooCommerce event tracking (product views, cart actions, purchases)
- Introduced admin settings page for tracking options

= 1.0.1 - February 27, 2023 =
- Replaced WP URL validator with regex pattern
- Minor fixes for WordPress plugin standards compatibility
- Updated readme.txt with installation instructions and plugin description

= 1.0.0 - January 31, 2023 =
- Initial WordPress plugin release
- Initial release with basic tracking pixel functionality and core files (usermaven.php, readme.txt)
- Admin settings for API key configuration
- Implemented requested changes (February 6, 2023)