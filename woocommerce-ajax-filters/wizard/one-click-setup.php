<?php
/**
 * Persistent contract for the wizard's intelligent one-click filters setup.
 *
 * This class deliberately has no UI or creation logic.  It is the single
 * owner of the option and post-meta keys which are shared by the catalog
 * analyzer, setup orchestrator, rollback and health check.
 */
class BeRocket_AAPF_One_Click_Setup {
    const SCHEMA_VERSION = 1;

    const OPTION_NAME = 'br_aapf_one_click_setup';

    const META_GENERATED = '_br_aapf_generated_by_one_click';
    const META_SETUP_ID = '_br_aapf_one_click_setup_id';
    const META_OBJECT_TYPE = '_br_aapf_one_click_object_type';
    const META_SCHEMA_VERSION = '_br_aapf_one_click_schema_version';

    const STATUS_IDLE = 'idle';
    const STATUS_ANALYZING = 'analyzing';
    const STATUS_READY = 'ready';
    const STATUS_CREATING = 'creating';
    const STATUS_ROLLING_BACK = 'rolling_back';
    const STATUS_ACTIVE = 'active';
    const STATUS_FAILED = 'failed';

    /**
     * Return the persisted setup state in the current schema.
     *
     * Missing or malformed values are returned as a safe idle state.  Future
     * schema migrations belong here, so callers never need to understand an
     * older option shape.
     */
    public static function get_state() {
        return self::normalize_state(get_option(self::OPTION_NAME, array()));
    }

    /**
     * Save one complete setup state.  Partial updates should be merged by the
     * caller before this method is used, keeping each write self-contained.
     */
    public static function save_state($state) {
        $state = self::normalize_state($state);
        $state['updated_at'] = current_time('mysql', true);
        update_option(self::OPTION_NAME, $state, false);
        return $state;
    }

    /**
     * Create the state used before a new analysis or creation operation.
     */
    public static function get_default_state() {
        return array(
            'schema_version' => self::SCHEMA_VERSION,
            'setup_id' => '',
            'status' => self::STATUS_IDLE,
            'generated_by_one_click' => false,
            'created_at' => '',
            'updated_at' => '',
            'analysis' => array(
                'hash' => '',
                'analyzed_at' => '',
                'catalog' => array(),
            ),
            'filters' => array(
                'ids' => array(),
            ),
            'groups' => array(
                'desktop' => self::get_default_group_state(),
                'mobile' => self::get_default_group_state(),
            ),
            'placements' => array(
                'desktop' => self::get_default_placement_state(),
                'mobile' => self::get_default_placement_state(),
            ),
            'health' => self::get_default_health_state(),
            'operation' => self::get_default_operation_state(),
            'undo' => self::get_default_undo_state(),
        );
    }

    public static function get_statuses() {
        return array(
            self::STATUS_IDLE,
            self::STATUS_ANALYZING,
            self::STATUS_READY,
            self::STATUS_CREATING,
            self::STATUS_ROLLING_BACK,
            self::STATUS_ACTIVE,
            self::STATUS_FAILED,
        );
    }

    /**
     * Allocate a stable setup ID before the first setup-related mutation. The
     * same ID is retained on updates so post ownership remains traceable.
     */
    public static function initialize_setup($state = array()) {
        $state = self::normalize_state($state);
        if (empty($state['setup_id'])) {
            $state['setup_id'] = self::generate_id();
        }
        if (empty($state['created_at'])) {
            $state['created_at'] = current_time('mysql', true);
        }
        return $state;
    }

    /**
     * Start a traceable operation.  The orchestrator must store the returned
     * state before changing posts, groups, widgets or plugin options.
     */
    public static function start_operation($state, $status, $step = '') {
        $state = self::normalize_state($state);
        if (!in_array($status, self::get_statuses(), true)) {
            $status = self::STATUS_FAILED;
        }
        $now = current_time('mysql', true);
        $state['status'] = $status;
        $state['operation'] = array(
            'id' => self::generate_id(),
            'status' => $status,
            'step' => sanitize_key($step),
            'started_at' => $now,
            'completed_at' => '',
            'error_code' => '',
            'error_message' => '',
        );
        return $state;
    }

    /** Mark a filter or group post as owned by this setup without using its title as an identifier. */
    public static function mark_post($post_id, $setup_id, $object_type) {
        $post_id = absint($post_id);
        if (!$post_id || !in_array($object_type, array('filter', 'group'), true)) {
            return false;
        }
        update_post_meta($post_id, self::META_GENERATED, '1');
        update_post_meta($post_id, self::META_SETUP_ID, sanitize_text_field($setup_id));
        update_post_meta($post_id, self::META_OBJECT_TYPE, $object_type);
        update_post_meta($post_id, self::META_SCHEMA_VERSION, self::SCHEMA_VERSION);
        return true;
    }

    public static function is_setup_post($post_id, $setup_id = '') {
        if ('1' !== get_post_meta($post_id, self::META_GENERATED, true)) {
            return false;
        }
        return empty($setup_id) || $setup_id === get_post_meta($post_id, self::META_SETUP_ID, true);
    }

    protected static function normalize_state($state) {
        $default = self::get_default_state();
        if (!is_array($state)) {
            return $default;
        }

        $state = wp_parse_args($state, $default);
        if ((int)$state['schema_version'] !== self::SCHEMA_VERSION) {
            // Schema migrations will be added here before a later version is released.
            return $default;
        }
        if (!in_array($state['status'], self::get_statuses(), true)) {
            $state['status'] = self::STATUS_FAILED;
        }
        $state['setup_id'] = is_string($state['setup_id']) ? $state['setup_id'] : '';
        $state['generated_by_one_click'] = !empty($state['generated_by_one_click']);
        $state['analysis'] = wp_parse_args(is_array($state['analysis']) ? $state['analysis'] : array(), $default['analysis']);
        $state['filters'] = wp_parse_args(is_array($state['filters']) ? $state['filters'] : array(), $default['filters']);
        $state['filters']['ids'] = self::sanitize_ids($state['filters']['ids']);
        $state['groups'] = wp_parse_args(is_array($state['groups']) ? $state['groups'] : array(), $default['groups']);
        $state['groups']['desktop'] = self::normalize_group_state($state['groups']['desktop']);
        $state['groups']['mobile'] = self::normalize_group_state($state['groups']['mobile']);
        $state['placements'] = wp_parse_args(is_array($state['placements']) ? $state['placements'] : array(), $default['placements']);
        $state['placements']['desktop'] = self::normalize_placement_state($state['placements']['desktop']);
        $state['placements']['mobile'] = self::normalize_placement_state($state['placements']['mobile']);
        $state['health'] = wp_parse_args(is_array($state['health']) ? $state['health'] : array(), $default['health']);
        $state['health']['status'] = is_string($state['health']['status']) ? sanitize_key($state['health']['status']) : '';
        $state['health']['checked_at'] = is_string($state['health']['checked_at']) ? $state['health']['checked_at'] : '';
        $state['health']['checks'] = is_array($state['health']['checks']) ? $state['health']['checks'] : array();
        $state['operation'] = wp_parse_args(is_array($state['operation']) ? $state['operation'] : array(), $default['operation']);
        if (!in_array($state['operation']['status'], self::get_statuses(), true)) {
            $state['operation']['status'] = self::STATUS_FAILED;
        }
        $state['operation']['id'] = is_string($state['operation']['id']) ? $state['operation']['id'] : '';
        $state['operation']['step'] = is_string($state['operation']['step']) ? sanitize_key($state['operation']['step']) : '';
        $state['operation']['error_code'] = is_string($state['operation']['error_code']) ? sanitize_key($state['operation']['error_code']) : '';
        // Never retain a technical exception message in persistent state.
        // This option is available in wp-admin and can be exposed by future
        // status UIs, so it must contain only a fixed public message.
        $state['operation']['error_message'] = in_array($state['operation']['error_code'], array(
            'brapf_one_click_setup_failed',
            'brapf_one_click_rollback_failed',
        ), true)
            ? __('The one-click setup could not be completed. Please try again.', 'BeRocket_AJAX_domain')
            : '';
        $state['undo'] = wp_parse_args(is_array($state['undo']) ? $state['undo'] : array(), $default['undo']);
        return $state;
    }

    protected static function get_default_group_state() {
        return array(
            'id' => 0,
            'filter_ids' => array(),
            'layout' => '',
            'visibility' => array(),
        );
    }

    protected static function normalize_group_state($group) {
        $group = wp_parse_args(is_array($group) ? $group : array(), self::get_default_group_state());
        $group['id'] = absint($group['id']);
        $group['filter_ids'] = self::sanitize_ids($group['filter_ids']);
        $group['layout'] = is_string($group['layout']) ? sanitize_key($group['layout']) : '';
        $group['visibility'] = is_array($group['visibility']) ? $group['visibility'] : array();
        return $group;
    }

    protected static function get_default_placement_state() {
        return array(
            'type' => '',
            'sidebar_id' => '',
            'widget' => array(
                'id_base' => '',
                'number' => 0,
            ),
            'fallback' => '',
            'details' => array(),
        );
    }

    protected static function normalize_placement_state($placement) {
        $placement = wp_parse_args(is_array($placement) ? $placement : array(), self::get_default_placement_state());
        $placement['type'] = is_string($placement['type']) ? sanitize_key($placement['type']) : '';
        $placement['sidebar_id'] = is_string($placement['sidebar_id']) ? sanitize_key($placement['sidebar_id']) : '';
        $placement['widget'] = wp_parse_args(is_array($placement['widget']) ? $placement['widget'] : array(), self::get_default_placement_state()['widget']);
        $placement['widget']['id_base'] = is_string($placement['widget']['id_base']) ? sanitize_key($placement['widget']['id_base']) : '';
        $placement['widget']['number'] = absint($placement['widget']['number']);
        $placement['fallback'] = is_string($placement['fallback']) ? sanitize_key($placement['fallback']) : '';
        $placement['details'] = is_array($placement['details']) ? $placement['details'] : array();
        return $placement;
    }

    protected static function get_default_operation_state() {
        return array(
            'id' => '',
            'status' => self::STATUS_IDLE,
            'step' => '',
            'started_at' => '',
            'completed_at' => '',
            'error_code' => '',
            'error_message' => '',
        );
    }

    protected static function get_default_health_state() {
        return array(
            'status' => '',
            'checked_at' => '',
            'checks' => array(),
        );
    }

    protected static function get_default_undo_state() {
        return array(
            'available' => false,
            'created' => array(
                'filter_ids' => array(),
                'group_ids' => array(),
                'widgets' => array(),
            ),
            'previous' => array(
                'sidebars_widgets' => array(),
                'widget_instances' => array(),
                'group_settings' => array(),
                'plugin_options' => array(),
            ),
        );
    }

    protected static function sanitize_ids($ids) {
        if (!is_array($ids)) {
            return array();
        }
        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    protected static function generate_id() {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        return uniqid('br_aapf_', true);
    }
}
