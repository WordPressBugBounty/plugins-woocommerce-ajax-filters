<?php
if ( ! class_exists('BAPF_filters_docs') ) {
	class BAPF_filters_docs {
        function __construct() {
            add_action( 'berocket_aapf_group_filters_conditions_docs', array( $this, 'show_docs') );
            add_action( 'berocket_aapf_single_filter_conditions_docs', array( $this, 'show_docs') );
        }

        public function show_docs() {
            echo "
            <div class='berocket_conditions_block_docs'>
                <div class='berocket_conditions_block_docs_content'>
                	<div class='block_docs_init'>
	                    <p>Click '+' to add a condition</p>
                	</div>
                	<div class='block_docs_main' style='display: none;'>
	                    <p class='docs_title'>Page ID</p>
	                    <p>Manage which pages display filter(s).</p>
	                    
	                    <p class='docs_title'>Product Category</p>
	                    <p>Manage which category pages display filter(s).</p>
	                    
	                    <p class='docs_title'>Product Attribute</p>
	                    <p>Manage which attribute, tag, and brand pages display filter(s).</p>
	                    
	                    <p class='docs_title'>Product Search Page</p>
	                    <p>Show filter(s) if the current page is equal/not equal to a search page.</p>
	                    
	                    <p class='docs_title'>User Role</p>
	                    <p>Show filter(s) based on user role.</p>
	                    
	                    <p class='docs_title'>User Status</p>
	                    <p>Show filter widget(s) based on user status: logged in, customer.</p>
	                    
	                    <p class='docs_title'><a href='https://docs.berocket.com/plugin/woocommerce-ajax-products-filter#conditions' target='_blank'>Conditions docs</a></p>
                	</div>
                </div>
				<script>
                function block_docs_hide_show() {
                    setTimeout(function() {
                    if (jQuery('#conditions .berocket_conditions_block .br_conditions').find('.br_html_condition').length < 1) {
		                jQuery('.block_docs_main').hide(0);
		                jQuery('.block_docs_init').show(0);
	                } else {
                        jQuery('.block_docs_init').hide(0); 
                        jQuery('.block_docs_main').show(0); 
                    }}, 50);
                }
				jQuery(document).on('click', '#conditions .button.br_add_group', block_docs_hide_show);
	            jQuery(document).on('berocket:filters:br_remove_group', block_docs_hide_show);
                jQuery(document).on('ready', block_docs_hide_show);
				</script>
            </div>
            ";
        }
	}

	new BAPF_filters_docs();
}
