<?php
class BeRocket_aapf_variations_tables_addon extends BeRocket_framework_addon_lib {
    const STATE_SCHEMA_VERSION = 1;
    const TABLE_SCHEMA_VERSION = 2;
    const STATE_OPTION = 'BeRocket_aapf_additional_tables_state';
    const LOCK_OPTION = 'BeRocket_aapf_additional_tables_worker_lock';
    const RERUN_OPTION = 'BeRocket_aapf_additional_tables_rerun';
    const CANCEL_OPTION = 'BeRocket_aapf_additional_tables_cancelled';
    const WORKER_HOOK = 'braapf_additional_table_worker';
    const WORKER_GROUP = 'berocket-aapf';
    public $addon_file = __FILE__;
    public $plugin_name = 'ajax_filters';
    public $php_file_name   = 'add_table';
    public $position_data;
    public $run_additional_tables = false;
    protected $worker_lock_token = false;
    protected $worker_lock_generation_id = false;
    protected $generation_context = false;
    protected $generation_context_revision = 0;
    protected $additional_tables_active = false;
    function __construct() {
        $this->position_data = array(
            0 => array(
                'percentage' => 0,
                'execute'    => array($this, 'increment_create_position'),
                'ajax_only'  => false
            ),
            1 => array(
                'percentage' => 4,
                'execute'    => array($this, 'create_all_tables'),
                'ajax_only'  => true
            ),
            2 => array(
                'percentage' => 13,
                'execute'    => array($this, 'insert_table_braapf_product_stock_status_parent'),
                'ajax_only'  => true
            ),
            3 => array(
                'percentage' => 70,
                'execute'    => array($this, 'insert_table_braapf_product_variation_attributes'),
                'ajax_only'  => true
            ),
            4 => array(
                'percentage' => 3,
                'execute'    => array($this, 'insert_table_braapf_variable_attributes'),
                'ajax_only'  => true
            ),
            5 => array(
                'percentage' => 3,
                'execute'    => array($this, 'insert_table_braapf_missing_variation_attributes'),
                'ajax_only'  => true
            ),
        );
        parent::__construct();
        // Active add-ons are loaded during init priority 1. Build the final
        // generation plan afterwards so optional stages never depend on the
        // order in which add-on files happened to be included.
        if( did_action('init') && ! doing_action('init') ) {
            $this->init_tables();
        } else {
            add_action('init', array($this, 'init_tables'), 2);
        }
        add_action(self::WORKER_HOOK, array($this, 'run_generation_worker'));
        // Keep accepting already scheduled events from previous versions.
        add_action('braapf_additional_table_cron', array($this, 'run_generation_worker'));
        add_action('deleted_option', array($this, 'wc_lookup_generation_option_deleted'), 20, 1);
        add_filter('brfr_ajax_filters_purge_additional_tables', array($this, 'section_purge_additional_tables'), 10, 3);
        add_action( "wp_ajax_braapf_additional_table_status", array( $this, 'get_global_status_ajax' ) );
        add_action( "wp_ajax_brapf_regenerate_additional_tables", array ( $this, 'regenerate_additional_tables' ) );
        add_action( "bapf_additional_tables_reset_all_table", array ( $this, 'reset_all_table' ) );
    }
    function init_tables() {
        if( $this->run_additional_tables ) {
            return false;
        }
        $this->run_additional_tables = true;
        $active_addons = apply_filters('berocket_addons_active_'.$this->plugin_name, array());
        if( ! in_array($this->addon_file, $active_addons, true) ) {
            $has_generation_state = get_option(self::STATE_OPTION, false) !== false
                || get_option('BeRocket_aapf_additional_tables_addon_position', false) !== false
                || get_option(self::CANCEL_OPTION, false) !== false
                || get_option(self::RERUN_OPTION, false) !== false
                || get_option(self::LOCK_OPTION, false) !== false;
            if( $has_generation_state ) {
                $this->deactivate();
            }
            do_action('BeRocket_aapf_variations_tables_addon_status', 'remove', false, $this);
            return false;
        }
        $create_position = $this->get_current_create_position();
        $status = 'start';
        if( in_array($this->addon_file, $active_addons, true) ) {
            $this->additional_tables_active = true;
            $this->position_data = apply_filters('BeRocket_aapf_variations_tables_addon_position_data', $this->position_data, $this);
            $state = $this->get_generation_state();
            if( intval($state['schema_version']) > self::STATE_SCHEMA_VERSION ) {
                do_action('BeRocket_aapf_variations_tables_addon_status', 'failed', $state['position'], $this);
                return false;
            }
            $cancel_request = $this->get_cancel_request_direct();
            if( $cancel_request && $this->additional_tables_addon_is_enabled() ) {
                $this->clear_cancel_request($cancel_request);
            }
            $plan_hash = $this->get_generation_plan_hash();
            if( empty($state['plan_hash']) ) {
                $state = $this->initialize_generation_plan_hash($plan_hash);
            }
            if( ! empty($state['plan_hash']) && $state['plan_hash'] !== $plan_hash ) {
                $this->request_regeneration('generation_plan_changed');
                $state = $this->get_generation_state();
            }
            if( intval($state['table_schema_version']) < self::TABLE_SCHEMA_VERSION
                && $state['status'] === 'complete' ) {
                $this->request_regeneration('table_schema_upgrade');
                $state = $this->get_generation_state();
            }
            if( is_admin() ) {
                $this->is_table_exist();
                $create_position = $this->get_current_create_position();
            }
            if( strpos((string)$create_position, 'ended') !== false && ! $this->is_valid_end_position($create_position) ) {
                $this->request_regeneration('completion_marker_changed');
                $create_position = $this->get_current_create_position();
                $state = $this->get_generation_state();
            }
            $generation_complete = $state['status'] === 'complete'
                && ! empty($state['tables_ready'])
                && $this->is_valid_end_position($create_position);
            if( ! $generation_complete ) {
                $this->init_activate();
                $create_position = $this->get_current_create_position();
                if( $this->get_generation_state()['status'] !== 'complete' ) {
                    add_action('admin_init', array($this, 'activate_hooks'));
                }
                add_action( 'br-filters/addon/add-table/destroy', array($this, 'destroy_table') );
                $status = 'generating';
            } else {
                $status = 'ready';
                // Completion feedback is now transient in the polling notice;
                // remove persistent notices left by previous plugin versions.
                $this->remove_success_notice();
                if(is_admin()) {
                    if( ! empty($create_position) ) {
                        add_action( 'br-filters/addon/add-table/destroy', array($this, 'destroy_table') );
                    }
                }
            }
        }
        do_action('BeRocket_aapf_variations_tables_addon_status', $status, $create_position, $this);
    }
    function is_table_exist() {
        $create_position = $this->get_current_create_position();
        if( strpos((string)$create_position, 'ended') !== false ) {
            if( ! $this->required_tables_exist() ) {
                $this->request_regeneration('required_table_missing');
            } elseif( ! $this->generated_table_schema_is_valid()
                || ! apply_filters('braapf_additional_tables_schema_is_valid', true, $this) ) {
                $this->request_regeneration('generated_table_schema_changed');
            }
        }
    }
    protected function get_required_table_names() {
        global $wpdb;
        $tables = apply_filters('BeRocket_aapf_variations_tables_addon_check_table_list', array(
            'braapf_product_stock_status_parent',
            'braapf_product_variation_attributes',
            'braapf_variable_attributes',
            'braapf_term_taxonomy_hierarchical'
        ));
        return array_map(function($table) use ($wpdb) {
            return $wpdb->prefix . $table;
        }, array_unique($tables));
    }
    protected function required_tables_exist() {
        global $wpdb;
        $existing_tables = $wpdb->get_col('SHOW TABLES');
        if( ! is_array($existing_tables) ) {
            return false;
        }
        foreach($this->get_required_table_names() as $table_name) {
            if( ! in_array($table_name, $existing_tables, true) ) {
                return false;
            }
        }
        return true;
    }
    protected function generated_table_schema_is_valid() {
        global $wpdb;
        $schemas = array(
            $wpdb->prefix . 'braapf_term_taxonomy_hierarchical' => array(
                'columns' => array('term_taxonomy_id', 'term_id', 'term_taxonomy_child_id', 'term_child_id', 'taxonomy'),
                'indexes' => array(
                    'PRIMARY' => array('term_taxonomy_id', 'term_id', 'term_taxonomy_child_id', 'term_child_id'),
                    'child_parent_id' => array('term_taxonomy_id', 'term_taxonomy_child_id'),
                ),
            ),
            $wpdb->prefix . 'braapf_product_stock_status_parent' => array(
                'columns' => array('post_id', 'parent_id', 'stock_status'),
                'indexes' => array(
                    'PRIMARY' => array('post_id'),
                    'parent_stock_status' => array('parent_id', 'stock_status'),
                ),
            ),
            $wpdb->prefix . 'braapf_variable_attributes' => array(
                'columns' => array('post_id', 'attribute'),
                'indexes' => array(
                    'PRIMARY' => array('post_id', 'attribute'),
                    'attribute' => array('attribute'),
                ),
            ),
            $wpdb->prefix . 'braapf_product_variation_attributes' => array(
                'columns' => array('post_id', 'parent_id', 'meta_key', 'meta_value_id', 'stock_status'),
                'indexes' => array(
                    'PRIMARY' => array('post_id', 'parent_id', 'meta_key', 'meta_value_id'),
                    'meta_key' => array('meta_key'),
                    'parent_meta_value' => array('parent_id', 'meta_value_id'),
                    'meta_value_post' => array('meta_value_id', 'post_id'),
                ),
            ),
        );
        foreach($schemas as $table_name => $schema) {
            if( ! $this->table_schema_is_valid($table_name, $schema['columns'], $schema['indexes']) ) {
                return false;
            }
        }
        return true;
    }
    function get_charset_collate() {
        global $wpdb;

        $table_status = $wpdb->get_row(
            $wpdb->prepare(
                'SHOW TABLE STATUS WHERE Name = %s',
                $wpdb->posts
            )
        );
        if( ! empty($table_status->Collation)
            && preg_match('/^[a-zA-Z0-9_]+$/', $table_status->Collation) ) {
            return 'COLLATE ' . $table_status->Collation;
        }

        return $wpdb->has_cap('collation') ? $wpdb->get_charset_collate() : '';
    }
    function cron() {
        return $this->run_generation_worker();
    }
    function init_activate() {
        $this->schedule_generation_worker();
    }
    function get_addon_data() {
        $data = parent::get_addon_data();
        return array_merge($data, array(
            'addon_name'    => __('Additional Tables', 'BeRocket_AJAX_domain'),
            'tooltip'       => __('Create 4 additional tables.<ul><li>Table to speed up hierarchical taxonomies recount: 
									<strong>Product categories</strong>, <strong>Brands</strong> etc</li><li>3 tables to 
									speed up functions for variation filtering</li></ul>', 'BeRocket_AJAX_domain'),
            'image'         => 'https://berocket.ams3.cdn.digitaloceanspaces.com/plugins/addons/filters/filters_c_tables.jpg',
            'image_class'   => 'c_tables',
        ));
    }
    function check_init() {
        $state = $this->get_generation_state();
        // This method runs from the framework constructor, before Action
        // Scheduler is guaranteed to have initialized. WooCommerce maintains
        // this option for the full lookup-table rebuild lifecycle, so reading
        // it here avoids bootstrapping the scheduler too early.
        $wc_lookup_running = (bool)get_option('woocommerce_product_lookup_table_is_generating', false);
        if( ! empty($state['tables_ready'])
            && $state['status'] === 'complete'
            && intval($state['table_schema_version']) === self::TABLE_SCHEMA_VERSION
            && ! $wc_lookup_running ) {
            if( empty($state['completion_published_at']) ) {
                $state = $this->reconcile_generation_completion($state);
            }
            if( ! empty($state['completion_published_at']) ) {
                parent::check_init();
            }
        }
    }
    protected function get_generation_state_defaults() {
        return array(
            'schema_version' => self::STATE_SCHEMA_VERSION,
            'revision'       => 0,
            'generation_id'  => '',
            'plan_hash'      => '',
            'table_schema_version' => 0,
            'status'         => 'queued',
            'tables_ready'   => false,
            'position'       => 1,
            'data'           => array(
                'status' => 0,
                'run'    => false,
            ),
            'progress'       => 0,
            'retry_count'    => 0,
            'last_error_code'=> '',
            'started_at'     => 0,
            'updated_at'     => 0,
            'completed_at'   => 0,
            'completion_published_at' => 0,
        );
    }
    protected function create_generation_id() {
        if( function_exists('wp_generate_uuid4') ) {
            return wp_generate_uuid4();
        }
        return uniqid('brapf-', true);
    }
    public function get_generation_state() {
        $state = get_option(self::STATE_OPTION, false);
        if( is_array($state) && ! empty($state['generation_id']) ) {
            $state = array_merge($this->get_generation_state_defaults(), $state);
            if( intval($state['schema_version']) > self::STATE_SCHEMA_VERSION ) {
                // A plugin downgrade must not rewrite or execute a state schema
                // it does not understand.
                $state['status'] = 'failed';
                $state['tables_ready'] = false;
                $state['last_error_code'] = 'state_schema_version_unsupported';
            }
            return $state;
        }

        $legacy_position = get_option('BeRocket_aapf_additional_tables_addon_position', false);
        $legacy_data = get_option('BeRocket_aapf_additional_tables_addon_position_data', false);
        if( empty($legacy_position) ) {
            $legacy_position = 1;
        }
        if( ! is_array($legacy_data) ) {
            $legacy_data = array('status' => 0, 'run' => false);
        }
        $legacy_tokens = preg_split('/\s+/', trim((string)$legacy_position), -1, PREG_SPLIT_NO_EMPTY);
        $is_complete = in_array('ended', $legacy_tokens, true);
        $state = array_merge($this->get_generation_state_defaults(), array(
            'revision'       => 1,
            'generation_id'  => $this->create_generation_id(),
            'status'         => $is_complete ? 'complete' : 'queued',
            'tables_ready'   => $is_complete,
            'position'       => $legacy_position,
            'data'           => $legacy_data,
            'progress'       => $is_complete ? 100 : 0,
            'updated_at'     => time(),
            'completed_at'   => $is_complete ? time() : 0,
        ));
        if( ! add_option(self::STATE_OPTION, $state, '', false) ) {
            $saved_state = get_option(self::STATE_OPTION, false);
            if( is_array($saved_state) && ! empty($saved_state['generation_id']) ) {
                return array_merge($this->get_generation_state_defaults(), $saved_state);
            }
            update_option(self::STATE_OPTION, $state, false);
        }
        return $state;
    }
    protected function initialize_generation_plan_hash($plan_hash) {
        for($attempt = 0; $attempt < 5; $attempt++) {
            $current = $this->get_generation_state_direct();
            if( ! is_array($current) ) {
                return $this->get_generation_state();
            }
            $state = $current['state'];
            if( ! empty($state['plan_hash'])
                || intval($state['schema_version']) > self::STATE_SCHEMA_VERSION ) {
                return $state;
            }
            $state['plan_hash'] = (string)$plan_hash;
            $saved = $this->compare_and_swap_generation_state(
                $state['generation_id'],
                $state['revision'],
                $state,
                false
            );
            if( is_array($saved) ) {
                return $saved;
            }
        }
        wp_cache_delete(self::STATE_OPTION, 'options');
        return $this->get_generation_state();
    }
    protected function save_generation_state($state, $mirror_legacy = false) {
        $current_state = $this->get_generation_state();
        $state = array_merge($this->get_generation_state_defaults(), $state);
        $state['schema_version'] = self::STATE_SCHEMA_VERSION;
        $state['revision'] = max(intval($current_state['revision']), intval($state['revision'])) + 1;
        $state['updated_at'] = time();
        update_option(self::STATE_OPTION, $state, false);
        if( $mirror_legacy ) {
            update_option('BeRocket_aapf_additional_tables_addon_position', $state['position'], false);
            update_option('BeRocket_aapf_additional_tables_addon_position_data', $state['data'], false);
        }
        return $state;
    }
    protected function get_generation_state_direct() {
        global $wpdb;
        $raw_state = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            self::STATE_OPTION
        ));
        if( $raw_state === null ) {
            return false;
        }
        $state = maybe_unserialize($raw_state);
        if( ! is_array($state) || empty($state['generation_id']) ) {
            return false;
        }
        return array(
            'raw'   => (string)$raw_state,
            'state' => array_merge($this->get_generation_state_defaults(), $state),
        );
    }
    protected function compare_and_swap_generation_state($expected_generation_id, $expected_revision, $state, $mirror_legacy = false) {
        global $wpdb;
        $current = $this->get_generation_state_direct();
        if( ! is_array($current)
            || $current['state']['generation_id'] !== (string)$expected_generation_id
            || intval($current['state']['revision']) !== intval($expected_revision) ) {
            return false;
        }
        $state = array_merge($this->get_generation_state_defaults(), $state);
        $state['schema_version'] = self::STATE_SCHEMA_VERSION;
        $state['revision'] = intval($current['state']['revision']) + 1;
        $state['updated_at'] = time();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
            maybe_serialize($state),
            self::STATE_OPTION,
            $current['raw']
        ));
        if( intval($updated) !== 1 ) {
            return false;
        }
        wp_cache_delete(self::STATE_OPTION, 'options');
        if( $mirror_legacy ) {
            update_option('BeRocket_aapf_additional_tables_addon_position', $state['position'], false);
            update_option('BeRocket_aapf_additional_tables_addon_position_data', $state['data'], false);
        }
        return $state;
    }
    protected function start_generation_context($state) {
        $this->generation_context = $state;
        $this->generation_context_revision = intval($state['revision']);
    }
    protected function discard_generation_context() {
        $this->generation_context = false;
        $this->generation_context_revision = 0;
    }
    protected function commit_generation_context() {
        if( ! is_array($this->generation_context) || ! $this->renew_worker_lock($this->worker_lock_token) ) {
            $this->discard_generation_context();
            return false;
        }
        $calculated_progress = $this->calculate_current_global_status(
            $this->generation_context['position'],
            $this->generation_context['data']
        );
        $this->generation_context['progress'] = max(
            intval($this->generation_context['progress']),
            min(99, $calculated_progress)
        );
        $state = $this->compare_and_swap_generation_state(
            $this->generation_context['generation_id'],
            $this->generation_context_revision,
            $this->generation_context,
            true
        );
        $this->discard_generation_context();
        return $state;
    }
    function get_current_create_position() {
        if( is_array($this->generation_context) ) {
            $current_position = $this->generation_context['position'];
        } else {
            $state = $this->get_generation_state();
            $current_position = $state['position'];
        }
        if( empty($current_position) ) {
            $current_position = 1;
        }
        return $current_position;
    }
    function set_current_create_position($position) {
        if( is_array($this->generation_context) ) {
            $this->generation_context['position'] = $position;
            return;
        }
        $state = $this->get_generation_state();
        $state['position'] = $position;
        $this->save_generation_state($state, true);
    }
    function increment_create_position() {
        $current_position = $this->get_current_create_position();
        $this->set_current_create_position($current_position+1);
    }
    function get_current_create_position_data() {
        if( is_array($this->generation_context) ) {
            return $this->generation_context['data'];
        }
        $state = $this->get_generation_state();
        return $state['data'];
    }
    function set_current_create_position_data($data) {
        if( ! is_array($data) ) {
            $data = array();
        }
        if( is_array($this->generation_context) ) {
            $this->generation_context['data'] = $data;
            return;
        }
        $state = $this->get_generation_state();
        $state['data'] = $data;
        $this->save_generation_state($state, true);
    }
    function activate($current_position = -1, $brajax = false) {
        if( $this->product_lookup_tables_is_running() ) {
            return;
        }
        if( $current_position == -1 ) {
            $current_position = $this->get_current_create_position();
        }
        if( ! empty($this->position_data[$current_position]) ) {
            if( empty($this->position_data[$current_position]['ajax_only']) || $brajax ) {
                call_user_func($this->position_data[$current_position]['execute'], $current_position, $this);
            }
        } else {
            $numeric_positions = array_filter(array_keys($this->position_data), 'is_numeric');
            $terminal_position = empty($numeric_positions) ? 1 : max($numeric_positions) + 1;
            if( $current_position === 'final' || intval($current_position) === intval($terminal_position) ) {
                $this->table_generation_end();
            } else {
                throw new RuntimeException('invalid_generation_position');
            }
        }
    }
    protected function get_database_timestamp() {
        global $wpdb;
        $timestamp = $wpdb->get_var('SELECT UNIX_TIMESTAMP()');
        return $timestamp === null ? time() : intval($timestamp);
    }
    protected function acquire_worker_lock($generation_id, $ttl = 600) {
        global $wpdb;
        $now = $this->get_database_timestamp();
        $token = $this->create_generation_id();
        $lock = array(
            'token'         => $token,
            'generation_id' => (string)$generation_id,
            'expires_at'    => $now + max(120, intval($ttl)),
        );
        if( ! add_option(self::LOCK_OPTION, $lock, '', false) ) {
            $raw_lock = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                self::LOCK_OPTION
            ));
            $current_lock = maybe_unserialize($raw_lock);
            if( is_array($current_lock) && ! empty($current_lock['expires_at']) && intval($current_lock['expires_at']) > $now ) {
                return false;
            }
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
                maybe_serialize($lock),
                self::LOCK_OPTION,
                (string)$raw_lock
            ));
            if( intval($updated) !== 1 ) {
                return false;
            }
            wp_cache_delete(self::LOCK_OPTION, 'options');
        }
        $this->worker_lock_token = $token;
        $this->worker_lock_generation_id = (string)$generation_id;
        register_shutdown_function(array($this, 'release_worker_lock'), $token);
        return $token;
    }
    protected function get_worker_lock_direct() {
        global $wpdb;
        $raw_lock = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            self::LOCK_OPTION
        ));
        if( $raw_lock === null ) {
            return false;
        }
        $lock = maybe_unserialize($raw_lock);
        if( ! is_array($lock) ) {
            return false;
        }
        return array(
            'raw'  => (string)$raw_lock,
            'data' => $lock,
        );
    }
    protected function is_worker_lock_owner($token) {
        if( empty($token) ) {
            return false;
        }
        $lock = $this->get_worker_lock_direct();
        return is_array($lock)
            && ! empty($lock['data']['token'])
            && hash_equals((string)$lock['data']['token'], (string)$token);
    }
    protected function renew_worker_lock($token, $ttl = 600) {
        global $wpdb;
        $lock = $this->get_worker_lock_direct();
        if( ! is_array($lock) || empty($lock['data']['token'])
            || ! hash_equals((string)$lock['data']['token'], (string)$token)
            || ( ! empty($this->worker_lock_generation_id)
                && ( ! isset($lock['data']['generation_id'])
                    || (string)$lock['data']['generation_id'] !== (string)$this->worker_lock_generation_id ) ) ) {
            return false;
        }
        $lock_data = $lock['data'];
        $lock_data['expires_at'] = max(
            intval(isset($lock_data['expires_at']) ? $lock_data['expires_at'] : 0) + 1,
            $this->get_database_timestamp() + max(120, intval($ttl))
        );
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
            maybe_serialize($lock_data),
            self::LOCK_OPTION,
            $lock['raw']
        ));
        if( intval($updated) === 1 ) {
            wp_cache_delete(self::LOCK_OPTION, 'options');
            return true;
        }
        return false;
    }
    public function release_worker_lock($token = false) {
        if( empty($token) ) {
            $token = $this->worker_lock_token;
        }
        if( ! empty($token) ) {
            global $wpdb;
            $lock = $this->get_worker_lock_direct();
            if( is_array($lock) && ! empty($lock['data']['token'])
                && hash_equals((string)$lock['data']['token'], (string)$token) ) {
                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s",
                    self::LOCK_OPTION,
                    $lock['raw']
                ));
                if( intval($deleted) === 1 ) {
                    wp_cache_delete(self::LOCK_OPTION, 'options');
                }
            }
        }
        if( $this->worker_lock_token === $token ) {
            $this->worker_lock_token = false;
            $this->worker_lock_generation_id = false;
        }
    }
    protected function action_scheduler_ready() {
        if( ! function_exists('as_enqueue_async_action') && ! function_exists('as_schedule_single_action') ) {
            return false;
        }
        if( class_exists('ActionScheduler', false) && is_callable(array('ActionScheduler', 'is_initialized')) ) {
            return ActionScheduler::is_initialized();
        }
        return did_action('init') > 0;
    }
    protected function action_scheduler_supports_unique($function_name) {
        if( ! function_exists($function_name) ) {
            return false;
        }
        try {
            $reflection = new ReflectionFunction($function_name);
            $required_parameters = $function_name === 'as_schedule_single_action' ? 5 : 4;
            return $reflection->getNumberOfParameters() >= $required_parameters;
        } catch( ReflectionException $error ) {
            return false;
        }
    }
    protected function get_worker_group($generation_id) {
        return self::WORKER_GROUP . '-' . substr(hash('sha256', (string)$generation_id), 0, 20);
    }
    protected function additional_tables_addon_is_enabled() {
        global $wpdb;
        $raw_options = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            'br_filters_options'
        ));
        $options = maybe_unserialize($raw_options);
        if( is_array($options) && array_key_exists('addons', $options) ) {
            $enabled = is_array($options['addons'])
                && in_array($this->addon_file, $options['addons'], true);
            return (bool)apply_filters('brapf_additional_tables_persisted_enabled', $enabled, $this);
        }
        return $this->additional_tables_addon_is_enabled_for_request();
    }
    protected function additional_tables_addon_is_enabled_for_request() {
        $active_addons = apply_filters('berocket_addons_active_'.$this->plugin_name, array());
        return in_array($this->addon_file, $active_addons, true);
    }
    protected function get_cancel_request_direct() {
        global $wpdb;
        $raw_cancel = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            self::CANCEL_OPTION
        ));
        if( $raw_cancel === null ) {
            return false;
        }
        return array(
            'raw'  => (string)$raw_cancel,
            'data' => maybe_unserialize($raw_cancel),
        );
    }
    protected function clear_cancel_request($cancel_request) {
        if( ! is_array($cancel_request) || ! array_key_exists('raw', $cancel_request) ) {
            return false;
        }
        global $wpdb;
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s",
            self::CANCEL_OPTION,
            $cancel_request['raw']
        ));
        if( intval($deleted) === 1 ) {
            wp_cache_delete(self::CANCEL_OPTION, 'options');
            return true;
        }
        return false;
    }
    public function schedule_generation_worker($delay = 0, $generation_id = false, $successor = false) {
        $state = $this->get_generation_state();
        if( empty($generation_id) ) {
            $generation_id = $state['generation_id'];
        }
        if( $state['generation_id'] !== $generation_id || in_array($state['status'], array('complete', 'failed'), true) ) {
            return false;
        }
        $delay = max(0, intval($delay));
        $args = array($generation_id);
        $group = $this->get_worker_group($generation_id);

        if( $this->action_scheduler_ready() ) {
            try {
                if( ! $successor && function_exists('as_has_scheduled_action')
                    && as_has_scheduled_action(self::WORKER_HOOK, $args, $group) ) {
                    return true;
                }
                if( $delay === 0 && function_exists('as_enqueue_async_action') ) {
                    if( ! $successor && $this->action_scheduler_supports_unique('as_enqueue_async_action') ) {
                        $action_id = as_enqueue_async_action(self::WORKER_HOOK, $args, $group, true);
                    } else {
                        $action_id = as_enqueue_async_action(self::WORKER_HOOK, $args, $group);
                    }
                } elseif( function_exists('as_schedule_single_action') ) {
                    $timestamp = time() + max(1, $delay);
                    if( ! $successor && $this->action_scheduler_supports_unique('as_schedule_single_action') ) {
                        $action_id = as_schedule_single_action($timestamp, self::WORKER_HOOK, $args, $group, true);
                    } else {
                        $action_id = as_schedule_single_action($timestamp, self::WORKER_HOOK, $args, $group);
                    }
                } else {
                    $action_id = 0;
                }
                if( ! empty($action_id) || ( ! $successor && function_exists('as_has_scheduled_action')
                    && as_has_scheduled_action(self::WORKER_HOOK, $args, $group)) ) {
                    return true;
                }
            } catch( Throwable $error ) {
                error_log('BeRocket Additional Tables scheduler error: ' . $error->getMessage());
            }
        }

        if( ! $successor && wp_next_scheduled(self::WORKER_HOOK, $args) !== false ) {
            return true;
        }
        return (bool)wp_schedule_single_event(time() + max(1, $delay), self::WORKER_HOOK, $args);
    }
    protected function unschedule_generation_workers($generation_id = false) {
        if( empty($generation_id) ) {
            $state = $this->get_generation_state();
            $generation_id = $state['generation_id'];
        }
        $args = array($generation_id);
        if( $this->action_scheduler_ready() && function_exists('as_unschedule_all_actions') ) {
            as_unschedule_all_actions(self::WORKER_HOOK, $args, $this->get_worker_group($generation_id));
        }
        wp_clear_scheduled_hook(self::WORKER_HOOK, $args);
        wp_clear_scheduled_hook('braapf_additional_table_cron');
    }
    protected function get_generation_plan_hash() {
        $plan = array();
        foreach($this->position_data as $position => $position_data) {
            $callback = isset($position_data['execute']) ? $position_data['execute'] : '';
            if( is_array($callback) ) {
                $callback = array(
                    is_object($callback[0]) ? get_class($callback[0]) : (string)$callback[0],
                    isset($callback[1]) ? (string)$callback[1] : '',
                );
            }
            $plan[(string)$position] = array(
                'percentage' => isset($position_data['percentage']) ? floatval($position_data['percentage']) : 0,
                'ajax_only'  => ! empty($position_data['ajax_only']),
                'callback'   => $callback,
            );
        }
        $plan['end'] = array_values(array_unique(preg_split('/\s+/', trim((string)$this->get_end_position()), -1, PREG_SPLIT_NO_EMPTY)));
        $plan['table_schema_version'] = self::TABLE_SCHEMA_VERSION;
        return hash('sha256', wp_json_encode($plan));
    }
    protected function remove_success_notice() {
        $notices = get_option('berocket_information_notices', array());
        $notice_name = $this->plugin_name . '_additional_table_status_end';
        if( is_array($notices) && isset($notices[$notice_name]) ) {
            unset($notices[$notice_name]);
            update_option('berocket_information_notices', $notices, false);
        }
    }
    protected function reset_generation_state_locked($reason = 'manual') {
        $old_state = $this->get_generation_state();
        // Clear only the rerun request consumed by this reset before
        // publishing the new generation. Cancellation represents a separate
        // disable operation and must survive generation transitions.
        delete_option(self::RERUN_OPTION);
        $this->unschedule_generation_workers($old_state['generation_id']);
        $state = array_merge($this->get_generation_state_defaults(), array(
            'revision'        => intval($old_state['revision']),
            'generation_id'   => $this->create_generation_id(),
            'plan_hash'       => $this->get_generation_plan_hash(),
            'status'          => 'queued',
            'tables_ready'    => false,
            'position'        => 1,
            'data'            => array('status' => 0, 'run' => false),
            'progress'        => 0,
            'last_error_code' => '',
            'started_at'      => time(),
            'updated_at'      => time(),
        ));
        $state['data']['reset_reason'] = sanitize_key($reason);
        // Hide the previous success notice before exposing the new queued
        // generation, so an admin request cannot render both states together.
        $this->remove_success_notice();
        $state = $this->save_generation_state($state, true);
        return $state;
    }
    public function request_regeneration($reason = 'manual') {
        $reason = sanitize_key($reason);
        $state = $this->get_generation_state();
        if( $reason === 'manual' && in_array($state['status'], array('queued', 'waiting_dependency', 'running'), true) ) {
            $this->schedule_generation_worker(0, $state['generation_id']);
            return $state;
        }
        if( ! empty($this->worker_lock_token) && $this->is_worker_lock_owner($this->worker_lock_token) ) {
            return $this->reset_generation_state_locked($reason);
        }
        $lock = $this->acquire_worker_lock($state['generation_id']);
        if( empty($lock) ) {
            update_option(self::RERUN_OPTION, array(
                'reason'       => $reason,
                'requested_at' => time(),
            ), false);
            return false;
        }
        try {
            $state = $this->reset_generation_state_locked($reason);
        } finally {
            $this->release_worker_lock($lock);
        }
        $this->schedule_generation_worker(0, $state['generation_id']);
        return $state;
    }
    public function run_generation_worker($scheduled_generation_id = false) {
        if( ! $this->additional_tables_active ) {
            $active_addons = apply_filters('berocket_addons_active_'.$this->plugin_name, array());
            if( ! in_array($this->addon_file, $active_addons, true) ) {
                return false;
            }
            $this->additional_tables_active = true;
            $this->position_data = apply_filters('BeRocket_aapf_variations_tables_addon_position_data', $this->position_data, $this);
        }
        $state = $this->get_generation_state();
        $worker_generation_id = (string)$state['generation_id'];
        if( ! empty($scheduled_generation_id) && $scheduled_generation_id !== $state['generation_id'] ) {
            return false;
        }
        if( in_array($state['status'], array('complete', 'failed'), true) ) {
            return false;
        }
        $lock = $this->acquire_worker_lock($state['generation_id']);
        if( empty($lock) ) {
            return false;
        }
        $schedule_next = false;
        $next_delay = 1;
        try {
            wp_cache_delete(self::STATE_OPTION, 'options');
            $state = $this->get_generation_state();
            if( ! empty($scheduled_generation_id) && $scheduled_generation_id !== $state['generation_id'] ) {
                return false;
            }
            if( in_array($state['status'], array('complete', 'failed'), true) ) {
                return false;
            }
            if( ! $this->renew_worker_lock($lock) ) {
                return false;
            }
            $cancel_request = $this->get_cancel_request_direct();
            if( $cancel_request ) {
                if( ! $this->additional_tables_addon_is_enabled() ) {
                    return false;
                }
                // A matching request belongs to a disable that was reverted;
                // a mismatched request belongs to an older generation.
                $this->clear_cancel_request($cancel_request);
            }
            $rerun = get_option(self::RERUN_OPTION, false);
            if( is_array($rerun) && ! empty($rerun['reason']) ) {
                $state = $this->reset_generation_state_locked($rerun['reason']);
                $schedule_next = true;
                return true;
            }
            if( $this->product_lookup_tables_is_running() ) {
                if( ! is_array($state['data']) ) {
                    $state['data'] = array();
                }
                $state['data']['dependency_dirty'] = true;
                if( $state['status'] !== 'waiting_dependency' ) {
                    $state['status'] = 'waiting_dependency';
                }
                $this->save_generation_state($state, true);
                $schedule_next = true;
                $next_delay = 30;
                return true;
            }
            if( $state['status'] === 'waiting_dependency' && ! empty($state['data']['dependency_dirty']) ) {
                $state = $this->reset_generation_state_locked('woocommerce_lookup_completed');
                $worker_generation_id = (string)$state['generation_id'];
                $schedule_next = true;
                return true;
            }

            $state['status'] = 'running';
            $state['started_at'] = empty($state['started_at']) ? time() : $state['started_at'];
            $state['last_error_code'] = '';
            if( ! is_array($state['data']) ) {
                $state['data'] = array();
            }
            // A previous PHP process may have stopped after setting the legacy
            // marker. The atomic worker lease is now the only execution lock.
            $state['data']['run'] = false;
            $state = $this->save_generation_state($state, true);
            $this->start_generation_context($state);
            if( ! $this->renew_worker_lock($lock) ) {
                $this->discard_generation_context();
                return false;
            }
            $checkpoint_before = wp_json_encode(array(
                'position' => $this->generation_context['position'],
                'data'     => $this->generation_context['data'],
            ));
            $this->activate(-1, true);
            $checkpoint_after = wp_json_encode(array(
                'position' => $this->generation_context['position'],
                'data'     => $this->generation_context['data'],
            ));
            if( $checkpoint_before === $checkpoint_after ) {
                throw new RuntimeException('generation_stage_made_no_progress');
            }
            // Retry count represents consecutive failures of the current
            // checkpoint, not the total number of intermittent errors in a
            // long-running catalogue build.
            $this->generation_context['retry_count'] = 0;
            $state = $this->commit_generation_context();
            if( $state === false ) {
                return false;
            }
            $rerun = get_option(self::RERUN_OPTION, false);
            if( is_array($rerun) && ! empty($rerun['reason']) ) {
                $state = $this->reset_generation_state_locked($rerun['reason']);
                $schedule_next = true;
                return true;
            }
            if( $state['status'] === 'complete' ) {
                try {
                    $published_state = $this->publish_generation_completion_state($state);
                    if( is_array($published_state) ) {
                        $state = $published_state;
                    }
                } catch( Throwable $publish_error ) {
                    error_log('BeRocket Additional Tables completion notice error: ' . $publish_error->getMessage());
                }
                $this->unschedule_generation_workers($state['generation_id']);
                return true;
            }
            $schedule_next = true;
        } catch( Throwable $error ) {
            $this->discard_generation_context();
            error_log('BeRocket Additional Tables generation error: ' . $error->getMessage());
            if( ! $this->is_worker_lock_owner($lock) ) {
                return false;
            }
            $current = $this->get_generation_state_direct();
            if( ! is_array($current) || $current['state']['generation_id'] !== $worker_generation_id ) {
                return false;
            }
            $state = $current['state'];
            $state['retry_count'] = intval($state['retry_count']) + 1;
            $state['last_error_code'] = sanitize_key($error->getMessage());
            if( $state['retry_count'] >= 3 ) {
                $state['status'] = 'failed';
            } else {
                $state['status'] = 'queued';
                $schedule_next = true;
                $next_delay = min(60, (int)pow(2, $state['retry_count']));
            }
            $state = $this->compare_and_swap_generation_state(
                $worker_generation_id,
                $current['state']['revision'],
                $state,
                true
            );
            if( $state === false ) {
                $schedule_next = false;
                return false;
            }
        } finally {
            $this->release_worker_lock($lock);
            $pending_rerun = false;
            $cancel_request = $this->get_cancel_request_direct();
            $addon_enabled_for_request = $this->additional_tables_addon_is_enabled_for_request();
            if( $cancel_request ) {
                $latest = $this->get_generation_state_direct();
                $latest_generation_id = is_array($latest) ? $latest['state']['generation_id'] : '';
                if( ! $this->additional_tables_addon_is_enabled() && ! empty($latest_generation_id) ) {
                    $schedule_next = false;
                    $this->deactivate($latest_generation_id);
                } else {
                    $this->clear_cancel_request($cancel_request);
                }
            }
            if( $addon_enabled_for_request ) {
                wp_cache_delete(self::RERUN_OPTION, 'options');
                $pending_rerun = get_option(self::RERUN_OPTION, false);
            } else {
                $schedule_next = false;
            }
            if( is_array($pending_rerun) && ! empty($pending_rerun['reason']) ) {
                $schedule_next = false;
                $this->request_regeneration($pending_rerun['reason']);
            } elseif( $schedule_next ) {
                $latest_state = $this->get_generation_state();
                $this->schedule_generation_worker($next_delay, $latest_state['generation_id'], true);
            }
        }
        return true;
    }
    public function wc_lookup_table_column_updated($column) {
        if( $column === 'tax_status' && $this->additional_tables_active ) {
            $this->request_regeneration('woocommerce_lookup_completed');
        }
    }
    public function wc_lookup_generation_option_deleted($option_name) {
        if( $option_name === 'woocommerce_product_lookup_table_is_generating' && $this->additional_tables_active ) {
            $this->request_regeneration('woocommerce_lookup_completed');
        }
    }
    function get_end_position() {
        return apply_filters('braapf_additional_table_ended_position', 'ended');
    }
    function is_valid_end_position($create_position, $expected_end_position = false) {
        if( $expected_end_position === false ) {
            $expected_end_position = $this->get_end_position();
        }
        $create_tokens = preg_split('/\s+/', trim((string)$create_position), -1, PREG_SPLIT_NO_EMPTY);
        $expected_tokens = preg_split('/\s+/', trim((string)$expected_end_position), -1, PREG_SPLIT_NO_EMPTY);
        // Add-ons may register their end-position token after this add-on has
        // initialized. A stored state can therefore contain additional valid
        // tokens (and older versions could duplicate them). Every token known
        // in the current request must be present; token order/count is irrelevant.
        foreach(array_unique($expected_tokens) as $token) {
            if( ! in_array($token, $create_tokens, true) ) {
                return false;
            }
        }
        return in_array('ended', $create_tokens, true);
    }
    function table_generation_end() {
        do_action('braapf_additional_tables_before_validation', $this);
        $addons_valid = apply_filters('braapf_additional_tables_generation_is_valid', true, $this);
        if( ! $addons_valid || ! $this->required_tables_exist() || ! $this->generated_table_schema_is_valid() ) {
            throw new RuntimeException('generated_tables_validation_failed');
        }
        $this->set_current_create_position($this->get_end_position());
        if( is_array($this->generation_context) ) {
            $this->generation_context['status'] = 'complete';
            $this->generation_context['tables_ready'] = true;
            $this->generation_context['progress'] = 100;
            $this->generation_context['retry_count'] = 0;
            $this->generation_context['last_error_code'] = '';
            $this->generation_context['completed_at'] = time();
            $this->generation_context['table_schema_version'] = self::TABLE_SCHEMA_VERSION;
        }
    }
    protected function publish_generation_completion() {
        global $wpdb;
        for($attempt = 0; $attempt < 3; $attempt++) {
            $raw_options = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                'br_filters_options'
            ));
            $options = maybe_unserialize($raw_options);
            if( ! is_array($options) ) {
                return false;
            }
            $options['purge_cache_time'] = max(
                time(),
                intval(isset($options['purge_cache_time']) ? $options['purge_cache_time'] : 0) + 1
            );
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
                maybe_serialize($options),
                'br_filters_options',
                (string)$raw_options
            ));
            if( intval($updated) === 1 ) {
                wp_cache_delete('br_filters_options', 'options');
                wp_cache_delete('br_filters_options', 'berocket_framework_option');
                return true;
            }
        }
        error_log('BeRocket Additional Tables could not bump the filter cache generation after three concurrent updates.');
        return false;
    }
    protected function publish_generation_completion_state($state) {
        if( ! is_array($state) || $state['status'] !== 'complete'
            || empty($state['generation_id']) || ! empty($state['completion_published_at']) ) {
            return $state;
        }
        if( ! $this->publish_generation_completion() ) {
            return false;
        }
        $state['completion_published_at'] = time();
        return $this->compare_and_swap_generation_state(
            $state['generation_id'],
            $state['revision'],
            $state,
            true
        );
    }
    protected function reconcile_generation_completion($known_state = false) {
        $state = is_array($known_state) ? $known_state : $this->get_generation_state();
        if( $state['status'] !== 'complete' || ! empty($state['completion_published_at']) ) {
            return $state;
        }
        $lock = $this->acquire_worker_lock($state['generation_id']);
        if( empty($lock) ) {
            return $state;
        }
        try {
            $current = $this->get_generation_state_direct();
            if( ! is_array($current) || $current['state']['generation_id'] !== $state['generation_id']
                || $current['state']['status'] !== 'complete'
                || ! empty($current['state']['completion_published_at']) ) {
                return is_array($current) ? $current['state'] : $state;
            }
            $published = $this->publish_generation_completion_state($current['state']);
            return is_array($published) ? $published : $current['state'];
        } finally {
            $this->release_worker_lock($lock);
        }
    }
    protected function get_status_capability() {
        return apply_filters('brapf_additional_tables_status_capability', 'manage_woocommerce');
    }
    protected function get_manage_capability() {
        return apply_filters('brapf_additional_tables_manage_capability', 'manage_woocommerce');
    }
    function activate_hooks() {
        if( ! current_user_can($this->get_status_capability()) ) {
            return;
        }
        add_action( "admin_footer", array( $this, 'script_update' ) );
        add_filter('berocket_display_additional_notices', array($this, 'status_notice'));
    }
    function status_notice($notices) {
        if( ! current_user_can($this->get_status_capability()) ) {
            return $notices;
        }
        $state = $this->get_generation_state();
        if( $state['status'] === 'failed' ) {
            $text = __('Additional tables generation could not be completed. Please retry the generation or contact the site administrator.', 'BeRocket_AJAX_domain');
        } elseif( ! function_exists('wc_update_product_lookup_tables_is_running') ) {
            $text = __('WooCommerce do not have needed table for Additional Table add-on. Add-on required WooCommerce 3.6 or newer', 'BeRocket_AJAX_domain');
        } elseif( $state['status'] === 'waiting_dependency' || $this->product_lookup_tables_is_running() ) {
            $text = __('WooCommerce <strong>Product lookup tables</strong> right now regenerating', 'BeRocket_AJAX_domain');
        } else {
            $current_status = $this->get_current_global_status();
            $text = sprintf(__('Additional tables are generating. They will be used after generation is completed. Current status is <strong><span class="braapf_additional_table_status">%d</span>%s</strong>', 'BeRocket_AJAX_domain'), $current_status, '%');
            $current_position = $this->get_current_create_position();
            if( $current_position == 2 ) {
                $run_data = $this->get_current_create_position_data();
                if ( ! empty($run_data) && is_array($run_data) && isset($run_data['min_id']) && isset($run_data['max_id']) 
                    && ( intval($run_data['max_id']) - intval($run_data['min_id']) ) > 1000000 ) {
                    $url = admin_url('admin.php?page=wc-status&tab=tools');
                    global $wpdb;
                    $text .= '<p>' . __('Seems you have some issue with Product lookup tables. Please try to remove all data from table', 'BeRocket_AJAX_domain') . ' <strong>'.$wpdb->prefix.'wc_product_meta_lookup</strong> ' . __('and regenerate it in ', 'BeRocket_AJAX_domain'). '<a href="'.$url.'">WooCommerce -> Status -> Tools</a></p>';
                }
            }
        }
        $notices[] = array(
            'start'         => 0,
            'end'           => 0,
            'name'          => $this->plugin_name.'_additional_table_status',
            'html'          => '<strong>BeRocket AJAX Product Filters</strong> '.$text,
            'righthtml'     => '',
            'rightwidth'    => 0,
            'nothankswidth' => 0,
            'contentwidth'  => 1600,
            'subscribe'     => false,
            'priority'      => 10,
            'height'        => 70,
            'repeat'        => false,
            'repeatcount'   => 1,
            'image'         => array(
                'local'  => '',
                'width'  => 0,
                'height' => 0,
                'scale'  => 1,
            )
        );
        return $notices;
    }
    function script_update() {
        $nonce = wp_create_nonce('brapf_additional_tables_status');
        $authorization_message = wp_json_encode(__('Status polling stopped because your session or permissions changed. Reload this page to continue.', 'BeRocket_AJAX_domain'));
        echo '<script>
        (function($) {
            var $status = $(".braapf_additional_table_status");
            var authorizationMessage = ' . $authorization_message . ';
            if( ! $status.length ) {
                return;
            }
            function pollAdditionalTablesStatus() {
                $.post(ajaxurl, {
                    action: "braapf_additional_table_status",
                    nonce: "' . esc_js($nonce) . '"
                }).done(function(response) {
                    if( ! response || ! response.success || ! response.data ) {
                        return;
                    }
                    var progress = parseInt(response.data.progress, 10) || 0;
                    $status.text(progress);
                    if( response.data.status === "complete" || response.data.status === "failed" ) {
                        if( response.data.message ) {
                            var $notice = $status.closest(".berocket_admin_notice");
                            $notice.find(".berocket_notice_content_wrap > .berocket_notice_content").text(response.data.message);
                            if( response.data.status === "complete" ) {
                                window.setTimeout(function() { $notice.fadeOut(); }, 5000);
                            }
                        }
                        return;
                    }
                    window.setTimeout(pollAdditionalTablesStatus, 4000);
                }).fail(function(xhr) {
                    if( xhr && (xhr.status === 401 || xhr.status === 403) ) {
                        var $notice = $status.closest(".berocket_admin_notice");
                        $notice.find(".berocket_notice_content_wrap > .berocket_notice_content").text(authorizationMessage);
                        return;
                    }
                    window.setTimeout(pollAdditionalTablesStatus, 10000);
                });
            }
            window.setTimeout(pollAdditionalTablesStatus, 1000);
        })(jQuery);
        </script>';
    }
    function get_global_status_ajax() {
        if( ! check_ajax_referer('brapf_additional_tables_status', 'nonce', false)
            || ! current_user_can($this->get_status_capability()) ) {
            wp_send_json_error(array('message' => __('You do not have permission to view this status.', 'BeRocket_AJAX_domain')), 403);
        }
        $state = $this->get_generation_state();
        if( $state['status'] === 'complete' ) {
            $message = __('Additional tables were successfully generated. They will be used automatically when needed.', 'BeRocket_AJAX_domain');
        } elseif( $state['status'] === 'failed' ) {
            $message = __('Additional tables generation could not be completed. Please retry or contact the site administrator.', 'BeRocket_AJAX_domain');
        } else {
            $message = '';
        }
        wp_send_json_success(array(
            'status'     => $state['status'],
            'progress'   => $this->get_current_global_status(),
            'updated_at' => intval($state['updated_at']),
            'message'    => $message,
        ));
    }
    protected function calculate_current_global_status($current_position, $position_data) {
        if( strpos((string)$current_position, 'ended') !== false ) {
            return 100;
        }
        $position_status = br_get_value_from_array($position_data, 'status', 0);
        $global_status = 0;
        $global_status_full = 0;
        foreach($this->position_data as $position_i => $position_data_arr) {
            if( $position_i < $current_position ) {
                $global_status += $position_data_arr['percentage'];
            } elseif( $position_i == $current_position ) {
                $global_status += ( $position_data_arr['percentage'] / 100 * $position_status );
            }
            $global_status_full += $position_data_arr['percentage'];;
        }
        if( $global_status_full <= 0 ) {
            return 0;
        }
        $global_status = (100 / $global_status_full) * $global_status;
        $global_status = intval($global_status);
        return $global_status;
    }
    function get_current_global_status($current_position = -1) {
        $state = $this->get_generation_state();
        if( $state['status'] === 'complete' ) {
            return 100;
        }
        if( $current_position == -1 ) {
            $current_position = $state['position'];
        }
        $calculated = $this->calculate_current_global_status($current_position, $state['data']);
        return max(intval($state['progress']), min(99, $calculated));
    }
    function save_query_error($query, $error = false) {
        global $wpdb;
        if( $error === false ) {
            $error = $wpdb->last_error;
        }
        if( empty($error) ) {
            return false;
        }
        BeRocket_error_notices::add_plugin_error(1, 'Additional tables generation', array(
            'query' => $query,
            'error' => $error,
            'cron'  => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'DISABLED' : 'ENABLED')
        ));
        return true;
    }
    public function assert_query_success($result, $query, $error_code = 'database_query_failed') {
        global $wpdb;
        if( $result === false || ! empty($wpdb->last_error) ) {
            $this->save_query_error($query, $wpdb->last_error);
            throw new RuntimeException($error_code);
        }
        return $result;
    }
    protected function get_id_source_stats($table_name, $id_column) {
        global $wpdb;
        if( ! preg_match('/^[A-Za-z0-9_]+$/', $table_name) || ! preg_match('/^[A-Za-z0-9_]+$/', $id_column) ) {
            throw new RuntimeException('invalid_batch_source');
        }
        // MIN/MAX use the ID index and avoid an exact COUNT(*) scan of large
        // lookup/postmeta tables. Progress is cursor-based, so an exact row
        // count is not required for correctness.
        $sql = "SELECT MIN({$id_column}) AS min_id, MAX({$id_column}) AS max_id FROM {$table_name}";
        $wpdb->last_error = '';
        $stats = $wpdb->get_row($sql, ARRAY_A);
        $this->assert_query_success($stats, $sql, 'source_stats_query_failed');
        if( empty($stats) || $stats['min_id'] === null || $stats['max_id'] === null ) {
            return false;
        }
        $min_id = intval($stats['min_id']);
        $max_id = intval($stats['max_id']);
        return array(
            'min_id'     => $min_id,
            'max_id'     => $max_id,
            'total_rows' => max(1, $max_id - $min_id + 1),
        );
    }
    protected function get_id_batch($table_name, $id_column, $cursor, $max_id, $batch_size) {
        global $wpdb;
        if( ! preg_match('/^[A-Za-z0-9_]+$/', $table_name) || ! preg_match('/^[A-Za-z0-9_]+$/', $id_column) ) {
            throw new RuntimeException('invalid_batch_source');
        }
        $batch_size = max(1, intval($batch_size));
        $sql = $wpdb->prepare(
            "SELECT MIN(source_id) AS min_id, MAX(source_id) AS max_id, COUNT(*) AS row_count
             FROM (
                 SELECT {$id_column} AS source_id
                 FROM {$table_name}
                 WHERE {$id_column} >= %d AND {$id_column} <= %d
                 ORDER BY {$id_column} ASC
                 LIMIT %d
             ) AS brapf_source_batch",
            intval($cursor),
            intval($max_id),
            $batch_size
        );
        $wpdb->last_error = '';
        $batch = $wpdb->get_row($sql, ARRAY_A);
        $this->assert_query_success($batch, $sql, 'source_batch_query_failed');
        if( empty($batch) || empty($batch['row_count']) ) {
            return false;
        }
        return array(
            'min_id'    => intval($batch['min_id']),
            'max_id'    => intval($batch['max_id']),
            'row_count' => intval($batch['row_count']),
        );
    }
    protected function get_variation_attribute_key_map() {
        global $wpdb;
        $pattern = $wpdb->esc_like('attribute_pa_') . '%';
        $sql = $wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s ORDER BY meta_key",
            $pattern
        );
        $wpdb->last_error = '';
        $meta_keys = $wpdb->get_col($sql);
        $this->assert_query_success($meta_keys, $sql, 'attribute_keys_query_failed');
        $map = array();
        foreach((array)$meta_keys as $meta_key) {
            $decoded_meta_key = urldecode($meta_key);
            if( $meta_key !== $decoded_meta_key ) {
                $map[$meta_key] = str_replace('attribute_pa_', 'pa_', $decoded_meta_key);
            }
        }
        return $map;
    }
    protected function get_variation_stage_data() {
        global $wpdb;
        $pattern = $wpdb->esc_like('attribute_pa_') . '%';
        $sql = $wpdb->prepare(
            "SELECT MIN(meta_id) AS min_id, MAX(meta_id) AS max_id
             FROM {$wpdb->postmeta}
             WHERE meta_key LIKE %s",
            $pattern
        );
        $wpdb->last_error = '';
        $stats = $wpdb->get_row($sql, ARRAY_A);
        $this->assert_query_success($stats, $sql, 'variation_source_stats_failed');
        if( empty($stats) || $stats['min_id'] === null || $stats['max_id'] === null ) {
            return false;
        }
        $min_id = intval($stats['min_id']);
        $max_id = intval($stats['max_id']);
        $stats = array(
            'min_id' => $min_id,
            'max_id' => $max_id,
            'total_rows' => max(1, $max_id - $min_id + 1),
        );
        return array(
            'status'            => 0,
            'run'               => false,
            'start_id'          => $stats['min_id'],
            'min_id'            => $stats['min_id'],
            'max_id'            => $stats['max_id'],
            'processed_rows'    => 0,
            'total_rows'        => $stats['total_rows'],
            'attribute_key_map' => $this->get_variation_attribute_key_map(),
        );
    }
    protected function get_variation_id_batch($cursor, $max_id, $batch_size, $attribute_pattern) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT MIN(source_id) AS min_id, MAX(source_id) AS max_id, COUNT(*) AS row_count
             FROM (
                 SELECT meta_id AS source_id
                 FROM {$wpdb->postmeta}
                 WHERE meta_id >= %d AND meta_id <= %d AND meta_key LIKE %s
                 ORDER BY meta_id ASC
                 LIMIT %d
             ) AS brapf_variation_source_batch",
            intval($cursor),
            intval($max_id),
            $attribute_pattern,
            max(1, intval($batch_size))
        );
        $wpdb->last_error = '';
        $batch = $wpdb->get_row($sql, ARRAY_A);
        $this->assert_query_success($batch, $sql, 'variation_source_batch_failed');
        if( empty($batch) || empty($batch['row_count']) ) {
            return false;
        }
        return array(
            'min_id' => intval($batch['min_id']),
            'max_id' => intval($batch['max_id']),
            'row_count' => intval($batch['row_count']),
        );
    }
    protected function move_to_variation_or_variable_stage() {
        $variation_data = $this->get_variation_stage_data();
        if( $variation_data !== false ) {
            $this->set_current_create_position(3);
            $this->set_current_create_position_data($variation_data);
        } else {
            $this->set_current_create_position(4);
            $this->set_current_create_position_data(array(
                'status'    => 0,
                'run'       => false,
                'cursor_id' => 0,
            ));
        }
    }
    function create_all_tables() {
        $run_data = $this->get_current_create_position_data();
        if( ! empty($run_data) && ! empty($run_data['run']) ) {
            return false;
        }
        global $wpdb;
        $this->set_current_create_position_data(array(
            'status' => 0,
            'run' => true,
        ));
        $this->create_table_braapf_term_taxonomy_hierarchical();
        $this->create_table_braapf_product_stock_status_parent();
        $this->create_table_braapf_variable_attributes();
        $this->create_table_braapf_product_variation_attributes();

        $product_data = $this->get_id_source_stats($wpdb->wc_product_meta_lookup, 'product_id');
        if( $product_data !== false ) {
            $this->set_current_create_position(2);
            $this->set_current_create_position_data(array(
                'status' => 0,
                'run' => false,
                'start_id' => $product_data['min_id'],
                'min_id' => $product_data['min_id'],
                'max_id' => $product_data['max_id'],
                'processed_rows' => 0,
                'total_rows' => $product_data['total_rows'],
            ));
        } else {
            $variation_data = $this->get_variation_stage_data();
            if( $variation_data !== false ) {
                $this->set_current_create_position(3);
                $this->set_current_create_position_data($variation_data);
            } else {
                $this->set_current_create_position(4);
                $this->set_current_create_position_data(array(
                    'status' => 0,
                    'run' => false,
                    'cursor_id' => 0,
                ));
            }
        }
    }
    function reset_table($table_name) {
        global $wpdb;
        if( $this->table_exists($table_name) ) {
            $sql = "TRUNCATE TABLE {$table_name};";
            $this->assert_query_success($wpdb->query($sql), $sql, 'table_truncate_failed');
            return true;
        }
        return false;
    }
    public function table_exists($table_name) {
        global $wpdb;
        if( ! preg_match('/^[A-Za-z0-9_]+$/', $table_name) ) {
            throw new RuntimeException('invalid_table_identifier');
        }
        $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));
        $wpdb->last_error = '';
        $result = $wpdb->get_var($sql);
        $this->assert_query_success($result, $sql, 'table_existence_check_failed');
        return $result === $table_name;
    }
    public function get_table_index_details($table_name) {
        global $wpdb;
        if( ! preg_match('/^[A-Za-z0-9_]+$/', $table_name) ) {
            throw new RuntimeException('invalid_table_identifier');
        }
        $sql = "SHOW INDEX FROM {$table_name}";
        $wpdb->last_error = '';
        $index_rows = $wpdb->get_results($sql, ARRAY_A);
        $this->assert_query_success($index_rows, $sql, 'table_index_read_failed');
        $indexes = array();
        foreach((array)$index_rows as $row) {
            if( ! isset($row['Key_name'], $row['Column_name']) ) {
                continue;
            }
            $key_name = (string)$row['Key_name'];
            if( ! isset($indexes[$key_name]) ) {
                $indexes[$key_name] = array(
                    'columns'   => array(),
                    'sub_parts' => array(),
                    'unique'    => isset($row['Non_unique']) && intval($row['Non_unique']) === 0,
                );
            }
            $sequence = intval(isset($row['Seq_in_index']) ? $row['Seq_in_index'] : 0);
            $indexes[$key_name]['columns'][$sequence] = (string)$row['Column_name'];
            $indexes[$key_name]['sub_parts'][$sequence] = isset($row['Sub_part']) && $row['Sub_part'] !== null
                ? intval($row['Sub_part'])
                : null;
            if( isset($row['Non_unique']) && intval($row['Non_unique']) !== 0 ) {
                $indexes[$key_name]['unique'] = false;
            }
        }
        foreach($indexes as $key_name => $details) {
            ksort($details['columns'], SORT_NUMERIC);
            ksort($details['sub_parts'], SORT_NUMERIC);
            $indexes[$key_name]['columns'] = array_values($details['columns']);
            $indexes[$key_name]['sub_parts'] = array_values($details['sub_parts']);
        }
        return $indexes;
    }
    public function get_table_index_map($table_name) {
        $details = $this->get_table_index_details($table_name);
        $indexes = array();
        foreach($details as $key_name => $index_details) {
            $indexes[$key_name] = $index_details['columns'];
        }
        return $indexes;
    }
    public function get_table_primary_key_columns($table_name) {
        $indexes = $this->get_table_index_map($table_name);
        return isset($indexes['PRIMARY']) ? $indexes['PRIMARY'] : array();
    }
    public function table_schema_is_valid($table_name, $required_columns, $required_indexes, $required_column_properties = array()) {
        global $wpdb;
        if( ! preg_match('/^[A-Za-z0-9_]+$/', $table_name) ) {
            throw new RuntimeException('invalid_table_identifier');
        }
        $sql = "SHOW COLUMNS FROM {$table_name}";
        $wpdb->last_error = '';
        $column_rows = $wpdb->get_results($sql, ARRAY_A);
        $this->assert_query_success($column_rows, $sql, 'table_columns_read_failed');
        $columns = array();
        $column_details = array();
        foreach((array)$column_rows as $row) {
            if( empty($row['Field']) ) {
                continue;
            }
            $column_name = (string)$row['Field'];
            $columns[] = $column_name;
            $column_details[$column_name] = $row;
        }
        foreach((array)$required_columns as $required_column) {
            if( ! in_array((string)$required_column, $columns, true) ) {
                return false;
            }
        }
        foreach((array)$required_column_properties as $column_name => $properties) {
            if( ! isset($column_details[$column_name]) || ! is_array($properties) ) {
                return false;
            }
            $details = $column_details[$column_name];
            if( ! empty($properties['type']) ) {
                preg_match('/^[a-z]+/i', isset($details['Type']) ? (string)$details['Type'] : '', $type_match);
                if( empty($type_match[0]) || strtolower($type_match[0]) !== strtolower((string)$properties['type']) ) {
                    return false;
                }
            }
            if( array_key_exists('nullable', $properties) ) {
                $is_nullable = isset($details['Null']) && strtoupper((string)$details['Null']) === 'YES';
                if( $is_nullable !== (bool)$properties['nullable'] ) {
                    return false;
                }
            }
            if( array_key_exists('auto_increment', $properties) ) {
                $is_auto_increment = isset($details['Extra'])
                    && stripos((string)$details['Extra'], 'auto_increment') !== false;
                if( $is_auto_increment !== (bool)$properties['auto_increment'] ) {
                    return false;
                }
            }
        }
        $indexes = $this->get_table_index_details($table_name);
        foreach((array)$required_indexes as $index_name => $index_spec) {
            $index_columns = is_array($index_spec) && isset($index_spec['columns'])
                ? array_values((array)$index_spec['columns'])
                : array_values((array)$index_spec);
            $must_be_unique = is_array($index_spec) && array_key_exists('unique', $index_spec)
                ? (bool)$index_spec['unique']
                : in_array((string)$index_name, array('PRIMARY', 'uniqueid'), true);
            $expected_sub_parts = is_array($index_spec) && array_key_exists('sub_parts', $index_spec)
                ? array_values((array)$index_spec['sub_parts'])
                : array_fill(0, count($index_columns), null);
            if( ! isset($indexes[$index_name])
                || $indexes[$index_name]['columns'] !== $index_columns
                || $indexes[$index_name]['sub_parts'] !== $expected_sub_parts
                || ( $must_be_unique && empty($indexes[$index_name]['unique']) ) ) {
                return false;
            }
        }
        return true;
    }
    protected function table_has_duplicate_key($table_name, $columns) {
        global $wpdb;
        $quoted_columns = array_map(function($column) {
            return '`' . $column . '`';
        }, $columns);
        $sql = "SELECT 1 FROM {$table_name} GROUP BY " . implode(', ', $quoted_columns) . ' HAVING COUNT(*) > 1 LIMIT 1';
        $wpdb->last_error = '';
        $duplicate = $wpdb->get_var($sql);
        $this->assert_query_success($duplicate, $sql, 'table_primary_key_duplicate_check_failed');
        return $duplicate !== null;
    }
    public function migrate_table_primary_key($table_name, $desired_columns, $legacy_primary_keys = array()) {
        global $wpdb;
        if( ! preg_match('/^[A-Za-z0-9_]+$/', $table_name) ) {
            throw new RuntimeException('invalid_table_identifier');
        }
        $desired_columns = array_values(array_filter(array_map('strval', (array)$desired_columns), function($column) {
            return preg_match('/^[A-Za-z0-9_]+$/', $column);
        }));
        if( empty($desired_columns) ) {
            throw new RuntimeException('invalid_primary_key_definition');
        }
        $current_columns = $this->get_table_primary_key_columns($table_name);
        if( $current_columns === $desired_columns ) {
            return true;
        }
        $known_legacy_key = empty($current_columns);
        foreach((array)$legacy_primary_keys as $legacy_primary_key) {
            if( $current_columns === array_values((array)$legacy_primary_key) ) {
                $known_legacy_key = true;
                break;
            }
        }
        if( ! $known_legacy_key ) {
            throw new RuntimeException('unknown_table_primary_key');
        }
        if( empty($current_columns) && $this->table_has_duplicate_key($table_name, $desired_columns) ) {
            throw new RuntimeException('table_primary_key_duplicates_found');
        }
        $quoted_columns = array_map(function($column) {
            return '`' . $column . '`';
        }, $desired_columns);
        $operations = array();
        if( ! empty($current_columns) ) {
            $operations[] = 'DROP PRIMARY KEY';
        }
        $operations[] = 'ADD PRIMARY KEY (' . implode(', ', $quoted_columns) . ')';
        // Keeping the ALTER free of ALGORITHM/LOCK/engine hints lets MySQL,
        // MariaDB and compatible servers select their supported implementation.
        $sql = "ALTER TABLE {$table_name} " . implode(', ', $operations);
        $this->assert_query_success($wpdb->query($sql), $sql, 'table_primary_key_update_failed');
        if( $this->get_table_primary_key_columns($table_name) !== $desired_columns ) {
            throw new RuntimeException('table_primary_key_validation_failed');
        }
        return true;
    }
    public function run_db_delta($sql) {
        global $wpdb;
        $wpdb->last_error = '';
        $result = dbDelta($sql);
        $this->assert_query_success($result, $sql, 'table_schema_update_failed');
        return $result;
    }
    function create_table_braapf_term_taxonomy_hierarchical() {
        global $wpdb;
        $collate = $this->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $table_name = $wpdb->prefix . 'braapf_term_taxonomy_hierarchical';
        $sql = "CREATE TABLE $table_name (
        term_taxonomy_id bigint(20) NOT NULL,
        term_id bigint(20) NOT NULL,
        term_taxonomy_child_id bigint(20) NOT NULL,
        term_child_id bigint(20) NOT NULL,
        taxonomy varchar(32) NOT NULL,
        INDEX term_taxonomy_id (term_taxonomy_id),
        INDEX term_taxonomy_child_id (term_taxonomy_child_id),
        INDEX child_parent_id (term_taxonomy_id, term_taxonomy_child_id),
        PRIMARY KEY (term_taxonomy_id, term_id, term_taxonomy_child_id, term_child_id)
        ) $collate;";
        $this->run_db_delta($sql);
        if( ! $this->table_schema_is_valid($table_name,
            array('term_taxonomy_id', 'term_id', 'term_taxonomy_child_id', 'term_child_id', 'taxonomy'),
            array(
                'PRIMARY' => array('term_taxonomy_id', 'term_id', 'term_taxonomy_child_id', 'term_child_id'),
                'child_parent_id' => array('term_taxonomy_id', 'term_taxonomy_child_id'),
            )) ) {
            throw new RuntimeException('table_schema_validation_failed');
        }
        $this->reset_table($table_name);
    }
    function create_table_braapf_product_stock_status_parent() {
        global $wpdb;
        $collate = $this->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $table_name = $wpdb->prefix . 'braapf_product_stock_status_parent';
        $sql = "CREATE TABLE $table_name (
        post_id bigint(20) NOT NULL,
        parent_id bigint(20) NOT NULL,
        stock_status tinyint(2),
        PRIMARY KEY (post_id),
        INDEX stock_status (stock_status),
        INDEX parent_stock_status (parent_id, stock_status)
        ) $collate;";
        $this->run_db_delta($sql);
        if( ! $this->table_schema_is_valid($table_name,
            array('post_id', 'parent_id', 'stock_status'),
            array(
                'PRIMARY' => array('post_id'),
                'parent_stock_status' => array('parent_id', 'stock_status'),
            )) ) {
            throw new RuntimeException('table_schema_validation_failed');
        }
        $this->reset_table($table_name);
    }
    function create_table_braapf_variable_attributes() {
        global $wpdb;
        $collate = $this->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $table_name = $wpdb->prefix . 'braapf_variable_attributes';
        $sql = "CREATE TABLE $table_name (
        post_id bigint(20) NOT NULL,
        attribute varchar(32) NOT NULL,
        INDEX post_id (post_id),
        INDEX attribute (attribute),
        PRIMARY KEY (post_id, attribute)
        ) $collate;";
        $this->run_db_delta($sql);
        if( ! $this->table_schema_is_valid($table_name,
            array('post_id', 'attribute'),
            array(
                'PRIMARY' => array('post_id', 'attribute'),
                'attribute' => array('attribute'),
            )) ) {
            throw new RuntimeException('table_schema_validation_failed');
        }
        $this->reset_table($table_name);
    }
    function create_table_braapf_product_variation_attributes() {
        global $wpdb;
        $collate = $this->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $table_name = $wpdb->prefix . 'braapf_product_variation_attributes';
        $desired_primary_key = array('post_id', 'parent_id', 'meta_key', 'meta_value_id');
        if( $this->table_exists($table_name) ) {
            $this->migrate_table_primary_key(
                $table_name,
                $desired_primary_key,
                array(array('post_id', 'meta_key', 'meta_value_id'))
            );
        }
        $sql = "CREATE TABLE $table_name (
        post_id bigint(20) NOT NULL,
        parent_id bigint(20) NOT NULL,
        meta_key varchar(32) NOT NULL,
        meta_value_id bigint(20) NOT NULL,
        stock_status tinyint(2),
        INDEX post_id (post_id),
        INDEX parent_id (parent_id),
        INDEX meta_key (meta_key),
        INDEX meta_value_id (meta_value_id),
        INDEX parent_meta_value (parent_id, meta_value_id),
        INDEX meta_value_post (meta_value_id, post_id),
        PRIMARY KEY (post_id, parent_id, meta_key, meta_value_id)
        ) $collate;";
        $this->run_db_delta($sql);
        if( ! $this->table_schema_is_valid($table_name,
            array('post_id', 'parent_id', 'meta_key', 'meta_value_id', 'stock_status'),
            array(
                'PRIMARY' => $desired_primary_key,
                'meta_key' => array('meta_key'),
                'parent_meta_value' => array('parent_id', 'meta_value_id'),
                'meta_value_post' => array('meta_value_id', 'post_id'),
            )) ) {
            throw new RuntimeException('table_schema_validation_failed');
        }
        $this->reset_table($table_name);
    }
    function insert_table_braapf_product_stock_status_parent() {
        $run_data = $this->get_current_create_position_data();
        if( empty($run_data) || ! empty($run_data['run']) ) {
            return false;
        }
        $run_data['run'] = true;
        $this->set_current_create_position_data($run_data);
        $start_id = intval($run_data['start_id']);
        $max_id = intval($run_data['max_id']);
        $batch_size = max(1, intval(apply_filters('berocket_insert_table_braapf_product_stock_status_parent_end', 5000)));
        global $wpdb;
        $batch = $this->get_id_batch($wpdb->wc_product_meta_lookup, 'product_id', $start_id, $max_id, $batch_size);
        if( $batch === false ) {
            $this->move_to_variation_or_variable_stage();
            return;
        }
        $table_name = $wpdb->prefix . 'braapf_product_stock_status_parent';
        $batch_source = $wpdb->prepare(
            "SELECT product_id FROM {$wpdb->wc_product_meta_lookup}
             WHERE product_id >= %d AND product_id <= %d
             ORDER BY product_id ASC LIMIT %d",
            $start_id,
            $max_id,
            $batch_size
        );
        $sql = "INSERT IGNORE INTO {$table_name} (post_id, parent_id, stock_status)
            SELECT {$wpdb->posts}.ID, {$wpdb->posts}.post_parent,
                IF(product_lookup.stock_status = 'instock', 1, 0)
            FROM ({$batch_source}) AS source_batch
            JOIN {$wpdb->wc_product_meta_lookup} AS product_lookup ON source_batch.product_id = product_lookup.product_id
            JOIN {$wpdb->posts} ON product_lookup.product_id = {$wpdb->posts}.ID";
        $this->assert_query_success($wpdb->query($sql), $sql, 'stock_table_batch_failed');

        $processed_rows = intval(isset($run_data['processed_rows']) ? $run_data['processed_rows'] : 0) + $batch['row_count'];
        $total_rows = max(1, intval(isset($run_data['total_rows']) ? $run_data['total_rows'] : $processed_rows));
        $min_id = intval(isset($run_data['min_id']) ? $run_data['min_id'] : $batch['min_id']);
        $id_span = max(1, $max_id - $min_id + 1);
        $status = min(99, max(0, intval((($batch['max_id'] - $min_id + 1) / $id_span) * 100)));
        if( $batch['max_id'] < $max_id && $batch['row_count'] >= $batch_size ) {
            $this->set_current_create_position_data(array(
                'status' => $status,
                'run' => false,
                'start_id' => $batch['max_id'] + 1,
                'min_id' => intval($run_data['min_id']),
                'max_id' => $max_id,
                'processed_rows' => $processed_rows,
                'total_rows' => $total_rows,
            ));
        } else {
            $this->move_to_variation_or_variable_stage();
        }
    }
    function insert_table_braapf_product_variation_attributes() {
        $run_data = $this->get_current_create_position_data();
        if( empty($run_data) || ! empty($run_data['run']) ) {
            return false;
        }
        $run_data['run'] = true;
        $this->set_current_create_position_data($run_data);
        $start_id = intval($run_data['start_id']);
        $max_id = intval($run_data['max_id']);
        $batch_size = max(1, intval(apply_filters('berocket_insert_table_braapf_product_variation_attributes_end', 5000)));
        global $wpdb;
        $attribute_pattern = $wpdb->esc_like('attribute_pa_') . '%';
        $batch = $this->get_variation_id_batch($start_id, $max_id, $batch_size, $attribute_pattern);
        if( $batch === false ) {
            $this->set_current_create_position(4);
            $this->set_current_create_position_data(array('status' => 0, 'run' => false, 'cursor_id' => 0));
            return;
        }
        $table_name = $wpdb->prefix . 'braapf_product_variation_attributes';
        $attribute_key_map = isset($run_data['attribute_key_map']) && is_array($run_data['attribute_key_map'])
            ? $run_data['attribute_key_map']
            : $this->get_variation_attribute_key_map();
        $join_tables = array("term_taxonomy.taxonomy = SUBSTRING(postmeta.meta_key, 11)");
        foreach($attribute_key_map as $postmeta_from => $taxonomy_name) {
            $join_tables[] = $wpdb->prepare(
                '(term_taxonomy.taxonomy = %s AND postmeta.meta_key = %s)',
                $taxonomy_name,
                $postmeta_from
            );
        }
        $join_tables = implode(' OR ', $join_tables);
        $sql = $wpdb->prepare(
            "INSERT IGNORE INTO {$table_name} (post_id, parent_id, meta_key, meta_value_id, stock_status)
             SELECT postmeta.post_id, posts.post_parent, term_taxonomy.taxonomy, terms.term_id,
                    IF(product_lookup.stock_status IN ('instock', 'onbackorder'), 1, 0)
             FROM {$wpdb->postmeta} AS postmeta
             JOIN {$wpdb->term_taxonomy} AS term_taxonomy ON {$join_tables}
             JOIN {$wpdb->terms} AS terms ON terms.term_id = term_taxonomy.term_id AND postmeta.meta_value = terms.slug
             JOIN {$wpdb->posts} AS posts ON postmeta.post_id = posts.ID
             JOIN {$wpdb->wc_product_meta_lookup} AS product_lookup ON posts.ID = product_lookup.product_id
             WHERE postmeta.meta_id >= %d AND postmeta.meta_id <= %d
               AND postmeta.meta_key LIKE %s",
            $batch['min_id'],
            $batch['max_id'],
            $attribute_pattern
        );
        $this->assert_query_success($wpdb->query($sql), $sql, 'variation_values_batch_failed');

        $sql = $wpdb->prepare(
            "INSERT IGNORE INTO {$table_name} (post_id, parent_id, meta_key, meta_value_id, stock_status)
             SELECT posts.ID, posts.post_parent, term_taxonomy.taxonomy, term_taxonomy.term_id,
                    IF(product_lookup.stock_status IN ('instock', 'onbackorder'), 1, 0)
             FROM {$wpdb->postmeta} AS postmeta
             JOIN {$wpdb->posts} AS posts ON postmeta.post_id = posts.ID
             JOIN {$wpdb->term_relationships} AS term_relationships ON posts.post_parent = term_relationships.object_id
             JOIN {$wpdb->term_taxonomy} AS term_taxonomy
                  ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id
                 AND term_taxonomy.taxonomy = SUBSTRING(postmeta.meta_key, 11)
             JOIN {$wpdb->wc_product_meta_lookup} AS product_lookup ON posts.ID = product_lookup.product_id
             WHERE postmeta.meta_id >= %d AND postmeta.meta_id <= %d
               AND postmeta.meta_key LIKE %s AND postmeta.meta_value = ''",
            $batch['min_id'],
            $batch['max_id'],
            $attribute_pattern
        );
        $this->assert_query_success($wpdb->query($sql), $sql, 'variation_inherited_batch_failed');

        $processed_rows = intval(isset($run_data['processed_rows']) ? $run_data['processed_rows'] : 0) + $batch['row_count'];
        $total_rows = max(1, intval(isset($run_data['total_rows']) ? $run_data['total_rows'] : $processed_rows));
        $min_id = intval(isset($run_data['min_id']) ? $run_data['min_id'] : $batch['min_id']);
        $id_span = max(1, $max_id - $min_id + 1);
        $status = min(99, max(0, intval((($batch['max_id'] - $min_id + 1) / $id_span) * 100)));
        if( $batch['max_id'] < $max_id && $batch['row_count'] >= $batch_size ) {
            $this->set_current_create_position_data(array(
                'status' => $status,
                'run' => false,
                'start_id' => $batch['max_id'] + 1,
                'min_id' => intval($run_data['min_id']),
                'max_id' => $max_id,
                'processed_rows' => $processed_rows,
                'total_rows' => $total_rows,
                'attribute_key_map' => $attribute_key_map,
            ));
        } else {
            $this->set_current_create_position(4);
            $this->set_current_create_position_data(array(
                'status' => 0,
                'run' => false,
                'cursor_id' => 0,
            ));
        }
    }
    function insert_table_braapf_variable_attributes() {
        $run_data = $this->get_current_create_position_data();
        if( empty($run_data) || ! empty($run_data['run']) ) {
            return false;
        }
        $run_data['run'] = true;
        $variable_taxonomy = get_term_by('slug', 'variable', 'product_type');
        $this->set_current_create_position_data($run_data);
        global $wpdb;
        if( empty($variable_taxonomy) || empty($variable_taxonomy->term_taxonomy_id) ) {
            $this->set_current_create_position(5);
            $this->set_current_create_position_data(array('status' => 0, 'run' => false, 'cursor_id' => 0));
            return;
        }
        $cursor_id = isset($run_data['cursor_id']) ? intval($run_data['cursor_id']) : 0;
        $batch_size = max(1, intval(apply_filters('berocket_insert_table_braapf_variable_attributes_end', 1000)));
        $table_name = $wpdb->prefix . 'braapf_variable_attributes';
        if( isset($run_data['max_id']) ) {
            $max_post_id = intval($run_data['max_id']);
        } else {
            $max_post_id_sql = "SELECT MAX(ID) FROM {$wpdb->posts}";
            $wpdb->last_error = '';
            $max_post_id_result = $wpdb->get_var($max_post_id_sql);
            $this->assert_query_success($max_post_id_result, $max_post_id_sql, 'variable_attributes_max_id_failed');
            $max_post_id = intval($max_post_id_result);
        }
        $sql_select = $wpdb->prepare(
            "SELECT posts.ID AS id, postmeta.meta_value AS value
             FROM {$wpdb->posts} AS posts
             JOIN {$wpdb->term_relationships} AS term_relationships
               ON posts.ID = term_relationships.object_id
              AND term_relationships.term_taxonomy_id = %d
             JOIN {$wpdb->postmeta} AS postmeta
               ON posts.ID = postmeta.post_id
              AND postmeta.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} AS earlier_postmeta
               ON earlier_postmeta.post_id = postmeta.post_id
              AND earlier_postmeta.meta_key = postmeta.meta_key
              AND earlier_postmeta.meta_id < postmeta.meta_id
             WHERE posts.ID > %d AND posts.ID <= %d
               AND earlier_postmeta.meta_id IS NULL
             ORDER BY posts.ID ASC
             LIMIT %d",
            intval($variable_taxonomy->term_taxonomy_id),
            '_product_attributes',
            $cursor_id,
            $max_post_id,
            $batch_size
        );
        $results = $wpdb->get_results($sql_select);
        $this->assert_query_success($results, $sql_select, 'variable_attributes_source_failed');
        if( ! empty($results) && is_array($results) ) {
            $insert_values = array();
            foreach($results as $product) {
                $cursor_id = max($cursor_id, intval($product->id));
                $product_attribute = maybe_unserialize($product->value);
                if( is_array($product_attribute) ) {
                    foreach($product_attribute as $attribute) {
                        if( ! empty($attribute['is_variation']) && isset($attribute['name']) ) {
                            $insert_values[] = $wpdb->prepare('(%d, %s)', intval($product->id), (string)$attribute['name']);
                        }
                    }
                }
            }
            if( ! empty($insert_values) ) {
                foreach(array_chunk(array_unique($insert_values), 500) as $insert_chunk) {
                    $sql = "INSERT IGNORE INTO {$table_name} (post_id, attribute) VALUES " . implode(',', $insert_chunk);
                    $this->assert_query_success($wpdb->query($sql), $sql, 'variable_attributes_batch_failed');
                }
            }
        }
        if( count((array)$results) >= $batch_size ) {
            $status = $max_post_id > 0 ? min(99, intval(($cursor_id / $max_post_id) * 100)) : 0;
            $this->set_current_create_position_data(array(
                'run' => false,
                'status' => $status,
                'cursor_id' => $cursor_id,
                'max_id' => $max_post_id,
            ));
        } else {
            $this->set_current_create_position(5);
            $this->set_current_create_position_data(array(
                'status' => 0,
                'run' => false,
                'cursor_id' => 0,
            ));
        }
    }
    function insert_table_braapf_missing_variation_attributes() {
        $run_data = $this->get_current_create_position_data();
        if( empty($run_data) || ! empty($run_data['run']) ) {
            return false;
        }
        $run_data['run'] = true;
        $this->set_current_create_position_data($run_data);
        global $wpdb;
        $cursor_id = isset($run_data['cursor_id']) ? intval($run_data['cursor_id']) : 0;
        $batch_size = max(1, intval(apply_filters('berocket_insert_table_braapf_missing_variation_attributes_end', 1000)));
        $variable_table = $wpdb->prefix . 'braapf_variable_attributes';
        $variation_table = $wpdb->prefix . 'braapf_product_variation_attributes';
        $source_sql = $wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$variable_table}
             WHERE post_id > %d
             ORDER BY post_id ASC
             LIMIT %d",
            $cursor_id,
            $batch_size
        );
        $parent_ids = array_values(array_unique(array_map('intval', (array)$wpdb->get_col($source_sql))));
        $this->assert_query_success($parent_ids, $source_sql, 'missing_variation_source_failed');
        $parent_ids = array_values(array_filter($parent_ids));
        if( empty($parent_ids) ) {
            $this->set_current_create_position(6);
            $this->set_current_create_position_data(array('status' => 0, 'run' => false));
            return;
        }
        $last_parent_id = max($parent_ids);
        $placeholders = implode(', ', array_fill(0, count($parent_ids), '%d'));
        $sql = "INSERT IGNORE INTO {$variation_table} (post_id, parent_id, meta_key, meta_value_id, stock_status)
            SELECT variable_attributes.post_id,
                   variable_attributes.post_id,
                   variable_attributes.attribute,
                   term_taxonomy.term_id,
                   0
            FROM {$variable_table} AS variable_attributes
            JOIN {$wpdb->term_relationships} AS term_relationships
              ON variable_attributes.post_id = term_relationships.object_id
            JOIN {$wpdb->term_taxonomy} AS term_taxonomy
              ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id
             AND term_taxonomy.taxonomy = variable_attributes.attribute
            LEFT JOIN {$variation_table} AS variation_attributes
              ON variation_attributes.parent_id = variable_attributes.post_id
             AND variation_attributes.meta_key = variable_attributes.attribute
             AND variation_attributes.meta_value_id = term_taxonomy.term_id
            WHERE variable_attributes.post_id IN ({$placeholders})
              AND variation_attributes.parent_id IS NULL
            GROUP BY variable_attributes.post_id, variable_attributes.attribute, term_taxonomy.term_id";
        $sql = $wpdb->prepare($sql, $parent_ids);
        $this->assert_query_success($wpdb->query($sql), $sql, 'missing_variation_batch_failed');

        $max_parent_id = isset($run_data['max_id']) ? intval($run_data['max_id']) : 0;
        if( $max_parent_id <= 0 ) {
            $max_sql = "SELECT MAX(post_id) FROM {$variable_table}";
            $wpdb->last_error = '';
            $max_parent_id = intval($wpdb->get_var($max_sql));
            $this->assert_query_success($max_parent_id, $max_sql, 'missing_variation_max_id_failed');
        }
        if( count($parent_ids) >= $batch_size && $last_parent_id < $max_parent_id ) {
            $status = $max_parent_id > 0 ? min(99, intval(($last_parent_id / $max_parent_id) * 100)) : 0;
            $this->set_current_create_position_data(array(
                'status' => $status,
                'run' => false,
                'cursor_id' => $last_parent_id,
                'max_id' => $max_parent_id,
            ));
        } else {
            $this->set_current_create_position(6);
            $this->set_current_create_position_data(array('status' => 0, 'run' => false));
        }
    }
    function get_table_list() {
        return apply_filters('BeRocket_aapf_variations_tables_addon_table_list', array(
            'braapf_product_stock_status_parent',
            'braapf_product_variation_attributes',
            'braapf_variation_attributes',
            'braapf_variable_attributes',
            'braapf_term_taxonomy_hierarchical'
        ));
    }
    function deactivate($expected_generation_id = false) {
        if( ! empty($expected_generation_id) && $this->additional_tables_addon_is_enabled() ) {
            return false;
        }
        $current = $this->get_generation_state_direct();
        $state = is_array($current) ? $current['state'] : $this->get_generation_state();
        if( ! empty($expected_generation_id)
            && (string)$state['generation_id'] !== (string)$expected_generation_id ) {
            return false;
        }
        $lock = $this->acquire_worker_lock($state['generation_id']);
        if( empty($lock) ) {
            update_option(self::CANCEL_OPTION, array(
                'generation_id' => $state['generation_id'],
                'requested_at'  => time(),
            ), false);
            $this->unschedule_generation_workers($state['generation_id']);
            return false;
        }
        $this->unschedule_generation_workers($state['generation_id']);
        global $wpdb;
        try {
            $current = $this->get_generation_state_direct();
            if( ! is_array($current) || $this->additional_tables_addon_is_enabled()
                || ( ! empty($expected_generation_id)
                    && (string)$current['state']['generation_id'] !== (string)$expected_generation_id ) ) {
                return false;
            }
            $tables_drop = $this->get_table_list();
            foreach($tables_drop as $table_drop) {
                $table_name = $wpdb->prefix . $table_drop;
                $sql = "DROP TABLE IF EXISTS {$table_name};";
                $wpdb->query($sql);
            }
            $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%br_custom_table_hierarhical_%';");
            delete_option('BeRocket_aapf_additional_tables_addon_position');
            delete_option('BeRocket_aapf_additional_tables_addon_position_data');
            delete_option(self::STATE_OPTION);
            delete_option(self::RERUN_OPTION);
            delete_option(self::CANCEL_OPTION);
            do_action('BeRocket_aapf_variations_tables_addon_destroy_table', $this);
        } finally {
            $this->release_worker_lock($lock);
        }
        return true;
    }
    function destroy_table_wc_regeneration() {
        if ( (! defined('BAPF_DISABLE_TABLE_UPDATES') || ! BAPF_DISABLE_TABLE_UPDATES)
            && apply_filters( 'br-filters/addon/add-table/wc-regenerate-destroy', $this->product_lookup_tables_is_running() ) ) {
            update_option(self::RERUN_OPTION, array(
                'reason'       => 'woocommerce_lookup_running',
                'requested_at' => time(),
            ), false);
        }
    }
    function destroy_table() {
        $this->deactivate();
    }
    function reset_all_table() {
        return $this->request_regeneration('legacy_reset');
    }
    public function section_purge_additional_tables ( $html, $item, $options ) {
        $html = '<tr>
            <th scope="row">' . __('Regenerate Additional Tables', 'BeRocket_AJAX_domain') . '</th>
            <td>';
        $old_filter_widgets = get_option('widget_berocket_aapf_widget');
        if( ! is_array($old_filter_widgets) ) {
            $old_filter_widgets = array();
        }
        foreach ($old_filter_widgets as $key => $value) {
            if (!is_numeric($key)) {
                unset($old_filter_widgets[$key]);
            }
        }
        $nonce = wp_create_nonce('regenerate_additional_tables');
        $html .= '
                <span class="button berocket_purge_additional_tables" data-time="'.time().'">
                    ' . __('Regenerate Additional Tables', 'BeRocket_AJAX_domain') . '
                </span>
                <p>' . __('Clear all tables from add-on Additional Tables', 'BeRocket_AJAX_domain') . '</p>
                <script>
                    jQuery(".berocket_purge_additional_tables").click(function() {
                        var $button = jQuery(this);
                        if( $button.hasClass("disabled") ) {
                            return;
                        }
                        $button.addClass("disabled");
                        jQuery.post(window.ajaxurl, {action:"brapf_regenerate_additional_tables",nonce:"' . $nonce . '"})
                            .done(function(response) {
                                if( response && response.success ) {
                                    location.reload();
                                }
                            })
                            .always(function() {
                                $button.removeClass("disabled");
                            });
                    });
                </script>
            </td>
        </tr>';
        return $html;
    }
    public function regenerate_additional_tables() {
        if( ! check_ajax_referer('regenerate_additional_tables', 'nonce', false)
            || ! current_user_can($this->get_manage_capability()) ) {
            wp_send_json_error(array('message' => __('You do not have permission to regenerate additional tables.', 'BeRocket_AJAX_domain')), 403);
        }
        $state = $this->request_regeneration('manual');
        if( ! is_array($state) ) {
            $state = $this->get_generation_state();
        }
        wp_send_json_success(array(
            'generation_id' => $state['generation_id'],
            'status'        => $state['status'],
        ), 202);
    }
    public function product_lookup_tables_is_running() {
        // Do not infer a WooCommerce lookup rebuild from Action Scheduler's
        // bootstrap state. It caused false positives on admin requests and
        // reset an in-progress Additional Tables build back to position zero.
        $is_running = (bool)get_option('woocommerce_product_lookup_table_is_generating', false);
        if( ! $is_running && function_exists('wc_update_product_lookup_tables_is_running') ) {
            $is_running = wc_update_product_lookup_tables_is_running();
        }
        return (bool)apply_filters(
            'braapf_product_lookup_tables_is_running',
            $is_running,
            $this
        );
    }
}
new BeRocket_aapf_variations_tables_addon();
