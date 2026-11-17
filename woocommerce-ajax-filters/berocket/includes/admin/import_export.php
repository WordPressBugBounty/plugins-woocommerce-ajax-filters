<?php
require_once(__DIR__ . '/import_export_conditions.php');
class BeRocket_import_export {
    const PLUGIN_URL_TOKEN = '__BRFR_PLUGIN_URL__';
    const MAP_ENTRIES_KEY = '__brfr_map_entries';
    const POST_CONTENT_KEY = 'brfr_import_post_content';
    const POST_PARENT_KEY = 'brfr_import_post_parent';
    const POST_ORDER_KEY = 'brfr_import_post_order';
    protected static $import_warnings = array();
    protected static $import_post_id_map = array();
    public function __construct() {
        add_action('BeRocket_framework_updater_account_form_after', array($this, 'account_form'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_brfr_get_export_settings', array($this, 'get_export') );
        add_action('wp_ajax_brfr_set_import_settings', array($this, 'set_import') );
        add_action('wp_ajax_brfr_confirm_import_settings', array($this, 'confirm_import') );
        add_action('wp_ajax_brfr_get_import_backups', array($this, 'get_backups') );
        add_action('wp_ajax_brfr_restore_import_backups', array($this, 'restore_backups') );
    }
    public static function admin_enqueue_scripts() {
        $script_handle = 'berocket_import_export_admin';
        $style_handle = 'berocket_import_export_admin';
        wp_register_style(
            $style_handle,
            plugins_url('../../assets/css/import-export.css', __FILE__),
            array(),
            false
        );
        wp_register_script(
            $script_handle,
            plugins_url('../../assets/js/import-export.js', __FILE__),
            array('jquery'),
            false,
            true
        );
        wp_localize_script($script_handle, 'berocket_import_export', array(
            'nonce' => wp_create_nonce('brfr_import_export'),
            'text' => array(
                'incorrect_data'        => __('Incorrect data', 'BeRocket_domain'),
                'import_failed'         => __('Import failed', 'BeRocket_domain'),
                'link'                  => __('Link', 'BeRocket_domain'),
                'leave_as_is'           => __('Leave as is', 'BeRocket_domain'),
                'replace_site'          => __('Use same path on this site', 'BeRocket_domain'),
                'custom'                => __('Custom', 'BeRocket_domain'),
                'select_image'          => __('Select image', 'BeRocket_domain'),
                'use_this_image'        => __('Use this image', 'BeRocket_domain'),
                'enter_url'             => __('Enter URL', 'BeRocket_domain'),
                'review_links_notice'   => __('Choose how to handle found links before the final import.', 'BeRocket_domain'),
                'imported_custom_posts' => __('Imported custom posts', 'BeRocket_domain'),
                'delete_existing_posts' => __('Delete existing custom posts of this type before import', 'BeRocket_domain'),
                'name'                  => __('Name', 'BeRocket_domain'),
                'update'                => __('Update', 'BeRocket_domain'),
                'create_new'            => __('Create New', 'BeRocket_domain'),
            ),
        ));
        wp_enqueue_style($style_handle);
        wp_enqueue_script($script_handle);
    }
    public static function get_export() {
        $nonce = $_GET['nonce'];
        $result = wp_verify_nonce( $nonce, 'brfr_import_export' );
        if( ! $result || ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        $plugin_slug = sanitize_text_field($_GET['plugin']);
        echo json_encode(self::get_export_data($plugin_slug));
        wp_die();
    }
    public static function get_export_data($plugin_slug, $plugin_instance = false) {
        if( $plugin_instance === false ) {
            $plugin_instance = apply_filters('brfr_plugin_get_instance_' . $plugin_slug, FALSE);
        }
        $export = array();
        if( $plugin_instance !== FALSE && $plugin_instance->import_export !== FALSE ) {
            $export = self::export_generate($plugin_slug);
            if( $plugin_instance->import_export_posts !== FALSE ) {
                $export_posts = array();
                foreach($plugin_instance->import_export_posts as $post_name) {
                    $export_post = self::export_generate_post($post_name);
                    if( is_array($export_post) && count($export_post) > 0 ) {
                        $export_posts[$post_name] = $export_post;
                    }
                }
                if( count($export_posts) > 0 ) {
                    $export['import_custom_posts'] = $export_posts;
                }
            }
            $export = self::replace_plugin_url_with_token_in_settings($export, self::get_plugin_url($plugin_instance));
            $export['brfr_service'] = self::get_export_service_data($plugin_instance);
            $export['brfr_plugin_slug'] = $plugin_slug;
            $export['brfr_plugin_version'] = (string) $plugin_instance->info['version'];
        }
        return $export;
    }
    public static function get_import_version_error($data, $plugin_instance) {
        if( ! is_array($data) || ! array_key_exists('brfr_plugin_version', $data) ) {
            return ''; // Exports made before version metadata was added remain compatible.
        }
        $required_version = $data['brfr_plugin_version'];
        if( ! is_scalar($required_version) || is_bool($required_version) || trim((string) $required_version) === '' ) {
            return __('Invalid plugin version in imported settings.', 'BeRocket_domain');
        }
        $required_version = trim((string) $required_version);
        $installed_version = ! empty($plugin_instance->info['version']) ? (string) $plugin_instance->info['version'] : '';
        if( $installed_version === '' ) {
            return __('Could not determine the installed plugin version.', 'BeRocket_domain');
        }
        if( version_compare($required_version, $installed_version, '>') ) {
            return sprintf(
                __('Update the plugin to version %s or newer to import these settings.', 'BeRocket_domain'),
                $required_version
            );
        }
        return '';
    }
    public static function set_import() {
        $nonce = $_POST['nonce'];
        $result = wp_verify_nonce( $nonce, 'brfr_import_export' );
        if( ! $result || ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        $plugin_slug = sanitize_text_field($_POST['plugin']);
        $data = stripslashes($_POST['data']);
        if( empty($plugin_slug) || empty($data) ) {
            _e('Empty data', 'BeRocket_domain');
            wp_die();
        }
        $data = json_decode($data, true);
        if( empty($data) ) {
            _e('Incorrect data', 'BeRocket_domain');
            wp_die();
        }
        $plugin_instance = apply_filters('brfr_plugin_get_instance_' . $plugin_slug, FALSE);
        if( $plugin_instance === FALSE || $plugin_instance->import_export === FALSE ) {
            _e('Import for this plugin not allowed', 'BeRocket_domain');
            wp_die();
        }
        if( is_array($data) && array_key_exists('brfr_plugin_slug', $data) && $data['brfr_plugin_slug'] !== $plugin_slug ) {
            wp_send_json_error(array(
                'message' => __('These settings were exported for a different plugin. Please select the correct plugin.', 'BeRocket_domain'),
            ));
        }
        $version_error = self::get_import_version_error($data, $plugin_instance);
        if( $version_error !== '' ) {
            wp_send_json_error(array('message' => $version_error));
        }
        $import_version = $data['brfr_plugin_version'] ?? null;
        unset($data['brfr_plugin_slug'], $data['brfr_plugin_version']);
        $service_data = self::get_import_service_data($data);
        $service_data['current_plugin_url'] = self::get_plugin_url($plugin_instance);
        $data = self::replace_plugin_token_with_url_in_settings($data, $service_data['current_plugin_url']);
        $custom_posts_data = self::get_import_custom_posts_data($data);
        $links = self::collect_import_links($data, $custom_posts_data, $service_data);
        $import_token = self::store_import_package(array(
            'plugin_slug'        => $plugin_slug,
            'plugin_version'     => $import_version,
            'data'               => $data,
            'custom_posts_data'  => $custom_posts_data,
            'service_data'       => $service_data,
        ));
        $custom_post_groups = self::prepare_custom_post_groups_for_review($custom_posts_data);
        wp_send_json_success(array(
            'token' => $import_token,
            'links' => $links,
            'custom_post_types' => array_keys($custom_posts_data),
            'has_custom_posts' => ! empty($custom_post_groups),
            'custom_posts' => self::prepare_custom_posts_for_review($custom_posts_data),
            'custom_post_groups' => $custom_post_groups,
        ));
    }
    public static function confirm_import() {
        $nonce = $_POST['nonce'];
        $result = wp_verify_nonce( $nonce, 'brfr_import_export' );
        if( ! $result || ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        $plugin_slug = sanitize_text_field($_POST['plugin']);
        $import_token = sanitize_text_field($_POST['import_token']);
        $package = self::get_import_package($import_token);
        if( empty($plugin_slug) || empty($package) || empty($package['plugin_slug']) || $package['plugin_slug'] !== $plugin_slug ) {
            wp_send_json_error(array(
                'message' => __('Import data expired. Please try again.', 'BeRocket_domain'),
            ));
        }
        $link_actions = array();
        if( ! empty($_POST['link_actions']) ) {
            $link_actions = json_decode(stripslashes($_POST['link_actions']), true);
            if( ! is_array($link_actions) ) {
                $link_actions = array();
            }
        }
        $import_options = array(
            'delete_existing_custom_posts' => self::sanitize_custom_post_type_flags(
                ! empty($_POST['delete_existing_custom_posts']) ? json_decode(stripslashes($_POST['delete_existing_custom_posts']), true) : array()
            ),
            'custom_post_slug_strategies' => self::sanitize_custom_post_slug_strategies(
                ! empty($_POST['custom_post_slug_strategies']) ? json_decode(stripslashes($_POST['custom_post_slug_strategies']), true) : array()
            ),
        );
        $result = self::process_import_package($plugin_slug, $package, $link_actions, $import_options);
        delete_transient(self::get_import_transient_name($import_token));
        if( empty($result['success']) ) {
            wp_send_json_error(array(
                'message' => $result['message'],
                'warnings' => ( ! empty($result['warnings']) ? $result['warnings'] : array() ),
            ));
        }
        wp_send_json_success(array(
            'message' => $result['message'],
            'warnings' => ( ! empty($result['warnings']) ? $result['warnings'] : array() ),
        ));
        wp_die();
    }
    public static function get_backups() {
        $nonce = $_GET['nonce'];
        $result = wp_verify_nonce( $nonce, 'brfr_import_export' );
        if( ! $result || ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        $plugin_slug = sanitize_text_field($_GET['plugin']);
        $exist_ids = array();
        foreach(array('1', '2', '3') as $save_id) {
            $transient_option = get_transient('brfr_bckp_' . $plugin_slug . '_' . $save_id);
            if( $transient_option != false && is_array($transient_option) && ! empty($transient_option['import_export_date']) ) {
                $exist_ids[] = array(
                    'id' => $save_id,
                    'time' => $transient_option['import_export_date']
                );
            }
        }
        if( count($exist_ids) > 0 ) {
            usort($exist_ids, function($a, $b) { return $b['time'] <=> $a['time']; });
            echo '<select name="backup">';
            echo '<option value="0">' . __('-= Select backup to restore =-', 'BeRocket_domain') . '</option>';
            foreach($exist_ids as $exist_id) {
                echo '<option value="'.$exist_id['id'].'">' . date('Y-m-d H:i:s', $exist_id['time']) . '</option>';
            }
            echo '</select>';
        }
        wp_die();
    }
    public static function restore_backups() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        $result = wp_verify_nonce( $nonce, 'brfr_import_export' );
        if( ! $result || ! current_user_can( 'manage_options' ) ) {
            wp_die( -1, 403 );
        }
        $plugin_slug = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
        $backup_id = isset( $_POST['backup'] ) ? intval( $_POST['backup'] ) : 0;
        if( ! in_array($backup_id, array(1, 2, 3), true) || empty($plugin_slug) ) {
            echo 'Incorrect data';
            wp_die();
        }
        $backup = get_transient('brfr_bckp_' . $plugin_slug . '_' . $backup_id);
        $plugin_instance = apply_filters('brfr_plugin_get_instance_' . $plugin_slug, FALSE);
        if( $plugin_instance === FALSE || $plugin_instance->import_export === FALSE || ! is_array($backup) || empty($backup['import_export_date']) ) {
            echo 'Backup cannot be used for plugin: ' . $plugin_slug . ' ID: ' . $backup_id;
            wp_die();
        }
        if( isset($backup['export_data']) ) {
            $data = json_decode($backup['export_data'], true);
            if( ! is_array($data) || empty($data['brfr_plugin_slug']) || $data['brfr_plugin_slug'] !== $plugin_slug ) {
                echo 'Invalid backup data';
                wp_die();
            }
            $version_error = self::get_import_version_error($data, $plugin_instance);
            if( $version_error !== '' ) {
                echo esc_html($version_error);
                wp_die();
            }
            unset($data['brfr_plugin_slug'], $data['brfr_plugin_version']);
            $service_data = self::get_import_service_data($data);
            $service_data['current_plugin_url'] = self::get_plugin_url($plugin_instance);
            $data = self::replace_plugin_token_with_url_in_settings($data, $service_data['current_plugin_url']);
            $custom_posts_data = self::get_import_custom_posts_data($data);
            $result = self::process_import_package($plugin_slug, array(
                'data' => $data,
                'custom_posts_data' => $custom_posts_data,
                'service_data' => $service_data,
            ), array(), array('restore_backup' => true));
            echo empty($result['success']) ? $result['message'] : 'OK';
        } else {
            // Backups created before the export format change contain only plugin options.
            unset($backup['import_export_date']);
            $import = $plugin_instance->save_settings_callback( $backup );
            update_option($plugin_instance->values['settings_name'], $import);
            echo 'OK';
        }
        wp_die();
    }
    public static function account_form($plugin_info) {
        $nonce = wp_create_nonce('brfr_import_export');
        do_action('berocket_enqueue_media');
        ?><div><span class="brfr_import_export_open button"><?php _e('Import/Export', 'BeRocket_domain') ?></span></div>
    <div class="brfr_import_export_block" style="display: none;">
        <form class="brfr_import_export_form">
            <h3><?php _e('Import/Export', 'BeRocket_domain') ?></h3>
            <div class="brfr_import_result notice is-dismissible" role="status" style="display:none;"><p></p><button type="button" class="notice-dismiss brfr_import_result_dismiss"><span class="screen-reader-text"><?php _e('Dismiss notice', 'BeRocket_domain') ?></span></button></div>
            <input name="action" type="hidden" value="brfr_set_import_settings">
            <input name="nonce" type="hidden" value="<?php echo $nonce; ?>">
            <select class="brfr_import_export_form_plugin" name="plugin">
                <?php
                if( is_array($plugin_info) ) {
                    foreach($plugin_info as $plugin_info_single) {
                        $plugin_instance = apply_filters('brfr_plugin_get_instance_' . $plugin_info_single['plugin_name'], FALSE);
                        if( $plugin_instance !== FALSE && $plugin_instance->import_export !== FALSE ) {
                            echo "<option value='{$plugin_info_single['plugin_name']}'>{$plugin_info_single['norm_name']}</option>";
                        }
                    }
                }
                ?>
            </select>
            <div class="brfr_export_all">
                <h3><?php _e('EXPORT', 'BeRocket_domain') ?></h3>
                <div class="brfr_export_wrap">
                    <div class="brapf_export_loading" style="display:none;"><i class="fa fa-spinner fa-spin"></i></div>
                    <textarea class="brfr_export" readonly></textarea>
                </div>
            </div>
            <div class="brfr_import_all">
                <h3><?php _e('IMPORT', 'BeRocket_domain') ?></h3>
                <div class="brfr_import_wrap">
                    <div class="brapf_import_loading" style="display:none;"><i class="fa fa-spinner fa-spin"></i></div>
                    <textarea name="data" class="brfr_import"></textarea>
                </div>
                <button class="button brfr_import_send"><?php _e('Import', 'BeRocket_domain') ?></button>
            </div>
            <div class="brfr_import_links_step" style="display:none;">
                <h4><?php _e('Review links before import', 'BeRocket_domain') ?></h4>
                <div class="brfr_import_links_notice"></div>
                <div class="brfr_import_links_list"></div>
                <div class="brfr_import_links_actions">
                    <button type="button" class="button button-primary brfr_import_confirm"><?php _e('Confirm import', 'BeRocket_domain') ?></button>
                    <button type="button" class="button brfr_import_cancel"><?php _e('Cancel', 'BeRocket_domain') ?></button>
                </div>
            </div>
        </form>
        <div class="brfr_backup_all">
            <form class="brfr_backup_form" style="display: none;">
                <input name="action" type="hidden" value="brfr_restore_import_backups">
                <input name="nonce" type="hidden" value="<?php echo $nonce; ?>">
                <h3></h3>
                <div class="brfr_backup_form_select"></div>
                <button class="button brfr_backup_form_send"><?php _e('Restore', 'BeRocket_domain') ?></button>
                <i class="fa fa-spinner fa-spin" style="display:none;"></i>
                <i class="fa fa-check" style="display:none;"></i>
            </form>
        </div>
    </div><?php
    }
    public static function export_generate($plugin_slug) {
        $plugin_slug = sanitize_text_field($plugin_slug);
        $plugin_instance = apply_filters('brfr_plugin_get_instance_' . $plugin_slug, FALSE);
        if( $plugin_instance != FALSE && $plugin_instance->import_export !== FALSE ) {
            $current_option = $plugin_instance->get_option();
            $default_option = $plugin_instance->defaults;
            $export = self::check_settings_remove($current_option, $default_option, $plugin_instance->import_export);
            $export = self::type_check_export($export, $plugin_instance->import_export, $current_option);
            return $export;
        }
        return array();
    }
    public static function export_generate_post($post_slug) {
        $post_slug = sanitize_text_field($post_slug);
        $post_instance = apply_filters('brfr_custom_post_get_instance_' . $post_slug, FALSE);
        if( $post_instance != FALSE && $post_instance->import_export !== FALSE ) {
            $posts_options = array();
            $default_option = $post_instance->default_settings;
            $posts = $post_instance->get_custom_posts();
            foreach($posts as $post_id) {
                $current_option = $post_instance->get_option($post_id);
                $post_export = self::check_settings_remove($current_option, $default_option, $post_instance->import_export);
                $post_export = self::type_check_export($post_export, $post_instance->import_export, $current_option);
                $post_parent = (int) get_post_field('post_parent', $post_id);
                $post_export['brfr_import_slug'] = get_post_field('post_name', $post_id);
                $post_export['brfr_import_title'] = get_the_title($post_id);
                $post_export[self::POST_CONTENT_KEY] = (string) get_post_field('post_content', $post_id);
                $post_export[self::POST_PARENT_KEY] = ( $post_parent > 0
                    ? self::id_to_slug($post_parent, 'post', array('scope' => 'package'))
                    : 0
                );
                if( metadata_exists('post', $post_id, 'berocket_post_order') ) {
                    $post_export[self::POST_ORDER_KEY] = (int) get_post_meta($post_id, 'berocket_post_order', true);
                }
                $posts_options[$post_id] = $post_export;
            }
            return $posts_options;
        }
        return array();
    }
    public static function import_generate($plugin_slug, $options = array()) {
        $plugin_slug = sanitize_text_field($plugin_slug);
        $plugin_instance = apply_filters('brfr_plugin_get_instance_' . $plugin_slug, FALSE);
        if( $plugin_instance != FALSE && $plugin_instance->import_export !== FALSE ) {
            $default_option = $plugin_instance->defaults;
            $import = self::check_settings_create($options, $default_option);
            $import = self::type_check_import($import, $plugin_instance->import_export, $import);
            return $import;
        }
        return array();
    }
    public static function import_generate_post($post_slug, $options = array(), $import_settings = array()) {
        $custom_posts_data = array(
            sanitize_text_field($post_slug) => $options,
        );
        self::reserve_import_custom_posts($custom_posts_data, $import_settings);
        self::hydrate_import_custom_posts($custom_posts_data);
    }
    public static function reserve_import_custom_posts($custom_posts_data = array(), $import_settings = array()) {
        $import_settings = array_merge(array(
            'custom_post_slug_strategies' => array(),
        ), ( is_array($import_settings) ? $import_settings : array() ));
        if( ! is_array($custom_posts_data) ) {
            return self::$import_post_id_map;
        }
        foreach($custom_posts_data as $post_slug => $options) {
            $post_slug = sanitize_text_field($post_slug);
            $post_instance = apply_filters('brfr_custom_post_get_instance_' . $post_slug, FALSE);
            if( $post_instance === FALSE || $post_instance->import_export === FALSE || ! is_array($options) ) {
                continue;
            }
            foreach($options as $source_post_id => $post_option) {
                if( ! is_array($post_option) ) {
                    continue;
                }
                $post_strategy = self::get_custom_post_slug_strategy($post_slug, $source_post_id, $import_settings);
                $post_data = self::get_import_post_data($post_slug, $source_post_id, $post_option, array(
                    'custom_post_slug_strategy' => $post_strategy,
                ));
                $target_post_id = false;
                if( $post_strategy !== 'create_new' && ! empty($post_data['post_name']) ) {
                    $target_post_id = self::get_post_by_slug($post_data['post_name'], $post_slug);
                }
                if( ! empty($target_post_id) ) {
                    $post_data['ID'] = $target_post_id;
                    $updated_post_id = wp_update_post($post_data, true);
                    $target_post_id = $updated_post_id;
                } else {
                    if( $post_strategy === 'create_new' ) {
                        unset($post_data['post_name']);
                    }
                    $post_data = array_merge(array(
                        'post_type'   => $post_slug,
                        'post_status' => 'publish',
                    ), $post_data);
                    $target_post_id = wp_insert_post($post_data, true);
                }
                if( is_wp_error($target_post_id) || empty($target_post_id) ) {
                    self::add_import_warnings(array(array(
                        'code'           => 'custom_post_reserve_failed',
                        'post_type'      => $post_slug,
                        'source_post_id' => (string) $source_post_id,
                    )));
                    continue;
                }
                if( empty(self::$import_post_id_map[$post_slug]) ) {
                    self::$import_post_id_map[$post_slug] = array();
                }
                self::$import_post_id_map[$post_slug][(string) $source_post_id] = (int) $target_post_id;
            }
        }
        return self::$import_post_id_map;
    }
    public static function hydrate_import_custom_posts($custom_posts_data = array()) {
        if( ! is_array($custom_posts_data) ) {
            return;
        }
        foreach($custom_posts_data as $post_slug => $options) {
            $post_slug = sanitize_text_field($post_slug);
            $post_instance = apply_filters('brfr_custom_post_get_instance_' . $post_slug, FALSE);
            if( $post_instance === FALSE || $post_instance->import_export === FALSE || ! is_array($options) ) {
                continue;
            }
            foreach($options as $source_post_id => $post_option) {
                $source_post_id = (string) $source_post_id;
                if( ! is_array($post_option) || empty(self::$import_post_id_map[$post_slug][$source_post_id]) ) {
                    continue;
                }
                $post_parent = ( array_key_exists(self::POST_PARENT_KEY, $post_option) ? $post_option[self::POST_PARENT_KEY] : null );
                $post_order = ( array_key_exists(self::POST_ORDER_KEY, $post_option) ? $post_option[self::POST_ORDER_KEY] : null );
                unset(
                    $post_option['brfr_import_slug'],
                    $post_option['brfr_import_title'],
                    $post_option[self::POST_CONTENT_KEY],
                    $post_option[self::POST_PARENT_KEY],
                    $post_option[self::POST_ORDER_KEY]
                );
                $import = self::check_settings_create($post_option, $post_instance->default_settings);
                $import = self::type_check_import($import, $post_instance->import_export, $import);
                $target_post_id = self::$import_post_id_map[$post_slug][$source_post_id];
                self::import_custom_post_parent($target_post_id, $post_parent, $post_slug, $source_post_id);
                if( $post_order !== null && is_scalar($post_order) ) {
                    update_post_meta($target_post_id, 'berocket_post_order', (int) $post_order);
                }
                $post_request_existed = array_key_exists($post_instance->post_name, $_POST);
                $previous_post_request = ( $post_request_existed ? $_POST[$post_instance->post_name] : null );
                $_POST[$post_instance->post_name] = $import;
                $post_instance->wc_save_product_without_check($target_post_id, get_post($target_post_id));
                if( $post_request_existed ) {
                    $_POST[$post_instance->post_name] = $previous_post_request;
                } else {
                    unset($_POST[$post_instance->post_name]);
                }
            }
        }
    }
    public static function get_import_post_data($post_slug, $post_id, $settings = array(), $import_settings = array()) {
        $import_settings = array_merge(array(
            'custom_post_slug_strategy' => 'update',
        ), ( is_array($import_settings) ? $import_settings : array() ));
        $post_title = '';
        if( ! empty($settings['brfr_import_title']) ) {
            $post_title = sanitize_text_field($settings['brfr_import_title']);
        } elseif( ! empty($settings['text']) ) {
            $post_title = sanitize_text_field($settings['text']);
        }
        if( empty($post_title) ) {
            $post_title = sprintf(__('Imported %1$s #%2$s', 'BeRocket_domain'), $post_slug, $post_id);
        }

        $post_name = '';
        if( $import_settings['custom_post_slug_strategy'] !== 'create_new' && ! empty($settings['brfr_import_slug']) ) {
            $post_name = sanitize_title($settings['brfr_import_slug']);
        }

        $post_data = array(
            'post_title' => $post_title,
            'post_name'  => $post_name,
        );
        if( array_key_exists(self::POST_CONTENT_KEY, $settings) && is_scalar($settings[self::POST_CONTENT_KEY]) ) {
            $post_data['post_content'] = (string) $settings[self::POST_CONTENT_KEY];
        }

        return $post_data;
    }
    protected static function import_custom_post_parent($target_post_id, $post_parent, $post_slug, $source_post_id) {
        if( $post_parent === null ) {
            return;
        }
        if( $post_parent === 0 || $post_parent === '0' || $post_parent === '' ) {
            $target_parent_id = 0;
        } else {
            $target_parent_id = self::slug_to_id($post_parent, 'post', array('scope' => 'package'));
            if( $target_parent_id === false ) {
                self::add_import_warnings(array(array(
                    'code'           => 'custom_post_parent_not_found',
                    'post_type'      => $post_slug,
                    'source_post_id' => (string) $source_post_id,
                    'reference'      => $post_parent,
                )));
                return;
            }
        }
        if( (int) get_post_field('post_parent', $target_post_id) === (int) $target_parent_id ) {
            return;
        }
        $updated_post_id = wp_update_post(array(
            'ID'          => (int) $target_post_id,
            'post_parent' => (int) $target_parent_id,
        ), true);
        if( is_wp_error($updated_post_id) || empty($updated_post_id) ) {
            self::add_import_warnings(array(array(
                'code'           => 'custom_post_parent_update_failed',
                'post_type'      => $post_slug,
                'source_post_id' => (string) $source_post_id,
            )));
        }
    }
    public static function process_import_package($plugin_slug, $package, $link_actions = array(), $import_options = array()) {
        self::$import_warnings = array();
        self::$import_post_id_map = array();
        $plugin_slug = sanitize_text_field($plugin_slug);
        $plugin_instance = apply_filters('brfr_plugin_get_instance_' . $plugin_slug, FALSE);
        if( $plugin_instance === FALSE || $plugin_instance->import_export === FALSE ) {
            return array(
                'success' => false,
                'message' => __('Import for this plugin not allowed', 'BeRocket_domain'),
            );
        }
        $version_error = self::get_import_version_error(
            isset($package['plugin_version']) ? array('brfr_plugin_version' => $package['plugin_version']) : array(),
            $plugin_instance
        );
        if( $version_error !== '' ) {
            return array('success' => false, 'message' => $version_error);
        }
        $data = ( ! empty($package['data']) && is_array($package['data']) ? $package['data'] : array() );
        $custom_posts_data = ( ! empty($package['custom_posts_data']) && is_array($package['custom_posts_data']) ? $package['custom_posts_data'] : array() );
        $service_data = ( ! empty($package['service_data']) && is_array($package['service_data']) ? $package['service_data'] : array() );
        $import_options = array_merge(array(
            'delete_existing_custom_posts' => array(),
            'custom_post_slug_strategies' => array(),
        ), ( is_array($import_options) ? $import_options : array() ));
        $import_options['delete_existing_custom_posts'] = self::sanitize_custom_post_type_flags($import_options['delete_existing_custom_posts']);
        $import_options['custom_post_slug_strategies'] = self::sanitize_custom_post_slug_strategies($import_options['custom_post_slug_strategies']);
        $data = self::apply_link_actions_to_settings($data, $link_actions, $service_data);
        $custom_posts_data = self::apply_link_actions_to_settings($custom_posts_data, $link_actions, $service_data);
        $has_custom_posts = ! empty($custom_posts_data);
        if( empty($data) && ! $has_custom_posts && empty($import_options['restore_backup']) ) {
            return array(
                'success' => false,
                'message' => __('This data cannot be used for import', 'BeRocket_domain'),
            );
        }

        self::backup_plugin_options($plugin_slug, $plugin_instance);
        if( $has_custom_posts ) {
            foreach($custom_posts_data as $post_name => $posts_data) {
                if( ! empty($import_options['delete_existing_custom_posts'][sanitize_key($post_name)]) ) {
                    self::delete_existing_custom_posts(array($post_name));
                }
            }
            self::reserve_import_custom_posts($custom_posts_data, $import_options);
            self::hydrate_import_custom_posts($custom_posts_data);
        }
        if( ! empty($data) || ! empty($import_options['restore_backup']) ) {
            $import = self::import_generate($plugin_slug, $data);
            $import = $plugin_instance->save_settings_callback( $import );
            update_option($plugin_instance->values['settings_name'], $import);
        }

        $warnings = self::get_import_warnings();
        $message = __('Imported', 'BeRocket_domain');
        if( ! empty($warnings) ) {
            $message .= "\n" . implode("\n", self::get_import_warning_messages($warnings));
        }

        return array(
            'success' => true,
            'message' => $message,
            'warnings' => $warnings,
        );
    }

    public static function get_import_warnings() {
        return array_values(self::$import_warnings);
    }

    protected static function add_import_warnings($warnings) {
        if( ! is_array($warnings) ) {
            return;
        }
        foreach($warnings as $warning) {
            if( ! is_array($warning) || empty($warning['code']) ) {
                continue;
            }
            $warning_key = md5(wp_json_encode($warning));
            self::$import_warnings[$warning_key] = $warning;
        }
    }

    protected static function get_import_warning_messages($warnings) {
        $messages = array();
        foreach($warnings as $warning) {
            if( ! is_array($warning) || empty($warning['code']) ) {
                continue;
            }
            switch($warning['code']) {
                case 'reference_not_found':
                    $messages[] = sprintf(
                        /* translators: %1$s: reference type, %2$s: settings key. */
                        __('A %1$s reference for setting "%2$s" could not be resolved.', 'BeRocket_domain'),
                        ( ! empty($warning['type']) ? $warning['type'] : 'entity' ),
                        ( isset($warning['setting_key']) ? $warning['setting_key'] : '' )
                    );
                    break;
                case 'custom_post_reserve_failed':
                    $messages[] = sprintf(
                        /* translators: %1$s: custom post type, %2$s: source post ID. */
                        __('Could not create or update imported %1$s item %2$s.', 'BeRocket_domain'),
                        ( ! empty($warning['post_type']) ? $warning['post_type'] : 'post' ),
                        ( isset($warning['source_post_id']) ? $warning['source_post_id'] : '' )
                    );
                    break;
            }
        }
        return $messages;
    }
    public static function get_custom_post_slug_strategy($post_slug, $post_id, $import_settings = array()) {
        $map_key = self::get_custom_post_map_key($post_slug, $post_id);
        if( ! empty($import_settings['custom_post_slug_strategies'][$map_key]) ) {
            return $import_settings['custom_post_slug_strategies'][$map_key];
        }
        return 'update';
    }
    public static function sanitize_custom_post_slug_strategies($strategies = array()) {
        $result = array();
        if( ! is_array($strategies) ) {
            return $result;
        }
        foreach($strategies as $map_key => $strategy) {
            $map_key = sanitize_text_field($map_key);
            $strategy = sanitize_text_field($strategy);
            if( in_array($strategy, array('update', 'create_new')) ) {
                $result[$map_key] = $strategy;
            }
        }
        return $result;
    }
    public static function sanitize_custom_post_type_flags($flags = array()) {
        $result = array();
        if( ! is_array($flags) ) {
            return $result;
        }
        foreach($flags as $post_type => $flag) {
            $post_type = sanitize_key($post_type);
            $result[$post_type] = ! empty($flag);
        }
        return $result;
    }
    public static function prepare_custom_posts_for_review($custom_posts_data = array()) {
        $result = array();
        if( ! is_array($custom_posts_data) ) {
            return $result;
        }
        foreach($custom_posts_data as $post_type => $posts_data) {
            if( ! is_array($posts_data) ) {
                continue;
            }
            $post_type_label = self::get_custom_post_type_label($post_type);
            foreach($posts_data as $post_id => $post_settings) {
                if( ! is_array($post_settings) ) {
                    continue;
                }
                $title = '';
                if( ! empty($post_settings['brfr_import_title']) ) {
                    $title = $post_settings['brfr_import_title'];
                } elseif( ! empty($post_settings['text']) ) {
                    $title = $post_settings['text'];
                } else {
                    $title = sprintf(__('Imported %1$s #%2$s', 'BeRocket_domain'), $post_type, $post_id);
                }
                $slug = ( ! empty($post_settings['brfr_import_slug']) ? $post_settings['brfr_import_slug'] : '' );
                $existing_post_id = false;
                if( ! empty($slug) ) {
                    $existing_post_id = self::get_post_by_slug($slug, $post_type);
                }
                if( empty($existing_post_id) ) {
                    continue;
                }
                $result[] = array(
                    'map_key'   => self::get_custom_post_map_key($post_type, $post_id),
                    'post_type' => $post_type,
                    'post_type_label' => $post_type_label,
                    'post_type_key' => sanitize_key($post_type),
                    'post_id'   => (string) $post_id,
                    'title_with_id' => sprintf('%1$s (ID: %2$s)', $title, $post_id),
                );
            }
        }
        return $result;
    }
    public static function get_custom_post_map_key($post_type, $post_id) {
        return sanitize_key($post_type . '__' . $post_id);
    }
    public static function get_custom_post_type_label($post_type) {
        $post_type = sanitize_text_field($post_type);
        $post_instance = apply_filters('brfr_custom_post_get_instance_' . $post_type, FALSE);
        if( $post_instance !== FALSE ) {
            if( ! empty($post_instance->post_settings['labels']['name']) ) {
                return $post_instance->post_settings['labels']['name'];
            }
            if( ! empty($post_instance->post_settings['label']) ) {
                return $post_instance->post_settings['label'];
            }
        }

        return $post_type;
    }
    public static function prepare_custom_post_groups_for_review($custom_posts_data = array()) {
        $groups = array();
        foreach(self::prepare_custom_posts_for_review($custom_posts_data) as $post_item) {
            $post_type_key = $post_item['post_type_key'];
            if( empty($groups[$post_type_key]) ) {
                $groups[$post_type_key] = array(
                    'post_type' => $post_item['post_type'],
                    'post_type_label' => $post_item['post_type_label'],
                    'post_type_key' => $post_type_key,
                    'posts' => array(),
                );
            }
            $groups[$post_type_key]['posts'][] = $post_item;
        }

        return array_values($groups);
    }
    public static function apply_link_actions_to_settings($settings, $link_actions = array(), $service_data = array()) {
        if( is_array($settings) ) {
            foreach($settings as $key => $value) {
                $settings[$key] = self::apply_link_actions_to_settings($value, $link_actions, $service_data);
            }
            return $settings;
        }

        if( ! is_string($settings) || $settings === '' ) {
            return $settings;
        }

        foreach($link_actions as $original_url => $action_data) {
            if( strpos($settings, $original_url) === false ) {
                continue;
            }
            $replacement = self::get_link_replacement($original_url, $action_data, $service_data);
            if( $replacement !== false ) {
                $settings = str_replace($original_url, $replacement, $settings);
            }
        }

        return $settings;
    }
    public static function get_link_replacement($original_url, $action_data = array(), $service_data = array()) {
        $action = ( ! empty($action_data['action']) ? $action_data['action'] : 'keep' );
        switch($action) {
            case 'replace_site':
                return self::build_current_site_url($original_url, ( ! empty($service_data['site_url']) ? $service_data['site_url'] : '' ));
            case 'media':
            case 'custom':
                return ( ! empty($action_data['replacement']) ? esc_url_raw($action_data['replacement']) : false );
            case 'keep':
            default:
                return false;
        }
    }
    public static function build_current_site_url($url, $site_url = '') {
        $url = trim((string) $url);
        if( empty($url) ) {
            return $url;
        }

        $source_site_url = self::normalize_site_url($site_url);
        $current_site_url = self::normalize_site_url(home_url('/'));
        if( empty($current_site_url) ) {
            return $url;
        }

        if( empty($source_site_url) ) {
            $url_parts = wp_parse_url($url);
            if( empty($url_parts['scheme']) || empty($url_parts['host']) ) {
                return $url;
            }
            $path = ( ! empty($url_parts['path']) ? $url_parts['path'] : '' );
            $path_parts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
            $source_site_url = $url_parts['scheme'] . '://' . $url_parts['host'];
            if( isset($url_parts['port']) ) {
                $source_site_url .= ':' . $url_parts['port'];
            }
            if( ! empty($path_parts) ) {
                $source_site_url .= '/' . $path_parts[0];
            }
            $source_site_url = self::normalize_site_url($source_site_url);
        }

        if( empty($source_site_url) ) {
            return $url;
        }

        return str_replace($source_site_url, $current_site_url, $url);
    }
    public static function get_export_service_data($plugin_instance = false) {
        return array(
            'site_url' => self::normalize_site_url(home_url('/')),
        );
    }
    public static function get_import_service_data(&$data) {
        $service_data = array();
        if( is_array($data) && ! empty($data['brfr_service']) && is_array($data['brfr_service']) ) {
            $service_data = $data['brfr_service'];
            unset($data['brfr_service']);
        }

        return array(
            'site_url' => ( ! empty($service_data['site_url']) ? $service_data['site_url'] : '' ),
            'current_plugin_url' => ( ! empty($service_data['current_plugin_url']) ? $service_data['current_plugin_url'] : '' ),
        );
    }
    public static function get_import_custom_posts_data(&$data) {
        $custom_posts_data = array();
        if( is_array($data) && ! empty($data['import_custom_posts']) && is_array($data['import_custom_posts']) ) {
            $custom_posts_data = $data['import_custom_posts'];
            unset($data['import_custom_posts']);
        }

        return $custom_posts_data;
    }
    public static function collect_import_links($data, $custom_posts_data = array(), $service_data = array()) {
        $links = array();
        self::collect_links_from_settings($data, $links, $service_data);
        self::collect_links_from_settings($custom_posts_data, $links, $service_data);
        return array_values($links);
    }
    public static function collect_links_from_settings($settings, &$links, $service_data = array()) {
        if( is_array($settings) ) {
            foreach($settings as $value) {
                self::collect_links_from_settings($value, $links, $service_data);
            }
            return;
        }
        if( ! is_string($settings) || $settings === '' ) {
            return;
        }
        if( preg_match_all('#https?://[^\s"\'<>\)\(]+#i', $settings, $matches) ) {
            foreach($matches[0] as $url) {
                $url = self::normalize_detected_url($url);
                if( empty($url) || isset($links[$url]) ) {
                    continue;
                }
                if( ! empty($service_data['current_plugin_url']) && strpos($url, $service_data['current_plugin_url']) === 0 ) {
                    continue;
                }
                $links[$url] = array(
                    'url'  => $url,
                    'type' => ( self::is_image_url($url) ? 'image' : 'link' ),
                );
            }
        }
    }
    public static function is_image_url($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if( empty($path) ) {
            $path = $url;
        }

        return (bool) preg_match('#\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)$#i', $path);
    }
    public static function normalize_detected_url($url) {
        $url = trim((string)$url);
        $url = rtrim($url, " \t\n\r\0\x0B,;:");
        $url = esc_url_raw($url);

        return $url;
    }
    public static function replace_plugin_url_with_token_in_settings($settings, $plugin_url = '') {
        if( is_array($settings ) ) {
            foreach($settings as $key => $value) {
                $settings[$key] = self::replace_plugin_url_with_token_in_settings($value, $plugin_url);
            }
            return $settings;
        }
        if( ! is_string($settings) || $settings === '' ) {
            return $settings;
        }
        $plugin_url = self::normalize_site_url($plugin_url);
        if( empty($plugin_url) ) {
            return $settings;
        }
        return str_replace($plugin_url, self::PLUGIN_URL_TOKEN, $settings);
    }
    public static function replace_plugin_token_with_url_in_settings($settings, $plugin_url = '') {
        if( is_array($settings ) ) {
            foreach($settings as $key => $value) {
                $settings[$key] = self::replace_plugin_token_with_url_in_settings($value, $plugin_url);
            }
            return $settings;
        }
        if( ! is_string($settings) || $settings === '' ) {
            return $settings;
        }
        $plugin_url = self::normalize_site_url($plugin_url);
        if( empty($plugin_url) ) {
            return $settings;
        }
        return str_replace(self::PLUGIN_URL_TOKEN, $plugin_url, $settings);
    }
    public static function get_plugin_url($plugin_instance) {
        if( ! empty($plugin_instance->info['plugin_file']) ) {
            return self::normalize_site_url(plugins_url('/', $plugin_instance->info['plugin_file']));
        }
        return '';
    }
    public static function get_import_transient_name($import_token) {
        return 'brfr_import_' . get_current_user_id() . '_' . sanitize_key($import_token);
    }
    public static function store_import_package($package) {
        $import_token = wp_generate_password(20, false, false);
        set_transient(self::get_import_transient_name($import_token), $package, HOUR_IN_SECONDS);
        return $import_token;
    }
    public static function get_import_package($import_token) {
        if( empty($import_token) ) {
            return false;
        }
        return get_transient(self::get_import_transient_name($import_token));
    }
    public static function backup_plugin_options($plugin_slug, $plugin_instance) {
        $backup = array(
            'import_export_date' => microtime(true),
            'export_data' => json_encode(self::get_export_data($plugin_slug, $plugin_instance)),
        );
        $save_id_use = '1';
        $save_id_time = false;
        foreach(array('1', '2', '3') as $save_id) {
            $transient_option = get_transient('brfr_bckp_' . $plugin_slug . '_' . $save_id);
            if( $transient_option == false ) {
                $save_id_use = $save_id;
                break;
            } elseif( $save_id_time === false || $transient_option['import_export_date'] < $save_id_time ) {
                $save_id_use = $save_id;
                $save_id_time = $transient_option['import_export_date'];
            }
        }
        set_transient( 'brfr_bckp_' . $plugin_slug . '_' . $save_id_use, $backup, 30 * DAY_IN_SECONDS );
    }
    public static function delete_existing_custom_posts($post_types = array()) {
        if( ! is_array($post_types) ) {
            return;
        }
        foreach($post_types as $post_type) {
            $post_type = sanitize_text_field($post_type);
            if( empty($post_type) ) {
                continue;
            }
            $post_instance = apply_filters('brfr_custom_post_get_instance_' . $post_type, FALSE);
            if( $post_instance === FALSE ) {
                continue;
            }
            $post_ids = $post_instance->get_custom_posts(array(
                'post_status' => array('publish', 'draft', 'pending', 'future', 'private', 'trash'),
            ));
            if( empty($post_ids) || ! is_array($post_ids) ) {
                continue;
            }
            foreach($post_ids as $post_id) {
                if( current_user_can('delete_post', $post_id) ) {
                    wp_delete_post($post_id, true);
                }
            }
        }
    }
    public static function normalize_site_url($url) {
        $url = trim((string)$url);
        if( empty($url) ) {
            return '';
        }

        return untrailingslashit($url);
    }
    public static function check_settings_create($option, $default) {
        if( is_array($option) && is_array($default) ) {
            foreach( $option as $key => $value ) {
                $key = sanitize_text_field($key);
                if( isset( $default[$key] ) && is_array($value) && is_array($default[$key]) ) {
                    $default[$key] = self::check_settings_create($value, $default[$key]);
                } elseif( is_array($value) ) {
                    $default[$key] = self::check_settings_create($value, array());
                } else {
                    $default[$key] = sanitize_textarea_field($value);
                }
            }
        } else {
            $default = $option;
        }
        return $default;
    }
    public static function check_settings_remove($option, $default, $types = array()) {
        $new_option = array();
        if( is_array($option) && is_array($default) ) {
            foreach( $option as $key => $value ) {
                if( isset( $default[$key] ) ) {
                    if( is_array($value) && is_array($default[$key]) ) {
                        $new_value = self::check_settings_remove($value, $default[$key]);
                        if( is_array($new_value) && count($new_value) > 0 ) {
                            $new_option[$key] = $new_value;
                        }
                    } elseif( $value !== $default[$key] ) {
                        $new_option[$key] = $value;
                    }
                } else {
                    $new_option[$key] = $value;
                }
            }
        } else {
            $new_option = $option;
        }
        return self::preserve_export_defaults($new_option, $option, $types, $option);
    }
    /** Restore complete declared fields after removing values equal to defaults. */
    protected static function preserve_export_defaults($export, $options, $types, $context_options) {
        if( ! is_array($export) || ! is_array($options) || ! is_array($types) ) {
            return $export;
        }
        foreach($types as $schema_key => $type) {
            if( ! is_array($type) ) {
                continue;
            }
            foreach(self::get_matching_setting_keys($options, $schema_key) as $key) {
                $resolved_type = self::resolve_type_config($type, $key, $options, 'export', $context_options);
                if( ! empty($resolved_type['preserve_defaults']) ) {
                    $export[$key] = $options[$key];
                } elseif( ! isset($resolved_type['export_type']) && is_array($options[$key]) ) {
                    $nested = self::preserve_export_defaults(
                        isset($export[$key]) ? $export[$key] : array(),
                        $options[$key],
                        $resolved_type,
                        $context_options
                    );
                    if( ! empty($nested) ) {
                        $export[$key] = $nested;
                    }
                }
            }
        }
        return $export;
    }

    public static function type_check_export($options, $types = array(), $context_options = false) {
        if( ! is_array($options) || ! is_array($types) ) {
            return $options;
        }
        if( $context_options === false ) {
            $context_options = $options;
        }
        foreach($types as $schema_key => $type) {
            if( ! is_array($type) ) {
                continue;
            }
            foreach(self::get_matching_setting_keys($options, $schema_key) as $key) {
                $resolved_type = self::resolve_type_config($type, $key, $options, 'export', $context_options);
                if( isset($resolved_type['export_type']) ) {
                    $original_value = $options[$key];
                    switch($resolved_type['export_type']) {
                        case 'remove':
                            unset($options[$key]);
                            break;
                        case 'post':
                        case 'taxonomy':
                            $options[$key] = self::convert_declared_reference($options[$key], $resolved_type, 'export', $key);
                            break;
                        case 'conditions':
                            $options[$key] = BeRocket_conditions_import_export::convert($options[$key], 'export', array(
                                'setting_key' => $key,
                                'settings'    => $context_options,
                                'declaration' => $resolved_type,
                            ));
                            break;
                    }
                    if( array_key_exists($key, $options) ) {
                        $options[$key] = apply_filters(
                            'brfr_export_type_' . $resolved_type['export_type'],
                            $options[$key],
                            array(
                                'direction'      => 'export',
                                'setting_key'    => $key,
                                'settings'       => $context_options,
                                'local_settings' => $options,
                                'declaration'    => $resolved_type,
                                'original_value' => $original_value,
                            )
                        );
                    }
                } else {
                    $options[$key] = self::type_check_export($options[$key], $resolved_type, $context_options);
                }
            }
        }
        return $options;
    }
    public static function type_check_import($options, $types = array(), $context_options = false) {
        if( ! is_array($options) || ! is_array($types) ) {
            return $options;
        }
        if( $context_options === false ) {
            $context_options = $options;
        }
        foreach($types as $schema_key => $type) {
            if( ! is_array($type) ) {
                continue;
            }
            foreach(self::get_matching_setting_keys($options, $schema_key) as $key) {
                $resolved_type = self::resolve_type_config($type, $key, $options, 'import', $context_options);
                if( isset($resolved_type['export_type']) ) {
                    $original_value = $options[$key];
                    switch($resolved_type['export_type']) {
                        case 'post':
                        case 'taxonomy':
                            $options[$key] = self::convert_declared_reference($options[$key], $resolved_type, 'import', $key);
                            break;
                        case 'conditions':
                            $options[$key] = BeRocket_conditions_import_export::convert($options[$key], 'import', array(
                                'setting_key' => $key,
                                'settings'    => $context_options,
                                'declaration' => $resolved_type,
                            ));
                            break;
                    }
                    if( array_key_exists($key, $options) ) {
                        $options[$key] = apply_filters(
                            'brfr_import_type_' . $resolved_type['export_type'],
                            $options[$key],
                            array(
                                'direction'      => 'import',
                                'setting_key'    => $key,
                                'settings'       => $context_options,
                                'local_settings' => $options,
                                'declaration'    => $resolved_type,
                                'original_value' => $original_value,
                            )
                        );
                    }
                } else {
                    $options[$key] = self::type_check_import($options[$key], $resolved_type, $context_options);
                }
            }
        }
        return $options;
    }
    protected static function get_matching_setting_keys($options, $schema_key) {
        if( ! is_array($options) ) {
            return array();
        }
        $schema_key = (string) $schema_key;
        if( strpos($schema_key, '*') === false ) {
            return array_key_exists($schema_key, $options) ? array($schema_key) : array();
        }
        $pattern = '/^' . str_replace('\\*', '.*', preg_quote($schema_key, '/')) . '$/';
        $keys = array();
        foreach(array_keys($options) as $option_key) {
            if( preg_match($pattern, (string) $option_key) ) {
                $keys[] = $option_key;
            }
        }
        return $keys;
    }
    protected static function resolve_type_config($type, $key, $options, $direction, $context_options = array()) {
        if( ! empty($type['taxonomy_from_key_prefix']) ) {
            $prefix = (string) $type['taxonomy_from_key_prefix'];
            if( strpos((string) $key, $prefix) === 0 ) {
                $type['taxonomy'] = substr((string) $key, strlen($prefix));
            }
        }
        if( ! empty($type['taxonomy_from']) ) {
            $taxonomy = self::get_setting_path_value($options, $type['taxonomy_from']);
            if( $taxonomy === null ) {
                $taxonomy = self::get_setting_path_value($context_options, $type['taxonomy_from']);
            }
            if( is_scalar($taxonomy) && $taxonomy !== '' ) {
                $type['taxonomy'] = (string) $taxonomy;
            }
        }
        return apply_filters('brfr_import_export_type_config', $type, $key, $options, $direction, $context_options);
    }
    protected static function get_setting_path_value($settings, $path) {
        $path = is_array($path) ? $path : explode('.', (string) $path);
        foreach($path as $path_part) {
            if( ! is_array($settings) || ! array_key_exists($path_part, $settings) ) {
                return null;
            }
            $settings = $settings[$path_part];
        }
        return $settings;
    }
    protected static function convert_declared_reference($value, $type, $direction, $setting_key = '') {
        if( ! empty($type['array_keys']) ) {
            return self::convert_reference_array_keys($value, $type, $direction, $setting_key);
        }
        if( ! empty($type['taxonomy_by_key']) ) {
            if( ! is_array($value) ) {
                return $value;
            }
            $converted = array();
            foreach($value as $taxonomy => $item_value) {
                $item_type = $type;
                $item_type['taxonomy'] = $taxonomy;
                $item_result = self::convert_reference_scalar($item_value, $item_type, $direction, $setting_key);
                if( $item_result === false && isset($type['on_missing']) && $type['on_missing'] === 'remove' ) {
                    continue;
                }
                $converted[$taxonomy] = $item_result;
            }
            return $converted;
        }
        $auto_reference = (
            isset($type['multiple'])
            && $type['multiple'] === 'auto'
            && $direction === 'import'
            && is_array($value)
            && (isset($value['s']) || isset($value['source_id']))
        );
        if( ! empty($type['multiple']) && ! $auto_reference && ($type['multiple'] !== 'auto' || is_array($value)) ) {
            if( ! is_array($value) ) {
                return $value;
            }
            $converted = array();
            foreach($value as $item_key => $item_value) {
                $item_result = self::convert_reference_scalar($item_value, $type, $direction, $setting_key);
                if( $item_result === false && isset($type['on_missing']) && $type['on_missing'] === 'remove' ) {
                    continue;
                }
                $converted[$item_key] = $item_result;
            }
            return $converted;
        }
        return self::convert_reference_scalar($value, $type, $direction, $setting_key);
    }
    protected static function convert_reference_array_keys($value, $type, $direction, $setting_key = '') {
        if( $direction === 'export' ) {
            if( ! is_array($value) ) {
                return $value;
            }
            $entries = array();
            foreach($value as $map_key => $map_value) {
                $entries[] = array(
                    'key'   => self::convert_reference_scalar($map_key, $type, $direction, $setting_key),
                    'value' => $map_value,
                );
            }
            return array(self::MAP_ENTRIES_KEY => $entries);
        }
        $entries = ( is_array($value) && isset($value[self::MAP_ENTRIES_KEY]) && is_array($value[self::MAP_ENTRIES_KEY]) )
            ? $value[self::MAP_ENTRIES_KEY]
            : array();
        if( empty($entries) && is_array($value) ) {
            foreach($value as $map_key => $map_value) {
                $reference = array(
                    'source_id' => (string) $map_key,
                    'pt'        => ( ! empty($type['post_type']) ? $type['post_type'] : '' ),
                );
                $entries[] = array('key' => $reference, 'value' => $map_value);
            }
        }
        $converted = array();
        foreach($entries as $entry) {
            if( ! is_array($entry) || ! array_key_exists('key', $entry) ) {
                continue;
            }
            $converted_key = self::convert_reference_scalar($entry['key'], $type, $direction, $setting_key);
            if( $converted_key === false || is_array($converted_key) || is_object($converted_key) ) {
                if( isset($type['on_missing']) && $type['on_missing'] === 'preserve' && is_scalar($entry['key']) ) {
                    $converted_key = $entry['key'];
                } else {
                    continue;
                }
            }
            $converted[$converted_key] = ( array_key_exists('value', $entry) ? $entry['value'] : null );
        }
        return $converted;
    }
    protected static function convert_reference_scalar($value, $type, $direction, $setting_key = '') {
        if( isset($type['passthrough']) && is_array($type['passthrough']) && in_array($value, $type['passthrough'], true) ) {
            return $value;
        }
        $result = ( $direction === 'export' )
            ? self::id_to_slug($value, $type['export_type'], $type)
            : self::slug_to_id($value, $type['export_type'], $type);
        if( $result !== false ) {
            return $result;
        }
        if( $direction === 'import' ) {
            self::add_import_warnings(array(array(
                'code'        => 'reference_not_found',
                'type'        => $type['export_type'],
                'setting_key' => (string) $setting_key,
                'reference'   => $value,
            )));
        }
        if( ! empty($type['on_missing']) && $type['on_missing'] === 'preserve' ) {
            return $value;
        }
        return false;
    }
    public static function id_to_slug($id, $type, $additional = false) {
        $result = false;
        if( ! is_scalar($id) ) {
            return $result;
        }
        switch($type) {
            case 'post':
                $post = false;
                if( ! empty($additional['post_type']) ) {
                    $post = get_post($id);
                    $allowed_post_types = is_array($additional['post_type'])
                        ? $additional['post_type']
                        : array($additional['post_type']);
                    if( empty($post) || ! in_array($post->post_type, $allowed_post_types, true) ) {
                        $post = false;
                    }
                } else {
                    $post = get_post($id);
                }
                if( ! empty($post) ) {
                    $result = array('s' => $post->post_name);
                    if( ! empty($additional['post_type']) && is_array($additional['post_type']) ) {
                        $result['pt'] = $post->post_type;
                    }
                    if( ! empty($additional['scope']) && $additional['scope'] === 'package' ) {
                        $result['source_id'] = (string) $id;
                        $result['pt'] = $post->post_type;
                    }
                }
                break;
            case 'taxonomy':
                $field = 'term_id';
                if( isset($additional['field']) ) {
                    $field = $additional['field'];
                }
                $taxonomy = 'product_cat';
                if( isset($additional['taxonomy']) ) {
                    $taxonomy = $additional['taxonomy'];
                }
                $term = get_term_by($field, $id, $taxonomy);
                if( ! empty($term) ) {
                    $result = array('s' => $term->slug, 'tx' => $taxonomy);
                }
                break;
            default:
                break;
        }
        return $result;
    }
    public static function slug_to_id($data, $type, $additional = false) {
        $result = false;
        if( ! is_array($data) ) {
            return $result;
        }
        switch($type) {
            case 'post':
                if( ! empty($additional['scope']) && $additional['scope'] === 'package' && isset($data['source_id']) ) {
                    $post_type = ! empty($data['pt']) ? sanitize_text_field($data['pt']) : '';
                    if( empty($post_type) && ! empty($additional['post_type']) && ! is_array($additional['post_type']) ) {
                        $post_type = sanitize_text_field($additional['post_type']);
                    }
                    $source_post_id = (string) $data['source_id'];
                    if( ! empty($post_type) && ! empty(self::$import_post_id_map[$post_type][$source_post_id]) ) {
                        return self::$import_post_id_map[$post_type][$source_post_id];
                    }
                }
                if( ! empty($data['s']) ) {
                    $post_type = ! empty($additional['post_type']) ? $additional['post_type'] : '';
                    if( ! empty($data['pt']) && is_array($post_type) ) {
                        $reference_post_type = sanitize_text_field($data['pt']);
                        if( in_array($reference_post_type, $post_type, true) ) {
                            $post_type = $reference_post_type;
                        }
                    } elseif( empty($post_type) && ! empty($data['pt']) ) {
                        $post_type = sanitize_text_field($data['pt']);
                    }
                    $post_id = self::get_post_by_slug($data['s'], $post_type);
                    if( ! empty($post_id) ) {
                        $result = $post_id;
                    }
                }
                break;
            case 'taxonomy':
                if( ! empty($data['s']) && ! empty($data['tx']) ) {
                    $taxonomy = ( ! empty($additional['taxonomy']) ? $additional['taxonomy'] : sanitize_text_field($data['tx']) );
                    $term = get_term_by('slug', $data['s'], $taxonomy);
                    if( ! empty($term) ) {
                        $field = 'term_id';
                        if( isset($additional['field']) ) {
                            $field = $additional['field'];
                        }
                        if( isset($term->$field) ) {
                            $result = $term->$field;
                        }
                    }
                }
                break;
        }
        return $result;
    }
    public static function get_post_by_slug($slug, $post_type = '') {
        $args = array(
            'name'           => $slug,
            'posts_per_page' => 1,
        );
        if( ! empty($post_type) ) {
            $args['post_type'] = $post_type;
        }
        $posts = get_posts($args);

        if ($posts) {
            return $posts[0]->ID;
        } else {
            return false;
        }
    }
}
new BeRocket_import_export();
