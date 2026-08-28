<?php
if( ! class_exists('BeRocket_aapf_variations_tables_postmeta_addon') ) {
    class BeRocket_aapf_variations_tables_postmeta_addon {
        const COUNTS_DIRTY_OPTION = 'berocket_cpm_update_required';
        const COUNTS_REFRESH_HOOK = 'braapf_custom_postmeta_refresh_counts';
        const COUNTS_REFRESH_GROUP = 'berocket-aapf';
        public $additional_table_class = false;
        public $position_update = 1;
        public $required_update = false;
        public $position_last = 1;
        public $post_clauses_number = 1;
        function __construct() {
            //Generate tables
            add_filter('BeRocket_aapf_variations_tables_addon_table_list', array($this, 'table_list'));
            add_filter('BeRocket_aapf_variations_tables_addon_check_table_list', array($this, 'table_list'));
            add_filter('BeRocket_aapf_variations_tables_addon_position_data', array($this, 'position_data'), 100000, 2);
            add_action('updated_post_meta', array($this, 'save_filter'), 10, 4);
            add_action('added_post_meta', array($this, 'save_filter'), 10, 4);
            add_action('deleted_post_meta', array($this, 'save_filter'), 10, 4);
            add_filter('braapf_additional_table_ended_position', array($this, 'add_end_string'), 100);
            //Replace 
            add_action('BeRocket_aapf_variations_tables_addon_status', array($this, 'addon_active'), 10, 3);
            add_action( 'admin_footer', array($this, 'admin_footer') );
            add_action('braapf_additional_tables_before_validation', array($this, 'finalize_counts'), 10, 1);
            add_filter('braapf_additional_tables_generation_is_valid', array($this, 'validate_generation'), 10, 2);
            add_filter('braapf_additional_tables_schema_is_valid', array($this, 'validate_schema'), 10, 2);
            add_action(self::COUNTS_REFRESH_HOOK, array($this, 'run_counts_refresh'));
        }
        function addon_active($status, $create_position, $instance) {
            if( is_admin() && $status == 'ready' ) {
                if( strpos($create_position, 'cpm') === FALSE ) {
                    $instance->request_regeneration('custom_postmeta_plan_changed');
                    $status = 'start';
                }
            }
            if($status == 'ready') {
                add_filter('berocket_aapf_postmeta_main_query', array($this, 'postmeta_main_query'));
                add_filter('berocket_aapf_recount_postmeta_query', array($this, 'recount_postmeta_query'), 10, 3);
                add_filter('bapf_uparse_generate_meta_query_postmeta_meta_query_use', array($this, 'disable'), 10, 1);
                add_filter('bapf_uparse_generate_custom_query_each', array($this, 'custom_query_each'), 10000, 4);
                add_filter('bapf_uparse_generate_posts_in_each', array($this, 'posts_in'), 10000, 4);
            }
        }
        function disable() {
            return false;
        }
        function add_end_string($ended) {
            $ended = $ended . ' cpm ';
            return $ended;
        }
        function table_list($list) {
            $list[] = 'braapf_custom_post_meta';
            $list[] = 'braapf_product_post_meta';
            return $list;
        }
        function position_data($position_data, $instance) {
            $this->position_last = count($position_data);
            $position_data[] = array(
                'percentage' => 4,
                'execute'    => array($this, 'create_table'),
                'ajax_only'  => true
            );
            $this->additional_table_class = $instance;
            $this->position_update = count($position_data);
            $position_data[] = array(
                'percentage' => 15,
                'execute'    => array($this, 'create_post_meta'),
                'ajax_only'  => true
            );
            $position_data[] = array(
                'percentage' => 120,
                'execute'    => array($this, 'generate_post_meta'),
                'ajax_only'  => true
            );
            $this->is_required_update();
            return $position_data;
        }
        function create_table($current_position, $instance) {
            $run_data = $instance->get_current_create_position_data();
            if( ! empty($run_data) && ! empty($run_data['run']) ) {
                return false;
            }
            $instance->set_current_create_position_data(array(
                'status' => 0,
                'run' => true,
            ));
            global $wpdb;
            $collate = $instance->get_charset_collate();
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            //braapf_custom_post_meta table
            $table_name = $wpdb->prefix . 'braapf_custom_post_meta';
            if( $instance->table_exists($table_name) ) {
                $instance->migrate_table_primary_key($table_name, array('meta_id'));
            }
            $sql = "CREATE TABLE $table_name (
    meta_id bigint(20) NOT NULL AUTO_INCREMENT,
    meta varchar(120) NOT NULL,
    slug varchar(120) NOT NULL,
    count bigint(20) DEFAULT '1',
    name text NOT NULL,
    PRIMARY KEY (meta_id),
    INDEX meta (meta),
    INDEX slug (slug),
    INDEX metaslug (meta, slug),
    UNIQUE KEY uniqueid (meta, slug)
) $collate;";
            $instance->run_db_delta($sql);
            if( ! $instance->table_schema_is_valid($table_name,
                array('meta_id', 'meta', 'slug', 'count', 'name'),
                array(
                    'PRIMARY' => array('meta_id'),
                    'uniqueid' => array('meta', 'slug'),
                ),
                array(
                    'meta_id' => array(
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => true,
                    ),
                )) ) {
                throw new RuntimeException('custom_postmeta_schema_validation_failed');
            }
            $instance->reset_table($table_name);
            //braapf_product_post_meta table
            $table_name = $wpdb->prefix . 'braapf_product_post_meta';
            $sql = "CREATE TABLE $table_name (
    meta_id bigint(20) NOT NULL,
    product_id bigint(20) NOT NULL,
    INDEX meta_id (meta_id),
    INDEX product_id (product_id),
    INDEX metaslug (meta_id, product_id),
    UNIQUE KEY uniqueid (meta_id, product_id)
) $collate;";
            $instance->run_db_delta($sql);
            if( ! $instance->table_schema_is_valid($table_name,
                array('meta_id', 'product_id'),
                array(
                    'uniqueid' => array('meta_id', 'product_id'),
                    'product_id' => array('product_id'),
                ),
                array(
                    'meta_id' => array('type' => 'bigint', 'nullable' => false),
                    'product_id' => array('type' => 'bigint', 'nullable' => false),
                )) ) {
                throw new RuntimeException('custom_postmeta_mapping_schema_validation_failed');
            }
            $instance->reset_table($table_name);
            //get_current post meta
            $BeRocket_AAPF_single_filter = BeRocket_AAPF_single_filter::getInstance();
            $filters = $BeRocket_AAPF_single_filter->get_custom_posts();
            $postmeta = array();
            foreach($filters as $filter) {
                $filter_option = $BeRocket_AAPF_single_filter->get_option($filter);
                if( ! empty($filter_option['filter_type']) && $filter_option['filter_type'] == 'custom_postmeta' && ! empty($filter_option['custom_postmeta']) ) {
                    $postmeta[] = $filter_option['custom_postmeta'];
                }
            }
            $postmeta = $this->normalize_postmeta_keys($postmeta);
            update_option('berocket_aapf_custom_post_meta', $postmeta);
            $instance->set_current_create_position_data(array(
                'status' => 0,
                'run' => false,
            ));
            if( count($postmeta) == 0 ) {
                $instance->set_current_create_position($current_position+3);
            } else {
                $instance->increment_create_position();
            }
        }
        function create_post_meta($current_position, $instance) {
            $run_data = $instance->get_current_create_position_data();
            if( empty($run_data) || ! empty($run_data['run']) ) {
                return false;
            }
            $run_data['run'] = true;
            $instance->set_current_create_position_data($run_data);
            global $wpdb;
            $postmeta = $this->normalize_postmeta_keys(get_option('berocket_aapf_custom_post_meta'));
            update_option('berocket_aapf_custom_post_meta', $postmeta);
            $postmeta_data = false;
            if( count($postmeta) > 0 ) {
                $meta_key_placeholders = implode(', ', array_fill(0, count($postmeta), '%s'));
                $sql = "SELECT MIN(postmeta.meta_id) as min, MAX(postmeta.meta_id) as max
                FROM {$wpdb->postmeta} as postmeta
                JOIN {$wpdb->posts} as posts ON posts.ID = postmeta.post_id
                WHERE postmeta.meta_key IN ({$meta_key_placeholders})
                AND posts.post_type = %s AND postmeta.meta_value != %s";
                $sql_args = array_merge($postmeta, array('product', ''));
                $sql = $wpdb->prepare($sql, $sql_args);
                $postmeta_data = $wpdb->get_row($sql);
                if( $postmeta_data === null && ! empty($wpdb->last_error) ) {
                    $instance->save_query_error($sql);
                    $run_data['run'] = false;
                    $instance->set_current_create_position_data($run_data);
                    return false;
                }
            }
            if( ! empty($postmeta_data) && isset($postmeta_data->min) && isset($postmeta_data->max) ) {
                $instance->set_current_create_position_data(array(
                    'status' => 0,
                    'run' => false,
                    'start_id' => $postmeta_data->min,
                    'cursor_id' => max(0, intval($postmeta_data->min) - 1),
                    'min_id' => $postmeta_data->min,
                    'max_id' => $postmeta_data->max
                ));
                $instance->increment_create_position();
            } else {
                $instance->set_current_create_position_data(array(
                    'status' => 0,
                    'run' => false,
                ));
                $instance->set_current_create_position($current_position+2);
            }
        }
        function generate_post_meta($current_position = false, $instance = false) {
            $run_data = $instance->get_current_create_position_data();
            if( empty($run_data) || ! empty($run_data['run']) ) {
                return false;
            }
            $run_data['run'] = true;
            $instance->set_current_create_position_data($run_data);
            $start_id = intval($run_data['start_id']);
            $min_id = intval($run_data['min_id']);
            $max_id = intval($run_data['max_id']);
            $cursor_id = isset($run_data['cursor_id']) ? intval($run_data['cursor_id']) : max(0, $start_id - 1);
            $batch_size = max(1, intval(apply_filters('berocket_insert_table_braapf_product_variation_post_meta_end', 1000)));
            $stored_postmeta = get_option('berocket_aapf_custom_post_meta');
            $postmeta = $this->normalize_postmeta_keys($stored_postmeta);
            if( $stored_postmeta !== $postmeta ) {
                update_option('berocket_aapf_custom_post_meta', $postmeta);
            }
            $batch_results = array();
            if( count($postmeta) > 0 ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'braapf_product_post_meta';
                $meta_key_placeholders = implode(', ', array_fill(0, count($postmeta), '%s'));
                $sql_select = "SELECT postmeta.meta_id, postmeta.post_id as id, postmeta.meta_key AS name, postmeta.meta_value as val
                FROM {$wpdb->postmeta} as postmeta
                JOIN {$wpdb->posts} as posts ON posts.ID = postmeta.post_id
                WHERE postmeta.meta_key IN ({$meta_key_placeholders})
                AND posts.post_type = %s AND postmeta.meta_value != %s
                AND postmeta.meta_id > %d
                AND postmeta.meta_id <= %d
                ORDER BY postmeta.meta_id ASC
                LIMIT %d";
                $sql_args = array_merge($postmeta, array('product', '', $cursor_id, $max_id, $batch_size));
                $sql_select = $wpdb->prepare($sql_select, $sql_args);
                $batch_results = $wpdb->get_results($sql_select);
                if( $batch_results === null && ! empty($wpdb->last_error) ) {
                    $instance->save_query_error($sql_select);
                    $run_data['run'] = false;
                    $instance->set_current_create_position_data($run_data);
                    return false;
                }
                if( ! is_array($batch_results) ) {
                    $batch_results = array();
                }
                $product_metas = array();
                $definition_values = array();
                foreach($batch_results as $post_meta_val) {
                    $name = $this->sanitize_name($post_meta_val->name);
                    $val = $this->sanitize_name($post_meta_val->val);
                    if( ! isset($product_metas[$name]) ) {
                        $product_metas[$name] = array();
                    }
                    if( ! isset($product_metas[$name][$val]) ) {
                        $product_metas[$name][$val] = array();
                    }
                    $product_metas[$name][$val][intval($post_meta_val->id)] = true;
                    $definition_key = $name . "\0" . $val;
                    if( ! isset($definition_values[$definition_key]) ) {
                        $display_name = apply_filters(
                            'berocket_aapf_custom_postmeta_value_name',
                            (string)$post_meta_val->val,
                            $post_meta_val->val,
                            $post_meta_val->name
                        );
                        $definition_values[$definition_key] = $wpdb->prepare(
                            '(%s, %s, %s)',
                            $name,
                            $val,
                            (string)$display_name
                        );
                    }
                }
                if( ! empty($definition_values) ) {
                    $table_name_meta = $wpdb->prefix . 'braapf_custom_post_meta';
                    foreach(array_chunk(array_values($definition_values), 500) as $definition_chunk) {
                        $definition_sql = "INSERT IGNORE INTO {$table_name_meta} (meta, slug, name) VALUES " . implode(',', $definition_chunk);
                        $query_status = $wpdb->query($definition_sql);
                        if( $query_status === false ) {
                            $instance->save_query_error($definition_sql);
                            $run_data['run'] = false;
                            $instance->set_current_create_position_data($run_data);
                            return false;
                        }
                    }
                }
                $product_meta_insert = array();
                foreach($product_metas as $name => $products_meta) {
                    $meta_slugs = array_keys($products_meta);
                    if( count($meta_slugs) == 0 ) {
                        continue;
                    }
                    $meta_slug_placeholders = implode(', ', array_fill(0, count($meta_slugs), '%s'));
                    $get_meta_id = "SELECT meta_id, slug FROM {$table_name_meta} WHERE meta = %s AND slug IN ({$meta_slug_placeholders})";
                    $get_meta_id = $wpdb->prepare($get_meta_id, array_merge(array($name), $meta_slugs));
                    $meta_id_results = $wpdb->get_results($get_meta_id);
                    if( $meta_id_results === null && ! empty($wpdb->last_error) ) {
                        $instance->save_query_error($get_meta_id);
                        $run_data['run'] = false;
                        $instance->set_current_create_position_data($run_data);
                        return false;
                    }
                    $post_meta_slug_id = array();
                    foreach((array)$meta_id_results as $post_meta_ids) {
                        $post_meta_slug_id[$post_meta_ids->slug] = $post_meta_ids->meta_id;
                    }
                    foreach($products_meta as $meta_val => $products_list) {
                        foreach(array_keys($products_list) as $product_id) {
                            if( ! empty($post_meta_slug_id[$meta_val]) ) {
                                $product_meta_insert[] = $wpdb->prepare("(%d, %d)", $post_meta_slug_id[$meta_val], $product_id);
                            }
                        }
                    }
                }
                if( count($product_meta_insert) > 0 ) {
                    foreach(array_chunk($product_meta_insert, 500) as $product_meta_insert_chunk) {
                        $include_sql = "INSERT IGNORE INTO {$table_name} (meta_id, product_id) VALUES ".implode(',', $product_meta_insert_chunk).';';
                        $query_status = $wpdb->query($include_sql);
                        if( $query_status === false ) {
                            $instance->save_query_error($include_sql);
                            $run_data['run'] = false;
                            $instance->set_current_create_position_data($run_data);
                            return false;
                        }
                    }
                }
            }
            $result_count = count($batch_results);
            $last_meta_id = $cursor_id;
            if( $result_count > 0 ) {
                $last_result = $batch_results[$result_count - 1];
                $last_meta_id = intval($last_result->meta_id);
            }
            $status = ($max_id <= $min_id ? 100 : max(0, min(100, (($last_meta_id - $min_id) / ($max_id - $min_id) * 100))));
            $has_more = count($postmeta) > 0 && $result_count >= $batch_size && $last_meta_id < $max_id;
            if( $has_more ) {
                $instance->set_current_create_position_data(array(
                    'status' => min(99, $status),
                    'run' => false,
                    'start_id' => $last_meta_id + 1,
                    'cursor_id' => $last_meta_id,
                    'min_id' => $min_id,
                    'max_id' => $max_id
                ));
            } else {
                $this->mark_counts_dirty();
                $instance->set_current_create_position_data(array(
                    'status' => 0,
                    'run' => false,
                ));
                $instance->increment_create_position();
            }
        }
        function normalize_postmeta_keys($postmeta) {
            if( ! is_array($postmeta) ) {
                return array();
            }
            $normalized = array();
            foreach($postmeta as $postmeta_key) {
                if( ! is_scalar($postmeta_key) ) {
                    continue;
                }
                $postmeta_key = (string)$postmeta_key;
                if( $postmeta_key === '' ) {
                    continue;
                }
                $normalized[$postmeta_key] = $postmeta_key;
            }
            return array_values($normalized);
        }
        function normalize_integer_ids($ids) {
            if( ! is_array($ids) ) {
                return array();
            }
            $normalized = array();
            foreach($ids as $id) {
                if( is_int($id) ) {
                    $id = intval($id);
                } elseif( is_string($id) && preg_match('/^\d+$/D', $id) ) {
                    $id = intval($id);
                } else {
                    continue;
                }
                if( $id > 0 ) {
                    $normalized[$id] = $id;
                }
            }
            return array_values($normalized);
        }
        function sanitize_name($name) {
            $name = sanitize_title($name);
            $name = mb_substr($name, 0, 100);
            return $name;
        }            
        function get_charset_collate() {
            global $wpdb;
            return $wpdb->has_cap('collation') ? $wpdb->get_charset_collate() : '';
        }
        function save_filter($meta_id, $object_id, $meta_key, $meta_value) {
            $stored_postmeta = get_option('berocket_aapf_custom_post_meta');
            $postmeta = $this->normalize_postmeta_keys($stored_postmeta);
            if( $stored_postmeta !== $postmeta ) {
                update_option('berocket_aapf_custom_post_meta', $postmeta);
            }
            if( $meta_key == 'br_product_filter' && ! empty($meta_value) && ! empty($meta_value['filter_type']) && $meta_value['filter_type'] == 'custom_postmeta' && ! empty($meta_value['custom_postmeta']) ) {
                if( ! in_array($meta_value['custom_postmeta'], $postmeta, true) ) {
                    $postmeta[] = $meta_value['custom_postmeta'];
                    $postmeta = $this->normalize_postmeta_keys($postmeta);
                    update_option('berocket_aapf_custom_post_meta', $postmeta);
                    $this->required_update = true;
                    $this->is_required_update();
                }
            }
            if( in_array($meta_key, $postmeta, true) ) {
                if( get_post_type($object_id) == 'product' ) {
                    if( $this->update_post_meta_product($meta_key, $object_id) === false ) {
                        global $wpdb;
                        error_log('BeRocket custom postmeta incremental update failed: ' . $wpdb->last_error);
                        if( $this->additional_table_class
                            && is_callable(array($this->additional_table_class, 'request_regeneration')) ) {
                            $this->additional_table_class->request_regeneration('custom_postmeta_incremental_update_failed');
                        }
                        return false;
                    }
                    $this->mark_counts_dirty();
                }
            }
        }
        function update_post_meta_product($meta_key, $product_id) {
            global $wpdb;
            $sanitize_meta_key = $this->sanitize_name($meta_key);
            $sql_select = $wpdb->prepare("SELECT postmeta.meta_value as val
            FROM {$wpdb->postmeta} as postmeta
            WHERE postmeta.meta_key = %s AND postmeta.post_id = %d AND postmeta.meta_value != %s
            GROUP BY postmeta.meta_key, postmeta.meta_value, postmeta.post_id", $meta_key, $product_id, '');
            $result = $wpdb->get_col($sql_select);
            if( $result === null && ! empty($wpdb->last_error) ) {
                return false;
            }
            $values = array();
            if( is_array($result) ) {
                foreach($result as $result_val) {
                    $values[$this->sanitize_name($result_val)] = apply_filters(
                        'berocket_aapf_custom_postmeta_value_name',
                        (string)$result_val,
                        $result_val,
                        $meta_key
                    );
                }
            }
            $table_name_meta = $wpdb->prefix . 'braapf_custom_post_meta';
            $meta_result = array();
            $get_meta_id = false;
            if( count($values) > 0 ) {
                $value_slugs = array_keys($values);
                $value_placeholders = implode(', ', array_fill(0, count($value_slugs), '%s'));
                $get_meta_id = "SELECT meta_id, slug FROM {$table_name_meta} WHERE meta = %s AND slug IN ({$value_placeholders})";
                $get_meta_id = $wpdb->prepare($get_meta_id, array_merge(array($sanitize_meta_key), $value_slugs));
                $meta_result = $wpdb->get_results($get_meta_id);
                if( $meta_result === null && ! empty($wpdb->last_error) ) {
                    return false;
                }
                if( ! is_array($meta_result) ) {
                    $meta_result = array();
                }
                $not_exist_meta = $values;
                if( count($meta_result) > 0 ) {
                    foreach($meta_result as $meta_value) {
                        if( isset($not_exist_meta[$meta_value->slug]) ) {
                            unset($not_exist_meta[$meta_value->slug]);
                        }
                    }
                }
                if( count($not_exist_meta) > 0 ) {
                    $terms = array();
                    foreach($not_exist_meta as $meta_slug => $meta_name) {
                        $terms[] = $wpdb->prepare('(%s, %s, %s)', $sanitize_meta_key, $meta_slug, $meta_name);
                    }
                    foreach(array_chunk($terms, 500) as $term_chunk) {
                        $insert_sql = "INSERT IGNORE INTO {$table_name_meta}(meta, slug, name) VALUES ". implode(',', $term_chunk);
                        if( $wpdb->query($insert_sql) === false ) {
                            return false;
                        }
                    }
                    $meta_result = $wpdb->get_results($get_meta_id);
                    if( $meta_result === null && ! empty($wpdb->last_error) ) {
                        return false;
                    }
                    if( ! is_array($meta_result) ) {
                        $meta_result = array();
                    }
                }
            }
            $product_meta_insert = array();
            foreach($meta_result as $meta_val) {
                $product_meta_insert[] = $wpdb->prepare('(%d, %d)', $meta_val->meta_id, $product_id);
            }
            $table_name = $wpdb->prefix . 'braapf_product_post_meta';
            $remove_sql = "DELETE FROM {$table_name} WHERE product_id = %d
                AND meta_id IN (SELECT meta_id FROM {$table_name_meta} WHERE meta = %s)";
            $remove_sql = $wpdb->prepare($remove_sql, $product_id, $sanitize_meta_key);
            if( $wpdb->query($remove_sql) === false ) {
                return false;
            }
            if( count($product_meta_insert) > 0 ) {
                foreach(array_chunk($product_meta_insert, 500) as $product_meta_insert_chunk) {
                    $include_sql = "INSERT IGNORE INTO {$table_name} (meta_id, product_id) VALUES ".implode(',', $product_meta_insert_chunk);
                    if( $wpdb->query($include_sql) === false ) {
                        return false;
                    }
                }
            }
            return true;
        }
        function is_required_update() {
            if( $this->required_update && ! empty($this->additional_table_class) ) {
                $this->additional_table_class->request_regeneration('custom_postmeta_changed');
                $this->required_update = false;
            }
        }       
        function postmeta_main_query($query) {
            if( ! is_admin() ) {
                global $wpdb;
                $table_name_meta = $wpdb->prefix . 'braapf_custom_post_meta';
                $query = "SELECT meta_id, slug as meta_value, name as meta_name, count FROM {$table_name_meta}
                WHERE meta LIKE %s ORDER BY meta_id";
            }
            return $query;
        }
        function recount_postmeta_query($query, $taxonomy_data, $postmeta) {
            if( ! is_admin() ) {
                global $wpdb;
                $table_name_meta = $wpdb->prefix . 'braapf_custom_post_meta';
                $table_name = $wpdb->prefix . 'braapf_product_post_meta';
                $query['select']['elements'] = array(
                    'meta_id'    => 'brpm_meta.meta_id as meta_id',
                    'meta_value' => 'brpm_meta.slug as meta_value',
                    'meta_name'  => 'brpm_meta.name as meta_name',
                    'count'      => "count(DISTINCT {$wpdb->posts}.ID) as count"
                );
                $query['join']['brpm_recount'] = "RIGHT JOIN {$table_name} as brpm_recount ON {$wpdb->posts}.ID = brpm_recount.product_id";
                $query['join']['brpm_meta'] = "JOIN {$table_name_meta} as brpm_meta ON brpm_recount.meta_id = brpm_meta.meta_id";
                $query['group'] = 'GROUP BY brpm_recount.meta_id';
                $query['where']['brpm_recount'] = $wpdb->prepare('AND brpm_meta.meta = %s', $postmeta);
                $query['order'] = " ORDER BY meta_id";
            }
            return $query;
        }
        function is_single_filter_and($filter) {
            return ! empty($filter['val_ids']) && is_array($filter['val_ids']) && count($filter['val_ids']) > 1
            && ! empty($filter['val_arr']['op']) && $filter['val_arr']['op'] == 'AND';
        }
        function posts_in($result, $instance, $filter, $data) {
            if( $result === NULL && ! empty($filter['type']) && $filter['type'] == 'custom_postmeta' 
            && $this->is_single_filter_and($filter) ) {
                global $wpdb;
                $val_ids = $this->normalize_integer_ids(isset($filter['val_ids']) ? $filter['val_ids'] : array());
                $table_name = $wpdb->prefix . 'braapf_product_post_meta';
                $post_ids = array();
                if( count($val_ids) > 0 ) {
                    $val_id_placeholders = implode(', ', array_fill(0, count($val_ids), '%d'));
                    $select_posts = "SELECT product_id, count(product_id) as count FROM {$table_name}
                    WHERE meta_id IN ({$val_id_placeholders})
                    GROUP BY product_id
                    HAVING count = %d";
                    $select_posts = $wpdb->prepare($select_posts, array_merge($val_ids, array(count($val_ids))));
                    $post_ids = $wpdb->get_col($select_posts);
                }
                if( empty($post_ids) ) {
                    $post_ids = array('0');
                }
                $result = $filter;
                $result['posts_in'] = $post_ids;
            }
            return $result;
        }
        function custom_query_each($result, $instance, $filter, $data) {
            if( $result === NULL && ! empty($filter['type']) && $filter['type'] == 'custom_postmeta'
            && ! $this->is_single_filter_and($filter) ) {
                $result = $filter;
                $result['custom_query'] = array($this, 'post_clauses');
                $result['custom_query_line'] = 'sale:'.$filter['val'];
            }
            return $result;
        }
        function post_clauses($args, $filter) {
            global $wpdb;
            $table_name_custom = 'brpm_filter_' . $this->post_clauses_number;
            $this->post_clauses_number = $this->post_clauses_number + 1;
            $table_name = $wpdb->prefix . 'braapf_product_post_meta';
            $val_ids = $this->normalize_integer_ids(isset($filter['val_ids']) ? $filter['val_ids'] : array());
            $args['join'] .= " JOIN {$table_name} as {$table_name_custom} ON {$wpdb->posts}.ID = {$table_name_custom}.product_id ";
            if( count($val_ids) > 0 ) {
                $val_id_placeholders = implode(', ', array_fill(0, count($val_ids), '%d'));
                $args['where'] .= $wpdb->prepare(" AND {$table_name_custom}.meta_id IN ({$val_id_placeholders})", $val_ids);
            } else {
                $args['where'] .= ' AND 1 = 0';
            }
            return $args;
        }
        function finalize_counts($instance = false) {
            global $wpdb;
            $raw_dirty = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                self::COUNTS_DIRTY_OPTION
            ));
            if( $raw_dirty !== null ) {
                $table_name_meta = $wpdb->prefix . 'braapf_custom_post_meta';
                $table_name = $wpdb->prefix . 'braapf_product_post_meta';
                $query = "UPDATE {$table_name_meta} as setable
                LEFT JOIN ( 
                    SELECT meta_id, count(product_id) as count
                 FROM {$table_name}
                 GROUP BY meta_id
                ) as getable ON setable.meta_id= getable.meta_id
                SET setable.count = COALESCE(getable.count, 0)";
                $query_status = $wpdb->query($query);
                if( $query_status === false ) {
                    if( $instance && is_callable(array($instance, 'assert_query_success')) ) {
                        $instance->assert_query_success($query_status, $query, 'custom_postmeta_counts_failed');
                    }
                    return false;
                }
                $delete_query = $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s",
                    self::COUNTS_DIRTY_OPTION,
                    (string)$raw_dirty
                );
                $deleted = $wpdb->query($delete_query);
                if( $deleted === false ) {
                    if( $instance && is_callable(array($instance, 'assert_query_success')) ) {
                        $instance->assert_query_success($deleted, $delete_query, 'custom_postmeta_dirty_flag_delete_failed');
                    }
                    return false;
                }
                if( intval($deleted) !== 1 ) {
                    // Another request marked counts dirty while aggregation was
                    // running. Keep that newer token and run once more.
                    return false;
                }
                wp_cache_delete(self::COUNTS_DIRTY_OPTION, 'options');
            }
            return true;
        }
        protected function schedule_counts_refresh($delay = 5, $successor = false) {
            $delay = max(1, intval($delay));
            $args = array();
            if( function_exists('as_schedule_single_action') ) {
                if( ! $successor && function_exists('as_has_scheduled_action')
                    && as_has_scheduled_action(self::COUNTS_REFRESH_HOOK, $args, self::COUNTS_REFRESH_GROUP) ) {
                    return true;
                }
                return ! empty(as_schedule_single_action(
                    time() + $delay,
                    self::COUNTS_REFRESH_HOOK,
                    $args,
                    self::COUNTS_REFRESH_GROUP
                ));
            }
            if( ! $successor && wp_next_scheduled(self::COUNTS_REFRESH_HOOK, $args) !== false ) {
                return true;
            }
            return (bool)wp_schedule_single_event(time() + $delay, self::COUNTS_REFRESH_HOOK, $args);
        }
        protected function mark_counts_dirty() {
            $token = function_exists('wp_generate_uuid4')
                ? wp_generate_uuid4()
                : uniqid('brapf-cpm-', true);
            update_option(self::COUNTS_DIRTY_OPTION, $token, false);
            $this->schedule_counts_refresh();
        }
        public function run_counts_refresh() {
            if( ! $this->finalize_counts() ) {
                $this->schedule_counts_refresh(30, true);
                return false;
            }
            return true;
        }
        function validate_generation($valid, $instance = false) {
            if( ! $valid ) {
                return false;
            }
            if( ! empty(get_option(self::COUNTS_DIRTY_OPTION)) || ! $instance ) {
                return false;
            }
            return $this->validate_schema(true, $instance);
        }
        function validate_schema($valid, $instance = false) {
            if( ! $valid || ! $instance ) {
                return false;
            }
            global $wpdb;
            return $instance->table_schema_is_valid(
                $wpdb->prefix . 'braapf_custom_post_meta',
                array('meta_id', 'meta', 'slug', 'count', 'name'),
                array(
                    'PRIMARY' => array('meta_id'),
                    'uniqueid' => array('meta', 'slug'),
                ),
                array(
                    'meta_id' => array(
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => true,
                    ),
                )
            ) && $instance->table_schema_is_valid(
                $wpdb->prefix . 'braapf_product_post_meta',
                array('meta_id', 'product_id'),
                array(
                    'uniqueid' => array('meta_id', 'product_id'),
                    'product_id' => array('product_id'),
                ),
                array(
                    'meta_id' => array('type' => 'bigint', 'nullable' => false),
                    'product_id' => array('type' => 'bigint', 'nullable' => false),
                )
            );
        }
        function admin_footer() {
            if( ! empty(get_option(self::COUNTS_DIRTY_OPTION)) ) {
                $this->schedule_counts_refresh();
            }
        }
    }
}
