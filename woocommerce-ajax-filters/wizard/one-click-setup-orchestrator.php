<?php
/**
 * The single server-side path behind "Create filters in 1 click".
 */
class BeRocket_AAPF_One_Click_Setup_Orchestrator {
    const GROUP_POST_TYPE = 'br_filters_group';
    const GROUP_SETTINGS_META = 'br_filters_group';
    const GROUP_WIDGET_BASE = 'berocket_aapf_group';

    /** @var BeRocket_AAPF_One_Click_Setup_Orchestrator */
    protected static $instance;

    public function __construct() {
        self::$instance = $this;
        add_action('brapf_one_click_setup_create_requested', array($this, 'handle_wizard_request'));
    }

    public static function get_instance() {
        return self::$instance;
    }

    public function handle_wizard_request($wizard = null) {
        if (!current_user_can('manage_woocommerce')
            || !BeRocket_AAPF_One_Click_Capabilities::current_user_can_manage_setup('create')) {
            return new WP_Error('brapf_one_click_setup_forbidden', __('You cannot create this setup.', 'BeRocket_AJAX_domain'));
        }
        $result = $this->run();
        if (is_wp_error($result)) {
            do_action('brapf_one_click_setup_failed', $result, $wizard);
            return $result;
        }
        do_action('brapf_one_click_setup_created', $result, $wizard);
        return $result;
    }

    /**
     * Creates a complete configuration. $analysis is injectable for tests and
     * background callers; normal wizard requests use the cached analysis.
     */
    public function run($analysis = null, $capability = null) {
        if ($analysis === null) {
            $analysis = (new BeRocket_AAPF_Wizard_Filter_Recommendations())->get_analysis($capability);
        }
        $recommendations = isset($analysis['ranking']['recommendations']) && is_array($analysis['ranking']['recommendations'])
            ? $analysis['ranking']['recommendations']
            : array();
        if (empty($analysis['snapshot']['status']) || $analysis['snapshot']['status'] !== 'ready') {
            return new WP_Error('brapf_one_click_analysis_unavailable', __('Catalog analysis is not ready yet.', 'BeRocket_AJAX_domain'));
        }
        // Business AI and future recommendation sources may only pass the
        // already-ranked list into the existing definition/group pipeline.
        // They cannot bypass reuse, rollback, placement, or health checks.
        $recommendations = apply_filters(
            'brapf_one_click_setup_recommendations',
            $recommendations,
            $analysis,
            $capability
        );
        $recommendations = is_array($recommendations) ? $recommendations : array();
        if (empty($recommendations)) {
            return new WP_Error('brapf_one_click_no_recommendations', __('No eligible filters were found for this catalog.', 'BeRocket_AJAX_domain'));
        }

        $state = BeRocket_AAPF_One_Click_Setup::get_state();
        if (!empty($state['generated_by_one_click']) && !empty($state['setup_id'])
            && $state['status'] === BeRocket_AAPF_One_Click_Setup::STATUS_ACTIVE) {
            if ($this->has_active_setup_groups($state)) {
                return $this->create_missing_definitions($state, $analysis, $recommendations);
            }
            // The user removed at least one generated group. This is not an
            // update: remove only stale one-click placements and rebuild the
            // missing setup objects below.
            $cleanup = $this->remove_stale_group_placements($state);
            if (is_wp_error($cleanup)) {
                return $cleanup;
            }
        }

        $intelligence = new BeRocket_AAPF_Wizard_Filter_Recommendations();
        $placement_context = $intelligence->get_placement_context($capability);
        $desktop_plan = $placement_context['desktop_placement'];
        $mobile_plan = $placement_context['mobile_placement'];
        $mobile_placement = new BeRocket_AAPF_One_Click_Mobile_Placement();
        if (empty($desktop_plan['available'])) {
            return new WP_Error('brapf_one_click_desktop_placement_unavailable', __('A reliable desktop filter location is not available.', 'BeRocket_AJAX_domain'));
        }
        if (empty($mobile_plan['available'])) {
            return new WP_Error('brapf_one_click_mobile_placement_unavailable', __('A mobile filter location is not available on this plan.', 'BeRocket_AJAX_domain'));
        }

        $state = BeRocket_AAPF_One_Click_Setup::initialize_setup($state);
        $state = BeRocket_AAPF_One_Click_Setup::start_operation(
            $state,
            BeRocket_AAPF_One_Click_Setup::STATUS_CREATING,
            'preflight'
        );
        $state['analysis'] = array(
            'hash' => isset($analysis['recommendation_hash']) ? sanitize_text_field($analysis['recommendation_hash']) : '',
            'analyzed_at' => current_time('mysql', true),
            'catalog' => isset($analysis['snapshot']['catalog']) ? $analysis['snapshot']['catalog'] : array(),
        );
        // This short-lived snapshot is used only for an automatic rollback
        // when initial creation fails. It is never exposed as a user Undo.
        $this->capture_undo_snapshot($state);
        $state = BeRocket_AAPF_One_Click_Setup::save_state($state);
        (new BeRocket_AAPF_One_Click_Telemetry())->record_started($state);

        try {
            $definitions = (new BeRocket_AAPF_One_Click_Filter_Definitions())->create_or_reuse(
                $recommendations,
                $state['setup_id']
            );
            $this->throw_if_error($definitions);
            if (empty($definitions['ids'])) {
                throw new Exception(__('No filter definitions could be created.', 'BeRocket_AJAX_domain'));
            }
            $state['filters']['ids'] = $definitions['ids'];
            $state['undo']['created']['filter_ids'] = $this->merge_ids(
                $state['undo']['created']['filter_ids'],
                $definitions['created_ids']
            );
            $state['operation']['step'] = 'filter_definitions';
            $state = BeRocket_AAPF_One_Click_Setup::save_state($state);

            $desktop_group = $this->ensure_desktop_group($state, $definitions['ids'], $capability);
            $this->throw_if_error($desktop_group);
            $state = $this->store_group($state, 'desktop', $desktop_group);
            $state['operation']['step'] = 'desktop_group';
            $state = BeRocket_AAPF_One_Click_Setup::save_state($state);
            $mobile_group = $this->ensure_mobile_group($state, $definitions['ids'], $capability, $mobile_placement, $desktop_plan);
            $this->throw_if_error($mobile_group);
            $state = $this->store_group($state, 'mobile', $mobile_group);
            $state['operation']['step'] = 'mobile_group';
            $state = BeRocket_AAPF_One_Click_Setup::save_state($state);

            $desktop_attachment = $this->attach_desktop_group($desktop_group['id'], $desktop_plan, $mobile_placement);
            $this->throw_if_error($desktop_attachment);
            $state['placements']['desktop'] = $this->build_placement_state($desktop_plan, $desktop_attachment);
            $state['undo']['created']['widgets'] = $this->merge_widget_descriptors(
                $state['undo']['created']['widgets'],
                array($desktop_attachment)
            );
            $state['operation']['step'] = 'desktop_placement';
            $state = BeRocket_AAPF_One_Click_Setup::save_state($state);
            $mobile_plan = $mobile_placement->resolve($definitions['ids'], $capability, '', $desktop_plan);
            $mobile_attachment = $mobile_placement->attach_group($mobile_group['id'], $mobile_plan);
            $this->throw_if_error($mobile_attachment);

            $state['placements']['mobile'] = $this->build_placement_state($mobile_plan, $mobile_attachment);
            $state['groups']['desktop']['layout'] = $desktop_plan['type'];
            $state['groups']['mobile']['layout'] = $mobile_plan['type'];
            $state['undo']['created']['widgets'] = $this->merge_widget_descriptors(
                $state['undo']['created']['widgets'],
                array($mobile_attachment)
            );
            $state['health'] = (new BeRocket_AAPF_One_Click_Health_Check())->check($state);
            if ($state['health']['status'] !== 'passed') {
                throw new Exception(__('The created filters did not pass the health check.', 'BeRocket_AJAX_domain'));
            }
            $state['generated_by_one_click'] = true;
            $this->clear_rollback_snapshot($state);
            $state['status'] = BeRocket_AAPF_One_Click_Setup::STATUS_ACTIVE;
            $state['operation']['status'] = BeRocket_AAPF_One_Click_Setup::STATUS_ACTIVE;
            $state['operation']['step'] = 'complete';
            $state['operation']['completed_at'] = current_time('mysql', true);
            $state = BeRocket_AAPF_One_Click_Setup::save_state($state);
            (new BeRocket_AAPF_One_Click_Telemetry())->record_completed($state);
            return $state;
        } catch (Exception $error) {
            // Rollback logs technical details server-side. Setup state and
            // telemetry can be surfaced in wp-admin and must stay safe.
            (new BeRocket_AAPF_One_Click_Telemetry())->record_failed($state, 'setup_failed');
            return (new BeRocket_AAPF_One_Click_Setup_Rollback())->rollback_failure($state, $error);
        }
    }

    /**
     * A repeat click is intentionally additive only. Existing filters, groups,
     * placements, and their manual changes are never updated or reattached.
     */
    protected function create_missing_definitions($state, $analysis, $recommendations) {
        $definitions = (new BeRocket_AAPF_One_Click_Filter_Definitions())->create_or_reuse(
            $recommendations,
            $state['setup_id']
        );
        if (is_wp_error($definitions)) {
            return $definitions;
        }
        if (empty($definitions['created_ids'])) {
            // Nothing new was needed. Keep the stored state byte-for-byte
            // intact so a manual configuration is never overwritten.
            return $state;
        }

        $state['filters']['ids'] = $this->merge_ids($state['filters']['ids'], $definitions['ids']);
        $state['analysis']['hash'] = isset($analysis['recommendation_hash'])
            ? sanitize_text_field($analysis['recommendation_hash'])
            : $state['analysis']['hash'];
        $state['analysis']['analyzed_at'] = current_time('mysql', true);
        $state['status'] = BeRocket_AAPF_One_Click_Setup::STATUS_ACTIVE;
        $state['operation']['status'] = BeRocket_AAPF_One_Click_Setup::STATUS_ACTIVE;
        $state['operation']['step'] = 'missing_filter_definitions_created';
        $state['operation']['completed_at'] = current_time('mysql', true);
        return BeRocket_AAPF_One_Click_Setup::save_state($state);
    }

    /** A repeat is additive only while both generated display groups still exist. */
    protected function has_active_setup_groups($state) {
        foreach (array('desktop', 'mobile') as $location) {
            $group_id = isset($state['groups'][$location]['id']) ? absint($state['groups'][$location]['id']) : 0;
            if (!$group_id || get_post_status($group_id) !== 'publish'
                || !BeRocket_AAPF_One_Click_Setup::is_setup_post($group_id, $state['setup_id'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removing groups in wp-admin can leave a sidebar widget or an
     * above-products option pointing at their deleted IDs. Clean only those
     * known stale references before a recovery creation; never touch a live
     * group or an unrelated widget.
     */
    protected function remove_stale_group_placements($state) {
        $missing_group_ids = array();
        foreach (array('desktop', 'mobile') as $location) {
            $group_id = isset($state['groups'][$location]['id']) ? absint($state['groups'][$location]['id']) : 0;
            if ($group_id && get_post_status($group_id) !== 'publish') {
                $missing_group_ids[] = $group_id;
            }
        }
        $missing_group_ids = $this->merge_ids(array(), $missing_group_ids);
        if (empty($missing_group_ids)) {
            return true;
        }

        $instances = get_option('widget_' . self::GROUP_WIDGET_BASE, array());
        $sidebars = get_option('sidebars_widgets', array());
        $instances = is_array($instances) ? $instances : array();
        $sidebars = is_array($sidebars) ? $sidebars : array();
        $previous_instances = $instances;
        $previous_sidebars = $sidebars;
        $widget_ids = array();
        foreach ($instances as $number => $instance) {
            if (!is_array($instance) || empty($instance['group_id'])
                || !in_array(absint($instance['group_id']), $missing_group_ids, true)) {
                continue;
            }
            $widget_ids[] = self::GROUP_WIDGET_BASE . '-' . absint($number);
            unset($instances[$number]);
        }
        if (!empty($widget_ids)) {
            foreach ($sidebars as $sidebar_id => $widgets) {
                if (!is_array($widgets)) {
                    continue;
                }
                $sidebars[$sidebar_id] = array_values(array_diff($widgets, $widget_ids));
            }
            $sidebars_saved = $sidebars === $previous_sidebars || update_option('sidebars_widgets', $sidebars);
            $instances_saved = $instances === $previous_instances || update_option('widget_' . self::GROUP_WIDGET_BASE, $instances);
            if (!$sidebars_saved || !$instances_saved) {
                return new WP_Error('brapf_one_click_stale_widget_cleanup_failed', __('Stale filter widgets could not be removed.', 'BeRocket_AJAX_domain'));
            }
        }

        $options = get_option('br_filters_options', array());
        if (is_array($options) && !empty($options['elements_above_products'])
            && is_array($options['elements_above_products'])) {
            $updated_group_ids = array_values(array_diff(
                array_map('absint', $options['elements_above_products']),
                $missing_group_ids
            ));
            if ($updated_group_ids !== $options['elements_above_products']) {
                $options['elements_above_products'] = $updated_group_ids;
                if (!update_option('br_filters_options', $options)) {
                    return new WP_Error('brapf_one_click_stale_placement_cleanup_failed', __('Stale filter placement could not be removed.', 'BeRocket_AJAX_domain'));
                }
                wp_cache_delete('br_filters_options', 'berocket_framework_option');
            }
        }
        return true;
    }

    protected function ensure_desktop_group($state, $filter_ids, $capability) {
        $group_id = isset($state['groups']['desktop']['id']) ? absint($state['groups']['desktop']['id']) : 0;
        if ($group_id && get_post_status($group_id) === 'publish'
            && BeRocket_AAPF_One_Click_Setup::is_setup_post($group_id, $state['setup_id'])) {
            $title_updated = $this->update_legacy_group_title($group_id, 'desktop');
            if (is_wp_error($title_updated)) {
                return $title_updated;
            }
            $settings = get_post_meta($group_id, self::GROUP_SETTINGS_META, true);
            return array(
                'id' => $group_id,
                'filter_ids' => $filter_ids,
                'settings' => is_array($settings) ? $settings : array(),
                'created' => false,
            );
        }
        $group_id = wp_insert_post(array(
            'post_title' => $this->get_group_title('desktop'),
            'post_type' => self::GROUP_POST_TYPE,
            'post_status' => 'publish',
        ));
        if (is_wp_error($group_id)) {
            return $group_id;
        }
        $group = BeRocket_AAPF_group_filters::getInstance();
        $settings = $group->get_option($group_id);
        $preset = BeRocket_AAPF_One_Click_Capabilities::get_group_preset('desktop', $capability);
        $settings = $this->merge_group_settings($settings, $preset['group_settings']);
        $settings['filters'] = $filter_ids;
        update_post_meta($group_id, self::GROUP_SETTINGS_META, $settings);
        BeRocket_AAPF_One_Click_Setup::mark_post($group_id, $state['setup_id'], 'group');
        return array(
            'id' => absint($group_id),
            'filter_ids' => $filter_ids,
            'settings' => $settings,
            'created' => true,
        );
    }

    protected function ensure_mobile_group($state, $filter_ids, $capability, $mobile_placement, $desktop_plan = array()) {
        $group_id = isset($state['groups']['mobile']['id']) ? absint($state['groups']['mobile']['id']) : 0;
        if ($group_id && get_post_status($group_id) === 'publish'
            && BeRocket_AAPF_One_Click_Setup::is_setup_post($group_id, $state['setup_id'])) {
            $title_updated = $this->update_legacy_group_title($group_id, 'mobile');
            if (is_wp_error($title_updated)) {
                return $title_updated;
            }
            $settings = get_post_meta($group_id, self::GROUP_SETTINGS_META, true);
            return array(
                'id' => $group_id,
                'filter_ids' => $filter_ids,
                'settings' => is_array($settings) ? $settings : array(),
                'created' => false,
            );
        }
        $created = $mobile_placement->create_group($filter_ids, $state['setup_id'], $capability, '', $desktop_plan);
        if (is_wp_error($created)) {
            return $created;
        }
        return array(
            'id' => $created['group_id'],
            'filter_ids' => $filter_ids,
            'settings' => $created['group_settings'],
            'created' => true,
        );
    }

    protected function get_group_title($location) {
        return $location === 'mobile'
            ? __('Featured filters — mobile', 'BeRocket_AJAX_domain')
            : __('Featured filters', 'BeRocket_AJAX_domain');
    }

    /** Rename only the exact machine-generated legacy title, never a manual edit. */
    protected function update_legacy_group_title($group_id, $location) {
        $legacy_titles = $location === 'mobile'
            ? array(__('Mobile filters — one-click setup', 'BeRocket_AJAX_domain'))
            : array(__('Desktop filters — one-click setup', 'BeRocket_AJAX_domain'));
        if (!in_array(get_post_field('post_title', $group_id), $legacy_titles, true)) {
            return true;
        }
        return wp_update_post(array(
            'ID' => absint($group_id),
            'post_title' => $this->get_group_title($location),
        ), true);
    }

    protected function store_group($state, $location, $group) {
        $state['groups'][$location] = array(
            'id' => absint($group['id']),
            'filter_ids' => $group['filter_ids'],
            'layout' => $location === 'desktop' ? 'sidebar' : 'mobile',
            'visibility' => isset($group['settings']['hide_group']) ? $group['settings']['hide_group'] : array(),
        );
        if (!empty($group['created'])) {
            $state['undo']['created']['group_ids'] = $this->merge_ids(
                $state['undo']['created']['group_ids'],
                array($group['id'])
            );
        }
        return $state;
    }

    protected function attach_desktop_group($group_id, $plan, $mobile_placement) {
        if ($plan['type'] === 'sidebar_widget') {
            return $this->attach_group_widget($group_id, $plan['sidebar_id']);
        }
        if ($plan['type'] === 'above_products') {
            if (empty($plan['option_mutations']) && !empty($plan['option_mutation'])) {
                $plan['option_mutations'] = array($plan['option_mutation']);
            }
            return $mobile_placement->attach_group($group_id, $plan);
        }
        if ($plan['type'] === 'berocket_controlled_sidebar') {
            return $mobile_placement->attach_group($group_id, $plan);
        }
        return new WP_Error('brapf_one_click_unknown_desktop_placement', __('Unknown desktop placement.', 'BeRocket_AJAX_domain'));
    }

    protected function attach_group_widget($group_id, $sidebar_id) {
        $sidebars = get_option('sidebars_widgets', array());
        $instances = get_option('widget_' . self::GROUP_WIDGET_BASE, array());
        $previous_sidebars = is_array($sidebars) ? $sidebars : array();
        $previous_instances = is_array($instances) ? $instances : array();
        $sidebars = $previous_sidebars;
        $instances = $previous_instances;
        $existing_number = 0;
        foreach ($instances as $number => $instance) {
            if (is_array($instance) && isset($instance['group_id']) && absint($instance['group_id']) === absint($group_id)) {
                $existing_number = absint($number);
                break;
            }
        }
        $numbers = array_filter(array_map('absint', array_keys($instances)));
        $number = $existing_number ? $existing_number : ($numbers ? max($numbers) + 1 : 2);
        $widget_id = self::GROUP_WIDGET_BASE . '-' . $number;
        if (!isset($sidebars[$sidebar_id]) || !is_array($sidebars[$sidebar_id])) {
            $sidebars[$sidebar_id] = array();
        }
        $created = !in_array($widget_id, $sidebars[$sidebar_id], true);
        if ($created) {
            $sidebars[$sidebar_id][] = $widget_id;
        }
        if (!$existing_number) {
            $instances[$number] = array('group_id' => absint($group_id));
            $created = true;
        }
        $sidebars_saved = $sidebars === $previous_sidebars || update_option('sidebars_widgets', $sidebars);
        $instances_saved = $instances === $previous_instances || update_option('widget_' . self::GROUP_WIDGET_BASE, $instances);
        if (!$sidebars_saved || !$instances_saved) {
            return new WP_Error('brapf_one_click_desktop_widget_save_failed', __('The desktop sidebar widget could not be saved.', 'BeRocket_AJAX_domain'));
        }
        return array(
            'type' => 'sidebar_widget',
            'sidebar_id' => $sidebar_id,
            'widget' => array('id_base' => self::GROUP_WIDGET_BASE, 'number' => $number),
            'created' => $created,
            'previous_sidebars_widgets' => $previous_sidebars,
            'previous_widget_instances' => $previous_instances,
        );
    }

    protected function capture_undo_snapshot(&$state) {
        if (!empty($state['undo']['available']) || !empty($state['undo']['created']['filter_ids'])
            || !empty($state['undo']['created']['group_ids'])) {
            return;
        }
        $state['undo']['previous'] = array(
            'sidebars_widgets' => get_option('sidebars_widgets', array()),
            'widget_instances' => get_option('widget_' . self::GROUP_WIDGET_BASE, array()),
            'group_settings' => array(),
            'plugin_options' => get_option('br_filters_options', array()),
        );
    }

    /** Remove rollback data after a successful operation; there is no user Undo. */
    protected function clear_rollback_snapshot(&$state) {
        $state['undo'] = array(
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

    protected function build_placement_state($plan, $attachment) {
        return array(
            'type' => isset($attachment['type']) ? $attachment['type'] : $plan['type'],
            'sidebar_id' => isset($attachment['sidebar_id']) ? $attachment['sidebar_id'] : $plan['sidebar_id'],
            'widget' => isset($attachment['widget']) ? $attachment['widget'] : array(),
            'fallback' => !empty($plan['is_fallback']) ? 'controlled' : '',
            'details' => array(
                'label' => isset($plan['label']) ? $plan['label'] : '',
                'reason' => isset($plan['reason']) ? $plan['reason'] : array(),
            ),
        );
    }

    protected function merge_group_settings($settings, $changes) {
        $settings = is_array($settings) ? $settings : array();
        $changes = is_array($changes) ? $changes : array();
        if (isset($changes['hide_group']) && is_array($changes['hide_group'])) {
            $settings['hide_group'] = array_merge(
                isset($settings['hide_group']) && is_array($settings['hide_group']) ? $settings['hide_group'] : array(),
                $changes['hide_group']
            );
            unset($changes['hide_group']);
        }
        return array_merge($settings, $changes);
    }

    protected function merge_ids($existing, $new) {
        return array_values(array_unique(array_filter(array_map('absint', array_merge((array)$existing, (array)$new)))));
    }

    protected function merge_widget_descriptors($existing, $attachments) {
        $widgets = is_array($existing) ? $existing : array();
        foreach ((array)$attachments as $attachment) {
            if (empty($attachment['created']) || empty($attachment['widget']['id_base'])) {
                continue;
            }
            $descriptor = array(
                'id_base' => $attachment['widget']['id_base'],
                'number' => absint($attachment['widget']['number']),
                'sidebar_id' => isset($attachment['sidebar_id']) ? $attachment['sidebar_id'] : '',
            );
            $widgets[md5(wp_json_encode($descriptor))] = $descriptor;
        }
        return array_values($widgets);
    }

    protected function throw_if_error($result) {
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message(), 0);
        }
    }

}
new BeRocket_AAPF_One_Click_Setup_Orchestrator();
