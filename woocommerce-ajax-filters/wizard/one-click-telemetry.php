<?php
/**
 * Local, aggregate quality metrics for the one-click setup.
 *
 * No catalog, product, user, or site-identifying data is sent anywhere. Hosts
 * that have their own analytics pipeline can subscribe to the action emitted
 * from record().
 */
class BeRocket_AAPF_One_Click_Telemetry {
    const OPTION_NAME = 'br_aapf_one_click_telemetry';
    const SCHEMA_VERSION = 1;
    const MAX_DURATIONS = 100;

    public function record_started($state) {
        return $this->record('started', $state);
    }

    public function record_completed($state) {
        return $this->record('completed', $state);
    }

    public function record_failed($state, $error_code = '') {
        $allowed_codes = array('setup_failed', 'rollback_failed');
        $error_code = sanitize_key($error_code);
        return $this->record('failed', $state, array(
            'error_code' => in_array($error_code, $allowed_codes, true) ? $error_code : 'setup_failed',
        ));
    }

    public function get_summary() {
        $metrics = $this->get_metrics();
        $attempts = $metrics['completed'] + $metrics['failed'];
        $durations = $metrics['click_to_visible_seconds'];
        sort($durations, SORT_NUMERIC);
        $median = 0;
        if (!empty($durations)) {
            $middle = (int)floor(count($durations) / 2);
            $median = count($durations) % 2
                ? $durations[$middle]
                : (int)round(($durations[$middle - 1] + $durations[$middle]) / 2);
        }
        return array(
            'started' => $metrics['started'],
            'completed' => $metrics['completed'],
            'failed' => $metrics['failed'],
            'attempts' => $attempts,
            'success_rate' => $attempts ? round($metrics['completed'] / $attempts, 4) : 0.0,
            'median_click_to_visible_seconds' => $median,
            'last_event' => $metrics['last_event'],
            'updated_at' => $metrics['updated_at'],
        );
    }

    protected function record($event, $state, $context = array()) {
        $metrics = $this->get_metrics();
        if (!isset($metrics[$event])) {
            return $this->get_summary();
        }
        $metrics[$event]++;
        $duration = $this->get_duration($state);
        if ($event === 'completed' && $duration !== null) {
            $metrics['click_to_visible_seconds'][] = $duration;
            if (count($metrics['click_to_visible_seconds']) > self::MAX_DURATIONS) {
                $metrics['click_to_visible_seconds'] = array_slice($metrics['click_to_visible_seconds'], -self::MAX_DURATIONS);
            }
        }
        $metrics['last_event'] = array(
            'event' => $event,
            'at' => current_time('mysql', true),
            'duration_seconds' => $duration,
            'error_code' => $event === 'failed' && !empty($context['error_code']) ? sanitize_key($context['error_code']) : '',
        );
        $metrics['updated_at'] = current_time('mysql', true);
        update_option(self::OPTION_NAME, $metrics, false);
        $summary = $this->get_summary();
        do_action('brapf_one_click_telemetry_recorded', $event, $summary, $metrics['last_event']);
        return $summary;
    }

    protected function get_metrics() {
        $default = array(
            'schema_version' => self::SCHEMA_VERSION,
            'started' => 0,
            'completed' => 0,
            'failed' => 0,
            'click_to_visible_seconds' => array(),
            'last_event' => array(),
            'updated_at' => '',
        );
        $metrics = get_option(self::OPTION_NAME, array());
        $metrics = wp_parse_args(is_array($metrics) ? $metrics : array(), $default);
        if ((int)$metrics['schema_version'] !== self::SCHEMA_VERSION) {
            return $default;
        }
        foreach (array('started', 'completed', 'failed') as $key) {
            $metrics[$key] = absint($metrics[$key]);
        }
        $durations = array();
        foreach ((array)$metrics['click_to_visible_seconds'] as $duration) {
            if (is_numeric($duration) && (float)$duration >= 0) {
                $durations[] = absint($duration);
            }
        }
        $metrics['click_to_visible_seconds'] = $durations;
        $metrics['last_event'] = is_array($metrics['last_event']) ? $metrics['last_event'] : array();
        $metrics['updated_at'] = is_string($metrics['updated_at']) ? $metrics['updated_at'] : '';
        return $metrics;
    }

    protected function get_duration($state) {
        $started_at = isset($state['operation']['started_at']) ? $state['operation']['started_at'] : '';
        $started = is_string($started_at) ? strtotime($started_at . ' UTC') : false;
        if ($started === false) {
            return null;
        }
        return max(0, time() - $started);
    }
}
