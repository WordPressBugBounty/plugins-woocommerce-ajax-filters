<?php
/**
 * Free-plan upgrade prompts for One-click setup.
 *
 * This class is presentation-only: locked recommendations never enter the
 * core ranking result and cannot be created until the licence permits them.
 */
class BeRocket_AAPF_One_Click_Free_Upgrade {
    public function __construct() {
        add_action('brapf_one_click_store_type_control', array($this, 'render_store_type_control'), 5, 2);
        add_action('brapf_one_click_recommendation_upsells', array($this, 'render_recommendation_upsells'), 10, 2);
        add_action('brapf_one_click_placement_upsells', array($this, 'render_placement_upsell'), 10, 2);
    }

    public function render_store_type_control($source, $analysis = array()) {
        if (!$this->is_free()) {
            return;
        }
        ?>
        <fieldset class="brapf-store-type-control brapf-store-type-control--locked">
            <legend><?php esc_html_e('Choose your store type', 'BeRocket_AJAX_domain'); ?></legend>
            <label class="screen-reader-text" for="brapf-one-click-store-type-<?php echo esc_attr(sanitize_key($source)); ?>"><?php esc_html_e('Store type', 'BeRocket_AJAX_domain'); ?></label>
            <select id="brapf-one-click-store-type-<?php echo esc_attr(sanitize_key($source)); ?>" disabled aria-describedby="brapf-store-type-upgrade-<?php echo esc_attr(sanitize_key($source)); ?>">
                <option><?php esc_html_e('General store', 'BeRocket_AJAX_domain'); ?></option>
            </select>
            <p id="brapf-store-type-upgrade-<?php echo esc_attr(sanitize_key($source)); ?>" class="brapf-one-click-upgrade-copy">
                <?php
                printf(
                    wp_kses(
                        __('Your store is set to General store. <a href="%s" target="_blank" rel="noopener noreferrer">Upgrade to Pro</a> to choose a store type. It only changes the priority of eligible recommendations; it does not add filters that do not fit your catalog.', 'BeRocket_AJAX_domain'),
                        array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))
                    ),
                    esc_url($this->get_upgrade_url('store_type'))
                );
                ?>
            </p>
        </fieldset>
        <?php
    }

    public function render_recommendation_upsells($source, $analysis = array()) {
        if (!$this->is_free()) {
            return;
        }
        foreach (array('Availability', 'On sale') as $label) {
            ?>
            <li class="brapf-one-click-recommendation--locked"><i class="fa fa-lock"></i><?php echo esc_html__($label, 'BeRocket_AJAX_domain'); ?> — <?php echo $this->get_upgrade_link('recommendation_' . sanitize_key($label), __('Upgrade to Pro', 'BeRocket_AJAX_domain')); ?> <?php esc_html_e('to add.', 'BeRocket_AJAX_domain'); ?></li>
            <?php
        }
    }

    public function render_placement_upsell($source, $placement = array()) {
        if (!$this->is_free()) {
            return;
        }
        ?>
        <p class="brapf-one-click-mobile-upgrade">
            <strong><?php esc_html_e('Better mobile position:', 'BeRocket_AJAX_domain'); ?></strong>
            <?php esc_html_e('Above products — display filters in line and show title only.', 'BeRocket_AJAX_domain'); ?>
            <?php echo $this->get_upgrade_link('mobile_position', __('Upgrade to Pro', 'BeRocket_AJAX_domain')); ?>
            <?php esc_html_e('to use this mobile setup. Available in Pro and Business.', 'BeRocket_AJAX_domain'); ?>
        </p>
        <?php
    }

    protected function is_free() {
        return BeRocket_AAPF_One_Click_Capabilities::get_tier() === 'free';
    }

    protected function get_upgrade_link($context, $label) {
        return sprintf(
            '<a class="brapf-one-click-upgrade-link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
            esc_url($this->get_upgrade_url($context)),
            esc_html($label)
        );
    }

    protected function get_upgrade_url($context) {
        $slug = 'woocommerce-ajax-products-filter';
        if (class_exists('BeRocket_AAPF') && method_exists('BeRocket_AAPF', 'getInstance')) {
            $plugin = BeRocket_AAPF::getInstance();
            if (is_object($plugin) && !empty($plugin->values['premium_slug'])) {
                $slug = sanitize_title_with_dashes($plugin->values['premium_slug']);
            }
        }
        $url = add_query_arg(array(
            'utm_source' => 'plugin',
            'utm_medium' => 'one_click',
            'utm_campaign' => 'upgrade',
            'utm_content' => sanitize_key($context),
            'utm_term' => 'filters',
        ), 'https://berocket.com/' . rawurlencode($slug));
        return apply_filters('brapf_one_click_upgrade_url', $url, sanitize_key($context));
    }
}
new BeRocket_AAPF_One_Click_Free_Upgrade();
