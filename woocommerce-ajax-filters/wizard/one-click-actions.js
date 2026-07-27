(function ($) {
    function setLoading($button, loading) {
        if (!$button || !$button.length) {
            return;
        }
        $button.toggleClass('brapf-one-click-is-loading', !!loading)
            .attr('aria-busy', loading ? 'true' : 'false')
            .prop('disabled', !!loading);
        if (loading && !$button.find('.brapf-one-click-button-spinner').length) {
            $button.prepend('<span class="brapf-one-click-button-spinner" aria-hidden="true"></span>');
        }
        if (!loading) {
            $button.find('.brapf-one-click-button-spinner').remove();
        }
    }

    // Reusable for future AJAX actions. Their success and failure callbacks
    // must call this helper with false.
    window.brapfOneClickSetButtonLoading = function (button, loading) {
        setLoading($(button), loading);
    };

    $(document).on('submit', 'form.brapf-one-click-welcome', function () {
        var $form = $(this);
        if ($form.data('brapf-one-click-submitting')) {
            return false;
        }
        var $button = $(document.activeElement).closest('button[type="submit"], input[type="submit"]');
        if (!$button.length || !$button.closest($form).length) {
            $button = $form.find('#brapf-one-click-filters-create:enabled, button[type="submit"]:enabled, input[type="submit"]:enabled').first();
        }
        if (!$button.length) {
            return;
        }
        $form.data('brapf-one-click-submitting', true);
        setLoading($button, true);
    });
})(jQuery);
