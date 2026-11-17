<?php
if( ! class_exists('BeRocket_conditions_import_export') ) {
    class BeRocket_conditions_import_export {
        public static function convert($conditions, $direction, $context = array()) {
            if( ! is_array($conditions) || ! in_array($direction, array('export', 'import'), true) ) {
                return $conditions;
            }
            $context = is_array($context) ? $context : array();
            $context['direction'] = $direction;
            $schema = class_exists('BeRocket_conditions')
                ? BeRocket_conditions::get_import_export_schema($context)
                : array();
            $schema = is_array($schema) ? $schema : array();

            foreach($conditions as $group_id => $group_conditions) {
                if( ! is_array($group_conditions) ) {
                    continue;
                }
                foreach($group_conditions as $condition_id => $condition) {
                    if( ! is_array($condition) || empty($condition['type']) ) {
                        continue;
                    }
                    $condition_type = sanitize_key($condition['type']);
                    $condition_context = array_merge($context, array(
                        'condition_type' => $condition_type,
                        'group_id'       => $group_id,
                        'condition_id'   => $condition_id,
                        'condition'      => $condition,
                    ));
                    $condition_schema = self::resolve_schema($condition_type, $schema);
                    $condition_schema = apply_filters(
                        'berocket_conditions_import_export_schema_' . $condition_type,
                        $condition_schema,
                        $condition_context
                    );
                    if( is_array($condition_schema) && ! empty($condition_schema) ) {
                        $condition = ( $direction === 'export' )
                            ? BeRocket_import_export::type_check_export($condition, $condition_schema, $condition)
                            : BeRocket_import_export::type_check_import($condition, $condition_schema, $condition);
                    }
                    $condition_context['schema'] = $condition_schema;
                    $condition = apply_filters(
                        'berocket_conditions_' . $direction . '_type_' . $condition_type,
                        $condition,
                        $condition_context
                    );
                    $conditions[$group_id][$condition_id] = $condition;
                }
            }
            return $conditions;
        }

        protected static function resolve_schema($condition_type, $schema) {
            if( empty($schema[$condition_type]) || ! is_array($schema[$condition_type]) ) {
                return array();
            }
            $condition_schema = $schema[$condition_type];
            $resolved = array();
            $visited = array();
            while( ! empty($condition_schema['inherit']) ) {
                $parent_type = sanitize_key($condition_schema['inherit']);
                unset($condition_schema['inherit']);
                $resolved = array_replace_recursive($condition_schema, $resolved);
                if( isset($visited[$parent_type]) || empty($schema[$parent_type]) || ! is_array($schema[$parent_type]) ) {
                    return $resolved;
                }
                $visited[$parent_type] = true;
                $condition_schema = $schema[$parent_type];
            }
            return array_replace_recursive($condition_schema, $resolved);
        }
    }
}
