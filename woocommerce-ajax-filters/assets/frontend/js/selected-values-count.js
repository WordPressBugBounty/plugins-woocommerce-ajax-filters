(function ($) {
    'use strict';
    function updateCounts() {
        $('.bapf_sfilter .bapf_selected_count').each(function () {
            var badge = $(this), filter = badge.closest('.bapf_sfilter'), values = [];
            var selector = badge.attr('data-count-kind') === 'checkbox'
                ? '.bapf_body input[type="checkbox"]:checked'
                : '.bapf_body select[multiple] option:selected';
            filter.find(selector).each(function () {
                var value = $(this).val();
                if ($(this).closest('.bapf_sfilter')[0] === filter[0] && value !== '' && value !== null && values.indexOf(String(value)) === -1) {
                    values.push(String(value));
                }
            });
            badge.prop('hidden', values.length === 0);
            badge.find('.bapf_selected_count_number').text(String(values.length));
            badge.find('.bapf_selected_count_label').text(badge.attr('data-count-label').replace('%s', String(values.length)));
        });
    }
    // Read existing control state only: no filtering, URL changes or extra requests.
    $(updateCounts);
    $(document).on('change', '.bapf_sfilter .bapf_body input[type="checkbox"], .bapf_sfilter .bapf_body select[multiple]', updateCounts);
    $(document).on('braapf_unselect braapf_unselect_all', '.bapf_sfilter', function () { setTimeout(updateCounts, 0); });
    if (typeof berocket_add_filter === 'function') {
        berocket_add_filter('braapf_init', updateCounts, 1800);
        berocket_add_filter('ajax_load_from_url_done_after', function (data) { updateCounts(); return data; }, 2000);
    }
})(jQuery);
