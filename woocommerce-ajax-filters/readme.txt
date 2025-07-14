=== Advanced AJAX Product Filters ===
Plugin Name: Advanced AJAX Product Filters
Contributors: dholovnia, berocket
Donate link: https://berocket.com/woocommerce-ajax-products-filter/?utm_source=wordpress_org&utm_medium=donate&utm_campaign=ajax_filters
Tags: product filters, ajax product filters, woocommerce filters, wc filters, category filter
Requires at least: 5.0
Tested up to: 6.8.1
Stable tag: 1.6.9.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce AJAX Product Filters - Advanced product filtering ability for your WooCommerce shop. Add unlimited filters with one widget.

== Description ==

WooCommerce AJAX Product Filters - Advanced product filtering ability for your WooCommerce shop. Add unlimited filters with one widget.

= New Feature in version 1.6.3 =

&#9989; Filter by Post Meta (Custom meta field)

= New Feature in version 1.5 =

&#9989; New styles for filters: Checkbox, Select, Slider, Color, Image
&#9989; New slider styles
&#9989; New buttons styles
&#9989; Selected filters area horizontal styles
&#9989; Single selection for check
&#9989; Select and Select2 can be displayed at the same time
&#9989; Collapse widget option with different settings
&#9989; Less JavaScript and HTML code for the same result.
&#9989; More compatibility with themes and plugins
&#9989; Better compatibility with Divi Builder, Beaver Builder, Elementor Builder
&#9989; Relevanssi compatibility
&#9989; More ways to customize filters and add additional functionality
&#9989; Option to set how hierarchical attributes must be displayed
&#9989; Separate admin title and frontend title
&#9989; Back button in the browser on AJAX
&#9989; All JavaScript in one minified file
&#9989; All CSS Styles in one minified file
&#9989; Checked style for image element style

= Features: =

&#9989; AJAX Filters, Pagination and Sorting!
&#9989; Filter by Price
&#9989; Filter by Product Category
&#9989; Filter by Attribute
&#9989; Unlimited Filters
&#9989; Multiple User Interface Elements
&#9989; Great support for custom/premium themes
&#9989; SEO Friendly URLs ( with HTML5 PushState )
&#9989; Filter Visibility By Product Category And Globals.
&#9989; Accessible through shortcode
&#9989; Filter box height limit with scroll themes
&#9989; Working great with custom widget area
&#9989; Drag and Drop Filter Building
&#9989; Select2 for the dropdown menu
&#9989; And More...

= Additional Features in Paid Plugin: =

&#9989; Filter by Custom Taxonomy, Price ranges, Sale status, Sub-categories, Date and Availability( in stock | out of stock | any )
&#9989; Nice URLs for SEO-Friendly URLs
&#9989; A slider can use strings as a value
&#9989; Price as a checkbox with min and max values
&#9989; Enhancements of the free features
&#9989; Show amount of products before updating with the "Update button" widget
&#9989; Search box widget
&#9989; Cache for Widgets
&#9989; Display only selected attribute values or hide selected attribute values


= Plugin Links: =
[Paid Plugin](https://berocket.com/woocommerce-ajax-products-filter/?utm_source=wordpress_org&utm_medium=plugin_links&utm_campaign=ajax_filters)
[Demo](https://woocommerce-products-filter.berocket.com/shop?utm_source=wordpress_org&utm_medium=plugin_links&utm_campaign=ajax_filters)
[Docs](https://docs.berocket.com/plugin/woocommerce-ajax-products-filter?utm_source=wordpress_org&utm_medium=plugin_links&utm_campaign=ajax_filters)

= &#127852; Wanna try the admin side? =
[Admin Demo](https://berocket.com/woocommerce-ajax-products-filter/?utm_source=wordpress_org&utm_medium=admin_demo&utm_campaign=ajax_filters#try-admin) - Get access to this plugin's admin and try it from inside. Change things and watch how they work.

= Premium plugin video =
[youtube https://youtu.be/PQTXzp9Tpbc]
[youtube https://youtu.be/Ltz82Zs5pl0]
[youtube https://youtu.be/GA3O1F6YVNE]
[youtube https://youtu.be/GPA77L0XBxM]
*we don't have a video with the free plugin right now, but we are working on it*

= Compatibility with WooCommerce plugins =
Advanced AJAX Product Filters has been tested and compatibility is certain with the following WooCommerce plugins that you can add to your site:

&#128312; [**Advanced Product Labels for WooCommerce**](https://wordpress.org/plugins/advanced-product-labels-for-woocommerce/)
&#128312; [**Load More Products for WooCommerce**](https://wordpress.org/plugins/load-more-products-for-woocommerce/)
&#128312; [**Brands for WooCommerce**](https://wordpress.org/plugins/brands-for-woocommerce/)
&#128312; [**Grid/List View for WooCommerce**](https://wordpress.org/plugins/gridlist-view-for-woocommerce/)
&#128312; [**Product Preview for WooCommerce**](https://wordpress.org/plugins/product-preview-for-woocommerce/)
&#128312; [**Products Compare for WooCommerce**](https://wordpress.org/plugins/products-compare-for-woocommerce/)
&#128312; [**Wishlist and Waitlist for WooCommerce**](https://wordpress.org/plugins/wish-wait-list-for-woocommerce/)

= Shortcode: =
* In editor `[br_filters attribute=price type=slider title="Price Filter"]`
* In PHP `do_shortcode('[br_filters attribute=price type=slider title="Price Filter"]');`

= Shortcode Options: =
* `attribute`(required) - product attribute, e.g. price or length. Don't forget that WooCommerce adds the pa_ suffix for created attributes.
 So if you create a new attribute `jump`, its name is `pa_jump`
* `type`(required) - checkbox, radio, slider or select
* `operator` - OR or AND
* `title` - whatever you want to see as a title. Can be empty
* `product_cat` - parent category id
* `cat_propagation` - should we propagate this filter to child categories? Set 1 to turn this on
* `height` - max filter box height. When height is met, the scroll will be added
* `scroll_theme` - used if height is set and actual height of the box is more


= Advanced Settings (Widget area): =
* Product Category - this is a good place to pin your filter to a product's category.
 For example, you sell phones and cases for them. If the user chooses the Category "Phones", the filter "Have Wi-Fi" will appear, but if the user chooses "Cases", it will not be there as the Admin sets that the "Have Wi-Fi" filter will be visible only on the "Phones" category.
* Filter Box Height - if your filter has too many options, it is nice to limit the height of the filter so that it does not prolong the page too much. A scroll will appear.
* Scroll theme - if "Filter Box Height" is set and the box length is more than "Filter Box Height," the scroll will appear, and how it looks depends on the theme you choose.


= Advanced Settings (Plugin Settings): =
* Plugin settings can be found in admin area, WooCommerce -> Product Filters
* "No Products" message - Text that will be shown if no products are found
* "No Products" class - Add class and use it to style "No Products" box
* Products selector - Selector for a tag that is holding products
* Sorting control - Take control over WooCommerce's sorting selection
* SEO-friendly URLs - URL will be changed when the filter is selected/changed
* Turn all filters off - If you want to hide filters without losing the current configuration, just turn them off



== Installation ==

= Step 1: =
* First, you need to add attributes to the products( the WooCommerce plugin should be installed and activated already )
* Go to Admin area -> Products -> Attributes and add attributes your products will have
* Click the attribute's name where the type is selected and add values to it. Predefine product options
* Go to your products and add attributes to each of them

= Step 2: =
* Install and activate the plugin
* First of all, open Admin area -> WooCommerce -> Product Filter and check what global options you can manage
* After that, open Admin area -> Appearance -> Widgets
* In Available Widgets ( left side of the screen ) find AJAX Product Filters
* Add it to the Sidebar you choose
* Enter title, choose an attribute that will be used for filtering products, choose filter type, choose operator( whether a product should have all selected values (AND) or one of them (OR) ),
* Click save and go to your shop to check how it works.
* That's it =)


== Frequently Asked Questions ==

= Is it compatible with all WordPress themes? =
BeRocket plugins are compatible with most themes that are developed according to WordPress and WooCommerce guidelines.


= How can I get support if my WooCommerce plugin is not working? =
If you have problems with our plugins or something is not working as it should, first follow these preliminary steps:

* Test the plugin with a WordPress default theme to ensure that the error is not caused by the theme you are currently using.
* Deactivate all plugins you use and check if the problem is still occurring.
* Ensure that your plugin, theme, and WordPress and WooCommerce versions (if required) are updated and that the problem you are experiencing has not already been solved in a later plugin update.

If none of the previously listed actions help you resolve the issue, please submit a ticket in the forum and accurately describe your problem. Specify the versions of WordPress and WooCommerce you are using, as well as any other information that may assist us in resolving your issue as swiftly as possible. Thank you!


= How can I get more features for my WooCommerce plugin? =
You can get more features with the premium version of Advanced AJAX Product Filters, available on [BeRocket page](https://berocket.com/woocommerce-ajax-products-filter/?utm_source=wordpress_org&utm_medium=faq&utm_campaign=ajax_filters). Here you can read more about the premium features of the plugin and make it give it its best shot!


= How can I try the full-featured plugin? =
You can try this plugin's admin side [here](https://berocket.com/woocommerce-ajax-products-filter/?utm_source=wordpress_org&utm_medium=faq&utm_campaign=ajax_filters#try-admin). Configure plugin the way you need to check the results.

---

== Screenshots ==
1. General settings
2. JavaScript settings
3. Widget

---

== Changelog ==

= 1.6.9.2 =
* Fix - Compatibility version: WooCommerce 10.0

= 1.6.9.1 =
* Enhancement - New hook to disable any compatibility for theme/builder/plugin.
* Fix - Error with wizard on some site
* Fix - WPbakery page builder compatibility

= 1.6.9 =
* Enhancement - Better support Divi Builder
* Enhancement - Better support Elementor Builder
* Enhancement - Support Live Composer Page Builder
* Enhancement - Support WPBakery Page Builder
* Enhancement - Support Page Builder by SiteOrigin
* Enhancement - Support Oxygen Builder
* Enhancement - Support Breakdance Builder
* Fix - Select2 initialize error
* Fix - Additional table variation update error
* Fix - Meta query parameters support in some cases
* Fix - Divi theme/builder issue in some case page not displayed or header has incorrect style
* Fix - Admin bar styles

= 1.6.8.2 =
* HOTFIX - Vulnerability in plugin
* Enhancement - New button designs
* Enhancement - New checkbox and selected filters area designs
* Enhancement - Style for selected filters area in global settings
* Enhancement - Better compatibility with Elementor page builder
* Fix - Compatibility with Advanced Custom Field
* Fix - Issues in storefront, button images text and few small issues

= 1.6.8.1 =
* HOTFIX - Display message for deprecated errors

= 1.6.8 =
* Enhancement - Compatibility version: WooCommerce 9.6
* Enhancement - Setup wizard
* Fix - JavaScript error for pagination when multiple selectors used
* Fix - Divi module script on some themes
* Fix - Initialize additional tables

= 1.6.7.1 =
* Enhancement - Option to disable MySQL derived merge for filter queries

= 1.6.7 =
* Enhancement - Compatibility version: WooCommerce 9.4 and WordPress 6.7
* Fix - Translation init errors with WordPress 6.7

= 1.6.6 =
* Enhancement - Compatibility version: WooCommerce 9.3
* Enhancement - WooCommerce requirements
* Fix - Plugin update to premium PHP error

= 1.6.5 =
* Enhancement - Compatibility version: Wordpress 6.6 and WooCommerce 9.1
* Enhancement - Button to regenerate additional tables
* Enhancement - Option to remove some variations data
* Enhancement - Recount all attributes when woocommerce recount used
* Enhancement - Advanced Custom Fields compatibility
* Enhancement - Add option to use values as color/image
* Fix - Additional table generation
* Fix - Correct filters update from different group
* Fix - Some templates with different attribute data
* Fix - New plugin framework

= 1.6.4.6 =
* Enhancement - Message that attribute do not have values
* Enhancement - Bottom position for price new slider
* Enhancement - New Selected Filters Area template
* Fix - Some UX texts
* Fix - Addon filters settings
* Fix - Trailing slash for canonical
* Fix - Link changes
* Fix - Template for price
* Fix - Additional tables generation for variations

= 1.6.4.5 =
* Enhancement - Add-on Filter Additional Settings
* Enhancement - Option to replace categories shortcode with products
* Fix - Divi module styles
* Fix - Pagination with incorrect selectors
* Fix - MariaDB 10.4 compatibility for database update

= 1.6.4.4 =
* Enhancement - WooCommerce High-Performance Order Storage support enable

= 1.6.4.3 =
* Fix - Link like WooCommerce add-on with slider

= 1.6.4.2 =
* Enhancement - New Divi module functionality
* Fix - Pagination replace with translation
* Fix - Price range on search page and some other pages
* Fix - Link like WooCommerce add-on
* Fix - Compatibility with WooCommerce 7.8
* Fix - Primary key for tables in plugin

= 1.6.4.1 =
* Fix - Divi Module for group do not display filters

= 1.6.4 =
* Enhancement - Divi Modules with more options
* Fix - Additional table generation for some database
* Fix - Price Range filtering
* Fix - Error filters do not exist
* Fix - Divi Module preview styles

= 1.6.3.4 =
* Enhancement - Additional tables generation to not change collation
* Enhancement - Additional tables check is tables exist
* Enhancement - Additional tables clear tables instead remove
* Fix - Barn2 Product table new check

= 1.6.3.3 =
* Enhancement - Compatibility version: Wordpress 6.1 and WooCommerce 7.1
* Fix - Some plugin links to match new BeRocket Site

= 1.6.3.2 =
* Enhancement - Option to fix pagination position after filter page without pagination
* Enhancement - Regenerate additional tables if it was removed
* Enhancement - Compatibility version: WooCommerce 7.0
* Enhancement - Remove some PHP 8.1 notices

= 1.6.3.1 =
* Fix - Post meta not displayed in filter by list

= 1.6.3 =
* Enhancement - Compatibility version: Wordpress 6.0 and WooCommerce 6.7
* Enhancement - POST META FILTERING ADD-ON
* Enhancement - Hierarchical view for taxonomies list
* Fix - Color/Image select with polylang
* Fix - Additional tables generation for some site
* Fix - Currency exchange compatibility
* Fix - Module for Divi theme
* Fix - Style of admin elements

= 1.6.2 =
* Enhancement - Compatibility version: WooCommerce 6.4
* Enhancement - Hierarchical view for color/image pick
* Enhancement - Compatibility for non latin slug for attributes
* Fix - Get collation from other tables

= 1.6.1.5 =
* Fix - XSS Vulnerability

= 1.6.1.4 =
* Enhancement - Compatibility version: WordPress 5.9
* Fix - Empty hook issue
* Fix - Link like WooCommerce with some optimization plugin
* Fix - Not exist attribute cause PHP errors

= 1.6.1.3 =
* Fix - Relevanssi Compatibility

= 1.6.1.2 =
* Enhancement - Compatibility version: WooCommerce 6.1
* Enhancement - Compatibility with WP Search WooCommerce
* Fix - Compatibility with Product Table

= 1.6.1.1 =
* Fix - Compatibility with Product Table plugin
* Fix - URL decoding option with Product Table plugin
* Fix - Link generation for price filters

= 1.6.1 =
* Fix - Compatibility filtering with WPML and Polylang
* Fix - Compatibility with WPML taxonomy translation
* Fix - Price filtering for variable products
* Fix - Attribute values with numeric slug

= 1.6.0.2 =
* Fix - Selected filters options do not work
* Fix - Link like WooCommerce add-on work incorrect in some cases
* Fix - Support query with product variations and other post types

= 1.6.0.1 =
* Fix - Incorrect query when used not default 'wp_' database prefix

= 1.6 =
* Enhancement - Less database queries
* Enhancement - Optimization for database queries. Speed up request to database
* Enhancement - Updated Additional tables for optimized requests and more correct filtering
* Enhancement - Possibility to filter any products request on the page with help of shortcode [brapf_next_shortcode_apply]
* Enhancement - Support for some page builders products elements with shortcode [brapf_next_shortcode_apply]
* Enhancement - Hide reset products button on page load with help of CSS code
* Enhancement - (DEV) New data for filtered page to get more control on selected elements
* Enhancement - Removed Deprecated Filters Add-on
* Fix - Multiple blocks with products in Divi Page Builder, when only single block must be filtered
* Fix - Stock status "On Backorder" work as "In stock" for filtering

= < 1.6 =
* Please check older versions of the plugin for older changelog