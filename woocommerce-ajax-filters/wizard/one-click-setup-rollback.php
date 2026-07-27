<?php
/** Restores one-click mutations and deletes only posts owned by that setup. */
class BeRocket_AAPF_One_Click_Setup_Rollback {
    const GROUP_WIDGET_BASE = 'berocket_aapf_group';

    public function rollback_failure($state, $error) {
        if ($error instanceof Exception) {
            error_log('BeRocket one-click setup rollback started after failure: ' . $error->getMessage());
        }
        $result = $this->rollback($state, BeRocket_AAPF_One_Click_Setup::STATUS_FAILED);
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_Error(
            'brapf_one_click_setup_failed',
            __('The one-click setup could not be completed. Please try again.', 'BeRocket_AJAX_domain')
        );
    }

    /** Persist rolling_back first, then restore snapshots and owned posts. */
    protected function rollback($state, $final_status) {
        $state = BeRocket_AAPF_One_Click_Setup::start_operation(
            $state,
            BeRocket_AAPF_One_Click_Setup::STATUS_ROLLING_BACK,
            'rollback'
        );
        BeRocket_AAPF_One_Click_Setup::save_state($state);
        $errors = array();
        $previous = isset($state['undo']['previous']) && is_array($state['undo']['previous'])
            ? $state['undo']['previous']
            : array();

        $this->restore_option('sidebars_widgets', isset($previous['sidebars_widgets']) ? $previous['sidebars_widgets'] : array(), $errors);
        $this->restore_option('widget_' . self::GROUP_WIDGET_BASE, isset($previous['widget_instances']) ? $previous['widget_instances'] : array(), $errors);
        $this->restore_option('br_filters_options', isset($previous['plugin_options']) ? $previous['plugin_options'] : array(), $errors);
        wp_cache_delete('br_filters_options', 'berocket_framework_option');
        $this->restore_group_settings(isset($previous['group_settings']) ? $previous['group_settings'] : array(), $errors);
        $this->delete_created_posts($state, $errors);

        if (!empty($errors)) {
            return $this->save_failed_rollback_state($state, $errors);
        }
        return $this->save_completed_rollback_state($final_status);
    }

    protected function restore_option($name, $value, &$errors) {
        if (get_option($name, null) === $value) {
            return;
        }
        if (!update_option($name, $value)) {
            $errors[] = 'restore_option:' . $name;
        }
    }

    protected function restore_group_settings($settings, &$errors) {
        foreach ((array)$settings as $group_id => $group_settings) {
            $group_id = absint($group_id);
            if (!$group_id || !is_array($group_settings)) {
                continue;
            }
            if (get_post_meta($group_id, 'br_filters_group', true) === $group_settings) {
                continue;
            }
            if (false === update_post_meta($group_id, 'br_filters_group', $group_settings)) {
                $errors[] = 'restore_group:' . $group_id;
            }
        }
    }

    protected function delete_created_posts($state, &$errors) {
        $created = isset($state['undo']['created']) && is_array($state['undo']['created'])
            ? $state['undo']['created']
            : array();
        $post_ids = array_merge(
            isset($created['group_ids']) ? (array)$created['group_ids'] : array(),
            isset($created['filter_ids']) ? (array)$created['filter_ids'] : array()
        );
        foreach (array_values(array_unique(array_filter(array_map('absint', $post_ids)))) as $post_id) {
            if (!BeRocket_AAPF_One_Click_Setup::is_setup_post($post_id, $state['setup_id'])) {
                continue;
            }
            if (!wp_delete_post($post_id, true)) {
                $errors[] = 'delete_post:' . $post_id;
            }
        }
    }

    protected function save_completed_rollback_state($status) {
        $result = BeRocket_AAPF_One_Click_Setup::get_default_state();
        $result['status'] = $status;
        $result['operation']['status'] = $status;
        $result['operation']['step'] = 'rollback_complete';
        $result['operation']['completed_at'] = current_time('mysql', true);
        $result['operation']['error_code'] = $status === BeRocket_AAPF_One_Click_Setup::STATUS_FAILED ? 'brapf_one_click_setup_failed' : '';
        $result['operation']['error_message'] = $status === BeRocket_AAPF_One_Click_Setup::STATUS_FAILED
            ? __('The one-click setup could not be completed. Please try again.', 'BeRocket_AJAX_domain')
            : '';
        return BeRocket_AAPF_One_Click_Setup::save_state($result);
    }

    protected function save_failed_rollback_state($state, $errors) {
        error_log('BeRocket one-click setup rollback failed: ' . implode(', ', $errors));
        $state['status'] = BeRocket_AAPF_One_Click_Setup::STATUS_FAILED;
        $state['operation']['status'] = BeRocket_AAPF_One_Click_Setup::STATUS_FAILED;
        $state['operation']['step'] = 'rollback_failed';
        $state['operation']['completed_at'] = current_time('mysql', true);
        $state['operation']['error_code'] = 'brapf_one_click_rollback_failed';
        $state['operation']['error_message'] = __('The one-click setup could not be completed. Please try again.', 'BeRocket_AJAX_domain');
        BeRocket_AAPF_One_Click_Setup::save_state($state);
        return new WP_Error('brapf_one_click_rollback_failed', __('The one-click setup could not be completed. Please try again.', 'BeRocket_AJAX_domain'));
    }
}
