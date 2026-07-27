<?php
/** First, low-friction screen for the intelligent one-click setup. */
class BeRocket_AAPF_One_Click_Welcome {
    const STEP = 'wizard_one_click_welcome';

    public function __construct() {
        // The legacy wizard replaces the steps at priority 10, so this must run after it.
        add_filter('berocket_wizard_steps_br-aapf-setup', array($this, 'add_welcome_step'), 20);
        add_action('before_wizard_run_br-aapf-setup', array($this, 'enqueue_assets'), 20);
    }

    public function add_welcome_step($steps) {
        $welcome_step = array(
            self::STEP => array(
                'name' => __('Quick setup', 'BeRocket_AJAX_domain'),
                'view' => array($this, 'render'),
                'handler' => array($this, 'request_one_click_creation'),
                'fa_icon' => 'fa-magic',
                'hide_skip_link' => true,
            ),
        );
        return array_merge($welcome_step, $steps);
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'brapf-one-click-welcome',
            plugins_url('one-click-welcome.css', __FILE__),
            array('wizard-setup'),
            $this->get_asset_version('one-click-welcome.css')
        );
        wp_enqueue_script(
            'brapf-one-click-actions',
            plugins_url('one-click-actions.js', __FILE__),
            array('jquery'),
            $this->get_asset_version('one-click-actions.js'),
            true
        );
        if (!$this->can_manage_setup()) {
            return;
        }
        wp_enqueue_script(
            'brapf-one-click-progress',
            plugins_url('one-click-progress.js', __FILE__),
            array('jquery'),
            BeRocket_AJAX_filters_version,
            true
        );
        wp_localize_script('brapf-one-click-progress', 'brapfOneClickProgress', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('brapf_one_click_analysis'),
            'failed' => __('Catalog analysis could not be completed. Wait a minute, then reload this page to try again.', 'BeRocket_AJAX_domain'),
        ));
    }

    protected function get_asset_version($filename) {
        $path = __DIR__ . '/' . ltrim($filename, '/\\');
        return BeRocket_AJAX_filters_version . '.' . (file_exists($path) ? (string) filemtime($path) : '0');
    }

    public function render($wizard) {
        $can_manage_setup = $this->can_manage_setup();
        $setup_state = BeRocket_AAPF_One_Click_Setup::get_state();
        if (!empty($setup_state['generated_by_one_click'])
            && $setup_state['status'] === BeRocket_AAPF_One_Click_Setup::STATUS_ACTIVE
            && $this->has_active_filter_definitions($setup_state)) {
            $this->render_success($wizard, $setup_state, $can_manage_setup);
            return;
        }
        $context = (new BeRocket_AAPF_Wizard_Filter_Recommendations())->get_cached_context();
        if ($context === false) {
            if (!$can_manage_setup) {
                $this->render_access_limited($wizard);
                return;
            }
            $this->render_analysis_progress((new BeRocket_AAPF_One_Click_Analysis_Job())->get_or_start());
            return;
        }
        $analysis = $context['analysis'];
        $catalog = isset($analysis['snapshot']['catalog']) ? $analysis['snapshot']['catalog'] : array();
        $recommendations = $context['recommendations'];
        $products = isset($catalog['products']['count']) ? absint($catalog['products']['count']) : 0;
        $categories = isset($catalog['categories']['count']) ? absint($catalog['categories']['count']) : 0;
        $attributes = isset($catalog['attributes']) && is_array($catalog['attributes']) ? count($catalog['attributes']) : 0;
        $desktop_placement = $context['desktop_placement'];
        $mobile_placement = $context['mobile_placement'];
        $can_create_setup = $can_manage_setup && !empty($desktop_placement['available']) && !empty($mobile_placement['available']);
        ?>
        <form method="post" class="brapf-one-click-welcome">
            <div class="brapf-one-click-card">
                <span class="brapf-one-click-kicker"><i class="fa fa-magic"></i> <?php esc_html_e('Quick setup', 'BeRocket_AJAX_domain'); ?></span>
                <h1><?php esc_html_e('Set up relevant product filters in one click', 'BeRocket_AJAX_domain'); ?></h1>
                <?php if (!empty($analysis['snapshot']['status']) && $analysis['snapshot']['status'] === 'ready') { ?>
                    <p class="brapf-one-click-summary">
                        <?php
                        printf(
                            esc_html__('We analyzed your catalog: %1$s products, %2$s categories, %3$s attributes.', 'BeRocket_AJAX_domain'),
                            number_format_i18n($products),
                            number_format_i18n($categories),
                            number_format_i18n($attributes)
                        );
                        ?>
                    </p>
                    <?php do_action('brapf_one_click_store_type_control', 'wizard', $analysis); ?>
                    <div class="brapf-one-click-columns">
                        <section>
                            <h2><?php esc_html_e('We will create', 'BeRocket_AJAX_domain'); ?></h2>
                            <ul class="brapf-one-click-recommendations">
                                <?php foreach ($recommendations as $recommendation) { ?>
                                    <li>
                                        <i class="fa fa-check"></i><?php echo esc_html($this->get_recommendation_label($recommendation)); ?>
                                        <?php do_action('brapf_one_click_recommendation_explanation', 'wizard', $recommendation, $analysis); ?>
                                    </li>
                                <?php } ?>
                                <?php do_action('brapf_one_click_recommendation_upsells', 'wizard', $analysis); ?>
                            </ul>
                        </section>
                        <section>
                            <h2><?php esc_html_e('Placement', 'BeRocket_AJAX_domain'); ?></h2>
                            <dl class="brapf-one-click-placement">
                                <div>
                                    <dt><?php esc_html_e('Desktop', 'BeRocket_AJAX_domain'); ?></dt>
                                    <dd>
                                        <?php echo esc_html($desktop_placement['label']); ?>
                                        <?php if (empty($desktop_placement['available']) && !empty($desktop_placement['required_feature'])) { ?>
                                            <?php $feature_state = BeRocket_AAPF_One_Click_Capabilities::get_feature_ui_state($desktop_placement['required_feature']); ?>
                                            <small><?php echo esc_html($feature_state['message']); ?></small>
                                        <?php } ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Mobile', 'BeRocket_AJAX_domain'); ?></dt>
                                    <dd>
                                        <?php echo esc_html($mobile_placement['label']); ?>
                                        <?php if (empty($mobile_placement['available']) && !empty($mobile_placement['required_feature'])) { ?>
                                            <?php $feature_state = BeRocket_AAPF_One_Click_Capabilities::get_feature_ui_state($mobile_placement['required_feature']); ?>
                                            <small><?php echo esc_html($feature_state['message']); ?></small>
                                        <?php } ?>
                                    </dd>
                                </div>
                            </dl>
                            <?php do_action('brapf_one_click_placement_upsells', 'wizard', $mobile_placement); ?>
                        </section>
                    </div>
                    <p class="brapf-one-click-note"><?php esc_html_e('All settings can be changed later.', 'BeRocket_AJAX_domain'); ?></p>
                    <div class="brapf-one-click-actions">
                        <?php if ($can_create_setup) { ?>
                            <button id="brapf-one-click-filters-create" class="button button-primary button-hero" type="submit" name="save_step" value="1">
                                <i class="fa fa-magic"></i><?php
                                esc_html_e('Create filters in 1 click', 'BeRocket_AJAX_domain');
                                ?>
                            </button>
                        <?php } else { ?>
                            <button id="brapf-one-click-filters-create" class="button button-primary button-hero" type="button" disabled aria-disabled="true">
                                <i class="fa fa-lock"></i><?php esc_html_e('Create filters in 1 click', 'BeRocket_AJAX_domain'); ?>
                            </button>
                        <?php } ?>
                        <a class="button button-secondary button-large" href="<?php echo esc_url($this->get_advanced_setup_url()); ?>">
                            <?php esc_html_e('Advanced setup', 'BeRocket_AJAX_domain'); ?>
                        </a>
                    </div>
                    <?php do_action('brapf_one_click_after_actions', 'wizard', $analysis); ?>
                    <?php if (!$can_manage_setup) { ?>
                        <p class="brapf-one-click-access-notice" role="status"><?php esc_html_e('One-click setup requires permission to manage theme/sidebar placement. Ask a site administrator.', 'BeRocket_AJAX_domain'); ?></p>
                    <?php } ?>
                <?php } else { ?>
                    <p class="brapf-one-click-summary"><?php esc_html_e('We could not analyze the catalog automatically. Use Advanced setup to configure your filters.', 'BeRocket_AJAX_domain'); ?></p>
                    <div class="brapf-one-click-actions">
                        <a class="button button-primary button-hero" href="<?php echo esc_url($this->get_advanced_setup_url()); ?>">
                            <?php esc_html_e('Open advanced setup', 'BeRocket_AJAX_domain'); ?>
                        </a>
                    </div>
                <?php } ?>
            </div>
            <?php wp_nonce_field($wizard->page_id); ?>
        </form>
        <?php
    }

    /**
     * A persisted setup state can outlive its generated posts when filters are
     * removed in wp-admin. Do not present a completed setup in that case.
     */
    protected function has_active_filter_definitions($state) {
        $filter_ids = isset($state['filters']['ids']) && is_array($state['filters']['ids'])
            ? array_values(array_unique(array_filter(array_map('absint', $state['filters']['ids']))))
            : array();
        if (empty($filter_ids)) {
            return false;
        }

        foreach ($filter_ids as $filter_id) {
            if (get_post_type($filter_id) !== 'br_product_filter'
                || get_post_status($filter_id) !== 'publish'
                || !BeRocket_AAPF_One_Click_Setup::is_setup_post($filter_id, $state['setup_id'])) {
                return false;
            }
        }
        return true;
    }

    protected function render_success($wizard, $state, $can_manage_setup) {
        $health_passed = isset($state['health']['status']) && $state['health']['status'] === 'passed';
        ?>
        <div class="brapf-one-click-welcome">
            <div class="brapf-one-click-card brapf-one-click-success">
                <span class="brapf-one-click-kicker"><i class="fa fa-check-circle"></i> <?php esc_html_e('Setup complete', 'BeRocket_AJAX_domain'); ?></span>
                <h1><?php esc_html_e('Your filters are ready', 'BeRocket_AJAX_domain'); ?></h1>
                <p class="brapf-one-click-summary">
                    <?php echo $health_passed
                        ? esc_html__('Your filter groups and filters are ready to use. View them in your shop or adjust their settings anytime.', 'BeRocket_AJAX_domain')
                        : esc_html__('Setup was created, but its health check needs attention.', 'BeRocket_AJAX_domain'); ?>
                </p>
                <div class="brapf-one-click-actions">
                    <a class="button button-primary button-hero" href="<?php echo esc_url($this->get_shop_url()); ?>" target="_blank">
                        <?php esc_html_e('View filters in shop', 'BeRocket_AJAX_domain'); ?>
                    </a>
                    <a class="button button-secondary button-large" href="<?php echo esc_url(admin_url('edit.php?post_type=br_product_filter')); ?>">
                        <?php esc_html_e('Edit filters', 'BeRocket_AJAX_domain'); ?>
                    </a>
                </div>
                <?php if (!$can_manage_setup) { ?>
                    <p class="brapf-one-click-access-notice" role="status"><?php esc_html_e('One-click setup requires permission to manage theme/sidebar placement. Ask a site administrator.', 'BeRocket_AJAX_domain'); ?></p>
                <?php } ?>
            </div>
        </div>
        <?php
    }

    protected function render_analysis_progress($job) {
        $progress = isset($job['progress']) ? absint($job['progress']) : 0;
        $message = isset($job['message']) ? $job['message'] : __('Preparing catalog analysis…', 'BeRocket_AJAX_domain');
        ?>
        <div class="brapf-one-click-card brapf-one-click-progress">
            <span class="brapf-one-click-kicker"><i class="fa fa-spinner fa-pulse"></i> <?php esc_html_e('Analyzing catalog', 'BeRocket_AJAX_domain'); ?></span>
            <h1><?php esc_html_e('Preparing your recommended filters', 'BeRocket_AJAX_domain'); ?></h1>
            <p class="brapf-one-click-progress-message"><?php echo esc_html($message); ?></p>
            <?php do_action('brapf_one_click_analysis_progress', 'wizard', $job); ?>
            <div class="brapf-one-click-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"><span style="width: <?php echo esc_attr($progress); ?>%"></span></div>
        </div>
        <?php
    }

    protected function render_access_limited($wizard) {
        ?>
        <form method="post" class="brapf-one-click-welcome">
            <div class="brapf-one-click-card">
                <span class="brapf-one-click-kicker"><i class="fa fa-magic"></i> <?php esc_html_e('Quick setup', 'BeRocket_AJAX_domain'); ?></span>
                <h1><?php esc_html_e('Set up relevant product filters in one click', 'BeRocket_AJAX_domain'); ?></h1>
                <p class="brapf-one-click-summary"><?php esc_html_e('One-click recommendations are available to users who can manage theme/sidebar placement.', 'BeRocket_AJAX_domain'); ?></p>
                <div class="brapf-one-click-actions">
                    <button id="brapf-one-click-filters-create" class="button button-primary button-hero" type="button" disabled aria-disabled="true">
                        <i class="fa fa-lock"></i><?php esc_html_e('Create filters in 1 click', 'BeRocket_AJAX_domain'); ?>
                    </button>
                    <a class="button button-secondary button-large" href="<?php echo esc_url($this->get_advanced_setup_url()); ?>">
                        <?php esc_html_e('Advanced setup', 'BeRocket_AJAX_domain'); ?>
                    </a>
                </div>
                <p class="brapf-one-click-access-notice" role="status"><?php esc_html_e('One-click setup requires permission to manage theme/sidebar placement. Ask a site administrator.', 'BeRocket_AJAX_domain'); ?></p>
            </div>
            <?php wp_nonce_field($wizard->page_id); ?>
        </form>
        <?php
    }

    /** The creation orchestrator from task 11 will attach to this action. */
    public function request_one_click_creation($wizard) {
        if (!current_user_can('manage_woocommerce')
            || !BeRocket_AAPF_One_Click_Capabilities::current_user_can_manage_setup('wizard')) {
            wp_die(esc_html__('Sorry, you are not allowed to create this setup.'), '', array('response' => 403));
        }
        if (!check_admin_referer($wizard->page_id)) {
            return;
        }
        do_action('brapf_one_click_before_create', 'wizard', $wizard);
        do_action('brapf_one_click_setup_create_requested', $wizard);
    }

    protected function get_advanced_setup_url() {
        return add_query_arg(array(
            'page' => 'br-aapf-setup',
            'step' => 'wizard_selectors',
        ), admin_url('admin.php'));
    }

    protected function get_shop_url() {
        if (function_exists('wc_get_page_permalink')) {
            return wc_get_page_permalink('shop');
        }
        return get_permalink(function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0);
    }

    public static function get_recommendation_label($recommendation) {
        $labels = array(
            'price' => __('Price', 'BeRocket_AJAX_domain'),
            'categories' => __('Categories', 'BeRocket_AJAX_domain'),
            'availability' => __('Availability', 'BeRocket_AJAX_domain'),
            'on_sale' => __('On sale', 'BeRocket_AJAX_domain'),
            'rating' => __('Rating', 'BeRocket_AJAX_domain'),
            'tags' => __('Tags', 'BeRocket_AJAX_domain'),
        );
        $key = isset($recommendation['key']) ? $recommendation['key'] : '';
        if (strpos($key, 'brand:') === 0) {
            return __('Brands', 'BeRocket_AJAX_domain');
        }
        if (strpos($key, 'attribute:') === 0 && !empty($recommendation['details']['attribute_metrics']['label'])) {
            return $recommendation['details']['attribute_metrics']['label'];
        }
        return isset($labels[$key]) ? $labels[$key] : __('Product filter', 'BeRocket_AJAX_domain');
    }

    protected function can_manage_setup() {
        return current_user_can('manage_woocommerce')
            && BeRocket_AAPF_One_Click_Capabilities::current_user_can_manage_setup('wizard');
    }
}
new BeRocket_AAPF_One_Click_Welcome();
