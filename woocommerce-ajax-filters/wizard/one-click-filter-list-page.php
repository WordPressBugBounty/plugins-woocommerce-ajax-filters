<?php
/**
 * Makes the automatic setup available from the Filters list for store owners
 * who do not use the wizard. The card is deliberately shown only on an empty
 * list, so it never competes with an existing manually managed configuration.
 */
class BeRocket_AAPF_One_Click_Filter_List_Page {
    const ACTION = 'brapf_one_click_filter_list_create';

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_notices', array($this, 'render_result_notice'));
        add_action('admin_footer-edit.php', array($this, 'render_empty_state_card'));
        add_action('admin_post_' . self::ACTION, array($this, 'handle_create'));
    }

    public function enqueue_assets() {
        if (!$this->is_filter_list_screen()) {
            return;
        }
        wp_enqueue_style(
            'brapf-one-click-welcome',
            plugins_url('one-click-welcome.css', __FILE__),
            array(),
            $this->get_asset_version('one-click-welcome.css')
        );
        wp_enqueue_script(
            'brapf-one-click-actions',
            plugins_url('one-click-actions.js', __FILE__),
            array('jquery'),
            $this->get_asset_version('one-click-actions.js'),
            true
        );
        if (!$this->can_manage_setup() || $this->has_filter_definitions()) {
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

    /** Render in the empty table row, or above the table for a repair action. */
    public function render_empty_state_card() {
        if (!$this->is_filter_list_screen()) {
            return;
        }
        if ($this->has_filter_definitions()) {
            return;
        }
        $can_manage_setup = $this->can_manage_setup();
        ?>
        <div class="brapf-one-click-filter-list-page brapf-one-click-filter-list-page--pending">
            <?php
            if (!$can_manage_setup) {
                $this->render_access_limited();
            } else {
                $context = (new BeRocket_AAPF_Wizard_Filter_Recommendations())->get_cached_context();
                if ($context === false) {
                $this->render_analysis_progress((new BeRocket_AAPF_One_Click_Analysis_Job())->get_or_start());
                } else {
                    $this->render_setup_card($context);
                }
            }
            ?>
        </div>
        <script>
        (function () {
            var card = document.querySelector('.brapf-one-click-filter-list-page--pending');
            var emptyCell = document.querySelector('.wp-list-table .no-items td');
            var table = document.querySelector('.wp-list-table');
            if (!card || !table) {
                return;
            }
            if (emptyCell) {
                // WordPress does not always set colspan on its empty-list row.
                // Without it the full setup card is constrained to the first
                // table column and its second content column appears outside it.
                var headers = table.querySelectorAll('thead tr:first-child th');
                if (headers.length) {
                    emptyCell.colSpan = 1 + headers.length;
                }
                emptyCell.textContent = '';
                emptyCell.appendChild(card);
            } else {
                table.parentNode.insertBefore(card, table);
            }
            card.classList.remove('brapf-one-click-filter-list-page--pending');
        })();
        </script>
        <?php
    }

    protected function get_asset_version($filename) {
        $path = __DIR__ . '/' . ltrim($filename, '/\\');
        return BeRocket_AJAX_filters_version . '.' . (file_exists($path) ? (string) filemtime($path) : '0');
    }

    public function handle_create() {
        check_admin_referer(self::ACTION);
        if (!$this->can_manage_setup()) {
            wp_die(esc_html__('Sorry, you are not allowed to create this setup.', 'BeRocket_AJAX_domain'), '', array('response' => 403));
        }
        if ($this->has_filter_definitions()) {
            $this->redirect_with_notice('exists');
        }
        do_action('brapf_one_click_before_create', 'filter_list', null);
        $orchestrator = BeRocket_AAPF_One_Click_Setup_Orchestrator::get_instance();
        $result = $orchestrator ? $orchestrator->handle_wizard_request() : new WP_Error('brapf_one_click_setup_unavailable');
        $this->redirect_with_notice(is_wp_error($result) ? 'failed' : 'created');
    }

    protected function render_setup_card($context) {
        $analysis = $context['analysis'];
        $catalog = isset($analysis['snapshot']['catalog']) ? $analysis['snapshot']['catalog'] : array();
        $recommendations = isset($analysis['ranking']['recommendations']) && is_array($analysis['ranking']['recommendations'])
            ? $analysis['ranking']['recommendations']
            : array();
        $products = isset($catalog['products']['count']) ? absint($catalog['products']['count']) : 0;
        $categories = isset($catalog['categories']['count']) ? absint($catalog['categories']['count']) : 0;
        $attributes = isset($catalog['attributes']) && is_array($catalog['attributes']) ? count($catalog['attributes']) : 0;
        $desktop_placement = $context['desktop_placement'];
        $mobile_placement = $context['mobile_placement'];
        $can_create_setup = !empty($desktop_placement['available']) && !empty($mobile_placement['available']);
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="brapf-one-click-welcome">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
            <?php wp_nonce_field(self::ACTION); ?>
            <div class="brapf-one-click-card">
                <span class="brapf-one-click-kicker"><i class="fa fa-magic"></i> <?php esc_html_e('Quick setup', 'BeRocket_AJAX_domain'); ?></span>
                <h1><?php esc_html_e('Set up relevant product filters in one click', 'BeRocket_AJAX_domain'); ?></h1>
                <p class="brapf-one-click-summary">
                    <?php printf(esc_html__('We analyzed your catalog: %1$s products, %2$s categories, %3$s attributes.', 'BeRocket_AJAX_domain'), number_format_i18n($products), number_format_i18n($categories), number_format_i18n($attributes)); ?>
                </p>
                <?php do_action('brapf_one_click_store_type_control', 'filter_list', $analysis); ?>
                <div class="brapf-one-click-columns">
                    <section>
                        <h2><?php esc_html_e('We will create', 'BeRocket_AJAX_domain'); ?></h2>
                        <ul class="brapf-one-click-recommendations">
                            <?php foreach ($recommendations as $recommendation) { ?>
                                <li>
                                    <i class="fa fa-check"></i><?php echo esc_html(BeRocket_AAPF_One_Click_Welcome::get_recommendation_label($recommendation)); ?>
                                    <?php do_action('brapf_one_click_recommendation_explanation', 'filter_list', $recommendation, $analysis); ?>
                                </li>
                            <?php } ?>
                            <?php do_action('brapf_one_click_recommendation_upsells', 'filter_list', $analysis); ?>
                        </ul>
                    </section>
                    <section>
                        <h2><?php esc_html_e('Placement', 'BeRocket_AJAX_domain'); ?></h2>
                        <dl class="brapf-one-click-placement">
                            <div><dt><?php esc_html_e('Desktop', 'BeRocket_AJAX_domain'); ?></dt><dd><?php echo esc_html($desktop_placement['label']); ?></dd></div>
                            <div><dt><?php esc_html_e('Mobile', 'BeRocket_AJAX_domain'); ?></dt><dd><?php echo esc_html($mobile_placement['label']); ?></dd></div>
                        </dl>
                        <?php do_action('brapf_one_click_placement_upsells', 'filter_list', $mobile_placement); ?>
                    </section>
                </div>
                <p class="brapf-one-click-note"><?php esc_html_e('All settings can be changed later.', 'BeRocket_AJAX_domain'); ?></p>
                <div class="brapf-one-click-actions">
                    <button id="brapf-one-click-filters-create" class="button button-primary button-hero" type="<?php echo $can_create_setup ? 'submit' : 'button'; ?>"<?php echo $can_create_setup ? '' : ' disabled aria-disabled="true"'; ?>><i class="fa fa-magic"></i><?php esc_html_e('Create filters in 1 click', 'BeRocket_AJAX_domain'); ?></button>
                    <a class="button button-secondary button-large" href="<?php echo esc_url(admin_url('post-new.php?post_type=br_product_filter')); ?>"><?php esc_html_e('Create a single filter', 'BeRocket_AJAX_domain'); ?></a>
                </div>
                <?php do_action('brapf_one_click_after_actions', 'filter_list', $analysis); ?>
            </div>
        </form>
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
            <?php do_action('brapf_one_click_analysis_progress', 'filter_list', $job); ?>
            <div class="brapf-one-click-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"><span style="width: <?php echo esc_attr($progress); ?>%"></span></div>
        </div>
        <?php
    }

    protected function render_access_limited() {
        ?>
        <div class="brapf-one-click-card">
            <span class="brapf-one-click-kicker"><i class="fa fa-magic"></i> <?php esc_html_e('Quick setup', 'BeRocket_AJAX_domain'); ?></span>
            <h1><?php esc_html_e('Set up relevant product filters in one click', 'BeRocket_AJAX_domain'); ?></h1>
            <p class="brapf-one-click-summary"><?php esc_html_e('One-click recommendations are available to users who can manage theme/sidebar placement.', 'BeRocket_AJAX_domain'); ?></p>
            <div class="brapf-one-click-actions">
                <button class="button button-primary button-hero" type="button" disabled aria-disabled="true"><i class="fa fa-lock"></i><?php esc_html_e('Create filters in 1 click', 'BeRocket_AJAX_domain'); ?></button>
                <a class="button button-secondary button-large" href="<?php echo esc_url(admin_url('post-new.php?post_type=br_product_filter')); ?>"><?php esc_html_e('Create a single filter', 'BeRocket_AJAX_domain'); ?></a>
            </div>
            <p class="brapf-one-click-access-notice" role="status"><?php esc_html_e('One-click setup requires permission to manage theme/sidebar placement. Ask a site administrator.', 'BeRocket_AJAX_domain'); ?></p>
        </div>
        <?php
    }

    public function render_result_notice() {
        $notice = isset($_GET['brapf_one_click_notice']) ? sanitize_key(wp_unslash($_GET['brapf_one_click_notice'])) : '';
        $messages = array(
            'created' => array('success', __('One-click filters were created.', 'BeRocket_AJAX_domain')),
            'exists' => array('info', __('Filters already exist, so no changes were made.', 'BeRocket_AJAX_domain')),
            'failed' => array('error', __('One-click setup could not be completed. Check your catalog analysis and try again.', 'BeRocket_AJAX_domain')),
        );
        if (!isset($messages[$notice])) {
            return;
        }
        printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($messages[$notice][0]), esc_html($messages[$notice][1]));
    }

    protected function has_filter_definitions() {
        $counts = wp_count_posts('br_product_filter');
        foreach (array('publish', 'draft', 'pending', 'private', 'future') as $status) {
            if (!empty($counts->$status)) {
                return true;
            }
        }
        return false;
    }

    protected function is_filter_list_screen() {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }
        $screen = get_current_screen();
        $post_status = isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : '';
        return $screen && $screen->base === 'edit' && $screen->post_type === 'br_product_filter'
            && $post_status !== 'trash';
    }

    protected function can_manage_setup() {
        return current_user_can('manage_woocommerce')
            && BeRocket_AAPF_One_Click_Capabilities::current_user_can_manage_setup('create');
    }

    protected function redirect_with_notice($notice) {
        wp_safe_redirect(add_query_arg(array('post_type' => 'br_product_filter', 'brapf_one_click_notice' => sanitize_key($notice)), admin_url('edit.php')));
        exit;
    }
}
new BeRocket_AAPF_One_Click_Filter_List_Page();
