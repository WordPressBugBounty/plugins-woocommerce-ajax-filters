/* global woodmartThemeModule, the_ajax_script, berocket_add_filter */
(function($) {
    'use strict';

    var generation = 0,
        filterContext = null;

    $(document).on('berocket_ajax_filtering_start.bapfWoodmart', function() {
        generation++;
    });
    $(document).on('pjax:success.bapfWoodmart', function() {
        generation++;
        filterContext = null;
    });

    // Use the actual filtering URL even when browser URL updates are disabled.
    berocket_add_filter('ajax_load_from_url_url', function(url, sendData, callbacks, type) {
        if (type === 'default') {
            filterContext = context(url);
        }
        return url;
    });

    // Pagination may change the page number, but must retain the archive and filters.
    function context(url) {
        var parsed = new URL(url, window.location.href),
            paginationBase = typeof the_ajax_script === 'object' && the_ajax_script.pagination_base || 'page',
            escapedBase = paginationBase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
            path = parsed.pathname.replace(new RegExp('/' + escapedBase + '/[0-9]+/?$'), '/'),
            query = [];

        parsed.searchParams.forEach(function(value, key) {
            if (!/^(woo_ajax|loop|atts|action|paged|product-page|product_page|_)($|\[)/.test(key)) {
                query.push(JSON.stringify([key, value]));
            }
        });
        query.sort();
        return parsed.origin + path.replace(/\/$/, '') + '?' + query.join('&');
    }

    $.ajaxPrefilter(function(options, originalOptions) {
        var data = originalOptions.data;

        // Woodmart's main product loop uses GET + JSON + { loop, woo_ajax, atts }.
        // Do not intercept shortcode grids, blog pagination, or BeRocket requests.
        if (!data || typeof data !== 'object' || String(data.woo_ajax) !== '1' ||
            !Object.prototype.hasOwnProperty.call(data, 'loop') ||
            String(options.type).toUpperCase() !== 'GET' || options.dataType !== 'json' ||
            data.action && data.action !== 'woodmart_get_products_main_loop') {
            return;
        }

        var requestGeneration = generation,
            requestContext;
        try {
            requestContext = context(options.url);
        } catch (error) {
            return;
        }

        function isCurrent() {
            return requestGeneration === generation && requestContext === (filterContext || context(window.location.href));
        }

        var success = options.success;
        options.success = function() {
            if (!isCurrent()) {
                return;
            }

            // Woodmart can defer append until its stylesheet loader calls back.
            // Carry the same guard across that asynchronous boundary as well.
            var module = typeof woodmartThemeModule === 'object' && woodmartThemeModule,
                loadStyles = module && module.removeDuplicatedStylesFromHTML;
            if (typeof loadStyles === 'function') {
                module.removeDuplicatedStylesFromHTML = function(html, callback, holder) {
                    return loadStyles.call(this, html, function() {
                        if (isCurrent()) {
                            return callback.apply(this, arguments);
                        }
                    }, holder);
                };
            }

            try {
                var args = arguments,
                    callbackThis = this;
                $.each([].concat(success || []), function(index, callback) {
                    if (typeof callback === 'function' && isCurrent()) {
                        callback.apply(callbackThis, args);
                    }
                });
            } finally {
                if (typeof loadStyles === 'function') {
                    module.removeDuplicatedStylesFromHTML = loadStyles;
                }
            }
        };
        // Leave error/complete intact so Woodmart always releases its loading state.
    });
})(jQuery);
