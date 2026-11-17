<?php
if ( ! class_exists( 'braapf_free_funnels' ) ) {
	class braapf_free_funnels {
		public $plugin_name;
		public $main_sale_url;
		public $version_capability_name;

		function __construct() {
			$this->plugin_name = 'filters';
			$this->main_sale_url = 'https://berocket.com/woocommerce-ajax-products-filter/';
			$this->version_capability_name = 'brfr_get_plugin_version_capability_ajax_filters';

			//delete_option('berocket_admin_notices');
			//update_option('berocket_last_close_notices_time', 1);
			// $this->spring_premium_days_2026();
			// $this->halloween_2026();
			$this->ebf_2026();
			$this->bf_2026();
			$this->cm_2026();
		}

		public function spring_premium_days_2026(): void {
			$type = $this->get_plugin_type();

			$name       = 'spring_premium_days_2026';
			$media      = '?utm_source=' . $type . '_plugin&utm_medium=admin_notice&utm_campaign=spring_2026&utm_content=top_banner_cta'.
			              '&utm_term=' . $this->plugin_name;
			$start_time = mktime(0, 0, 0, 4, 13, 2026);
			$end_time   = mktime(23, 59, 59, 4, 17, 2026);
			$c_notice   = berocket_admin_notices::get_notice_by_priority_and_name( 19, $name );

			$is_closed = false;
			if ( isset( $c_notice['closed'] ) and $c_notice['closed'] > 0 ) {
				$is_closed = true;
			}

			if ( ! $is_closed and $start_time <= time() and $end_time > time() ) {
				new berocket_admin_notices( array(
					'start'     => $start_time,
					'end'       => $end_time,
					'name'      => $name,
					'html'      => '
				<div class="berocket-notice-template-big" style="">
					<div class="berocket-notice-description-container">
						<h1 style="padding-top: 0;line-height: 0.8;">🌿 Spring Premium Days - <b class="br-notice-text-label">Save 30% on Premium</b> Plugins!</h1>
						<h3>Take advantage of Spring to deliver a better shopping experience</h3>
						<ul style="font-size: 16px">
							<li>Advanced product filtering features</li>
							<li>Intelligent (Grouped) Filters with Business license</li>
							<li>Powerful tools to improve store navigation and UX</li>
						</ul>
						<p><i>30% OFF applies to all BeRocket plugins.</i></p>
					</div>
					<div class="berocket-notice-actions-container">
						<div class="berocket-notice-countdown-container">
							<span class="berocket-notice-countdown-title">Hurry Up! Deal ends in:</span>
							<ul class="berocket-notice-countdown-timer" data-endtime="' . ($end_time) . '">
								<li class="berocket-notice-countdown-days"><span>0</span><br />Days</li>
								<li class="berocket-notice-countdown-hours"><span>0</span><br />Hours</li>
								<li class="berocket-notice-countdown-minutes"><span>0</span><br />Minutes</li>
								<li class="berocket-notice-countdown-seconds"><span>0</span><br />Seconds</li>
							</ul>
						</div>
						<div class="berocket-notice-buttons-container">
							<a href="https://berocket.com/woocommerce-ajax-products-filter/' . $media . '" 
							   class="button notice-action-link not_berocket_button" target="_blank" 
							   style="position: static; display: inline-block; right: 0; top: 0; margin: 0;font-size: 24px;padding-left: 25px;padding-right: 25px;">Upgrade Filters → 30% OFF</a>
							<span style="padding: 0 10px;font-size: 16px;"> or </span>
							<a href="https://berocket.com/plugins/' . $media . '" target="_blank" 
							   style="font-size: 18px; text-decoration: none;">Open shop page</a>
						</div> 
					</div>
				</div>',
					'type'      => 'info',
					'righthtml' => '<span class="berocket-notice-dismiss notice-dismiss berocket_no_thanks" role="button" tabindex="0">
						<span class="screen-reader-text">
						<input class="berocket-notice-dismiss-check" type="checkbox" value="1">Dismiss this notice.
						</span>
					</span>',
					'subscribe' => false,
					'priority'  => 19,
					'image'     => '',
				) );

				$this->br_show_admin_notice_sign();
				$this->br_show_admin_notice_timer();
			}
		}

		public function halloween_2026(): void {
			$type = $this->get_plugin_type();

			$name       = 'halloween_2026';
			$media      = '?utm_source=' . $type . '_plugin&utm_medium=admin_notice&utm_campaign=halloween_2026&utm_content=top_banner_cta&'.
			              'utm_term=' . $this->plugin_name;
			$start_time = 1792969200; // 26/10
			$end_time   = 1793573999; // 1/11
			$c_notice   = berocket_admin_notices::get_notice_by_priority_and_name( 19, $name );

			$is_closed = false;
			if ( isset( $c_notice['closed'] ) and $c_notice['closed'] > 0 ) {
				$is_closed = true;
			}

			if ( ! $is_closed and $start_time <= time() and $end_time > time() ) {
				new berocket_admin_notices( array(
					'start'     => $start_time,
					'end'       => $end_time,
					'name'      => $name,
					'html'      => '
				<style>/* Scoped component: no fonts, trackers, framework or external asset requests. */
					[data-br-halloween-promo] {
					  --br-promo-ink: #1d0b4a;
					  --br-promo-purple: #742eff;
					  --br-promo-border: #d6baff;
					  container: br-halloween / inline-size;
					  position: relative;
					  isolation: isolate;
					  width: 99%;
					  min-width: 0;
					  margin: 16px 0 24px;
					  overflow: hidden;
					  border: 1px solid var(--br-promo-border);
					  border-radius: 18px;
					  background: radial-gradient(ellipse at 4% 100%, #e6d5ff 0, transparent 42%), linear-gradient(105deg, #f8f2ff, #f2ebff 70%, #f6efff);
					  color: var(--br-promo-ink);
					  font-family: Arial, Helvetica, sans-serif;
					  font-size: 16px;
					  line-height: 1.45;
					  text-align: left;
					}
					[data-br-halloween-promo] *,
					[data-br-halloween-promo] *::before,
					[data-br-halloween-promo] *::after { box-sizing: border-box; }
					[data-br-halloween-promo] .br-halloween-promo__layout {
					  display: grid;
					  grid-template-columns: clamp(145px, 16cqi, 195px) minmax(0, 1fr) clamp(225px, 23cqi, 280px);
					  gap: clamp(18px, 2.2cqi, 32px);
					  align-items: center;
					  min-height: 220px;
					  padding: 26px 24px 22px;
					}
					[data-br-halloween-promo] .br-halloween-promo__layout::before {
					  content: "";
					  position: absolute;
					  z-index: -1;
					  top: -28px;
					  right: 36px;
					  width: 165px;
					  height: 165px;
					  background: #742eff;
					  background: url("https://berocket.ams3.cdn.digitaloceanspaces.com/sale/halloween%202026/halloween-web.png") center / contain no-repeat;
				      -webkit-mask: none;
					  mask: none;
					  opacity: .13;
					  transform: scaleX(-1);
					  pointer-events: none;
					}
					[data-br-halloween-promo] .br-halloween-promo__art { position: relative; align-self: stretch; min-height: 170px; }
					[data-br-halloween-promo] .br-halloween-promo__logo {
					  position: relative;
					  z-index: 1;
					  display: flex;
					  align-items: center;
					  justify-content: center;
					  width: 100%;
					  max-width: 190px;
					  padding: 11px 14px;
					  border-radius: 19px;
					  background: linear-gradient(120deg, #321074, #20064f);
					}
					[data-br-halloween-promo] .br-halloween-promo__logo img { display: block; width: 100%; height: auto; max-width: 160px; }
					[data-br-halloween-promo] .br-halloween-promo__pumpkins {
					  position: absolute;
					  bottom: -27px;
					  left: -20px;
					  display: block;
					  width: calc(100% + 30px);
					  max-width: 235px;
					  height: auto;
					  pointer-events: none;
					}
					[data-br-halloween-promo] .br-halloween-promo__content {
					  min-width: 0;
					  width: fit-content;
                      margin: 0 auto;
                    }
					[data-br-halloween-promo] .br-halloween-promo__title {
					  margin: 0 0 13px;
					  padding: 0;
					  color: var(--br-promo-ink);
					  font-family: inherit;
					  font-size: clamp(25px, 3.15cqi, 42px);
					  font-weight: 800;
					  line-height: 1.15;
					  letter-spacing: -.04em;
					  text-wrap: balance;
					}
					[data-br-halloween-promo] .br-halloween-promo__discount {
					  display: inline-block;
					  color: #984214;
					  white-space: nowrap;
					}
					@supports (background-clip: text) {
					  [data-br-halloween-promo] .br-halloween-promo__discount {
					    background: linear-gradient(100deg, #7427df 5%, #a42bb6 47%, #c25500 78%);
					    background-clip: text;
					    -webkit-background-clip: text;
					    -webkit-text-fill-color: transparent;
					  }
					}
					[data-br-halloween-promo] .br-halloween-promo__description,
					[data-br-halloween-promo] .br-halloween-promo__code-line {
					  margin: 0;
					  padding: 0;
					  color: var(--br-promo-ink);
					  font-family: inherit;
					  font-size: clamp(14px, 1.5cqi, 18px);
					  line-height: 1.55;
					}
					[data-br-halloween-promo] .br-halloween-promo__code-line { margin-top: 15px; line-height: 1.9; }
					[data-br-halloween-promo] .br-halloween-promo__code {
					  display: inline-block;
					  margin: 2px 3px;
					  padding: 5px 11px;
					  border: 1px solid #b48aff;
					  border-radius: 9px;
					  background: #eee0ff;
					  color: #6520c9;
					  font-family: inherit;
					  font-size: 1em;
					  font-weight: 700;
					  line-height: 1.4;
					  letter-spacing: 0;
					  user-select: all;
					}
					[data-br-halloween-promo] .br-halloween-promo__ending { white-space: nowrap; }
					[data-br-halloween-promo] .br-halloween-promo__action {
					  display: flex;
					  flex-direction: column;
					  gap: 15px;
					  align-items: stretch;
					  justify-content: center;
					  min-width: 0;
					  padding: 22px 0 18px 18px;
					  border-left: 1px solid var(--br-promo-border);
					}
					[data-br-halloween-promo] .br-halloween-promo__dates {
					  margin: 0;
					  color: var(--br-promo-ink);
					  font-size: clamp(14px, 1.4cqi, 18px);
					  font-weight: 700;
					  line-height: 1.4;
					  text-align: center;
					}
					[data-br-halloween-promo] .br-halloween-promo__cta {
					  display: inline-flex;
					  gap: 8px;
					  align-items: center;
					  justify-content: center;
					  min-height: 48px;
					  padding: 14px 12px;
					  border: 1px solid transparent;
					  border-radius: 12px;
					  background: linear-gradient(110deg, #7624ee, #6b20e4);
					  box-shadow: 0 6px 15px #742eff28;
					  color: #fff;
					  font-family: inherit;
					  font-size: clamp(14px, 1.2cqi, 15px);
					  font-weight: 700;
					  line-height: 1.3;
					  text-align: center;
					  text-decoration: none;
					}
					[data-br-halloween-promo] .br-halloween-promo__cta:hover { background: #5c18ce; color: #fff; text-decoration: none; }
					[data-br-halloween-promo] .br-halloween-promo__arrow { flex: 0 0 auto; font-size: 22px; font-weight: 400; line-height: 1; }
					[data-br-halloween-promo] .br-halloween-promo__dismiss {
					  position: absolute;
					  z-index: 3;
					  top: 5px;
					  right: 5px;
					  display: grid;
					  place-items: center;
					  width: 40px;
					  height: 40px;
					  margin: 0;
					  padding: 0;
					  border: 0;
					  border-radius: 8px;
					  background: transparent;
					  box-shadow: none;
					  color: #68567a;
					  font: 30px/1 Arial, sans-serif;
					  cursor: pointer;
					}
					[data-br-halloween-promo] .br-halloween-promo__dismiss:hover { background: #e4d7f8; color: #2b104c; }
					[data-br-halloween-promo] .br-halloween-promo__cta:focus-visible,
					[data-br-halloween-promo] .br-halloween-promo__dismiss:focus-visible { outline: 3px solid #41207a; outline-offset: 3px; }
					[data-br-halloween-promo] .br-halloween-promo__sr-only {
					  position: absolute;
					  width: 1px;
					  height: 1px;
					  padding: 0;
					  overflow: hidden;
					  clip-path: inset(50%);
					  white-space: nowrap;
					  border: 0;
					}
					@container br-halloween (max-width: 1040px) {
					  [data-br-halloween-promo] .br-halloween-promo__layout { grid-template-columns: 145px minmax(0, 1fr); gap: 15px 24px; padding: 24px; }
					  [data-br-halloween-promo] .br-halloween-promo__art { grid-row: span 2; min-height: 185px; }
					  [data-br-halloween-promo] .br-halloween-promo__title { font-size: clamp(26px, 4cqi, 35px); margin-bottom: 10px; }
					  [data-br-halloween-promo] .br-halloween-promo__content { min-width: 0; width: auto; margin: 0; }
					  [data-br-halloween-promo] .br-halloween-promo__description,
					  [data-br-halloween-promo] .br-halloween-promo__code-line { font-size: 16px; }
					  [data-br-halloween-promo] .br-halloween-promo__code-line { margin-top: 9px; }
					  [data-br-halloween-promo] .br-halloween-promo__action { grid-column: 2; flex-direction: row; align-items: center; justify-content: flex-start; gap: 18px; border: 0; padding: 0; }
					  [data-br-halloween-promo] .br-halloween-promo__dates { text-align: left; font-size: 14px; }
					  [data-br-halloween-promo] .br-halloween-promo__cta { flex: 0 1 auto; font-size: 14px; padding: 12px 16px; }
					}
					@container br-halloween (max-width: 700px) {
					  [data-br-halloween-promo] .br-halloween-promo__layout { grid-template-columns: minmax(0, 1fr); gap: 14px; padding: 18px 20px 22px; }
					  [data-br-halloween-promo] .br-halloween-promo__art { grid-row: auto; min-height: 72px; }
					  [data-br-halloween-promo] .br-halloween-promo__logo { width: 150px; border-radius: 15px; padding: 10px 13px; }
					  [data-br-halloween-promo] .br-halloween-promo__pumpkins { width: 128px; max-width: none; left: auto; right: 22px; bottom: -8px; }
					  [data-br-halloween-promo] .br-halloween-promo__title { font-size: clamp(27px, 5.3cqi, 34px); }
					  [data-br-halloween-promo] .br-halloween-promo__description,
					  [data-br-halloween-promo] .br-halloween-promo__code-line { font-size: 15px; }
					  [data-br-halloween-promo] .br-halloween-promo__ending { display: none; }
					  [data-br-halloween-promo] .br-halloween-promo__action { grid-column: 1; flex-wrap: wrap; gap: 12px; }
					  [data-br-halloween-promo] .br-halloween-promo__dates { flex: 1 0 100%; }
					  [data-br-halloween-promo] .br-halloween-promo__cta { width: 100%; min-height: 48px; }
					  [data-br-halloween-promo] .br-halloween-promo__layout::before { width: 115px; height: 115px; right: 8px; opacity: .11; }
					}
					@container br-halloween (max-width: 360px) {
					  [data-br-halloween-promo] .br-halloween-promo__logo { width: 130px; }
					  [data-br-halloween-promo] .br-halloween-promo__pumpkins { width: 100px; right: 10px; bottom: -4px; }
					}
					@media (forced-colors: active) {
					  [data-br-halloween-promo] .br-halloween-promo__discount { -webkit-text-fill-color: currentColor; background: none; color: CanvasText; }
					  [data-br-halloween-promo] .br-halloween-promo__cta { border-color: LinkText; box-shadow: none; }
					}
				</style>
				<section class="br-halloween-promo"
					  data-br-halloween-promo
					  data-campaign="halloween-2026"
						  data-start="2026-10-26T00:00:00+01:00"
						  data-end="2026-11-02T00:00:00+01:00"
					  data-dismiss-scope="preview-user" data-preview="true" data-focus-after-dismiss="#preview-settings-title"
					  aria-label="BeRocket Halloween offer">
					  <div class="br-halloween-promo__layout">
					    <button class="br-halloween-promo__dismiss berocket-notice-dismiss berocket-notice-dismiss- berocket_no_thanks" type="button" aria-label="Dismiss Halloween offer">
					      <span class="berocket-notice-dismiss-check" aria-hidden="true">×</span>
					    </button>
					    <div class="br-halloween-promo__art">
					      <div class="br-halloween-promo__logo">
					        <img src="https://e8e3g4v6.rocketcdn.me/wp-content/uploads/2022/12/logo-with-text-white-237x70-1.png" alt="BeRocket" width="237" height="70" decoding="async">
					      </div>
					      <img class="br-halloween-promo__pumpkins" src="https://berocket.ams3.cdn.digitaloceanspaces.com/sale/halloween%202026/pumpkins-banner-2026.png" alt="" width="640" height="480" decoding="async">
					    </div>
					    <div class="br-halloween-promo__content">
					      <h2 class="br-halloween-promo__title">Halloween Sale <span class="br-halloween-promo__dash" aria-hidden="true">—</span> <span class="br-halloween-promo__discount">25% OFF</span></h2>
					      <p class="br-halloween-promo__description">Get more from your WooCommerce store with premium BeRocket plugins.</p>
					      <p class="br-halloween-promo__code-line">Use <code class="br-halloween-promo__code">HALLOWEEN25</code> at checkout. <span class="br-halloween-promo__ending">Offer ends November 1.</span></p>
					    </div>
					    <div class="br-halloween-promo__action">
					      <p class="br-halloween-promo__dates"><time datetime="2026-10-26">October 26</time><span aria-hidden="true">–</span><span class="br-halloween-promo__sr-only"> through </span><time datetime="2026-11-01">November 1</time><span class="br-halloween-promo__sr-only">, 2026, Paris time</span></p>
					      <a class="br-halloween-promo__cta" href="https://berocket.com/halloween-2026/' . $media . '" target="_blank" rel="noopener noreferrer">View the Halloween Sale <span class="br-halloween-promo__arrow" aria-hidden="true">→</span><span class="br-halloween-promo__sr-only"> (opens in a new tab)</span></a>
					    </div>
					  </div>
					</section>',
					'type'      => 'custom',
					'display_area' => 'settings',
					'righthtml' => '',
					'subscribe' => false,
					'priority'  => 19,
					'image'     => '',
				) );

				$this->br_show_admin_notice_sign();
			}
		}

		public function ebf_2026(): void {
			$type = $this->get_plugin_type();

			$name       = 'ebf_2026';
			$media      = '?coupon=ebf2026&utm_source=' . $type . '_plugin&utm_medium=admin_notice&utm_campaign='. $name .'&utm_content=top_banner_cta&'.
			              'utm_term=' . $this->plugin_name;
			$cta        = $this->main_sale_url . $media;
			$start_time = $this->mktime( "2026-11-16" );
			$end_time   = $this->mktime( "2026-11-27" );
			$c_notice   = berocket_admin_notices::get_notice_by_priority_and_name( 19, $name );

			$is_closed = false;
			if ( isset( $c_notice['closed'] ) and $c_notice['closed'] > 0 ) {
				$is_closed = true;
			}

			if ( ! $is_closed and $start_time <= time() and $end_time > time() ) {
				new berocket_admin_notices( array(
					'start'     => $start_time,
					'end'       => $end_time,
					'name'      => $name,
					'html'      => '
					<style id="br-early-studio-styles">
    [data-br-early-studio] {
      --studio-cream: #fff8eb;
      --studio-gold: #f0d097;
      --studio-border: #aa91715c;
      position: relative;
      isolation: isolate;
      container: br-studio / inline-size;
      margin: 16px 20px 16px 0;
      overflow: hidden;
      border: 1px solid #766956;
      border-radius: clamp(14px, 1.35vw, 26px);
      background:
        radial-gradient(ellipse at 35% 34%, #606973 0%, #434a53 31%, transparent 63%),
        linear-gradient(105deg, #2c3036 0%, #31363d 51%, #22252a 100%);
      color: var(--studio-cream);
      font: 400 16px/1.2 system-ui, -apple-system, "Segoe UI", sans-serif;
      text-align: left;
    }

    [data-br-early-studio][hidden] { display: none !important; }
    [data-br-early-studio] *, [data-br-early-studio] *::before { box-sizing: border-box; }

    [data-br-early-studio] .br-early-layout {
      display: grid;
      grid-template-columns: minmax(0, 25%) minmax(0, 1fr) minmax(0, 27%);
      align-items: center;
      gap: 1.6cqi;
      min-height: max(190px, 21.2cqi);
      padding: 1.1cqi 2.9cqi 1.1cqi 3.1cqi;
    }

    [data-br-early-studio] .br-early-copy { min-width: 0; text-align: center; }
    [data-br-early-studio] .br-early-logo { display: block; width: min(100%, clamp(99px, 10.7cqi, 232px)); height: auto; }

    [data-br-early-studio] .br-early-kicker {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .7em;
      margin: 0 0 1em;
      color: var(--studio-gold);
      font-size: clamp(11px, 1.02cqi, 22px);
      font-weight: 680;
      letter-spacing: .25em;
      text-transform: uppercase;
    }

    [data-br-early-studio] .br-early-kicker::before,
    [data-br-early-studio] .br-early-kicker::after {
      content: "";
      width: 1.65em;
      height: 1px;
      background: currentColor;
    }

    [data-br-early-studio] .br-early-title {
      margin: 0;
      padding: 0;
      color: inherit;
      font: inherit;
      font-size: clamp(40px, 5.25cqi, 112px);
      font-weight: 810;
      line-height: .95;
      letter-spacing: -.065em;
      text-transform: uppercase;
    }

    [data-br-early-studio] .br-studio-friday {
      position: relative;
      display: inline-block;
      margin-inline-start: .12em;
      color: var(--studio-gold);
      font-family: "French Script MT", "Snell Roundhand", "Segoe Script", "Apple Chancery", Georgia, serif;
      font-size: 1.16em;
      font-style: italic;
      font-weight: 400;
      line-height: 1;
      letter-spacing: -.065em;
      text-transform: none;
      transform: rotate(-6deg);
    }

    [data-br-early-studio] .br-early-offer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      gap: .4em .85em;
      margin: 1.05em 0 0;
      padding: 0;
      color: inherit;
      font-size: clamp(15px, 1.7cqi, 36px);
      line-height: 1.25;
    }

    [data-br-early-studio] .br-early-offer > span { white-space: nowrap; }
    [data-br-early-studio] .br-early-discount { color: var(--studio-gold); font-weight: 750; }
    [data-br-early-studio] .br-early-divider { width: 1px; height: 1em; background: var(--studio-border); }
    [data-br-early-studio] :is(.br-early-code, .br-early-action) {
      position: relative;
      border: 1px solid var(--studio-border);
      border-radius: 5px;
    }

    [data-br-early-studio] .br-early-code {
      display: inline-block;
      padding: .22em .51em;
      background: #17181c66;
      color: var(--studio-gold);
      font: 650 .85em/1.2 ui-monospace, Consolas, monospace;
      letter-spacing: .045em;
      user-select: all;
    }

    [data-br-early-studio] .br-early-art {
      position: relative;
      isolation: isolate;
      display: grid;
      justify-items: center;
      gap: .5cqi;
      width: min(100%, 16.5cqi);
      min-width: 0;
    }

    [data-br-early-studio] .br-early-art::before {
      content: "";
      position: absolute;
      inset: auto 3% 3%;
      z-index: -1;
      height: 15%;
      background: radial-gradient(ellipse, #111319 0%, transparent 67%);
      opacity: .85;
    }

    [data-br-early-studio] .br-early-gifts {
      display: block;
      width: 100%;
      height: clamp(96px, 13.875cqi, 298px);
      object-fit: contain;
      filter: drop-shadow(0 .5em .8em #12131855);
    }

    [data-br-early-studio] .br-early-action {
      display: grid;
      gap: clamp(8px, .85cqi, 18px);
      min-width: 0;
      padding: clamp(11px, 1.3cqi, 29px);
      border-radius: 15px;
      background: #202328bf;
    }

    [data-br-early-studio] .br-early-label {
      margin: 0;
      padding: 0;
      color: var(--studio-cream);
      font-size: clamp(10px, .91cqi, 20px);
      font-weight: 700;
      letter-spacing: .135em;
      text-align: center;
    }

    [data-br-early-studio] .br-early-timer {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      padding: .25em 0;
      text-align: center;
    }

    [data-br-early-studio] .br-early-tile {
      display: grid;
      gap: .37em;
      padding: .27em .1em;
      color: #e3dcd2;
      font-size: clamp(9px, .76cqi, 16px);
      font-weight: 650;
      line-height: 1.05;
      letter-spacing: .08em;
    }

    [data-br-early-studio] .br-early-tile + .br-early-tile { border-inline-start: 1px solid var(--studio-border); }
    [data-br-early-studio] .br-early-value {
      color: var(--studio-gold);
      font-size: clamp(20px, 2.72cqi, 58px);
      font-weight: 680;
      font-variant-numeric: tabular-nums;
      letter-spacing: -.05em;
    }

    [data-br-early-studio] .br-early-cta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5em;
      min-height: 44px;
      padding: .7em .9em;
      border-radius: 6px;
      background: #e6c58d;
      color: #29231b;
      font-size: clamp(13px, 1.22cqi, 26px);
      font-weight: 720;
      text-decoration: none;
    }

    [data-br-early-studio] .br-early-cta:hover { background: #f6d9a6; color: #29231b; }
    [data-br-early-studio] .br-early-cta svg { width: 1.05em; height: 1.05em; flex: none; }
    [data-br-early-studio] .br-early-dismiss {
      position: absolute;
      z-index: 1;
      inset: 3px 3px auto auto;
      display: grid;
      place-items: center;
      width: clamp(34px, 2.8cqi, 58px);
      aspect-ratio: 1;
      padding: 0;
      border: 0;
      border-radius: 50%;
      background: transparent;
      color: var(--studio-cream);
      font: 400 clamp(21px, 2cqi, 40px)/1 Arial, sans-serif;
      cursor: pointer;
    }

    [data-br-early-studio] .br-early-dismiss:hover { background: #ffffff18; }
    [data-br-early-studio] :is(a, button):focus-visible { outline: 2px solid white; outline-offset: 2px; }

    [data-br-early-studio] .br-studio-title-glint,
    [data-br-early-studio] .br-studio-dust { display: none; }

    @media (prefers-reduced-motion: no-preference) {
      @supports (background-clip: text) {
        [data-br-early-studio] .br-studio-title-glint {
          position: absolute;
          inset: -.24em -.2em;
          display: block;
          padding: .24em .2em;
          background: linear-gradient(105deg, transparent 43%, #fff8e4 50%, transparent 57%) 100% 50% / 270% 100%;
          background-clip: text;
          color: transparent;
          animation: br-studio-glint 2.4s linear .15s infinite;
          pointer-events: none;
          user-select: none;
        }
      }

      @supports (mask-composite: exclude) {
        [data-br-early-studio] :is(.br-early-code, .br-early-action)::after {
          content: "";
          position: absolute;
          inset: -1px;
          padding: 1px;
          border-radius: inherit;
          background: linear-gradient(105deg, transparent 43%, #edc989 48%, #fff8df 50%, #edc989 52%, transparent 57%) 100% 50% / 270% 100%;
          mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
          mask-composite: exclude;
          animation: br-studio-glint 2.9s linear 1.3s infinite;
          pointer-events: none;
        }

        [data-br-early-studio] .br-early-action::after {
          animation-duration: 2.7s;
          animation-delay: .65s;
        }
      }

      [data-br-early-studio] .br-studio-dust {
        position: absolute;
        z-index: 1;
        inset: 0;
        display: block;
        pointer-events: none;
      }

      [data-br-early-studio] .br-studio-dust span {
        position: absolute;
        top: var(--dust-y);
        left: var(--dust-x);
        width: clamp(2px, .22cqi, 5px);
        aspect-ratio: 1;
        border-radius: 50%;
        background: #ffe6ac;
        box-shadow: 0 0 6px #f7c777;
        opacity: 0;
        animation: br-studio-dust-rise var(--dust-period) ease-out var(--dust-delay) infinite;
      }

      [data-br-early-studio] .br-studio-dust span:nth-child(even) {
        --dust-drift: -13px;
        width: clamp(2px, .17cqi, 4px);
      }

      [data-br-early-studio] .br-studio-dust span:nth-child(3n) {
        background: #fff4cf;
      }
    }

    @keyframes br-studio-glint {
      from { background-position: 100% 50%; }
      42%, to { background-position: 0% 50%; }
    }

    @keyframes br-studio-dust-rise {
      from { transform: translate(0, 0); opacity: 0; }
      12% { opacity: .9; }
      54%, to { transform: translate(var(--dust-drift, 15px), var(--dust-lift, -36px)); opacity: 0; }
    }

    @container br-studio (max-width: 700px) {
      [data-br-early-studio] .br-early-layout {
        grid-template-columns: minmax(0, .78fr) minmax(0, 1.22fr);
        gap: 15px 10px;
        padding: 25px 16px 16px;
      }
      [data-br-early-studio] .br-early-title { font-size: clamp(34px, 8.2cqi, 62px); }
      [data-br-early-studio] .br-early-art { gap: 6px; width: max(75%, min(82px, 100%)); }
      [data-br-early-studio] .br-early-gifts { height: auto; max-height: 146px; }
      [data-br-early-studio] .br-studio-dust { --dust-lift: -22px; }
      [data-br-early-studio] .br-early-action {
        grid-column: 1 / -1;
        grid-template-columns: minmax(0, 1fr) minmax(128px, .63fr);
        align-items: center;
      }
      [data-br-early-studio] .br-early-label { grid-column: 1 / -1; text-align: left; }
      [data-br-early-studio] .br-early-value { font-size: clamp(22px, 4.8cqi, 32px); }
    }

    @container br-studio (max-width: 420px) {
      [data-br-early-studio] .br-early-layout { gap: 10px; padding: 18px 13px 12px; }
      [data-br-early-studio] .br-early-action { grid-template-columns: minmax(0, 1fr); gap: 6px; padding: 9px; }
      [data-br-early-studio] .br-early-kicker { margin-bottom: .65em; }
      [data-br-early-studio] .br-early-title { font-size: clamp(36px, 10.25cqi, 44px); }
      [data-br-early-studio] .br-studio-friday { display: block; width: fit-content; margin: 0 auto; }
      [data-br-early-studio] .br-early-offer { gap: .35em .62em; margin-top: .55em; }
      [data-br-early-studio] .br-early-divider { display: none; }
    }

    @container br-studio (max-width: 330px) {
      [data-br-early-studio] .br-early-layout { padding-block: 15px 9px; }
    }
  </style>
  <section data-br-early-studio data-end="2026-11-27T00:00:00+01:00"
    aria-label="BeRocket Black Friday Early Access">
    <span class="br-studio-dust" aria-hidden="true">
      <span style="--dust-x:17%; --dust-y:72%; --dust-delay:.2s; --dust-period:2.4s"></span>
      <span style="--dust-x:76%; --dust-y:73%; --dust-delay:.35s; --dust-period:2.55s"></span>
      <span style="--dust-x:63%; --dust-y:40%; --dust-delay:.5s; --dust-period:2.7s"></span>
      <span style="--dust-x:32%; --dust-y:54%; --dust-delay:.7s; --dust-period:2.85s"></span>
      <span style="--dust-x:81%; --dust-y:47%; --dust-delay:.85s; --dust-period:2.6s"></span>
      <span style="--dust-x:12%; --dust-y:51%; --dust-delay:1s; --dust-period:2.5s"></span>
      <span style="--dust-x:52%; --dust-y:61%; --dust-delay:1.15s; --dust-period:2.75s"></span>
      <span style="--dust-x:45%; --dust-y:30%; --dust-delay:1.35s; --dust-period:2.95s"></span>
      <span style="--dust-x:23%; --dust-y:41%; --dust-delay:1.5s; --dust-period:2.35s"></span>
      <span style="--dust-x:89%; --dust-y:64%; --dust-delay:1.65s; --dust-period:2.65s"></span>
      <span style="--dust-x:66%; --dust-y:79%; --dust-delay:1.8s; --dust-period:2.8s"></span>
      <span style="--dust-x:8%; --dust-y:83%; --dust-delay:1.95s; --dust-period:2.45s"></span>
      <span style="--dust-x:72%; --dust-y:12%; --dust-delay:2.1s; --dust-period:2.9s"></span>
      <span style="--dust-x:37%; --dust-y:80%; --dust-delay:2.25s; --dust-period:2.3s"></span>
      <span style="--dust-x:55%; --dust-y:51%; --dust-delay:2.4s; --dust-period:2.52s"></span>
      <span style="--dust-x:30%; --dust-y:14%; --dust-delay:2.55s; --dust-period:2.92s"></span>
      <span style="--dust-x:8%; --dust-y:63%; --dust-delay:.27s; --dust-period:2.48s"></span>
      <span style="--dust-x:92%; --dust-y:83%; --dust-delay:.42s; --dust-period:2.7s"></span>
      <span style="--dust-x:48%; --dust-y:94%; --dust-delay:.57s; --dust-period:2.83s"></span>
      <span style="--dust-x:73%; --dust-y:61%; --dust-delay:.77s; --dust-period:2.36s"></span>
      <span style="--dust-x:21%; --dust-y:97%; --dust-delay:.92s; --dust-period:2.67s"></span>
      <span style="--dust-x:96%; --dust-y:44%; --dust-delay:1.07s; --dust-period:2.89s"></span>
      <span style="--dust-x:14%; --dust-y:17%; --dust-delay:1.22s; --dust-period:2.54s"></span>
      <span style="--dust-x:85%; --dust-y:15%; --dust-delay:1.42s; --dust-period:2.96s"></span>
      <span style="--dust-x:34%; --dust-y:37%; --dust-delay:1.57s; --dust-period:2.38s"></span>
      <span style="--dust-x:66%; --dust-y:9%; --dust-delay:1.72s; --dust-period:2.59s"></span>
      <span style="--dust-x:61%; --dust-y:87%; --dust-delay:1.87s; --dust-period:2.81s"></span>
      <span style="--dust-x:4%; --dust-y:72%; --dust-delay:2.02s; --dust-period:2.43s"></span>
      <span style="--dust-x:91%; --dust-y:58%; --dust-delay:2.17s; --dust-period:2.74s"></span>
      <span style="--dust-x:39%; --dust-y:11%; --dust-delay:2.32s; --dust-period:2.94s"></span>
      <span style="--dust-x:27%; --dust-y:60%; --dust-delay:2.47s; --dust-period:2.49s"></span>
      <span style="--dust-x:80%; --dust-y:95%; --dust-delay:2.62s; --dust-period:2.87s"></span>
    </span>
    <div class="br-early-layout">
      <button class="br-early-dismiss berocket-notice-dismiss berocket-notice-dismiss- berocket_no_thanks" type="button" aria-label="Dismiss Early Access offer">
        <span class="berocket-notice-dismiss-check" aria-hidden="true">×</span>
      </button>
      <div class="br-early-art">
        <img class="br-early-logo" src="https://e8e3g4v6.delivery.rocketcdn.me/wp-content/uploads/2022/12/logo-with-text-white-237x70-1.png" alt="BeRocket">
        <img class="br-early-gifts" src="https://berocket.ams3.cdn.digitaloceanspaces.com/sale/bfcm2026/BeRocket_Black_Friday_Early_Access_Website_Banner_Studio1.png" alt="">
      </div>
      <div class="br-early-copy">
        <p class="br-early-kicker">Early Access</p>
        <h2 class="br-early-title">Black <span class="br-studio-friday">Friday<span class="br-studio-title-glint" aria-hidden="true">Friday</span></span></h2>
        <p class="br-early-offer">
          <span>Save <strong class="br-early-discount">35%</strong></span>
          <span class="br-early-divider" aria-hidden="true"></span>
          <span>Use <code class="br-early-code">EBF2026</code></span>
        </p>
      </div>
      <div class="br-early-action">
        <p class="br-early-label">EARLY ACCESS ENDS IN</p>
        <div class="br-early-timer" role="timer" aria-live="off" aria-label="Time remaining">
          <span class="br-early-tile"><span class="br-early-value" data-unit="days">00</span> DAYS</span>
          <span class="br-early-tile"><span class="br-early-value" data-unit="hours">00</span> HRS</span>
          <span class="br-early-tile"><span class="br-early-value" data-unit="minutes">00</span> MIN</span>
          <span class="br-early-tile"><span class="br-early-value" data-unit="seconds">00</span> SEC</span>
        </div>
        <a class="br-early-cta" href="'. $cta .'" target="_blank" rel="noopener noreferrer">
          Get Early Access
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 12h15m-6-6 6 6-6 6"/>
          </svg>
        </a>
      </div>
    </div>
  </section>'.
				"
				<script id='br-early-studio-script'>
    (() => {
      const banner = document.querySelector('[data-br-early-studio]');
      if (!banner || banner.hasAttribute('data-initialized')) return;
      banner.setAttribute('data-initialized', '');

      const deadline = Date.parse(banner.dataset.end);
      if (!Number.isFinite(deadline)) return;

      const days = banner.querySelector('[data-unit=\"days\"]');
      const hours = banner.querySelector('[data-unit=\"hours\"]');
      const minutes = banner.querySelector('[data-unit=\"minutes\"]');
      const seconds = banner.querySelector('[data-unit=\"seconds\"]');
      let tick;

      function hide() {
        clearTimeout(tick);
        banner.hidden = true;
        document.removeEventListener('visibilitychange', update);
        window.removeEventListener('pageshow', update);
      }

      function render(node, value) {
        node.textContent = String(value).padStart(2, '0');
      }

      function update() {
        clearTimeout(tick);
        const remaining = deadline - Date.now();
        if (remaining <= 0) {
          hide();
          return;
        }

        const totalSeconds = Math.ceil(remaining / 1000);
        render(days, Math.floor(totalSeconds / 86400));
        render(hours, Math.floor(totalSeconds / 3600) % 24);
        render(minutes, Math.floor(totalSeconds / 60) % 60);
        render(seconds, totalSeconds % 60);
        banner.hidden = false;
        tick = setTimeout(update, remaining % 1000 || 1000);
      }

      banner.querySelector('.br-early-dismiss').addEventListener('click', () => {
        hide();
      });

      document.addEventListener('visibilitychange', update);
      window.addEventListener('pageshow', update);
      update();
    })();
  </script>",
					'type'      => 'custom',
					'display_area' => 'settings',
					'righthtml' => '',
					'subscribe' => false,
					'priority'  => 19,
					'image'     => '',
				) );

				$this->br_show_admin_notice_sign();
			}
		}

		public function bf_2026(): void {
			$type = $this->get_plugin_type();

			$name       = 'bf_2026';
			$media      = '?coupon=bf2026&utm_source=' . $type . '_plugin&utm_medium=admin_notice&utm_campaign='. $name .'&utm_content=top_banner_cta&'.
			              'utm_term=' . $this->plugin_name;
			$cta        = $this->main_sale_url . $media;
			$start_time = $this->mktime( "2026-11-27" );
			$end_time   = $this->mktime( "2026-11-30" );
			$c_notice   = berocket_admin_notices::get_notice_by_priority_and_name( 19, $name );

			$is_closed = false;
			if ( isset( $c_notice['closed'] ) and $c_notice['closed'] > 0 ) {
				$is_closed = true;
			}

			if ( ! $is_closed and $start_time <= time() and $end_time > time() ) {
				new berocket_admin_notices( array(
					'start'     => $start_time,
					'end'       => $end_time,
					'name'      => $name,
					'html'      => '
					<style id="br-black-friday-studio-styles">
    [data-br-black-friday-studio] {
      --studio-cream: #fff8eb;
      --studio-gold: #f0d097;
      --studio-border: #aa91715c;
      position: relative;
      isolation: isolate;
      container: br-studio / inline-size;
      margin: 16px 20px 16px 0;
      overflow: hidden;
      border: 1px solid #766956;
      border-radius: clamp(14px, 1.35vw, 26px);
      background:
        radial-gradient(ellipse at 35% 34%, #606973 0%, #434a53 31%, transparent 63%),
        linear-gradient(105deg, #2c3036 0%, #31363d 51%, #22252a 100%);
      color: var(--studio-cream);
      font: 400 16px/1.2 system-ui, -apple-system, "Segoe UI", sans-serif;
      text-align: left;
    }

    [data-br-black-friday-studio][hidden] { display: none !important; }
    [data-br-black-friday-studio] *, [data-br-black-friday-studio] *::before { box-sizing: border-box; }

    [data-br-black-friday-studio] .br-early-layout {
      display: grid;
      grid-template-columns: minmax(0, 25%) minmax(0, 1fr) minmax(0, 27%);
      align-items: center;
      gap: 1.6cqi;
      min-height: max(190px, 21.2cqi);
      padding: 1.1cqi 2.9cqi 1.1cqi 3.1cqi;
    }

    [data-br-black-friday-studio] .br-early-copy { min-width: 0; text-align: center; }
    [data-br-black-friday-studio] .br-early-logo { display: block; width: min(100%, clamp(99px, 10.7cqi, 232px)); height: auto; }

    [data-br-black-friday-studio] .br-early-title {
      margin: 0;
      padding: 0;
      color: inherit;
      font: inherit;
      font-size: clamp(46px, 6.05cqi, 130px);
      font-weight: 810;
      line-height: .95;
      letter-spacing: -.065em;
      text-transform: uppercase;
    }

    [data-br-black-friday-studio] .br-studio-friday {
      position: relative;
      display: inline-block;
      margin-inline-start: .12em;
      color: var(--studio-gold);
      font-family: "French Script MT", "Snell Roundhand", "Segoe Script", "Apple Chancery", Georgia, serif;
      font-size: 1.16em;
      font-style: italic;
      font-weight: 400;
      line-height: 1;
      letter-spacing: -.065em;
      text-transform: none;
      transform: rotate(-6deg);
    }

    [data-br-black-friday-studio] .br-early-offer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      gap: .4em .85em;
      margin: .85em 0 0;
      padding: 0;
      color: inherit;
      font-size: clamp(18px, 2cqi, 43px);
      line-height: 1.25;
    }

    [data-br-black-friday-studio] .br-early-offer > span { white-space: nowrap; }
    [data-br-black-friday-studio] .br-early-discount { color: var(--studio-gold); font-weight: 750; }
    [data-br-black-friday-studio] .br-early-divider { width: 1px; height: 1em; background: var(--studio-border); }
    [data-br-black-friday-studio] :is(.br-early-code, .br-early-action) {
      position: relative;
      border: 1px solid var(--studio-border);
      border-radius: 5px;
    }

    [data-br-black-friday-studio] .br-early-code {
      display: inline-block;
      padding: .22em .51em;
      background: #17181c66;
      color: var(--studio-gold);
      font: 650 .85em/1.2 ui-monospace, Consolas, monospace;
      letter-spacing: .045em;
      user-select: all;
    }

    [data-br-black-friday-studio] .br-early-art {
      position: relative;
      isolation: isolate;
      display: grid;
      justify-items: center;
      gap: .5cqi;
      width: min(100%, 16.5cqi);
      min-width: 0;
    }

    [data-br-black-friday-studio] .br-early-art::before {
      content: "";
      position: absolute;
      inset: auto 3% 3%;
      z-index: -1;
      height: 15%;
      background: radial-gradient(ellipse, #111319 0%, transparent 67%);
      opacity: .85;
    }

    [data-br-black-friday-studio] .br-early-gifts {
      display: block;
      width: 100%;
      height: clamp(96px, 13.875cqi, 298px);
      object-fit: contain;
      transform: scaleX(-1);
      filter: drop-shadow(0 .5em .8em #12131855);
    }

    [data-br-black-friday-studio] .br-early-action {
      display: grid;
      gap: clamp(8px, .85cqi, 18px);
      min-width: 0;
      padding: clamp(11px, 1.3cqi, 29px);
      border-radius: 15px;
      background: #202328bf;
    }

    [data-br-black-friday-studio] .br-early-label {
      margin: 0;
      padding: 0;
      color: var(--studio-cream);
      font-size: clamp(10px, .91cqi, 20px);
      font-weight: 700;
      letter-spacing: .135em;
      text-align: center;
    }

    [data-br-black-friday-studio] .br-early-timer {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      padding: .25em 0;
      text-align: center;
    }

    [data-br-black-friday-studio] .br-early-tile {
      display: grid;
      gap: .37em;
      padding: .27em .1em;
      color: #e3dcd2;
      font-size: clamp(9px, .76cqi, 16px);
      font-weight: 650;
      line-height: 1.05;
      letter-spacing: .08em;
    }

    [data-br-black-friday-studio] .br-early-tile + .br-early-tile { border-inline-start: 1px solid var(--studio-border); }
    [data-br-black-friday-studio] .br-early-value {
      color: var(--studio-gold);
      font-size: clamp(20px, 2.72cqi, 58px);
      font-weight: 680;
      font-variant-numeric: tabular-nums;
      letter-spacing: -.05em;
    }

    [data-br-black-friday-studio] .br-early-cta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5em;
      min-height: 44px;
      padding: .7em .9em;
      border-radius: 6px;
      background: #e6c58d;
      color: #29231b;
      font-size: clamp(13px, 1.22cqi, 26px);
      font-weight: 720;
      text-decoration: none;
    }

    [data-br-black-friday-studio] .br-early-cta:hover { background: #f6d9a6; color: #29231b; }
    [data-br-black-friday-studio] .br-early-cta svg { width: 1.05em; height: 1.05em; flex: none; }
    [data-br-black-friday-studio] .br-early-dismiss {
      position: absolute;
      z-index: 1;
      inset: 3px 3px auto auto;
      display: grid;
      place-items: center;
      width: clamp(34px, 2.8cqi, 58px);
      aspect-ratio: 1;
      padding: 0;
      border: 0;
      border-radius: 50%;
      background: transparent;
      color: var(--studio-cream);
      font: 400 clamp(21px, 2cqi, 40px)/1 Arial, sans-serif;
      cursor: pointer;
    }

    [data-br-black-friday-studio] .br-early-dismiss:hover { background: #ffffff18; }
    [data-br-black-friday-studio] :is(a, button):focus-visible { outline: 2px solid white; outline-offset: 2px; }

    [data-br-black-friday-studio] .br-studio-title-glint,
    [data-br-black-friday-studio] .br-studio-dust { display: none; }

    @media (prefers-reduced-motion: no-preference) {
      @supports (background-clip: text) {
        [data-br-black-friday-studio] .br-studio-title-glint {
          position: absolute;
          inset: -.24em -.2em;
          display: block;
          padding: .24em .2em;
          background: linear-gradient(105deg, transparent 43%, #fff8e4 50%, transparent 57%) 100% 50% / 270% 100%;
          background-clip: text;
          color: transparent;
          animation: br-studio-glint 2.4s linear .15s infinite;
          pointer-events: none;
          user-select: none;
        }
      }

      @supports (mask-composite: exclude) {
        [data-br-black-friday-studio] :is(.br-early-code, .br-early-action)::after {
          content: "";
          position: absolute;
          inset: -1px;
          padding: 1px;
          border-radius: inherit;
          background: linear-gradient(105deg, transparent 43%, #edc989 48%, #fff8df 50%, #edc989 52%, transparent 57%) 100% 50% / 270% 100%;
          mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
          mask-composite: exclude;
          animation: br-studio-glint 2.9s linear 1.3s infinite;
          pointer-events: none;
        }

        [data-br-black-friday-studio] .br-early-action::after {
          animation-duration: 2.7s;
          animation-delay: .65s;
        }
      }

      [data-br-black-friday-studio] .br-studio-dust {
        position: absolute;
        z-index: 1;
        inset: 0;
        display: block;
        pointer-events: none;
      }

      [data-br-black-friday-studio] .br-studio-dust span {
        position: absolute;
        top: var(--dust-y);
        left: var(--dust-x);
        width: clamp(2px, .22cqi, 5px);
        aspect-ratio: 1;
        border-radius: 50%;
        background: #ffe6ac;
        box-shadow: 0 0 6px #f7c777;
        opacity: 0;
        animation: br-studio-dust-rise var(--dust-period) ease-out var(--dust-delay) infinite;
      }

      [data-br-black-friday-studio] .br-studio-dust span:nth-child(even) {
        --dust-drift: -13px;
        width: clamp(2px, .17cqi, 4px);
      }

      [data-br-black-friday-studio] .br-studio-dust span:nth-child(3n) {
        background: #fff4cf;
      }
    }

    @keyframes br-studio-glint {
      from { background-position: 100% 50%; }
      42%, to { background-position: 0% 50%; }
    }

    @keyframes br-studio-dust-rise {
      from { transform: translate(0, 0); opacity: 0; }
      12% { opacity: .9; }
      54%, to { transform: translate(var(--dust-drift, 15px), var(--dust-lift, -36px)); opacity: 0; }
    }

    @container br-studio (max-width: 700px) {
      [data-br-black-friday-studio] .br-early-layout {
        grid-template-columns: minmax(0, .78fr) minmax(0, 1.22fr);
        gap: 15px 10px;
        padding: 25px 16px 16px;
      }
      [data-br-black-friday-studio] .br-early-title { font-size: clamp(34px, 8.2cqi, 62px); }
      [data-br-black-friday-studio] .br-early-art { gap: 6px; width: max(75%, min(82px, 100%)); }
      [data-br-black-friday-studio] .br-early-gifts { height: auto; max-height: 146px; }
      [data-br-black-friday-studio] .br-studio-dust { --dust-lift: -22px; }
      [data-br-black-friday-studio] .br-early-action {
        grid-column: 1 / -1;
        grid-template-columns: minmax(0, 1fr) minmax(128px, .63fr);
        align-items: center;
      }
      [data-br-black-friday-studio] .br-early-label { grid-column: 1 / -1; }
      [data-br-black-friday-studio] .br-early-value { font-size: clamp(22px, 4.8cqi, 32px); }
    }

    @container br-studio (max-width: 420px) {
      [data-br-black-friday-studio] .br-early-layout { gap: 10px; padding: 18px 13px 12px; }
      [data-br-black-friday-studio] .br-early-action { grid-template-columns: minmax(0, 1fr); gap: 6px; padding: 9px; }
      [data-br-black-friday-studio] .br-early-title { font-size: clamp(36px, 10.25cqi, 44px); }
      [data-br-black-friday-studio] .br-studio-friday { display: block; width: fit-content; margin: 0 auto; }
      [data-br-black-friday-studio] .br-early-offer { gap: .35em .62em; margin-top: .55em; }
      [data-br-black-friday-studio] .br-early-divider { display: none; }
    }

    @container br-studio (max-width: 330px) {
      [data-br-black-friday-studio] .br-early-layout { padding-block: 15px 9px; }
    }
  </style>
  <section data-br-black-friday-studio data-end="2026-11-30T00:00:00+01:00"
    aria-label="BeRocket Black Friday">
    <span class="br-studio-dust" aria-hidden="true">
      <span style="--dust-x:17%; --dust-y:72%; --dust-delay:.2s; --dust-period:2.4s"></span>
      <span style="--dust-x:76%; --dust-y:73%; --dust-delay:.35s; --dust-period:2.55s"></span>
      <span style="--dust-x:63%; --dust-y:40%; --dust-delay:.5s; --dust-period:2.7s"></span>
      <span style="--dust-x:32%; --dust-y:54%; --dust-delay:.7s; --dust-period:2.85s"></span>
      <span style="--dust-x:81%; --dust-y:47%; --dust-delay:.85s; --dust-period:2.6s"></span>
      <span style="--dust-x:12%; --dust-y:51%; --dust-delay:1s; --dust-period:2.5s"></span>
      <span style="--dust-x:52%; --dust-y:61%; --dust-delay:1.15s; --dust-period:2.75s"></span>
      <span style="--dust-x:45%; --dust-y:30%; --dust-delay:1.35s; --dust-period:2.95s"></span>
      <span style="--dust-x:23%; --dust-y:41%; --dust-delay:1.5s; --dust-period:2.35s"></span>
      <span style="--dust-x:89%; --dust-y:64%; --dust-delay:1.65s; --dust-period:2.65s"></span>
      <span style="--dust-x:66%; --dust-y:79%; --dust-delay:1.8s; --dust-period:2.8s"></span>
      <span style="--dust-x:8%; --dust-y:83%; --dust-delay:1.95s; --dust-period:2.45s"></span>
      <span style="--dust-x:72%; --dust-y:12%; --dust-delay:2.1s; --dust-period:2.9s"></span>
      <span style="--dust-x:37%; --dust-y:80%; --dust-delay:2.25s; --dust-period:2.3s"></span>
      <span style="--dust-x:55%; --dust-y:51%; --dust-delay:2.4s; --dust-period:2.52s"></span>
      <span style="--dust-x:30%; --dust-y:14%; --dust-delay:2.55s; --dust-period:2.92s"></span>
      <span style="--dust-x:8%; --dust-y:63%; --dust-delay:.27s; --dust-period:2.48s"></span>
      <span style="--dust-x:92%; --dust-y:83%; --dust-delay:.42s; --dust-period:2.7s"></span>
      <span style="--dust-x:48%; --dust-y:94%; --dust-delay:.57s; --dust-period:2.83s"></span>
      <span style="--dust-x:73%; --dust-y:61%; --dust-delay:.77s; --dust-period:2.36s"></span>
      <span style="--dust-x:21%; --dust-y:97%; --dust-delay:.92s; --dust-period:2.67s"></span>
      <span style="--dust-x:96%; --dust-y:44%; --dust-delay:1.07s; --dust-period:2.89s"></span>
      <span style="--dust-x:14%; --dust-y:17%; --dust-delay:1.22s; --dust-period:2.54s"></span>
      <span style="--dust-x:85%; --dust-y:15%; --dust-delay:1.42s; --dust-period:2.96s"></span>
      <span style="--dust-x:34%; --dust-y:37%; --dust-delay:1.57s; --dust-period:2.38s"></span>
      <span style="--dust-x:66%; --dust-y:9%; --dust-delay:1.72s; --dust-period:2.59s"></span>
      <span style="--dust-x:61%; --dust-y:87%; --dust-delay:1.87s; --dust-period:2.81s"></span>
      <span style="--dust-x:4%; --dust-y:72%; --dust-delay:2.02s; --dust-period:2.43s"></span>
      <span style="--dust-x:91%; --dust-y:58%; --dust-delay:2.17s; --dust-period:2.74s"></span>
      <span style="--dust-x:39%; --dust-y:11%; --dust-delay:2.32s; --dust-period:2.94s"></span>
      <span style="--dust-x:27%; --dust-y:60%; --dust-delay:2.47s; --dust-period:2.49s"></span>
      <span style="--dust-x:80%; --dust-y:95%; --dust-delay:2.62s; --dust-period:2.87s"></span>
    </span>
    <div class="br-early-layout">
      <button class="br-early-dismiss berocket-notice-dismiss berocket-notice-dismiss- berocket_no_thanks" type="button" aria-label="Dismiss offer">
        <span class="berocket-notice-dismiss-check" aria-hidden="true">×</span>
      </button>
      <div class="br-early-art">
        <img class="br-early-logo" src="https://e8e3g4v6.delivery.rocketcdn.me/wp-content/uploads/2022/12/logo-with-text-white-237x70-1.png" alt="BeRocket">
        <img class="br-early-gifts" src="https://berocket.ams3.cdn.digitaloceanspaces.com/sale/bfcm2026/BeRocket_Black_Friday_Early_Access_Website_Banner_Studio1.png" alt="">
      </div>
      <div class="br-early-copy">
        <h2 class="br-early-title">Black <span class="br-studio-friday">Friday<span class="br-studio-title-glint" aria-hidden="true">Friday</span></span></h2>
        <p class="br-early-offer">
          <span>Save <strong class="br-early-discount">35%</strong></span>
          <span class="br-early-divider" aria-hidden="true"></span>
          <span>Use <code class="br-early-code">BF2026</code></span>
        </p>
      </div>
      <div class="br-early-action">
        <p class="br-early-label">ENDS IN</p>
        <div class="br-early-timer" role="timer" aria-live="off" aria-label="Time remaining">
          <span class="br-early-tile"><span class="br-early-value" data-unit="days">00</span> DAYS</span>
          <span class="br-early-tile"><span class="br-early-value" data-unit="hours">00</span> HRS</span>
          <span class="br-early-tile"><span class="br-early-value" data-unit="minutes">00</span> MIN</span>
          <span class="br-early-tile"><span class="br-early-value" data-unit="seconds">00</span> SEC</span>
        </div>
        <a class="br-early-cta" href="'. $cta .'" target="_blank" rel="noopener noreferrer">
          Shop Black Friday
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 12h15m-6-6 6 6-6 6"/>
          </svg>
        </a>
      </div>
    </div>
  </section>'.
					               "
				<script id='br-black-friday-studio-script'>
    (() => {
      const banner = document.querySelector('[data-br-black-friday-studio]');
      if (!banner || banner.hasAttribute('data-initialized')) return;
      banner.setAttribute('data-initialized', '');

      const deadline = Date.parse(banner.dataset.end);
      if (!Number.isFinite(deadline)) return;

      const days = banner.querySelector('[data-unit=\"days\"]');
      const hours = banner.querySelector('[data-unit=\"hours\"]');
      const minutes = banner.querySelector('[data-unit=\"minutes\"]');
      const seconds = banner.querySelector('[data-unit=\"seconds\"]');
      let tick;

      function hide() {
        clearTimeout(tick);
        banner.hidden = true;
        document.removeEventListener('visibilitychange', update);
        window.removeEventListener('pageshow', update);
      }

      function render(node, value) {
        node.textContent = String(value).padStart(2, '0');
      }

      function update() {
        clearTimeout(tick);
        const remaining = deadline - Date.now();
        if (remaining <= 0) {
          hide();
          return;
        }

        const totalSeconds = Math.ceil(remaining / 1000);
        render(days, Math.floor(totalSeconds / 86400));
        render(hours, Math.floor(totalSeconds / 3600) % 24);
        render(minutes, Math.floor(totalSeconds / 60) % 60);
        render(seconds, totalSeconds % 60);
        banner.hidden = false;
        tick = setTimeout(update, remaining % 1000 || 1000);
      }

      banner.querySelector('.br-early-dismiss').addEventListener('click', () => {
        hide();
      });

      document.addEventListener('visibilitychange', update);
      window.addEventListener('pageshow', update);
      update();
    })();
  </script>",
					'type'      => 'custom',
					'display_area' => 'settings',
					'righthtml' => '',
					'subscribe' => false,
					'priority'  => 19,
					'image'     => '',
				) );

				$this->br_show_admin_notice_sign();
			}
		}

		public function cm_2026(): void {
			$type = $this->get_plugin_type();

			$name       = 'cm_2026';
			$media      = '?coupon=cm2026&utm_source=' . $type . '_plugin&utm_medium=admin_notice&utm_campaign='.
			              $name .'&utm_content=top_banner_cta&utm_term=' . $this->plugin_name;
			$cta        = $this->main_sale_url . $media;
			$start_time = $this->mktime( "2026-11-30" );
			$end_time   = $this->mktime( "2026-12-05" );
			$c_notice   = berocket_admin_notices::get_notice_by_priority_and_name( 19, $name );

			$is_closed = false;
			if ( isset( $c_notice['closed'] ) and $c_notice['closed'] > 0 ) {
				$is_closed = true;
			}

			if ( ! $is_closed and $start_time <= time() and $end_time > time() ) {
				new berocket_admin_notices( array(
					'start'     => $start_time,
					'end'       => $end_time,
					'name'      => $name,
					'html'      => '
					<style id="br-cyber-monday-styles">
    [data-br-cyber-monday] {
      --cyber-white: #eff8ff;
      --cyber-cyan: #7ceaff;
      --cyber-line: #5293c870;
      position: relative;
      isolation: isolate;
      container: br-cyber / inline-size;
      margin: 16px 20px 16px 0;
      overflow: hidden;
      border: 1px solid #3972ac;
      border-radius: clamp(14px, 1.35vw, 26px);
      background:
        radial-gradient(ellipse at 12% 72%, #075283b0 0%, transparent 41%),
        radial-gradient(ellipse at 90% 96%, #28246f9c 0%, transparent 46%),
        linear-gradient(105deg, #0a172b, #0b1425 54%, #070d1e);
      color: var(--cyber-white);
      font: 400 16px/1.2 system-ui, -apple-system, "Segoe UI", sans-serif;
      text-align: left;
    }

    [data-br-cyber-monday][hidden] { display: none !important; }
    [data-br-cyber-monday] *, [data-br-cyber-monday] *::before { box-sizing: border-box; }

    [data-br-cyber-monday] .br-cyber-traces {
      position: absolute;
      inset: 0;
      z-index: -1;
      width: 100%;
      height: 100%;
      color: #58caff;
      filter: drop-shadow(0 0 4px #388eff);
      pointer-events: none;
    }

    [data-br-cyber-monday] .br-cyber-trace-lines { opacity: .68; }
    [data-br-cyber-monday] .br-cyber-trace-sparks { display: none; }
    [data-br-cyber-monday] .br-cyber-trace-sparks circle { filter: drop-shadow(0 0 3px #8df7ff); }

    [data-br-cyber-monday] .br-cyber-traces path {
      vector-effect: non-scaling-stroke;
      stroke-width: 1px;
    }

    [data-br-cyber-monday] .br-cyber-layout {
      display: grid;
      grid-template-columns: minmax(0, 25%) minmax(0, 1fr) minmax(0, 27%);
      align-items: center;
      gap: 1.6cqi;
      min-height: max(190px, 21.2cqi);
      padding: 1.1cqi 2.9cqi 1.1cqi 3.1cqi;
    }

    [data-br-cyber-monday] .br-cyber-copy { min-width: 0; text-align: center; }
    [data-br-cyber-monday] .br-cyber-logo { display: block; width: min(100%, clamp(99px, 10.7cqi, 232px)); height: auto; }

    [data-br-cyber-monday] .br-cyber-title {
      margin: 0;
      padding: 0;
      color: inherit;
      font: inherit;
      font-size: clamp(32px, 5.22cqi, 112px);
      font-weight: 850;
      line-height: 1;
      letter-spacing: -.068em;
      text-transform: uppercase;
    }

    [data-br-cyber-monday] .br-cyber-title span {
      display: inline-block;
      color: var(--cyber-cyan);
      text-shadow: 0 0 .55em #379cf356;
    }

    [data-br-cyber-monday] .br-cyber-offer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      gap: .4em .85em;
      margin: .85em 0 0;
      padding: 0;
      color: inherit;
      font-size: clamp(18px, 2cqi, 43px);
      line-height: 1.25;
    }

    [data-br-cyber-monday] .br-cyber-offer > span { white-space: nowrap; }
    [data-br-cyber-monday] .br-cyber-discount { color: var(--cyber-cyan); font-weight: 780; }
    [data-br-cyber-monday] .br-cyber-divider { width: 1px; height: 1em; background: var(--cyber-line); }
    [data-br-cyber-monday] .br-cyber-code {
      display: inline-block;
      padding: .22em .51em;
      border: 1px solid #448de3;
      border-radius: 6px;
      background: #0d2345;
      color: var(--cyber-cyan);
      box-shadow: 0 0 12px #3476ff38;
      font: 650 .85em/1.2 ui-monospace, Consolas, monospace;
      letter-spacing: .045em;
      user-select: all;
    }

    [data-br-cyber-monday] .br-cyber-art {
      position: relative;
      isolation: isolate;
      display: grid;
      justify-items: center;
      gap: .5cqi;
      width: min(100%, 18cqi);
      min-width: 0;
    }

    [data-br-cyber-monday] .br-cyber-art::before {
      content: "";
      position: absolute;
      inset: auto 3% 3%;
      z-index: -1;
      height: 15%;
      background: radial-gradient(ellipse, #07142d 0%, transparent 67%);
      opacity: .85;
    }

    [data-br-cyber-monday] .br-cyber-gifts {
      display: block;
      width: 100%;
      height: clamp(96px, 13.875cqi, 298px);
      object-fit: contain;
      filter: drop-shadow(0 .18em .82em #0885fb68);
    }

    [data-br-cyber-monday] .br-cyber-action {
      --cyber-panel-radius: clamp(9px, 1cqi, 22px);
      position: relative;
      display: grid;
      gap: clamp(8px, .85cqi, 18px);
      min-width: 0;
      padding: clamp(11px, 1.3cqi, 29px);
      border: 1px solid transparent;
      border-radius: var(--cyber-panel-radius);
      background:
        linear-gradient(145deg, #10233e, #0c142c 58%, #141737) padding-box,
        linear-gradient(125deg, #6ff1ff, #3993ff 57%, #a782ff) border-box;
      box-shadow: 0 0 17px #3a7bff58, inset 0 0 14px #2489ff20;
    }

    [data-br-cyber-monday] .br-cyber-spark { display: none; }

    @media (prefers-reduced-motion: no-preference) {
      [data-br-cyber-monday] .br-cyber-trace-sparks { display: inline; }
      [data-br-cyber-monday] .br-cyber-code { animation: br-cyber-code-flicker 1.65s step-end infinite; }

      @supports (offset-path: inset(0 round 1px)) {
        [data-br-cyber-monday] .br-cyber-spark {
          position: absolute;
          z-index: 1;
          top: 0;
          left: 0;
          display: block;
          width: clamp(5px, .4cqi, 8px);
          aspect-ratio: 1;
          border-radius: 50%;
          background: #edfcff;
          box-shadow: 0 0 5px 1px #94f2ff, 0 0 13px 3px #309cffb3;
          offset-path: inset(.5px round calc(var(--cyber-panel-radius) - .5px));
          animation: br-cyber-spark-lap 7.5s linear -1s infinite;
          pointer-events: none;
        }
      }
    }

    @keyframes br-cyber-spark-lap {
      from { offset-distance: 0%; }
      to { offset-distance: 100%; }
    }

    @keyframes br-cyber-code-flicker {
      0%, 21% {
        color: #abc8e5;
        border-color: #3a6499;
        box-shadow: none;
        text-shadow: none;
      }
      9%, 39%, 100% {
        color: #a5f3ff;
        border-color: #79dfff;
        box-shadow: 0 0 12px #389fff70;
        text-shadow: 0 0 .4em #62ccff9c;
      }
    }

    [data-br-cyber-monday] .br-cyber-label {
      margin: 0;
      padding: 0;
      color: var(--cyber-white);
      font-size: clamp(10px, .91cqi, 20px);
      font-weight: 700;
      letter-spacing: .135em;
      text-align: center;
    }

    [data-br-cyber-monday] .br-cyber-timer {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      padding: .25em 0;
      text-align: center;
    }

    [data-br-cyber-monday] .br-cyber-tile {
      display: grid;
      gap: .37em;
      padding: .27em .1em;
      color: #cdddf8;
      font-size: clamp(9px, .76cqi, 16px);
      font-weight: 650;
      line-height: 1.05;
      letter-spacing: .08em;
    }

    [data-br-cyber-monday] .br-cyber-tile + .br-cyber-tile { border-inline-start: 1px solid var(--cyber-line); }
    [data-br-cyber-monday] .br-cyber-value {
      color: var(--cyber-cyan);
      font-size: clamp(20px, 2.72cqi, 58px);
      font-weight: 740;
      font-variant-numeric: tabular-nums;
      letter-spacing: -.05em;
    }

    [data-br-cyber-monday] .br-cyber-cta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5em;
      min-height: 44px;
      padding: .7em .9em;
      border-radius: 6px;
      background: linear-gradient(100deg, #8bedff, #72afff);
      color: #08162d;
      font-size: clamp(13px, 1.22cqi, 26px);
      font-weight: 720;
      text-decoration: none;
    }

    [data-br-cyber-monday] .br-cyber-cta:hover { background: #acf2ff; color: #08162d; }
    [data-br-cyber-monday] .br-cyber-cta svg { width: 1.05em; height: 1.05em; flex: none; }
    [data-br-cyber-monday] .br-cyber-dismiss {
      position: absolute;
      z-index: 1;
      inset: 3px 3px auto auto;
      display: grid;
      place-items: center;
      width: clamp(34px, 2.8cqi, 58px);
      aspect-ratio: 1;
      padding: 0;
      border: 0;
      border-radius: 50%;
      background: transparent;
      color: var(--cyber-white);
      font: 400 clamp(21px, 2cqi, 40px)/1 Arial, sans-serif;
      cursor: pointer;
    }

    [data-br-cyber-monday] .br-cyber-dismiss:hover { background: #7ceaff22; }
    [data-br-cyber-monday] :is(a, button):focus-visible { outline: 2px solid white; outline-offset: 2px; }

    @container br-cyber (max-width: 700px) {
      [data-br-cyber-monday] .br-cyber-layout {
        grid-template-columns: minmax(0, .78fr) minmax(0, 1.22fr);
        gap: 15px 10px;
        padding: 25px 16px 16px;
      }
      [data-br-cyber-monday] .br-cyber-title { font-size: clamp(32px, 7.6cqi, 60px); }
      [data-br-cyber-monday] .br-cyber-trace-lines { opacity: .35; }
      [data-br-cyber-monday] .br-cyber-art { gap: 6px; width: max(75%, min(82px, 100%)); }
      [data-br-cyber-monday] .br-cyber-gifts { height: auto; max-height: 146px; }
      [data-br-cyber-monday] .br-cyber-action {
        grid-column: 1 / -1;
        grid-template-columns: minmax(0, 1fr) minmax(128px, .63fr);
        align-items: center;
      }
      [data-br-cyber-monday] .br-cyber-label { grid-column: 1 / -1; }
      [data-br-cyber-monday] .br-cyber-value { font-size: clamp(22px, 4.8cqi, 32px); }
    }

    @container br-cyber (max-width: 420px) {
      [data-br-cyber-monday] .br-cyber-layout { gap: 10px; padding: 18px 13px 12px; }
      [data-br-cyber-monday] .br-cyber-action { grid-template-columns: minmax(0, 1fr); gap: 6px; padding: 9px; }
      [data-br-cyber-monday] .br-cyber-title { font-size: clamp(31px, 9.5cqi, 40px); }
      [data-br-cyber-monday] .br-cyber-title span { display: block; width: fit-content; margin: 0 auto; }
      [data-br-cyber-monday] .br-cyber-offer { gap: .35em .62em; margin-top: .55em; }
      [data-br-cyber-monday] .br-cyber-divider { display: none; }
    }

    @container br-cyber (max-width: 330px) {
      [data-br-cyber-monday] .br-cyber-layout { padding-block: 15px 9px; }
    }
  </style>
  <section data-br-cyber-monday data-end="2026-12-05T00:00:00+01:00"
    aria-label="BeRocket Cyber Monday">
    <svg class="br-cyber-traces" viewBox="0 0 1000 212" preserveAspectRatio="none" aria-hidden="true" focusable="false">
      <g class="br-cyber-trace-lines" fill="none" stroke="currentColor">
        <path d="M0 44h16l13-13h29 M0 54h20l12-12h8 M161 199l13-13h43l13-13h22"/>
        <path id="br-cyber-lower-left" d="M0 174h14l14 14h118l16-16h30"/>
        <path id="br-cyber-upper-left" d="M239 0v10l11 11h58l9 9h34"/>
        <path d="M690 0v11l-12 12h-31"/>
        <path id="br-cyber-upper-right" d="M791 0v9l11 11h147l11 11h40"/>
        <path id="br-cyber-lower-right" d="M1000 168h-18l-14 14h-93l-14 14h-96" stroke="#9882ff"/>
        <path d="M1000 181h-13l-9 9h-31" stroke="#9882ff"/>
        <circle cx="192" cy="172" r="2"/><circle cx="252" cy="173" r="2"/>
        <circle cx="351" cy="30" r="2"/><circle cx="647" cy="23" r="2"/>
      </g>
      <g class="br-cyber-trace-sparks" fill="#edfcff" stroke="#a6f1ff" stroke-width="3">
        <circle r=".6" opacity="0" vector-effect="non-scaling-stroke">
          <animateMotion begin=".35s" dur="3.8s" repeatCount="indefinite">
            <mpath href="#br-cyber-lower-left"/>
          </animateMotion>
          <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;.1;.8;1" begin=".35s" dur="3.8s" repeatCount="indefinite"/>
        </circle>
        <circle r=".6" opacity="0" vector-effect="non-scaling-stroke">
          <animateMotion begin="1.3s" dur="3.4s" repeatCount="indefinite">
            <mpath href="#br-cyber-upper-left"/>
          </animateMotion>
          <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;.1;.8;1" begin="1.3s" dur="3.4s" repeatCount="indefinite"/>
        </circle>
        <circle r=".6" opacity="0" vector-effect="non-scaling-stroke">
          <animateMotion begin="2.15s" dur="5.2s" repeatCount="indefinite">
            <mpath href="#br-cyber-upper-right"/>
          </animateMotion>
          <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;.1;.8;1" begin="2.15s" dur="5.2s" repeatCount="indefinite"/>
        </circle>
        <circle r=".6" opacity="0" stroke="#d8c7ff" vector-effect="non-scaling-stroke">
          <animateMotion begin="3.05s" dur="4.6s" repeatCount="indefinite">
            <mpath href="#br-cyber-lower-right"/>
          </animateMotion>
          <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;.1;.8;1" begin="3.05s" dur="4.6s" repeatCount="indefinite"/>
        </circle>
      </g>
    </svg>
    <div class="br-cyber-layout">
      <button class="br-cyber-dismiss berocket-notice-dismiss berocket-notice-dismiss- berocket_no_thanks" type="button" aria-label="Dismiss Cyber Monday offer">
        <span class="berocket-notice-dismiss-check" aria-hidden="true">×</span>
      </button>
      <div class="br-cyber-art">
        <img class="br-cyber-logo" src="https://e8e3g4v6.delivery.rocketcdn.me/wp-content/uploads/2022/12/logo-with-text-white-237x70-1.png" alt="BeRocket">
        <img class="br-cyber-gifts" src="https://berocket.ams3.cdn.digitaloceanspaces.com/sale/bfcm2026/BeRocket_Cyber_Monday_Website_Banner1.png" alt="">
      </div>
      <div class="br-cyber-copy">
        <h2 class="br-cyber-title">Cyber <span>Monday</span></h2>
        <p class="br-cyber-offer">
          <span>Save <strong class="br-cyber-discount">35%</strong></span>
          <span class="br-cyber-divider" aria-hidden="true"></span>
          <span>Use <code class="br-cyber-code">CM2026</code></span>
        </p>
      </div>
      <div class="br-cyber-action">
        <span class="br-cyber-spark" aria-hidden="true"></span>
        <p class="br-cyber-label">Ends in</p>
        <div class="br-cyber-timer" role="timer" aria-live="off" aria-label="Time remaining">
          <span class="br-cyber-tile"><span class="br-cyber-value" data-unit="days">00</span> DAYS</span>
          <span class="br-cyber-tile"><span class="br-cyber-value" data-unit="hours">00</span> HRS</span>
          <span class="br-cyber-tile"><span class="br-cyber-value" data-unit="minutes">00</span> MIN</span>
          <span class="br-cyber-tile"><span class="br-cyber-value" data-unit="seconds">00</span> SEC</span>
        </div>
        <a class="br-cyber-cta" href="'. $cta .'" target="_blank" rel="noopener noreferrer">
          Shop Cyber Monday
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 12h15m-6-6 6 6-6 6"/>
          </svg>
        </a>
      </div>
    </div>
  </section>'.
					               "
				<script id='br-cyber-monday-script'>
    (() => {
      const banner = document.querySelector('[data-br-cyber-monday]');
      if (!banner || banner.hasAttribute('data-initialized')) return;
      banner.setAttribute('data-initialized', '');

      const deadline = Date.parse(banner.dataset.end);
      if (!Number.isFinite(deadline)) return;

      const days = banner.querySelector('[data-unit=\"days\"]');
      const hours = banner.querySelector('[data-unit=\"hours\"]');
      const minutes = banner.querySelector('[data-unit=\"minutes\"]');
      const seconds = banner.querySelector('[data-unit=\"seconds\"]');
      let tick;

      function hide() {
        clearTimeout(tick);
        banner.hidden = true;
        document.removeEventListener('visibilitychange', update);
        window.removeEventListener('pageshow', update);
      }

      function render(node, value) {
        node.textContent = String(value).padStart(2, '0');
      }

      function update() {
        clearTimeout(tick);
        const remaining = deadline - Date.now();
        if (remaining <= 0) {
          hide();
          return;
        }

        const totalSeconds = Math.ceil(remaining / 1000);
        render(days, Math.floor(totalSeconds / 86400));
        render(hours, Math.floor(totalSeconds / 3600) % 24);
        render(minutes, Math.floor(totalSeconds / 60) % 60);
        render(seconds, totalSeconds % 60);
        banner.hidden = false;
        tick = setTimeout(update, remaining % 1000 || 1000);
      }

      banner.querySelector('.br-cyber-dismiss').addEventListener('click', () => {
        hide();
      });

      document.addEventListener('visibilitychange', update);
      window.addEventListener('pageshow', update);
      update();
    })();
  </script>",
					'type'      => 'custom',
					'display_area' => 'settings',
					'righthtml' => '',
					'subscribe' => false,
					'priority'  => 19,
					'image'     => '',
				) );

				$this->br_show_admin_notice_sign();
			}
		}

		public function br_show_admin_notice_sign(): void {
			add_action('admin_head', function () {
				echo "
				<style>
				#toplevel_page_berocket_account .toplevel_page_berocket_account .wp-menu-name:after {
					content: '🎁';
					display: flex;
					vertical-align: top;
					box-sizing: border-box;
					margin: 0;
					padding: 0 5px;
					min-width: 22px;
					border-radius: 0;
					background-color: #6f32b1;
					color: #fff;
					font-size: 18px;
					line-height: 1.6;
					text-align: center;
					z-index: 26;
					position: absolute;
					right: 10px;
					top: 2px;
					bottom: 2px;
					align-items: center;
					justify-content: center;
					font-weight: 600;
					opacity: 1;
					transition: opacity 0.5s ease;
				}
				#toplevel_page_berocket_account .toplevel_page_berocket_account .wp-menu-name.hideAfter:after {
					opacity: 0;
					pointer-events: none;
				}
				</style>
				";
			});
		}

		public function br_show_admin_notice_timer() {
			add_action('admin_head', function () {
				echo "
				<script>
				function berocket_notice_countdown() {
                    time = jQuery('.berocket-notice-countdown-timer').data('endtime')*1 - Math.floor(Date.now()/1000);
                    berocket_notice_countdown_timer(time);
                }
                function berocket_notice_countdown_timer(time) {
                    formatted_time = berocket_notice_countdown_timer_format_time(time);
                    jQuery('.berocket-notice-countdown-days span').text(formatted_time.days);
                    jQuery('.berocket-notice-countdown-hours span').text(formatted_time.hours);
                    jQuery('.berocket-notice-countdown-minutes span').text(formatted_time.minutes);
                    jQuery('.berocket-notice-countdown-seconds span').text(formatted_time.seconds);
                    if ( time > 0 ) {
                        setTimeout(berocket_notice_countdown_timer, 1000, time-1);
                    }
                }
                function berocket_notice_countdown_timer_format_time(totalSeconds) {
					const days = Math.floor(totalSeconds / 86400);
					totalSeconds %= 86400;
					
					const hours = Math.floor(totalSeconds / 3600);
					totalSeconds %= 3600;
					
					const minutes = Math.floor(totalSeconds / 60);
					const seconds = totalSeconds % 60;
					
					return { days, hours, minutes, seconds };
				}
				jQuery(document).ready(function () {
	                berocket_notice_countdown();
				});
				</script>
				<style>
				.berocket-notice-countdown-container {
					background: #ffd453;
					width: 516px;
					margin: 0 auto;
					border-radius: 10px;
					padding: 13px 0 7px;
				}
				.berocket-notice-countdown-title {
					font-size: 16px;
				}
				.berocket-notice.notice-warning ul.berocket-notice-countdown-timer {
					display: flex;
					gap: 10px;
					justify-content: center;
					margin-bottom: 0;
					padding-left: 10px !important;					
					padding-right: 10px !important;					
				}
				.berocket-notice-countdown-timer li {
					list-style-type: none;
					background: white;
					height: 60px;
					width: 80px;
					border-radius: 10px;
					font-size: 15px;
					padding: 10px 5px;
					box-sizing: border-box;
				}
				.berocket-notice-countdown-timer li span {
					font-size: 24px;
					font-weight: 700;
				}
				</style>
				";
			});
		}

		public function get_plugin_type(): string {
			$plugin_version_capability = apply_filters( $this->version_capability_name, 0 );
			return ( $plugin_version_capability >= 15 ) ? 'paid' : 'free';
		}

		private function mktime( $date, $time = '00:00:00' ): int {
			return (new DateTimeImmutable(
				$date . ' ' . $time,
				new DateTimeZone('Europe/Madrid')
			))->getTimestamp();
		}
	}

	new braapf_free_funnels();
}
