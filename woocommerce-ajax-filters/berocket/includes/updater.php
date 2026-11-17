<?php
if ( ! class_exists( 'BeRocket_updater' ) ) {
    define( "BeRocket_update_path", 'https://api.berocket.com/' );
    define( "BeRocket_cdn_path", 'https://apicdn.berocket.com/' );
    define( "BeRocket_main_path", 'https://berocket.com/' );
    define( "BeRocket_updater_log", true );
    include_once( plugin_dir_path( __FILE__ ) . 'error_notices.php' );

    class BeRocket_updater {
        public static $plugin_info = array();
        public static $slugs       = array();
        public static $free_slugs  = array();
        public static $key         = '';
        public static $error_log   = array();
        public static $debug_mode  = false;
        const LICENSE_PROTOCOL = 'berocket-license/1';
        private static $license_downloads = array();
        private static $license_download_upgrader = null;

        public static function init() {
            add_action( 'admin_init', array(__CLASS__, 'admin_init') );
            $options = get_option( 'BeRocket_account_option' );
            if( ! is_array($options) ) {
                $options = array();
            }
            self::$debug_mode = ! empty( $options[ 'debug_mode' ] );
        }

        public static function admin_init() {
            add_filter('woocommerce_addons_sections', array(__CLASS__, 'woocommerce_addons_sections'));
            add_filter( 'is_berocket_settings_page', array( __CLASS__, 'is_settings_page' ) );
            if( isset($_GET['page']) && isset($_GET['section']) && $_GET['page'] == 'wc-addons' && ( $_GET['section'] == 'berocket' || ! empty($_GET['search']) ) ) {
                add_action('admin_footer', array(__CLASS__, 'woocommerce_addons_berocket'));
            }
        }

        public static function run() {
            $options          = self::get_options();
            self::$debug_mode = ! empty( $options[ 'debug_mode' ] );
            self::$key        = ( empty( $options[ 'account_key' ] ) ? '' : $options[ 'account_key' ] );

            add_action( 'admin_head', array( __CLASS__, 'scripts' ) );
            add_action( 'admin_menu', array( __CLASS__, 'main_menu_item' ), 1 );
            add_action( 'admin_menu', array( __CLASS__, 'account_page' ), 500 );
            add_action( 'network_admin_menu', array( __CLASS__, 'network_account_page' ) );
            add_action( 'admin_init', array( __CLASS__, 'account_option_register' ) );
            add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'update_check_set' ) );
            add_filter('site_transient_update_plugins', array(__CLASS__, 'update_check_read'));
            add_filter('plugins_api', array(__CLASS__, 'plugin_api_data'), 10, 3);
            add_filter('upgrader_pre_download', array(__CLASS__, 'license_before_download'), 100, 4);
            add_filter('upgrader_source_selection', array(__CLASS__, 'license_check_source'), 100, 4);
            add_filter('auto_update_plugin', array(__CLASS__, 'license_automatic'), 100, 2);
            add_action('load-update-core.php', array(__CLASS__, 'license_check_again'));

	        add_filter( 'plugins_api_result', array( __CLASS__, 'plugin_api_data' ), 10, 3 );
            add_action( "wp_ajax_br_test_key", array( __CLASS__, 'test_key' ) );
            add_action( "wp_ajax_br_test_keys", array( __CLASS__, 'test_keys' ) );
            add_filter( 'http_request_host_is_external', array( __CLASS__, 'allow_berocket_host' ), 10, 3 );

            if ( BeRocket_updater_log ) {
                add_action( 'admin_footer', array( __CLASS__, 'error_log' ) );
                add_action( 'wp_footer', array( __CLASS__, 'error_log' ) );
            }

            $plugin = array();
            $plugin = apply_filters( 'BeRocket_updater_add_plugin', $plugin );

            if ( ! isset( $options[ 'plugin_key' ] ) || ! is_array( $options[ 'plugin_key' ] ) ) {
                $options[ 'plugin_key' ] = array();
            }
            $update = false;
            foreach ( $plugin as $plug_id => $plug ) {
                if( ! empty($plug[ 'slug' ]) ) {
                    self::$slugs[ $plug[ 'id' ] ] = $plug[ 'slug' ];
                }
                if( ! empty($plug[ 'free_slug' ]) ) {
                    self::$free_slugs[ $plug[ 'id' ] ] = $plug[ 'free_slug' ];
                }
                if ( isset( $options[ 'plugin_key' ][ $plug[ 'id' ] ] ) && $options[ 'plugin_key' ][ $plug[ 'id' ] ] != '' ) {
                    $plugin[ $plug_id ][ 'key' ] = $options[ 'plugin_key' ][ $plug[ 'id' ] ];
                } elseif ( isset( $plugin[ $plug_id ][ 'key' ] ) && $plugin[ $plug_id ][ 'key' ] != '' ) {
                    $options[ 'plugin_key' ][ $plug[ 'id' ] ] = $plugin[ $plug_id ][ 'key' ];
                    $update                                   = true;
                }
            }
            if ( is_multisite() ) {
                $options_ms = BeRocket_Framework::get_global_option(true);
                if( ! empty($options_ms) && is_array($options_ms) && isset($options_ms['plugin_key']) && is_array($options_ms['plugin_key']) ) {
                    foreach ( $plugin as $plug_id => $plug ) {
                        if ( isset( $options_ms[ 'plugin_key' ][ $plug[ 'id' ] ] ) && $options_ms[ 'plugin_key' ][ $plug[ 'id' ] ] != '' ) {
                            $plugin[ $plug_id ][ 'key' ] = $options_ms[ 'plugin_key' ][ $plug[ 'id' ] ];
                        }
                    }
                }
            }
            self::$plugin_info = $plugin;

            if ( $update ) {
                self::set_options( $options );
            }

            add_filter( 'berocket_display_additional_notices', array(
                __CLASS__,
                'berocket_display_additional_notices'
            ) );

            if( ! is_network_admin() ) {
                add_filter( 'custom_menu_order', array( __CLASS__, 'wp_menu_order' ) );
            }

            //ADMIN NOTICE CHECK
            add_filter( 'berocket_admin_notice_is_display_notice', array( __CLASS__, 'admin_notice_is_display_notice' ), 10, 3 );
            add_filter( 'berocket_admin_notice_is_display_notice_priority', array( __CLASS__, 'admin_notice_is_display_notice' ), 10, 3 );
            foreach ( self::$plugin_info as $plugin ) {
                $plugin_file = $plugin['plugin'];
                add_action( "after_plugin_row_{$plugin_file}", array( __CLASS__, 'update_message'), 10, 1 );
                add_action("in_plugin_update_message-{$plugin_file}", array(__CLASS__, 'license_message'), 10, 2);
            }


        }

        public static function error_log() {
            if ( self::$debug_mode ) {
                $plugins_list = self::$plugin_info;
                if( is_array($plugins_list) && count($plugins_list) > 0 ) {
                    foreach($plugins_list as &$plugin) {
                        if( ! empty($plugin['key']) ) {
                            $plugin['key'] = self::hide_key($plugin['key']);
                        }
                    }
                }
                self::$error_log                          = apply_filters( 'BeRocket_updater_error_log', self::$error_log );
                self::$error_log[ 'real_memory_usage' ]   = memory_get_peak_usage( true );
                self::$error_log[ 'script_memory_usage' ] = memory_get_peak_usage( false );
                self::$error_log[ 'plugins' ]             = $plugins_list;
                self::$error_log[ 'memory_limit' ]        = ini_get( 'memory_limit' );
                self::$error_log[ 'WP_DEBUG' ]            = 'WP_DEBUG:' . ( defined( 'WP_DEBUG' ) ? ( WP_DEBUG ? 'true' : 'false' ) : 'false' ) . '; WP_DEBUG_DISPLAY:' . ( defined( 'WP_DEBUG_DISPLAY' ) ? ( WP_DEBUG_DISPLAY ? 'true' : 'false' ) : 'false' );
                $error_log_json = wp_json_encode(
                    self::$error_log,
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if ( ! is_string( $error_log_json ) ) {
                    $error_log_json = 'null';
                }
                ?>
                <script>
                    console.log(<?php echo $error_log_json; ?>);
                </script>
                <?php
            }
            if( ! empty($_GET['BRvercheck']) ) {
                $plugin_versions = array();
                foreach(self::$plugin_info as $plugin_i) {
                    $plugin_versions[$plugin_i['plugin_name']] = array('name' => $plugin_i['name'], 'version' => $plugin_i['version']);
                }
                $plugin_versions_json = wp_json_encode(
                    $plugin_versions,
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if ( ! is_string( $plugin_versions_json ) ) {
                    $plugin_versions_json = 'null';
                }
                ?>
                <script>
                    console.log(<?php echo $plugin_versions_json; ?>);
                </script>
                <?php
            }
        }

        public static function wp_menu_order( $menu_ord ) {
            global $submenu;

            if( empty($submenu[ 'berocket_account' ]) || ! is_array($submenu[ 'berocket_account' ]) || count($submenu[ 'berocket_account' ]) == 0 ) {
                return $menu_ord;
            }
            $new_order_temp = array();
            $new_sub_order  = array();
            $new_order_sort = array();

            $compatibility_hack = apply_filters('BeRocket_updater_menu_order_custom_post', array());

            $BeRocket_item = false;
            $account_keys_item = false;
            foreach ( $submenu[ 'berocket_account' ] as $item ) {
                if ( $item[ 0 ] == 'BeRocket' ) {
                    $BeRocket_item = $item;
                    continue;
                } elseif ( $item[ 0 ] == __('Settings & Keys', 'BeRocket_domain') ) {
                    $account_keys_item = $item;
                    continue;
                }
                if ( ! empty($compatibility_hack[ str_replace( "edit.php?post_type=", "", $item[ 2 ] ) ]) ) {
                    $item_0 = "<span class='berocket_admin_menu_custom_post_submenu";
                    if ( $item[ 0 ] == 'Upgrade' ) {
                        $item_0 .= " berocket_admin_menu_custom_post_submenu_upgrade";
                        $item[ 0 ] .= " &#9889;";
                    }
                    $item[ 0 ] = $item_0 . "'>" . $item[ 0 ] . "</span>";
                    $new_sub_order[ $compatibility_hack[ str_replace( "edit.php?post_type=", "", $item[ 2 ] ) ] ][] = $item;
                } else {
                    $new_order_temp[] = $item;
                    $new_order_sort[] = $item[ 0 ];
                }
            }
            $new_sub_order = apply_filters('BeRocket_updater_menu_order_sub_order', $new_sub_order);

            array_multisort( $new_order_sort, $new_order_temp );

            $new_order = array();
            if( $BeRocket_item !== false ) {
                $new_order[] = $BeRocket_item;
            }
            foreach ( $new_order_temp as $item ) {
                $new_order[] = $item;

                if ( ! empty( $new_sub_order[ $item[ 2 ] ] ) ) {
                    foreach ( $new_sub_order[ $item[ 2 ] ] as $sub_item ) {
                        $new_order[] = $sub_item;
                    }
                }
            }
            if( $account_keys_item !== false ) {
                $new_order[] = $account_keys_item;
            }

            $submenu[ 'berocket_account' ] = $new_order;

            return $menu_ord;
        }

        public static function add_to_debug( $data, $plugin, $keys = false ) {
            if ( self::$debug_mode ) {
                if ( $keys === false ) {
                    self::$error_log[ $plugin ][] = $data;
                } elseif ( is_array( $keys ) ) {
                    if ( count( $keys ) > 0 ) {
                        $data_set = self::$error_log[ $plugin ];
                        $last_key = array_pop( $keys );

                        foreach ( $keys as $key ) {
                            if ( ! is_array( $data_set[ $key ] ) ) {
                                $data_set[ $key ] = array();
                            }

                            $new_set = &$data_set[ $key ];
                            unset( $data_set );

                            $data_set = &$new_set;
                            unset( $new_set );
                        }

                        if ( empty( $last_key ) ) {
                            $data_set[] = $data;
                        } else {
                            $data_set[ $last_key ] = $data;
                        }
                    } else {
                        self::$error_log[ $plugin ][] = $data;
                    }
                } else {
                    self::$error_log[ $plugin ][ $keys ] = $data;
                }
            }
        }

        public static function berocket_display_additional_notices( $notices ) {
            if ( ! empty( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'berocket_account' ) {
                return $notices;
            }

            $not_activated_notices = array();
            foreach ( self::$plugin_info as $plugin ) {
                $check_plugin_activation = self::check_plugin_activation($plugin[ 'id' ]);
                $is_active = ( ! empty($check_plugin_activation) && is_array($check_plugin_activation) && ! empty($check_plugin_activation['status']) );
                if ( ! $is_active ) {
                    $version_capability = br_get_value_from_array($plugin, array('version_capability'), 15);
                    if ( $version_capability > 5 && ! in_array($version_capability, array(15, 3, 17)) ) {
                        $meta_data = '?utm_source=paid_plugin&utm_medium=notice&utm_campaign='.$plugin['plugin_name'];
                        $not_activated_notices[] = array(
                            'start'         => 0,
                            'end'           => 0,
                            'type'          => 'error',
                            'name'          => $plugin[ 'name' ],
                            'html'          => '<p style="margin-right: 225px;">' .
                                               __('Please', 'BeRocket_domain'). ' ' .
                                               __('activate plugin', 'BeRocket_domain') . ' ' . $plugin[ 'name' ] . ' ' .
                                               __('with help of plugin/account key from', 'BeRocket_domain'). ' '
                                               . '<a href="' . BeRocket_main_path . 'my-account' . $meta_data . '" target="_blank">' .
                                               __('BeRocket account', 'BeRocket_domain') . '</a>. '
                                               . __('You can activate plugin in', 'BeRocket_domain') .
                                               '</p>'
                                               . '<a class="button notice-action-link not_berocket_button" href="' .
                                               ( is_network_admin() ? admin_url( 'network/admin.php?page=berocket_account' ) : admin_url( 'admin.php?page=berocket_account' ) ) . '">' .
                                               __('BeRocket Account settings', 'BeRocket_domain') . '</a>
                                ',
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
                    }
                }
            }

            $notices = array_merge( $notices, $not_activated_notices );

            return $notices;
        }

        public static function get_plugin_count() {
            $count = count( self::$plugin_info );

            return $count;
        }

        public static function allow_berocket_host( $allow, $host, $url ) {
            // Preserve the existing BeRocket exception during WordPress URL
            // safety validation. This hook does not override
            // WP_HTTP_BLOCK_EXTERNAL; site owners control those exceptions
            // through WP_ACCESSIBLE_HOSTS.
            if ( strtolower((string)$host) === 'berocket.com' ) {
                $allow = true;
            }

            return $allow;
        }

        public static function test_keys() {
            $wp_nonce = (empty($_GET['wp_nonce']) ? (empty($_POST['wp_nonce']) ? '' : $_POST['wp_nonce']) : $_GET['wp_nonce']);
            if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $wp_nonce, 'br_test_keys' ) ) {
                echo __( 'Do not have access for this feature', 'BeRocket_domain' );
                wp_die();
            }
            $result = array();
            if( ! empty($_POST['keys']) && is_array($_POST['keys']) ) {
                $options = self::get_options();
                $check_keys = array();
                foreach($_POST['keys'] as $key) {
                    if( ! empty($key['key']) ) {
                        $key_id = intval($key['id']);
                        if( strpos($key['key'], '********************') === FALSE ) {
                            $check_keys[] = array(
                                'id'  => $key_id,
                                'key' => sanitize_text_field($key['key'])
                            );
                        } else {
                            if( $key_id == 0 ) {
                                if( ! empty($options['account_key']) ) {
                                    $check_keys[] = array(
                                        'id'  => $key_id,
                                        'key' => sanitize_text_field($options['account_key'])
                                    );
                                }
                            } else {
                                if( ! empty($options['plugin_key'][$key_id]) ) {
                                    $check_keys[] = array(
                                        'id'  => $key_id,
                                        'key' => sanitize_text_field($options['plugin_key'][$key_id])
                                    );
                                }
                            }
                        }
                    }
                }
                if( count($check_keys) > 0 ) {
                    $check_plugin_activation = self::check_plugin_activation(false, array('keys' => $check_keys));
                    $check_plugin_activation_plugins = $check_plugin_activation['plugins'];
                    $result = array();
                    if( is_array($check_plugin_activation_plugins) ) {
                        foreach($check_plugin_activation_plugins as $plugin_id => $plugin_data) {
                            $result[$plugin_id] = ( ! empty($plugin_data) && is_array($plugin_data) && ! empty($plugin_data['status']) );
                        }
                    }
                }
            }
            $out = json_encode( $result );
            echo $out;
            wp_die();
        }
        public static function test_key() {
            $wp_nonce = (empty($_GET['wp_nonce']) ? (empty($_POST['wp_nonce']) ? '' : $_POST['wp_nonce']) : $_GET['wp_nonce']);
            if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $wp_nonce, 'br_test_key' ) ) {
                echo __( 'Do not have access for this feature', 'BeRocket_domain' );
                wp_die();
            }
            if ( ! isset( $_POST[ 'key' ] ) || ! isset( $_POST[ 'id' ] ) ) {
                $data = array(
                    'key_exist' => 0,
                    'status'    => 'Failed',
                    'error'     => 'Incorrect query for this function(ID and Key must be sended)'
                );

                $out  = json_encode( $data );
            } elseif( strlen($_POST[ 'key' ]) < 35 || strlen($_POST[ 'key' ]) > 45 ) {
                $data = array(
                    'key_exist' => 0,
                    'status'    => 'Failed',
                    'error'     => 'Incorrect query for this function(Incorrect key length)'
                );

                $out  = json_encode( $data );
            } else {
                $key = sanitize_text_field( $_POST[ 'key' ] );
                $id  = sanitize_text_field( $_POST[ 'id' ] );
                $fast  = ! empty($_POST[ 'fast' ]);
                $site_url = get_site_url();
                $plugins  = self::$plugin_info;
                if( strpos($key, '**') !== false ) {
                    if ($id == 0 ) {
                        $key = self::$key;
                    } elseif( is_array($plugins) && count($plugins) > 0 ) {
                        foreach($plugins as $plugin_info) {
                            if($plugin_info['id'] == $id) {
                                $key = $plugin_info['key'];
                            }
                        }
                    }
                }
                

                if ( is_array( $plugins ) ) {
                    $plugins = array_keys( $plugins );
                    $plugins = implode( ',', $plugins );
                } else {
                    $plugins = '';
                }

                $response = wp_remote_post( BeRocket_update_path . 'v1/account_updater', array(
                    'body'        => array(
                        'key'     => $key,
                        'id'      => $id,
                        'url'     => $site_url,
                        'plugins' => $plugins
                    ),
                    'method'      => 'POST',
                    'timeout'     => 30,
                    'redirection' => 5,
                    'blocking'    => true,
                    'sslverify'   => false
                ) );

                $options            = self::get_options();
                if ( ! is_wp_error( $response ) ) {
                    $out            = wp_remote_retrieve_body( $response );
                    $current_plugin = false;
                    $out            = json_decode( $out, true );

                    if ( ! is_array( $out ) ) {
                        $out = array();
                    }

                    $out[ 'upgrade' ] = array();

                    if( ! $fast ) {
                        if ( $id != 0 ) {
                            foreach ( self::$plugin_info as $plugin ) {
                                if ( $plugin[ 'id' ] == $id ) {
                                    $current_plugin = $plugin;
                                    break;
                                }
                            }

                            if ( $current_plugin !== false ) {
                                if ( empty( $out[ 'error' ] ) ) {
                                    $options[ 'plugin_key' ][ $id ] = $key;

                                    if ( isset( $out[ 'versions' ][ $id ] ) && version_compare( $current_plugin[ 'version' ], $out[ 'versions' ][ $id ], '<' ) ) {
                                        $upgrade_button        = '<a href="' . wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $current_plugin[ 'plugin' ], 'upgrade-plugin_' . $current_plugin[ 'plugin' ] ) . '" class="button tiny-button">Upgrade plugin</a>';
                                        $out[ 'upgrade' ][]    = array( 'id' => $id, 'upgrade' => $upgrade_button );
                                    }
                                }
                            }
                        } else {
                            foreach ( self::$plugin_info as $plugin ) {
                                if ( isset( $out[ 'versions' ][ $plugin[ 'id' ] ] ) && version_compare( $plugin[ 'version' ], $out[ 'versions' ][ $plugin[ 'id' ] ], '<' ) ) {
                                    $upgrade_button     = '<a href="' . wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin[ 'plugin' ], 'upgrade-plugin_' . $plugin[ 'plugin' ] ) . '" class="button tiny-button">Upgrade plugin</a>';
                                    $out[ 'upgrade' ][] = array(
                                        'id'      => $plugin[ 'id' ],
                                        'upgrade' => $upgrade_button
                                    );
                                }
                            }
                            $options[ 'account_key' ] = $key;
                        }
                    }
                    $out = json_encode( $out );
                } else {
                    $data = array(
                        'key_exist' => 0,
                        'status'    => 'Failed',
                        'error'     => $response->get_error_message()
                    );

                    $out  = json_encode( $data );
                }
                if( ! $fast ) {
                    self::set_options( $options );
                }
            }
            echo $out;
            wp_die();
        }

        public static function scripts() {
            ?>
            <script>
                function BeRocket_key_check(key, show_correct, product_id, async_func, fast) {
                    if (typeof( product_id ) == 'undefined' || product_id == null) {
                        product_id = 0;
                    }
                    if (typeof( async_func ) == 'undefined' || async_func == null) {
                        async_func = false;
                    }
                    if (typeof( fast ) == 'undefined' || fast == null) {
                        fast = 0;
                    }
                    var async = false;
                    if( async_func !== false ) {
                        async = true;
                    }
                    data = {action: 'br_test_key', key: key, id: product_id, fast:fast, wp_nonce: "<?php echo wp_create_nonce('br_test_key'); ?>"};
                    is_submit = false;
                    jQuery.ajax({
                        url: ajaxurl,
                        data: data,
                        type: 'POST',
                        success: function (data) {
                            jQuery('.berocket_test_result').html(data);
                            if (data.key_exist == 1) {
                                if (show_correct) {
                                    html = '<h3>' + data.status + '</h3>';
                                    jQuery('.berocket_test_result').html(html);
                                    data.upgrade.forEach(function (el, i, arr) {
                                        jQuery('.berocket_product_key_' + el.id + '_status').html(el.upgrade);
                                    });
                                }
                                is_submit = true;
                            } else {
                                if (show_correct) {
                                    html = '<h3>' + data.status + '</h3>';
                                    html += '<p><b>Error message:</b>' + data.error + '</p>';
                                    jQuery('.berocket_test_result').html(html);
                                }
                            }
                            jQuery('.berocket_product_key_' + product_id + '_status').text(data.status);
                            if( ! data.key_exist ) {
                                jQuery('#berocket_product_key_' + product_id).val('');
                            }
                            if( typeof(async_func) == 'function' ) {
                                async_func(is_submit);
                            }
                        },
                        dataType: 'json',
                        async: async
                    });
                    return is_submit;
                }
                jQuery(document).on('click', '.berocket_test_account_product', function (event) {
                    event.preventDefault();
                    if (jQuery(this).data('product')) {
                        key = jQuery(jQuery(this).data('product')).val();
                    } else {
                        key = jQuery('#berocket_product_key').val();
                    }
                    BeRocket_key_check(key, true, jQuery(this).data('id'));
                });
            </script>
            <style>
                .toplevel_page_berocket_account .dashicons-before img {
                    max-width: 20px;
                }
                .admin-color-modern .toplevel_page_berocket_account .dashicons-before img {
                    opacity: 1 !important;
                }
            </style>
            <?php
        }

        public static function network_account_page() {
            add_menu_page( __('BeRocket Account Settings', 'BeRocket_domain'), __('BeRocket Account', 'BeRocket_domain'), 'manage_berocket', 'berocket_account', array(
                    __CLASS__,
                    'account_form_network'
                ), 'https://apicdn.berocket.com/logo_rocket_white.svg', '55.55' );
        }

        public static function main_menu_item() {
            add_menu_page( 'BeRocket Account', 'BeRocket', 'manage_berocket', 'berocket_account', array(
                    __CLASS__,
                    'account_form'
                ), 'https://apicdn.berocket.com/logo_rocket_white.svg', '55.55' );
        }

        public static function account_page() {
            add_submenu_page( 'berocket_account', __('BeRocket Account Settings', 'BeRocket_domain'), __('Settings & Keys', 'BeRocket_domain'), 'manage_berocket_account', 'berocket_account', array(
                    __CLASS__,
                    'account_form'
                ) );
        }

        public static function account_option_register() {
            register_setting( 'BeRocket_account_option_settings', 'BeRocket_account_option', array('sanitize_callback' => array(__CLASS__, 'reset_update_plugin_data')) );
        }

        public static function reset_update_plugin_data($options) {
            $options = self::restore_keys($options);
            self::update_check_set('');
            delete_site_transient( 'update_plugins' );
            delete_transient('berocket_plugin_paid_info');
            return $options;
        }

        public static function account_form() {
            wp_enqueue_style( 'berocket_framework_admin_style' );
            ?>
            <div class="wrap">
                <form method="post" action="options.php" class="account_key_send br_framework_settings">
                    <?php
                    if ( ! empty( $_POST[ 'BeRocket_account_option' ] ) and
                         ( is_super_admin() or ! is_network_admin() and current_user_can( 'manage_options' ) ) and
                         ! empty( $_POST['_wpnonce'] ) and
                         wp_verify_nonce( $_POST['_wpnonce'], 'BeRocket_account_option_settings-options' )
                    ) {
	                    $previous_options = BeRocket_Framework::get_global_option();
	                    $option = berocket_sanitize_array( $_POST[ 'BeRocket_account_option' ], array('BeRocket_account_option'), $previous_options );
	                    BeRocket_Framework::save_global_option($option);
	                    self::update_check_set('');
	                    delete_site_transient( 'update_plugins' );
	                    delete_transient('berocket_plugin_paid_info');
                    }

                    $options = BeRocket_Framework::get_global_option();
                    self::inside_form( $options );
                    ?>
                </form>
                <?php do_action('BeRocket_framework_updater_account_form_after', self::$plugin_info); ?>
            </div>
            <?php
        }

        public static function account_form_network() {
            ?>
            <div class="wrap">
                <form method="post" action="edit.php?page=berocket_account" class="account_key_send br_framework_settings">
                    <?php
                    if ( ! empty( $_POST[ 'BeRocket_account_option' ] ) and
                         ( is_super_admin() or ! is_network_admin() and current_user_can( 'manage_options' ) ) and
                         ! empty( $_POST['_wpnonce'] ) and
                         wp_verify_nonce( $_POST['_wpnonce'], 'BeRocket_account_option_settings-options' )
                    ) {
                        $previous_options = BeRocket_Framework::get_global_option(true);
                        $option = berocket_sanitize_array( $_POST[ 'BeRocket_account_option' ], array('BeRocket_account_option'), $previous_options );
                        BeRocket_Framework::save_global_option($option, true);
                        self::update_check_set('');
                        delete_site_transient( 'update_plugins' );
                        delete_transient('berocket_plugin_paid_info');
                    }

                    $options = BeRocket_Framework::get_global_option(true);
                    self::inside_form( $options );
                    ?>
                </form>
            </div>
            <?php
        }

        public static function inside_form( $options ) {
            settings_fields( 'BeRocket_account_option_settings' );
            if ( isset( $options[ 'plugin_key' ] ) && is_array( $options[ 'plugin_key' ] ) ) {
                $plugins_key = $options[ 'plugin_key' ];
            } else {
                $plugins_key = array();
            }
            $has_free = false;
            foreach(self::$plugin_info as $plugin) {
                if( $plugin['version_capability'] < 10 ) {
                    $has_free = true;
                }
            }
            if( $has_free ) {
                if ( time() > 1637841600 and time() < 1637841600+302400 ) {
                    echo "
                    <div class='berocket-above-settings-banner' style='background: #1a1a1a; padding: 0;'>
                        <a href='https://berocket.com/products?utm_source=free_plugin&utm_medium=settings&utm_campaign=account_keys&utm_content=top' target='_blank' 
                        style='background: transparent; width: auto; border: 0 none; box-shadow: none; padding: 0; margin: 0;'>
                            <img alt='BeRocket Products' src='https://berocket.ams3.cdn.digitaloceanspaces.com/g/bf21-1202x280.jpg' style='display: block;'>
                        </a>
                    </div>";
                } else if ( time() > 1637841600+302400 and time() < 1637841600+302400+518400 ) {
	                echo "
                    <div class='berocket-above-settings-banner berocket-cm21-settings-wrapper' style='background: #07002e; padding: 0;'>
                        <a href='https://berocket.com/products?utm_source=free_plugin&utm_medium=settings&utm_campaign=account_keys&utm_content=top' target='_blank' >
                            <img alt='BeRocket Products' src='https://berocket.ams3.cdn.digitaloceanspaces.com/g/cm21.jpg'>
                            <div class='berocket-cm21-settings'>
                                <div class='berocket-cm21-settings-header'>
                                    <p>Don't lose another 5% of the discount. Purchase now!</p>
                                </div>
                                <p style='top: 30%; left: 6%; '><span>Monday: <span style='padding-left: 20px; font-size: 1.25em; font-weight: bold;'>-30%</span></span></p>
                                <p style='top: 32%; left: 55%;'><span>Tuesday: <span style='padding-left: 15px; font-size: 1.2em; font-weight: bold;'>-25%</span></span></p>
                                <p style='top: 48%; left: 10%;'><span>Wednesday: <span style='padding-left: 5px; font-size: 1.15em'>-20%</span></span></p>
                                <p style='top: 50%; left: 59%;'><span>Thursday: <span style='padding-left: 10px; font-size: 1.1em'>-15%</span></span></p>
                                <p style='top: 66%; left: 16%;'><span>Friday: <span style='padding-left: 20px; font-size: 0.9em'>-10%</span></span></p>
                                <p style='top: 68%; left: 63%;'><span>Saturday: <span style='padding-left: 15px; font-size: 0.9em'>-5%</span></span></p>
                            </div>
                            <div class='berocket-cm21-settings-mobiles-title' style='display: none;'>Up to 30% off sitewide!</div>
                        </a>
                    </div>";
                }
            }
            ?>
            <h2><?php _e('BeRocket Account Settings', 'BeRocket_domain'); ?></h2>
            <div>
                <table>
                    <tr<?php if(empty( $options[ 'account_key' ] )) { echo ' style="display:none!important;"';}?>>
                        <td><h3><?php _e('Account key', 'BeRocket_domain'); ?></h3></td>
                        <td><input type="text" id="berocket_account_key" name="BeRocket_account_option[account_key]"
                                   size="50"
                                   value="<?php echo( empty( $options[ 'account_key' ] ) ? '' : self::hide_key($options[ 'account_key' ]) ) ?>">
                        </td>
                        <td><input class="berocket_test_account button tiny-button" type="button" value="Test"></td>
                        <td class="berocket_product_key_0_status"></td>
                    </tr>
                    <?php
                    foreach ( self::$plugin_info as $plugin ) {
                        echo '<tr class="berocket_updater_plugin_key" data-id="', $plugin[ 'id' ], '">';
                        echo '<td><h4>';
                        if ( isset( $plugin[ 'name' ] ) ) {
                            echo $plugin[ 'name' ];
                        } else {
                            echo $plugin[ 'slug' ];
                        }
                        echo '</h4></td>';
                        echo '<td><input class="berocket_test_account_product_key" id="berocket_product_key_', $plugin[ 'id' ], '" size="50" name="BeRocket_account_option[plugin_key][', $plugin[ 'id' ], ']" type="text" value="', ( empty( $options[ 'plugin_key' ][ $plugin[ 'id' ] ] ) ? '' : self::hide_key($options[ 'plugin_key' ][ $plugin[ 'id' ] ]) ), '"></td>';
                        echo '<td><input class="berocket_test_account_product save_checked button tiny-button" data-id="', $plugin[ 'id' ], '" data-product="#berocket_product_key_', $plugin[ 'id' ], '" type="button" value="Test"></td>';
                        echo '<td class="berocket_product_key_status berocket_product_key_', $plugin[ 'id' ], '_status"></td>';
                        echo '</tr>';
                        unset( $plugins_key[ $plugin[ 'id' ] ] );
                    }
                    foreach ( $plugins_key as $key_id => $key_val ) {
                        echo '<input name="BeRocket_account_option[plugin_key][', $key_id, ']" type="hidden" value="', $key_val, '">';
                    }
                    ?>
                </table>
            </div>
            <div class="berocket_test_result"></div>
            <h2><?php _e('Troubleshooting & Tools', 'BeRocket_domain'); ?></h2>
            <div>
                <table>
                    <tr>
                        <td><h3><?php _e('DEBUG MODE', 'BeRocket_domain'); ?></h3></td>
                        <td colspan=3><label><input type="checkbox" name="BeRocket_account_option[debug_mode]"
                                                    value="1"<?php if ( ! empty( $options[ 'debug_mode' ] ) )
                                    echo ' checked' ?>><?php _e('Enable debug mode', 'BeRocket_domain'); ?></label></td>
                    </tr>
                    <tr>
                        <td><h3><?php _e('Products per page', 'BeRocket_domain'); ?></h3></td>
                        <td colspan=3><input type="number" name="BeRocket_account_option[framework_products_per_page]" value="<?php echo (empty($options['framework_products_per_page']) ? '' : $options['framework_products_per_page']); ?>" placeholder="<?php _e('From WooCommerce', 'BeRocket_domain'); ?>"></td>
                    </tr>
                    <tr>
                        <td><h3><?php _e('Admin bar status', 'BeRocket_domain'); ?></h3></td>
                        <td colspan=3><select name="BeRocket_account_option[disable_admin_bar_panel]">
                            <option value="enable"<?php 
                            if( ! empty($options['disable_admin_bar_panel']) && $options['disable_admin_bar_panel'] == 'enable' ) echo ' selected';
                            ?>><?php _e('Enable', 'BeRocket_domain') ?></option>
                            <option value="disable"<?php 
                            if( ! empty($options['disable_admin_bar_panel']) && $options['disable_admin_bar_panel'] == 'disable' ) echo ' selected';
                            ?>><?php _e('Disable', 'BeRocket_domain') ?></option>
                        </select><?php _e('Enable panel in WordPress Admin Bar', 'BeRocket_domain'); ?></td>
                    </tr>
                    <tr>
                        <td><h3><?php _e('Disable Font Awesome', 'BeRocket_domain'); ?></h3></td>
                        <td colspan=3><label><input type="checkbox" name="BeRocket_account_option[fontawesome_frontend_disable]"
                                                    value="1"<?php if ( ! empty( $options[ 'fontawesome_frontend_disable' ] ) )
                                    echo ' checked' ?>><?php _e('Don\'t load CSS files for Font Awesome on the site\'s front end. Use it only if you don\'t use Font Awesome icons in widgets or have Font Awesome in your theme.', 'BeRocket_domain'); ?></label></td>
                    </tr>
                    <tr>
                        <td><h3><?php _e('Font Awesome Version', 'BeRocket_domain'); ?></h3></td>
                        <td colspan=3><select name="BeRocket_account_option[fontawesome_frontend_version]">
                            <option value=""<?php 
                            if( empty($options['fontawesome_frontend_version']) ) echo ' selected';
                            ?>><?php _e('Enable', 'BeRocket_domain') ?></option>
                            <option value="fontawesome5"<?php 
                            if( ! empty($options['fontawesome_frontend_version']) && $options['fontawesome_frontend_version'] == 'fontawesome5' ) echo ' selected';
                            ?>><?php _e('Font Awesome 5', 'BeRocket_domain') ?></option>
                        </select></td>
                    </tr>
                </table>
            </div>
            <button type="submit" class="button"><?php _e('Save Changes', 'BeRocket_domain'); ?></button>

            <div class="berocket_debug_errors">
                <h3><?php _e('Errors', 'BeRocket_domain'); ?></h3>
                <div>
                    <?php _e('Select plugin', 'BeRocket_domain'); ?>
                    <select class="berocket_select_plugin_for_error">
                        <?php
                        foreach ( self::$plugin_info as $plugin ) {
                            echo '<option value="' . $plugin[ 'id' ] . '">' . $plugin[ 'name' ] . '</option>';
                        }
                        ?>
                    </select>
                    <button type="button" class="button tiny-button berocket_get_plugin_for_error"  data-nonce="<?php echo wp_create_nonce('berocket_error_notices_get'); ?>">Get errors</button>
                    <div class="berocket_html_plugin_for_error"></div>
                </div>
            </div>
            <script>
                jQuery('.berocket_get_plugin_for_error').click(function () {
                    var plugin_id = jQuery('.berocket_select_plugin_for_error').val();
                    var nonce = jQuery(this).data("nonce");
                    jQuery.post(ajaxurl, {action: 'berocket_error_notices_get', plugin_id: plugin_id, wp_nonce: nonce}, function (data) {
                        jQuery('.berocket_html_plugin_for_error').html(data);
                    });
                });
                jQuery('.berocket_test_account').click(function (event) {
                    event.preventDefault();
                    key = jQuery('#berocket_account_key').val();
                    BeRocket_key_check(key, true);
                });
                jQuery(document).on('change', '.berocket_test_account_product_key', function() {
                    jQuery(this).parents('.berocket_updater_plugin_key').find('.berocket_test_account_product').removeClass('save_checked');
                });
                var berocket_key_checked = 0;
                var berocket_key_count = 1;
                function next_berocket_key_check(result) {
                    berocket_key_checked = berocket_key_count - jQuery('.berocket_test_account_product:not(.save_checked), #berocket_account_key:not(.save_checked)').length;
                    var button = jQuery('.account_key_send .button[type="submit"]');
                    var element = jQuery('.berocket_test_account_product:not(.save_checked)').first();
                    if( element.length ) {
                        button.html('Checking keys '+berocket_key_checked+' / '+berocket_key_count+' <i class="fa fa-refresh fa-spin"></i>');
                        
                        if (element.data('product')) {
                            key_element = jQuery(element.data('product'));
                        } else {
                            key_element = jQuery('#berocket_product_key');
                        }
                        key = key_element.val();
                        element.addClass('save_checked');
                        var is_correct = BeRocket_key_check(key, false, element.data('id'), next_berocket_key_check, 1);
                    } else {
                        button.html('Saving keys <i class="fa fa-refresh fa-spin"></i>');
                        jQuery('.account_key_send').trigger('submit');
                    }
                }
                var br_key_checked_before_send = 0;
                jQuery(document).on('submit', '.account_key_send', function (event) {
                    if( br_key_checked_before_send < 2 ) {
                        event.preventDefault();
                    }
                    if( br_key_checked_before_send < 1 ) {
                        br_key_checked_before_send = 1;
                        jQuery('.account_key_send .button[type="submit"]').html('Checking keys <i class="fa fa-refresh fa-spin"></i>');
                        if( ! jQuery('#berocket_account_key').val() ) {
                            jQuery('#berocket_account_key').addClass('save_checked');
                        }
                        var keys = [];
                        var account_key = jQuery('#berocket_account_key').val();
                        if( account_key ) {
                            keys.push({id:'0', key:account_key});
                        }
                        jQuery('.berocket_updater_plugin_key').each(function() {
                            var plugin_key = jQuery(this).find('.berocket_test_account_product_key').val();
                            if( plugin_key ) {
                                keys.push({id: jQuery(this).data('id'), key: plugin_key});
                            }
                        });
                        data = {action: 'br_test_keys', keys: keys, wp_nonce: "<?php echo wp_create_nonce('br_test_keys'); ?>"};
                        jQuery.ajax({
                            url: ajaxurl,
                            data: data,
                            type: 'POST',
                            success: function (data) {
                                jQuery.each(data, function(plugin_id) {
                                    if( this == true ) {
                                        jQuery('.berocket_product_key_' + plugin_id + '_status').html('Success');
                                    } else {
                                        jQuery('.berocket_product_key_' + plugin_id + '_status').html('Failed');
                                        if( plugin_id == 0 ) {
                                            jQuery('#berocket_account_key').val('');
                                        } else {
                                            jQuery('#berocket_product_key_' + plugin_id).val('');
                                        }
                                    }
                                });
                                br_key_checked_before_send = 2;
                                jQuery('.account_key_send .button[type="submit"]').html('Saving keys <i class="fa fa-refresh fa-spin"></i>');
                                jQuery('.account_key_send').trigger('submit');
                            },
                            dataType: 'json'
                        });
                    }
                });
            </script>
			<style>.notice:not(.berocket_admin_notice){display:none!important;}</style>
            <?php
        }

        /** One request/cache serves both activation and update permissions. */
        public static function check_plugin_activation($plugin_test_id = false, $data_send = false, $force = false) {
            $store_cache = $data_send === false;
            $plugins = array();
            foreach (self::$plugin_info as $plugin) {
                if (!empty($plugin['key'])) { $plugins[(int)$plugin['id']] = $plugin; }
            }
            if ($store_cache) {
                $options = self::get_options();
                $data_send = array('keys'=>array());
                if (!empty($options['account_key'])) {
                    $data_send['keys'][] = array('id'=>0, 'key'=>$options['account_key']);
                }
                foreach ($plugins as $plugin) {
                    $data_send['keys'][] = array('id'=>(int)$plugin['id'], 'key'=>$plugin['key']);
                }
            }
            // AJAX key tests can contain a new key; do not enroll it until it is saved.
            $requested = array();
            foreach ($data_send['keys'] ?? array() as $entry) {
                if (!is_array($entry) || !isset($entry['id'], $entry['key']) || !is_scalar($entry['id']) || !is_string($entry['key'])) { continue; }
                $id = (int)$entry['id'];
                if (isset($plugins[$id]) && $plugins[$id]['key'] === $entry['key']) {
                    $entry['installed_version'] = (string)$plugins[$id]['version'];
                    $entry['installed_licenses'] = self::license_installed($plugins[$id]);
                }
                $requested[$id] = $entry;
            }
            $data_send['keys'] = array_values($requested);
            $data_send['url'] = get_site_url();
            $data_send['license_protocol'] = self::LICENSE_PROTOCOL;
            $hash = hash('sha256', get_current_blog_id().'|'.wp_json_encode($data_send));
            $cached = $store_cache ? get_transient('br_plugin_activation') : false;
            $same_request = is_array($cached) && ($cached['hash'] ?? '') === $hash && ($cached['cache_version'] ?? 0) === 2;
            $public_versions = $same_request ? ($cached['public_versions'] ?? array()) : array();
            $public_changed = false;
            // CDN changes refresh enrolled rights once. Record the high-water mark
            // even on an API error so lagging CDN edges cannot cause request loops.
            foreach ($plugins as $id => $plugin) {
                $public = self::license_public_version($plugin, false);
                if ($public !== null && (!isset($public_versions[$id]) || version_compare($public, $public_versions[$id], '>'))) {
                    $public_versions[$id] = $public;
                    if ($same_request && isset($cached['plugins'][$id]['updates'])) { $public_changed = true; }
                }
            }
            $retry_wait = $same_request && ($cached['retry_after'] ?? 0) > time();
            if (!$force && $same_request && ($cached['refresh_at'] ?? 0) > time() && (!$public_changed || $retry_wait)) {
                return $plugin_test_id === false ? $cached : ($cached['plugins'][$plugin_test_id] ?? false);
            }
            $result = array('cache_version'=>2, 'hash'=>$hash, 'plugins'=>array(), 'public_versions'=>$public_versions);
            $rows = array();
            $data = $requested ? null : array();
            if ($requested) {
                $response = wp_remote_post(BeRocket_update_path.'v1/check_plugin_key/', array(
                    'body'=>$data_send, 'timeout'=>15, 'redirection'=>0, 'sslverify'=>true, 'limit_response_size'=>262144
                ));
                if (!is_wp_error($response) && (int)wp_remote_retrieve_response_code($response) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                }
                foreach (is_array($data) ? $data : array() as $row) {
                    if (!is_array($row) || !isset($row['id']) || !is_scalar($row['id']) || !ctype_digit((string)$row['id'])) { continue; }
                    $id = (int)$row['id'];
                    $rows[$id] = array_key_exists($id, $rows) ? false : $row;
                }
            }
            $ttl = 604800; // Preserve the upstream seven-day legacy activation cache.
            $failed = !is_array($data);
            $cutoff = null;
            $jitter = hexdec(substr($hash, 0, 4));
            // Preserve universal account-key products; an explicit product key takes precedence.
            if (isset($requested[0])) {
                $universal_valid = isset($rows[0]) && is_array($rows[0]) && is_bool($rows[0]['status'] ?? null) && is_array($rows[0]['products'] ?? null);
                if (!$universal_valid) { $failed = true; }
                $products = $universal_valid && $rows[0]['status'] ? $rows[0]['products'] : array();
                if (!$universal_valid && $same_request && !empty($cached['plugins'][0])) {
                    foreach ($cached['plugins'] as $id => $previous_row) {
                        if ($id && !isset($requested[$id]) && !isset($previous_row['updates'])) { $products[] = $id; }
                    }
                }
                $result['plugins'][0] = !empty($products) && is_array($products);
                foreach (is_array($products) ? $products : array() as $product_id) {
                    if (is_scalar($product_id) && (int)$product_id > 0) {
                        $result['plugins'][(int)$product_id] = array('status'=>true, 'license'=>'paid');
                    }
                }
            }
            foreach ($requested as $id => $entry) {
                if (!$id) { continue; }
                $row = $rows[$id] ?? null;
                $row_valid = is_array($row) && in_array($row['status'] ?? null, array(true,false,0,1), true) && in_array($row['license'] ?? 'paid', array('paid','business'), true);
                if ($row_valid) {
                    $result['plugins'][$id] = array('status'=>!empty($row['status']), 'license'=>empty($row['license']) ? 'paid' : $row['license']);
                } else {
                    $failed = true;
                    // A transport/malformed reply does not invalidate a previously
                    // confirmed activation. Enrolled update packages are held below.
                    if ($same_request && isset($cached['plugins'][$id])) { $result['plugins'][$id] = $cached['plugins'][$id]; }
                }
                if (!isset($plugins[$id]) || $plugins[$id]['key'] !== $entry['key'] || !self::license_eligible($plugins[$id]) || self::license_key($plugins[$id]) === '') { continue; }
                $plugin = $plugins[$id];
                $known_key = 'br_lu_known_'.self::license_identity($plugin);
                $previous = get_option($known_key, null);
                $record = is_array($row) ? ($row['updates'] ?? null) : null;
                $managed = is_array($record) && ($record['protocol'] ?? '') === self::LICENSE_PROTOCOL && ($record['mode'] ?? '') === 'enforce';
                if ($managed) {
                    $decision = $row_valid && ($record['valid_key'] ?? null) === (bool)$row['status'] ? self::license_validate($record, $plugin, $row['license'] ?? 'paid') : false;
                    if ($decision === false) {
                        $decision = self::license_held($previous, 'response_invalid');
                    } elseif (($record['decision_status'] ?? '') === 'unavailable') {
                        $decision = self::license_held($decision, 'verification_unavailable');
                    }
                } elseif (is_array($previous)) {
                    $decision = self::license_held($previous, is_array($data) ? 'confirmation_missing' : 'verification_unavailable');
                } else { $decision = null; }
                if ($decision !== null) {
                    // Activation is confirmed by the outer v1 status, even when
                    // the duration/allowed release is still unresolved.
                    if ($row_valid) { $decision['valid_key'] = (bool)$row['status']; }
                    if ($row_valid && !$row['status']) {
                        $decision['updates_expired'] = false;
                        $decision['allowed_version'] = null;
                        $decision['package_available'] = false;
                        $decision['fresh'] = true;
                    }
                    if ($store_cache && ($managed || ($row_valid && !$row['status']))) { update_option($known_key, $decision, false); }
                    // Expiry or a transport failure is not an activation failure.
                    $result['plugins'][$id] = array(
                        'status'=>is_bool($decision['valid_key']) ? $decision['valid_key'] : !empty($row['status']),
                        'license'=>$decision['license'], 'updates'=>$decision
                    );
                    if (!$decision['fresh']) { $failed = true; }
                    else {
                        $ttl = min($ttl, $decision['updates_expired'] ? 3600 + ($jitter % 601) : 43200 + ($jitter % 3601));
                    }
                    if ($decision['updates_until_utc']) {
                        $until = self::license_utc($decision['updates_until_utc']);
                        if ($until > time()) { $cutoff = $cutoff === null ? $until : min($cutoff, $until); }
                    }
                }
            }
            $failures = $failed ? min(6, ($same_request ? (int)($cached['failures'] ?? 0) : 0) + 1) : 0;
            $result['failures'] = $failures;
            $result['retry_after'] = 0;
            if ($failed) {
                $backoff = min(21600, 900 * pow(2, $failures - 1));
                $ttl = min($ttl, 21600, $backoff + ($jitter % 91));
                $result['retry_after'] = time() + $ttl;
            }
            if ($cutoff !== null) { $ttl = min($ttl, max(1, $cutoff - time())); }
            $result['refresh_at'] = time()+$ttl;
            // Keep retry history across refresh deadlines; refresh_at still governs
            // reuse. The persistent known decision remains the enforcement fallback.
            if ($store_cache) { set_transient('br_plugin_activation', $result, 604800); }
            return $plugin_test_id === false ? $result : ($result['plugins'][$plugin_test_id] ?? false);
        }

        public static function update_check_set( $value, $managed_only = false ) {
            if (!is_object($value)) { self::check_plugin_activation(); return $value; }
            $no_update_paid = array();

            foreach ( self::$plugin_info as $plugin ) {
                $check_plugin_activation = self::check_plugin_activation($plugin[ 'id' ]);
                $decision = is_array($check_plugin_activation) ? ($check_plugin_activation['updates'] ?? null) : null;
                if ($decision !== null) {
                    $file = $plugin['plugin'];
                    $can_update = self::license_can_update($plugin, $decision);
                    $public_version = self::license_public_version($plugin);
                    $newer = $public_version !== null && version_compare($public_version, $plugin['version'], '>');
                    $target = $can_update ? $decision['allowed_version'] : ($newer ? $public_version : $plugin['version']);
                    $current = isset($value->response[$file]) ? (array)$value->response[$file] : array();
                    $val = (object)array_merge($current, array('id'=>'br_'.$plugin['id'], 'slug'=>$plugin['slug'], 'plugin'=>$file,
                        'new_version'=>$target, 'url'=>BeRocket_main_path,
                        'package'=>$can_update ? self::license_package($plugin, $decision['license'], $target) : '', 'br_license_managed'=>true));
                    $value->checked[$file] = (string)$plugin['version'];
                    unset($value->response[$file], $value->no_update[$file]);
                    if ($can_update || $newer) { $value->response[$file] = $val; }
                    else { $value->no_update[$file] = $val; $no_update_paid[$file] = $val; }
                    continue;
                }
                if ($managed_only) { continue; }
                $is_active = ( ! empty($check_plugin_activation) && is_array($check_plugin_activation) && ! empty($check_plugin_activation['status']) );
                $license_key_name = ( is_array($check_plugin_activation) && ! empty($check_plugin_activation['license']) ? $check_plugin_activation['license'] : 'paid' );
                $plugin_data = BeRocket_Framework::get_product_data_berocket($plugin[ 'id' ]);
                $plugin_licenses = apply_filters('brfr_plugin_get_licenses_current_id_' . $plugin[ 'id' ], array('free'));
                if ( ! empty( self::$key ) && strlen( self::$key ) == 40 ) {
                    $key = self::$key;
                }
                if ( ! empty( $plugin[ 'key' ] ) && strlen( $plugin[ 'key' ] ) == 40 ) {
                    $key = $plugin[ 'key' ];
                }

                $responsed = false;
                $remote_version = ( ! empty($plugin_data['version']) && is_scalar($plugin_data['version']) )
                    ? sanitize_text_field((string)$plugin_data['version'])
                    : '';
                if ( $is_active && ! empty($plugin_data) && is_array($plugin_data) && is_array($plugin) && ! empty($remote_version)
                && ( version_compare( (string)$plugin[ 'version' ], $remote_version, '<' ) || ! in_array($license_key_name, $plugin_licenses) ) && ! empty($value) ) {
                    $value->checked[ $plugin[ 'plugin' ] ]  = (string)$plugin['version'];
                    $val                                    = array(
                        'id'          => 'br_' . $plugin[ 'id' ],
                        'new_version' => $remote_version . '(' . sanitize_text_field((string)$license_key_name) . ')',
                        'package'     => BeRocket_update_path . 'v1/update_product/' . $plugin[ 'id' ] . '/' . $key,
                        'url'         => BeRocket_cdn_path . 'product/' . $plugin[ 'slug' ],
                        'plugin'      => $plugin[ 'plugin' ],
                        'slug'        => $plugin[ 'slug' ]
                    );
                    if( ! empty($plugin['free_slug']) ) {
                        if( ! empty($value->response[ $plugin[ 'plugin' ] ]) ) {
                            $api = $value->response[ $plugin[ 'plugin' ] ];
                        } else {
                            include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
                            $api = get_transient( 'br_plugin_api_' . $plugin['free_slug'] );
                            if( empty($api) ) {
                                $api = plugins_api( 'plugin_information', array(
                                    'slug' => wp_unslash( $plugin['free_slug'] ),
                                    'is_ssl' => is_ssl(),
                                    'fields' => array(
                                        'banners' => true,
                                        'reviews' => false,
                                        'downloaded' => false,
                                        'active_installs' => true,
                                        'icons' => true
                                    )
                                ) );
                                set_transient( 'br_plugin_api_' . $plugin['free_slug'], $api, 604800 );
                            }
                        }
                        $api = (array)$api;
                        $val = array_merge($api, $val);
                    }
                    if( isset($val['upgrade_notice']) && ! is_string($val['upgrade_notice']) ) {
                        $val['upgrade_notice'] = '';
                    }
                    $val = (object)$val;
                    $value->response[ $plugin[ 'plugin' ] ] = $val;
                    
                    $responsed = true;
                }
                if( ! $responsed && isset($plugin[ 'version_capability' ]) && $plugin[ 'version_capability' ] >= 10 ) {
                    $val                                    = (object) array(
                        'id'          => 'br_' . $plugin[ 'id' ],
                        'new_version' => $plugin[ 'version' ],
                        'package'     => BeRocket_update_path . 'v1/update_product/' . $plugin[ 'id' ] . '/' . ( empty($key) ? 'none' : $key ),
                        'url'         => BeRocket_cdn_path . 'product/' . $plugin[ 'slug' ],
                        'plugin'      => $plugin[ 'plugin' ],
                        'slug'        => $plugin[ 'slug' ]
                    );
                    $no_update_paid[$plugin[ 'plugin' ]] = $val;
                }
            }
            if ($managed_only) { return $value; }
            if ( ! empty($value) && isset( $value->no_update ) && is_array( $value->no_update ) ) {
                $value->no_update = array_merge($value->no_update, $no_update_paid);
                foreach ( $value->no_update as $key => $val ) {
                    if ( isset( $val->slug ) && in_array( $val->slug, self::$slugs ) ) {
                        if( ! array_key_exists($key, $no_update_paid) ) {
                            unset( $value->no_update[ $key ] );
                        }
                    }
                    if ( isset( $val->slug ) && in_array( $val->slug, self::$free_slugs ) ) {
                        if( ! array_key_exists($key, $no_update_paid) ) {
                            unset( $value->no_update[ $key ] );
                        }
                    }
                }
            }
            if ( ! empty($value) && isset( $value->response ) && is_array( $value->response ) ) {
                foreach ( $value->response as $key => $val ) {
                    if( array_key_exists($key, $no_update_paid) ) {
                        unset( $value->response[ $key ] );
                    }
                }
            }
            return $value;
        }

        public static function get_options() {
            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
            }

            $multisite = (is_multisite() && is_plugin_active_for_network( plugin_basename( __FILE__ ) ) );
            $options = BeRocket_Framework::get_global_option($multisite);

            if( empty($options) || ! is_array($options) ) {
                $options = array();
            }

            return $options;
        }

        public static function set_options( $options ) {
            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
            }

            $options = self::restore_keys($options);
            $multisite = ( is_multisite() && is_plugin_active_for_network( plugin_basename( __FILE__ ) ) );
            BeRocket_Framework::save_global_option($options, $multisite);
            if ( $multisite ) {
                delete_site_transient('br_plugin_activation');
            } else {
                delete_transient('br_plugin_activation');
            }
            delete_transient('berocket_plugin_paid_info');
            self::update_check_set('');
            delete_site_transient( 'update_plugins' );
        }
        public static function admin_notice_is_display_notice($display_notice, $item, $search_data) {
            if( ! $display_notice ) {
                return $display_notice;
            }
            if( ! empty($item['conditions']) && is_array($item['conditions'])
                && isset($item['conditions']['plugin_version_capability']) && isset($item['conditions']['plugin_id']) ) {
                $from = min($item['conditions']['plugin_version_capability']);
                $to = max($item['conditions']['plugin_version_capability']);
                $plugin_exist = false;
                foreach ( self::$plugin_info as $plugin ) {
                    if ( $plugin[ 'id' ] == $item['conditions']['plugin_id'] ) {
                        $display_notice = ($plugin['version_capability'] >= $from && $plugin['version_capability'] < $to);
                        $plugin_exist = true;
                        break;
                    }
                }
                if( ! $plugin_exist ) {
                    $display_notice = false;
                }
            }
            return $display_notice;
        }

        public static function woocommerce_addons_sections($sections) {
            if( ! is_array($sections) ) {
                $sections = array();
            }
            $sections[] = (object)array(
                'slug' => 'berocket',
                'label' => 'BeRocket'
            );
            return $sections;
        }
        public static function woocommerce_addons_berocket() {
            $addons = array();
            $products = BeRocket_Framework::get_product_data_berocket();
            if( ! empty($products) && is_array($products) ) {
                foreach($products as $product) {
                    $addons[] = (object)array(
                        'title' => $product->name,
                        'image' => $product->mini_image,
                        'excerpt' => $product->about,
                        'link'      => $product->plugin_url,
                        'price'     => '$'.$product->price,
                        'hash'      => '',
                        'slug'      => $product->slug
                    );
                }
            }
            if(! empty($_GET['search']) ) {
                $correct_addon = array();
                $search  = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
                foreach($addons as $addon) {
                    if( stripos($addon->title, $search) !== FALSE || stripos($addon->excerpt, $search) !== FALSE ) {
                        $correct_addon[] = $addon;
                    }
                }
                $addons = $correct_addon;
            }
            ?>
            <ul class="berocket_section_wc_addons" style="display: none;">
            <?php foreach ( $addons as $addon ) : ?>
                <li class="product">
                    <a href="<?php echo esc_url( $addon->link ); ?>">
                        <?php if ( ! empty( $addon->image ) ) : ?>
                            <span class="product-img-wrap"><img src="<?php echo esc_url( $addon->image ); ?>"/></span>
                        <?php else : ?>
                            <h2><?php echo esc_html( $addon->title ); ?></h2>
                        <?php endif; ?>
                        <span class="price"><?php echo wp_kses_post( $addon->price ); ?></span>
                        <p><?php echo wp_kses_post( $addon->excerpt ); ?></p>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
            <ul class="berocket_section_wc_addons_new" style="display: none;">
            <?php 
            $class_names = array( 'product' );
            $product_details_classes = 'product-details';
            foreach( $addons as $addon ) {
                ?>
                <li class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>">
                    <div class="<?php echo esc_attr( $product_details_classes ); ?>">
                        <div class="product-text-container">
                            <a href="<?php echo esc_url( self::link_marketplace($addon->link) ); ?>">
                                <h2><?php echo esc_html( $addon->title ); ?></h2>
                            </a>
                            <div class="product-developed-by">
                                <?php
                                $vendor_url = self::link_marketplace('https://berocket.com');

                                printf(
                                /* translators: %s vendor link */
                                    esc_html__( 'Developed by %s', 'woocommerce' ),
                                    sprintf(
                                        '<a class="product-vendor-link" href="%1$s" target="_blank">%2$s</a>',
                                        esc_url_raw( $vendor_url ),
                                        esc_html( 'BeRocket' )
                                    )
                                );
                                ?>
                            </div>
                            <p><?php echo wp_kses_post( $addon->excerpt ); ?></p>
                        </div>
                        <?php if ( ! empty( $addon->image ) ) : ?>
                            <span class="product-img-wrap">
                                <?php /* Show an icon if it exists */ ?>
                                <img src="<?php echo esc_url( $addon->image ); ?>" />
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="product-footer">
                        <div class="product-price-and-reviews-container">
                            <div class="product-price-block">
                                <?php if ( $addon->price == 0 || $addon->price == '$0' ) : ?>
                                    <span class="price"><?php esc_html_e( 'Free', 'woocommerce' ); ?></span>
                                <?php else : ?>
                                    <span class="price">
                                        <?php
                                        echo wp_kses(
                                            $addon->price,
                                            array(
                                                'span' => array(
                                                    'class' => array(),
                                                ),
                                                'bdi'  => array(),
                                            )
                                        );
                                        ?>
                                    </span>
                                    <span class="price-suffix"><?php esc_html_e( 'one time', 'woocommerce' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ( ! empty( $mapped->reviews_count ) && ! empty( $mapped->rating ) ) : ?>
                                <?php /* Show rating and the number of reviews */ ?>
                                <div class="product-reviews-block">
                                    <?php for ( $index = 1; $index <= 5; ++$index ) : ?>
                                        <?php $rating_star_class = 'product-rating-star product-rating-star__' . self::get_star_class( $mapped->rating, $index ); ?>
                                        <div class="<?php echo esc_attr( $rating_star_class ); ?>"></div>
                                    <?php endfor; ?>
                                    <span class="product-reviews-count">(<?php echo (int) $mapped->reviews_count; ?>)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a class="button" href="<?php echo esc_url( self::link_marketplace($addon->link) ); ?>">
                            <?php esc_html_e( 'View details', 'woocommerce' ); ?>
                        </a>
                    </div>
                </li>
                <?php
            }
            ?>
            </ul>
            <script>
                if( jQuery('.berocket_section_wc_addons').length && jQuery('.wc_addons_wrap .search-form').length ) {
                    jQuery('.berocket_section_wc_addons').addClass('products').show();
                    jQuery('.wc_addons_wrap .search-form').after(jQuery('.berocket_section_wc_addons'));
                }
                if( jQuery('.berocket_section_wc_addons_new').length && jQuery('.marketplace-content-wrapper').length ) {
                    jQuery('.berocket_section_wc_addons_new').show();
                    if( jQuery('.marketplace-content-wrapper .products').length > 0 ) {
                        jQuery('.marketplace-content-wrapper .products').prepend(jQuery('.berocket_section_wc_addons_new .product'));
                    } else {
                        jQuery('.berocket_section_wc_addons_new').addClass('products');
                        jQuery('.marketplace-content-wrapper').prepend(jQuery('.berocket_section_wc_addons_new'));
                    }
                }
            </script>
            <?php
        }
        public static function link_marketplace($link) {
            $link = add_query_arg( array(
                'utm_source'    => 'free_plugin',
                'utm_medium'    => 'marketplace',
                'utm_campaign'  => 'marketplace',
            ), $link );
            return $link;
        }
        public static function hide_key($key) {
            if( ! empty($key) ) {
                $part = (int)(strlen($key) / 3);
                $replace = strlen($key) - $part;
                $key = substr($key, $replace);
                for($i = 0; $i < $replace; $i++) {
                    $key = '*'.$key;
                }
            }
            return $key;
        }
        public static function restore_keys($options) {
            $options_old = self::get_options();
            if( ! empty($options['account_key']) && strpos($options['account_key'], '**') !== false && ! empty($options_old['account_key']) ) {
                $options['account_key'] = $options_old['account_key'];
            }
            if( ! empty($options['plugin_key']) && is_array($options['plugin_key']) ) {
                foreach($options['plugin_key'] as $plugin_id => $plugin_key) {
                    if( ! empty($plugin_key) && strpos($plugin_key, '**') !== false && ! empty($options_old['plugin_key']) && ! empty($options_old['plugin_key'][$plugin_id]) ) {
                        $options['plugin_key'][$plugin_id] = $options_old['plugin_key'][$plugin_id];
                    }
                }
            }
            return $options;
        }
        public static function get_plugin_data($plugin_id) {
            $data = array();
            if( is_array(self::$plugin_info) ) {
                foreach(self::$plugin_info as $plugin) {
                    if( $plugin['id'] == $plugin_id ) {
                        return $plugin;
                    }
                }
            }
            return $data;
        }
        public static function update_message($plugin_file) {
            $registered = self::license_find($plugin_file);
            $decision = $registered ? self::license_decision($registered) : null;
            if ($decision !== null) {
                $public_version = self::license_public_version($registered);
                $has_row = self::license_can_update($registered, $decision) || ($public_version !== null && version_compare($public_version, $registered['version'], '>'));
                if (!$has_row && self::license_text($registered, $decision) !== '') {
                    $table = _get_list_table('WP_Plugins_List_Table', array('screen'=>get_current_screen()));
                    echo '<tr class="plugin-update-tr"><td colspan="'.esc_attr($table->get_column_count()).'"><div class="notice inline notice-warning"><p>';
                    self::print_license_message($registered, $decision);
                    echo '</p></div></td></tr>';
                }
                return;
            }
            $plugin_data = false;
            foreach ( self::$plugin_info as $plugin ) {
                if( $plugin_file == $plugin['plugin'] ) {
                    $version_capability = br_get_value_from_array($plugin, array('version_capability'), 15);
                    if ( $version_capability > 5 && ! in_array($version_capability, array(15, 3, 17)) ) {
                        $plugin_data = $plugin;
                    }
                    break;
                }
            }
            if( $plugin_data !== false ) {
                $wp_list_table = _get_list_table(
                    'WP_Plugins_List_Table',
                    array(
                        'screen' => get_current_screen(),
                    )
                );
                $check_plugin_activation = self::check_plugin_activation($plugin_data['id']);
                $is_active = ( ! empty($check_plugin_activation) && is_array($check_plugin_activation) && ! empty($check_plugin_activation['status']) );
                if( ! $is_active ) {
                    $plugin_data = BeRocket_Framework::get_product_data_berocket($plugin[ 'id' ]);
                    $remote_version = ( ! empty($plugin_data['version']) && is_scalar($plugin_data['version']) )
                        ? sanitize_text_field((string)$plugin_data['version'])
                        : '';
                    $remote_name = ( ! empty($plugin_data['name']) && is_scalar($plugin_data['name']) )
                        ? sanitize_text_field((string)$plugin_data['name'])
                        : '';
                    if ( ! empty($plugin_data) && is_array($plugin_data) && ! empty($remote_version) && version_compare($remote_version, $plugin[ 'version' ], '>') ) {
                        echo '<tr class="active plugin-update-tr"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>';
                        printf(
                            __( 'There is a new version %1$s of %2$s available. But <a href="%3$s">Activation required to update</a>.' ),
                            esc_html($remote_version),
                            esc_html($remote_name),
                            esc_url( is_network_admin() ? admin_url( 'network/admin.php?page=berocket_account' ) : admin_url( 'admin.php?page=berocket_account' ) )
                        );
                        echo '</p></div></td></tr>';
                    }
                }
            }
        }

        public static function plugin_api_data($res, $action, $args) {
            if ($action !== 'plugin_information' || !is_object($args) || !isset($args->slug)) { return $res; }
            $plugin = self::license_find($args->slug);
            if (!$plugin || !self::license_eligible($plugin)) { return $res; }
            $decision = self::license_decision($plugin);
            // Only enrolled products short-circuit the WordPress.org request.
            if (current_filter() === 'plugins_api' && $decision === null) { return $res; }
            $plugin_id = (int)$plugin['id'];
            $live = BeRocket_Framework::get_product_data_berocket($plugin_id);
            $remote_version = self::license_public_version($plugin);
            $plugin_url = !empty($live['plugin_url']) && is_string($live['plugin_url']) ? esc_url_raw($live['plugin_url']) : BeRocket_main_path;
            $slug = isset($live['slug']) && is_string($live['slug']) ? preg_replace('/\s+/', '-', strtolower($live['slug'])) : '';
            $notes_url = preg_match('/^[a-z0-9][a-z0-9_-]{0,149}$/D', $slug) ? BeRocket_cdn_path.'update/'.$slug : '';
            $cached = get_transient('brplugin_info_'.$plugin_id);
            if (is_array($cached) && ($cached['source'] ?? '') === 'cdn-html/1' &&
                ($cached['version'] ?? null) === $remote_version && ($cached['url'] ?? '') === $notes_url && is_object($cached['public'] ?? null)) {
                $res = clone $cached['public'];
            } else {
                if ($remote_version === null && $decision === null) { return $res; }
                $res = is_object($res) && !is_wp_error($res) ? clone $res : new stdClass();
                $res->name = $plugin['full_name'] ?? $plugin['name'] ?? $plugin['slug'];
                $res->slug = $plugin['slug'];
                $res->version = $remote_version ?: (string)$plugin['version'];
                $res->author = '<a href="https://berocket.com/">BeRocket</a>';
                $res->author_profile = BeRocket_main_path;
                $res->support_url = $res->commercial_support_url = BeRocket_main_path.'support/';
                $res->support_threads = $res->support_threads_resolved = 0;
                $res->ratings = $res->contributors = array();
                $res->donate_link = '';
                $res->external = true;
                $res->sections = array('changelog'=>'');
                // Existing API publisher writes an HTML document at update/<slug>.
                // No automatic shop fallback, scripts, remote images or stylesheets.
                $details = $notes_url !== '' ? wp_remote_get($notes_url,
                    array('timeout'=>15, 'redirection'=>0, 'sslverify'=>true, 'limit_response_size'=>262144)) : false;
                $body = is_array($details) && (int)wp_remote_retrieve_response_code($details) === 200 ? wp_remote_retrieve_body($details) : '';
                if (is_string($body) && strlen($body) < 262144 && strpos($body, 'plugin_info-changelog') !== false) {
                    $body = preg_replace('~<(script|style|head|iframe|object|template|noscript|form)\b[^>]*>.*?</\1\s*>~is', '', $body);
                    $body = preg_replace('~<li\b[^>]*class=["\'][^"\']*plugin_info-changelog-load-more[^"\']*["\'][^>]*>.*?</li\s*>~is', '', $body);
                    $res->sections['changelog'] = wp_kses($body, array(
                        'div'=>array('class'=>true), 'ul'=>array('class'=>true), 'ol'=>array(), 'li'=>array('class'=>true),
                        'h2'=>array(), 'h3'=>array(), 'h4'=>array(), 'p'=>array(), 'span'=>array('class'=>true),
                        'strong'=>array(), 'b'=>array(), 'em'=>array(), 'i'=>array(), 'br'=>array(),
                        'code'=>array(), 'pre'=>array(), 'a'=>array('href'=>true, 'title'=>true), 'time'=>array('datetime'=>true)
                    ));
                    $res->sections['changelog'] .= '<p><a target="_blank" rel="noopener noreferrer" href="'.esc_url($plugin_url).'">'.esc_html__('View the full release history on BeRocket.', 'BeRocket_domain').'</a></p>';
                }
                if ($res->sections['changelog'] === '') {
                    $res->sections = array('description'=>'<p><a href="'.esc_url($plugin_url ?: BeRocket_main_path).'">'.esc_html__('View product information and release notes on BeRocket.', 'BeRocket_domain').'</a></p>');
                }
                // Cache public metadata only. Key-specific links are computed below.
                unset($res->download_link, $res->package);
                set_transient('brplugin_info_'.$plugin_id, array('source'=>'cdn-html/1', 'version'=>$remote_version, 'url'=>$notes_url, 'public'=>clone $res),
                    isset($res->sections['description']) ? 3600 : 43200);
            }
            unset($res->sections['license']);
            if ($decision === null) {
                $key = self::license_key($plugin) ?: self::$key;
                $res->download_link = BeRocket_update_path.'v1/update_product/'.$plugin_id.'/'.($key ?: 'none');
            } else {
                $can_update = self::license_can_update($plugin, $decision);
                $res->name = $plugin['full_name'] ?? $plugin['name'] ?? $plugin['slug'];
                $res->slug = $plugin['slug'];
                $res->external = true;
                $res->version = $can_update ? $decision['allowed_version'] : ($remote_version ?: $plugin['version']);
                $res->download_link = $can_update ? self::license_package($plugin, $decision['license'], $decision['allowed_version']) : '';
                $text = self::license_text($plugin, $decision);
                if ($text !== '') {
                    $res->sections['license'] = '<p>'.esc_html($text).'</p>';
                    if ($decision['fresh'] && $decision['updates_expired']) {
                        $res->sections['license'] .= '<p><a href="'.esc_url(BeRocket_main_path.'my-account/').'">'.esc_html__('Manage license', 'BeRocket_domain').'</a></p>';
                    }
                }
            }
            return $res;
        }

        public static function license_decision($plugin, $force = false) {
            if (!self::license_eligible($plugin) || self::license_key($plugin) === '') { return null; }
            if (self::license_key(self::get_plugin_data($plugin['id'])) !== self::license_key($plugin)) { return null; }
            $activation = self::check_plugin_activation($plugin['id'], false, $force);
            return is_array($activation) ? ($activation['updates'] ?? null) : null;
        }

        public static function license_check_again() {
            if (!empty($_GET['force-check']) && current_user_can('update_plugins')) {
                delete_transient('br_plugin_activation');
                delete_transient('berocket_plugin_paid_info');
            }
        }

        public static function update_check_read($value) {
            // Reuse the same builder for cached rows after renewal/expiry, without
            // re-running legacy public metadata requests for unconverted products.
            return is_object($value) ? self::update_check_set($value, true) : $value;
        }


        private static function license_eligible($plugin) {
            return is_array($plugin) && !empty($plugin['id']) && !empty($plugin['plugin']) &&
                isset($plugin['version']) && (int)($plugin['version_capability'] ?? 0) >= 10;
        }

        private static function license_key($plugin) {
            // Universal keys keep their agreed lifetime/legacy behavior.
            return isset($plugin['key']) && is_string($plugin['key']) && preg_match('/^[A-Za-z0-9_-]{40}$/D', $plugin['key']) ? $plugin['key'] : '';
        }

        private static function license_identity($plugin) {
            return hash('sha256', get_current_blog_id().'|'.get_site_url().'|'.(int)$plugin['id'].'|'.self::license_key($plugin));
        }

        public static function license_version($value) {
            return is_string($value) && preg_match('/^[0-9][0-9A-Za-z.+_-]{0,49}$/D', $value);
        }

        private static function license_public_version($plugin, $allow_cached = true) {
            $public = BeRocket_Framework::get_product_data_berocket((int)$plugin['id']);
            if (is_array($public) && self::license_version($public['version'] ?? null)) { return $public['version']; }
            // This is a version previously observed on CDN, never the API's latest.
            $cached = $allow_cached ? get_transient('br_plugin_activation') : false;
            $version = is_array($cached) ? ($cached['public_versions'][(int)$plugin['id']] ?? null) : null;
            return self::license_version($version) ? $version : null;
        }

        private static function license_utc($value) {
            if (!is_string($value)) { return false; }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
            return $date && $date->format('Y-m-d\TH:i:s\Z') === $value ? $date->getTimestamp() : false;
        }

        public static function license_package($plugin, $license, $version) {
            return BeRocket_update_path.'v1/update_release/'.(int)$plugin['id'].'/'.rawurlencode(self::license_key($plugin)).'/'.$license.'/'.rawurlencode($version);
        }

        public static function license_validate($record, $plugin, $license) {
            if (!is_array($record) || ($record['protocol'] ?? '') !== self::LICENSE_PROTOCOL || ($record['mode'] ?? '') !== 'enforce' ||
                ($record['product_id'] ?? null) !== (int)$plugin['id'] || !is_bool($record['valid_key'] ?? null) ||
                !is_bool($record['updates_expired'] ?? null) || !array_key_exists('updates_until_utc', $record) ||
                !array_key_exists('allowed_version', $record) || !self::license_version($record['latest_version'] ?? null) ||
                !is_string($record['package'] ?? null) || !in_array($license, array('paid','business'), true)) { return false; }
            $kind = $record['license_kind'] ?? '';
            $until = $record['updates_until_utc'];
            if ($kind === 'subscription') {
                if (self::license_utc($until) === false) { return false; }
            } elseif ($kind === 'lifetime') {
                if ($until !== null || $record['updates_expired']) { return false; }
            } elseif ($kind !== 'unknown' || $record['valid_key'] || $until !== null || $record['updates_expired']) { return false; }
            $allowed = $record['allowed_version'];
            if ($allowed !== null && (!self::license_version($allowed) || version_compare($allowed, $record['latest_version'], '>'))) { return false; }
            if (!$record['valid_key'] && ($allowed !== null || $record['package'] !== '' || $record['updates_expired'])) { return false; }
            if ($record['package'] !== '' && (!$record['valid_key'] || $allowed === null ||
                $record['package'] !== self::license_package($plugin, $license, $allowed))) { return false; }
            return array('valid_key'=>$record['valid_key'], 'license_kind'=>$kind, 'updates_expired'=>$record['updates_expired'],
                'updates_until_utc'=>$until, 'latest_version'=>$record['latest_version'], 'allowed_version'=>$allowed,
                'license'=>$license, 'package_available'=>$record['package'] !== '', 'fresh'=>true, 'error'=>'');
        }

        private static function license_held($previous, $reason) {
            $r = is_array($previous) ? $previous : array('valid_key'=>null, 'license_kind'=>'unknown', 'updates_expired'=>false,
                'updates_until_utc'=>null, 'latest_version'=>null, 'allowed_version'=>null, 'license'=>'paid', 'package_available'=>false);
            $r['fresh'] = false;
            $r['error'] = $reason;
            return $r;
        }

        public static function license_can_update($plugin, $decision) {
            if (empty($decision['fresh']) || empty($decision['valid_key']) || empty($decision['package_available']) ||
                !self::license_version($decision['allowed_version'] ?? null)) { return false; }
            $public_version = self::license_public_version($plugin);
            if ($public_version === null || version_compare($decision['allowed_version'], $public_version, '>')) { return false; }
            $compare = version_compare($decision['allowed_version'], (string)$plugin['version']);
            $licenses = self::license_installed($plugin);
            return $compare > 0 || ($compare === 0 && $licenses && !in_array($decision['license'], $licenses, true));
        }

        private static function license_find($file_or_slug) {
            foreach (self::$plugin_info as $plugin) {
                if (($plugin['plugin'] ?? '') === $file_or_slug || ($plugin['slug'] ?? '') === $file_or_slug ||
                    (!empty($plugin['free_slug']) && $plugin['free_slug'] === $file_or_slug)) { return $plugin; }
            }
            return null;
        }

        public static function license_text($plugin, $decision) {
            if (empty($decision['fresh'])) { return __('License information could not be verified. Please try checking for updates again.', 'BeRocket_domain'); }
            if (!$decision['valid_key']) { return __('This key is not authorized for updates. Please check your BeRocket account key.', 'BeRocket_domain'); }
            $public_version = self::license_public_version($plugin);
            if ($decision['updates_expired']) {
                $text = __('Your update license has expired. Renew your license or purchase a new one to receive newer releases.', 'BeRocket_domain');
                if (self::license_version($decision['allowed_version'])) { $text .= ' '.sprintf(__('Your license includes version %s.', 'BeRocket_domain'), $decision['allowed_version']); }
                if (self::license_can_update($plugin, $decision) && $public_version !== null && version_compare($public_version, $decision['allowed_version'], '>')) {
                    $text .= ' '.sprintf(__('The latest release is %s.', 'BeRocket_domain'), $public_version);
                }
                return $text;
            }
            if ($public_version === null || (self::license_version($decision['allowed_version']) && version_compare($decision['allowed_version'], $public_version, '>'))) {
                return __('Update information is temporarily unavailable. Please check for updates again later.', 'BeRocket_domain');
            }
            if ((!$decision['package_available'] || !self::license_version($decision['allowed_version']) || version_compare($public_version, $decision['allowed_version'], '>')) && version_compare($public_version, (string)$plugin['version'], '>')) {
                return __('The update package is temporarily unavailable. Please try again later.', 'BeRocket_domain');
            }
            return '';
        }

        public static function license_message($metadata, $response) {
            $plugin = self::license_find($response->plugin ?? '');
            if (!$plugin || ($decision = self::license_decision($plugin)) === null) { return; }
            self::print_license_message($plugin, $decision);
        }

        public static function license_automatic($update, $item) {
            $plugin = self::license_find($item->plugin ?? '');
            if (!$plugin || ($decision = self::license_decision($plugin)) === null) { return $update; }
            return self::license_can_update($plugin, $decision) && ($item->package ?? '') === self::license_package($plugin, $decision['license'], $decision['allowed_version']) ? $update : false;
        }

        public static function license_before_download($reply, $package, $upgrader, $extra = array()) {
            if (is_wp_error($reply)) { return $reply; }
            $plugin = !empty($extra['plugin']) ? self::license_find($extra['plugin']) : null;
            if (!$plugin && is_string($package)) {
                // Covers stale bulk-update entries and older WordPress callers without hook_extra.
                foreach (self::$plugin_info as $candidate) {
                    foreach (array('update_product','update_release') as $route) {
                        $prefix = BeRocket_update_path.'v1/'.$route.'/'.(int)$candidate['id'].'/';
                        if (strpos($package, $prefix) === 0) { $plugin = $candidate; break 2; }
                    }
                }
            }
            if (!$plugin) { return $reply; }
            // A bulk upgrader shares one freshly confirmed batch within this PHP
            // request. A new key/request identity still invalidates that cache.
            $force = !is_object($upgrader) || self::$license_download_upgrader !== $upgrader;
            $decision = self::license_decision($plugin, $force);
            if (is_object($upgrader)) { self::$license_download_upgrader = $upgrader; }
            if ($decision === null) { return $reply; }
            if (!self::license_can_update($plugin, $decision)) {
                return new WP_Error('berocket_license_update_unavailable', self::license_text($plugin, $decision) ?: __('No permitted newer release is available.', 'BeRocket_domain'));
            }
            $expected = self::license_package($plugin, $decision['license'], $decision['allowed_version']);
            if (!is_string($package) || $package !== $expected) {
                return new WP_Error('berocket_license_package_changed', __('The permitted update has changed. Check for updates again before installing it.', 'BeRocket_domain'));
            }
            // A previous filter must not substitute an unverified local ZIP for this version.
            self::$license_downloads[$plugin['plugin']] = $decision['allowed_version'];
            return false;
        }

        public static function license_check_source($source, $remote_source, $upgrader, $extra = array()) {
            $file = $extra['plugin'] ?? '';
            if (is_wp_error($source) || !isset(self::$license_downloads[$file])) { return $source; }
            $expected = self::$license_downloads[$file];
            unset(self::$license_downloads[$file]);
            global $wp_filesystem;
            $header = $wp_filesystem->get_contents(rtrim($source, '/\\').'/'.basename($file));
            if (!is_string($header) || !preg_match('/^[ \t\/*#@]*Version:\s*([^\r\n]+)/mi', substr($header, 0, 8192), $match) ||
                trim($match[1], " \t*/") !== $expected) {
                return new WP_Error('berocket_license_version_mismatch', __('The downloaded plugin version does not match the permitted update. The installed plugin has not been replaced.', 'BeRocket_domain'));
            }
            return $source;
        }

        private static function license_installed($plugin) {
            $licenses = apply_filters('brfr_plugin_get_licenses_current_id_'.(int)$plugin['id'], array());
            return is_array($licenses) ? array_values(array_intersect(array('free','paid','business'), $licenses)) : array();
        }

        private static function print_license_message($plugin, $decision) {
            $text = self::license_text($plugin, $decision);
            if ($text === '') { return; }
            echo ' <span class="berocket-license-message">'.esc_html($text);
            if (!empty($decision['fresh']) && !empty($decision['updates_expired'])) {
                echo ' <a href="'.esc_url(BeRocket_main_path.'my-account/').'">'.esc_html__('Manage license', 'BeRocket_domain').'</a>';
            }
            echo '</span>';
        }

        public static function is_settings_page($is_page) {
            if ( ! empty( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'berocket_account' ) {
                return true;
            }
            return $is_page;
        }
    }

    BeRocket_updater::init();
    add_action( 'init', array( 'BeRocket_updater', 'run' ), 1 );
}
