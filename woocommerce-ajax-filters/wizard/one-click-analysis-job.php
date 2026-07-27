<?php
/** Runs the lookup-table analysis outside the wizard page request. */
class BeRocket_AAPF_One_Click_Analysis_Job {
    const OPTION_NAME = 'br_aapf_one_click_analysis_job';
    const LOCK_OPTION_NAME = 'br_aapf_one_click_analysis_lock';
    const LOCK_TTL = 120;
    const RETRY_COOLDOWN = 60;

    public function __construct() {
        add_action('wp_ajax_brapf_one_click_analysis_step', array($this, 'ajax_step'));
    }

    public function get_or_start() {
        $job = get_option(self::OPTION_NAME, array());
        if ($this->is_complete_with_fresh_cache($job)) {
            return $job;
        }
        if (is_array($job) && isset($job['status']) && $job['status'] === 'failed'
            && !empty($job['retry_after']) && absint($job['retry_after']) > time()) {
            return $job;
        }
        if (!is_array($job) || empty($job['status']) || in_array($job['status'], array('complete', 'failed'), true)) {
            $job = $this->get_queued_job();
            update_option(self::OPTION_NAME, $job, false);
        }
        return $job;
    }

    public function run_next_step() {
        $job = get_option(self::OPTION_NAME, array());
        if (!is_array($job) || empty($job['status'])) {
            return $this->get_failed_job();
        }
        if ($job['status'] === 'complete' || $job['status'] === 'failed') {
            return $job;
        }
        if ($job['status'] === 'queued') {
            $job['status'] = 'running';
            $job['progress'] = 10;
            $job['message'] = __('Reading WooCommerce lookup tables…', 'BeRocket_AJAX_domain');
            update_option(self::OPTION_NAME, $job, false);
            return $job;
        }
        if (!$this->acquire_lock()) {
            $job['message'] = __('Catalog analysis is already running…', 'BeRocket_AJAX_domain');
            return $job;
        }
        try {
            // The analyzer uses aggregate lookup/taxonomy queries and never
            // loops over product postmeta, keeping this AJAX request bounded.
            // Reuse a valid catalog snapshot when only a recommendation
            // context (for example, Store Type) changed. If no snapshot is
            // cached, get_analysis() builds it as usual.
            (new BeRocket_AAPF_Wizard_Filter_Recommendations())->get_analysis();
            $job = array('status' => 'complete', 'progress' => 100, 'message' => __('Catalog analysis is ready.', 'BeRocket_AJAX_domain'), 'error' => '');
        } catch (Exception $error) {
            $this->log_failure($error);
            $job = $this->get_failed_job();
        }
        $this->release_lock();
        update_option(self::OPTION_NAME, $job, false);
        return $job;
    }

    protected function get_queued_job() {
        return array(
            'status' => 'queued',
            'progress' => 0,
            'message' => __('Preparing catalog analysis…', 'BeRocket_AJAX_domain'),
            'error' => '',
            'retry_after' => 0,
        );
    }

    protected function get_failed_job() {
        return array(
            'status' => 'failed',
            'progress' => 100,
            'message' => __('Catalog analysis failed.', 'BeRocket_AJAX_domain'),
            'error' => '',
            'retry_after' => time() + self::RETRY_COOLDOWN,
        );
    }

    protected function is_complete_with_fresh_cache($job) {
        return is_array($job) && isset($job['status']) && $job['status'] === 'complete'
            && (new BeRocket_AAPF_Wizard_Filter_Recommendations())->get_cached_analysis() !== false;
    }

    /** add_option makes the per-site lock atomic in the WordPress options table. */
    protected function acquire_lock() {
        $lock = get_option(self::LOCK_OPTION_NAME, false);
        $started = is_array($lock) && !empty($lock['started_at']) ? strtotime($lock['started_at'] . ' UTC') : false;
        if ($started && $started > time() - self::LOCK_TTL) {
            return false;
        }
        if ($lock !== false) {
            delete_option(self::LOCK_OPTION_NAME);
        }
        return add_option(self::LOCK_OPTION_NAME, array('started_at' => current_time('mysql', true)), '', 'no');
    }

    protected function release_lock() {
        delete_option(self::LOCK_OPTION_NAME);
    }

    protected function log_failure($error) {
        if ($error instanceof Exception) {
            error_log('BeRocket one-click catalog analysis failed: ' . $error->getMessage());
        }
    }

    public function ajax_step() {
        check_ajax_referer('brapf_one_click_analysis', 'nonce');
        if (!current_user_can('manage_woocommerce')
            || !BeRocket_AAPF_One_Click_Capabilities::current_user_can_manage_setup('analysis')) {
            wp_send_json_error(array('message' => __('You cannot run this setup.', 'BeRocket_AJAX_domain')), 403);
        }
        wp_send_json_success($this->run_next_step());
    }
}
new BeRocket_AAPF_One_Click_Analysis_Job();
