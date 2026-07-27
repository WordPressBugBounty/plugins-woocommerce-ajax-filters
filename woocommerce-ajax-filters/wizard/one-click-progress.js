(function ($) {
    function runProgress($panel) {
        $panel.attr('aria-busy', 'true');
        var request = function () {
            $.post(window.brapfOneClickProgress.ajaxUrl, {
                action: 'brapf_one_click_analysis_step',
                nonce: window.brapfOneClickProgress.nonce
            }).done(function (response) {
                if (!response || !response.success) {
                    $panel.find('.brapf-one-click-progress-message').text(window.brapfOneClickProgress.failed);
                    $panel.attr('aria-busy', 'false');
                    return;
                }
                var job = response.data;
                $panel.find('.brapf-one-click-progress-bar span').css('width', job.progress + '%');
                $panel.find('.brapf-one-click-progress-message').text(job.message);
                if (job.status === 'complete') {
                    window.location.reload();
                } else if (job.status !== 'failed') {
                    window.setTimeout(request, 250);
                } else {
                    $panel.attr('aria-busy', 'false');
                }
            }).fail(function () {
                $panel.find('.brapf-one-click-progress-message').text(window.brapfOneClickProgress.failed);
                $panel.attr('aria-busy', 'false');
            });
        };
        request();
    }
    $(function () {
        $('.brapf-one-click-progress').each(function () { runProgress($(this)); });
    });
})(jQuery);
