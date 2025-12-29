<?php
/**
 * Plugin Name: Casanova Portal - GIAV
 * Description: Área privada Casanova Golf conectada a GIAV por SOAP (Cliente, Expedientes, Reservas).
 * Version: 0.27.0
 * Author: Casanova Golf
 * Text Domain: casanova-portal
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('CASANOVA_GIAV_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CASANOVA_GIAV_PLUGIN_URL', plugin_dir_url(__FILE__));


add_action('plugins_loaded', function () {
  load_plugin_textdomain('casanova-portal', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('wp_enqueue_scripts', function () {
  if (is_admin()) return;

  $file = CASANOVA_GIAV_PLUGIN_PATH . 'assets/portal.css';
  $ver  = file_exists($file) ? (string) filemtime($file) : '0';

  wp_enqueue_style(
    'casanova-portal-giav',
    CASANOVA_GIAV_PLUGIN_URL . 'assets/portal.css',
    [],
    $ver
  );

  // Custom CSS del portal (FUERA del plugin, para que sobreviva a actualizaciones)
  // Ruta esperada: wp-content/uploads/casanova-portal/portal-custom.css
  $u = wp_upload_dir();
  $custom_path = trailingslashit($u['basedir']) . 'casanova-portal/portal-custom.css';
  $custom_url  = trailingslashit($u['baseurl']) . 'casanova-portal/portal-custom.css';
  if (!empty($u['basedir']) && file_exists($custom_path)) {
    $cver = (string) filemtime($custom_path);
    wp_enqueue_style('casanova-portal-giav-custom', $custom_url, ['casanova-portal-giav'], $cver);
  }

  // JS: drawer (mobile) + loading overlay
  $js = CASANOVA_GIAV_PLUGIN_PATH . 'assets/portal.js';
  $js_ver = file_exists($js) ? (string) filemtime($js) : '0';
  wp_enqueue_script(
    'casanova-portal-giav',
    CASANOVA_GIAV_PLUGIN_URL . 'assets/portal.js',
    [],
    $js_ver,
    true
  );

  // i18n strings for JS
  wp_localize_script('casanova-portal-giav', 'casanovaPortalI18n', [
    'saving' => __('Guardando…', 'casanova-portal'),
  ]);

}, 20);

// 1) INCLUDES MÍNIMOS para activación (solo DB)
require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-db.php';

// 2) HOOK de activación (tabla)
register_activation_hook(__FILE__, 'casanova_payments_install');

// Activación: programa de fidelización (cron)
register_activation_hook(__FILE__, function () {
  if (!wp_next_scheduled('casanova_mulligans_daily_sync')) {
    // Evitamos el "pico" típico de medianoche; WordPress terminará ejecutándolo cuando pueda.
    wp_schedule_event(time() + 120, 'daily', 'casanova_mulligans_daily_sync');
  }
});

// Desactivación: limpia cron fidelización
register_deactivation_hook(__FILE__, function () {
  $ts = wp_next_scheduled('casanova_mulligans_daily_sync');
  if ($ts) wp_unschedule_event($ts, 'casanova_mulligans_daily_sync');
});

// 3) Carga del resto del plugin (después, ya con WP listo)
add_action('plugins_loaded', function () {

  // Optimizaciones (cache/log/base URL). Cargado primero para que el resto lo use.
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-optimizations.php';

  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-core.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-client-search.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-expedientes.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-reservas.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-oficina.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-proveedores.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-pasajeros.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-facturas.php';

  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/helpers-http.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/helpers-giav.php';

  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-format.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-dashboard.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-cards.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-messages.php';
require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-bonos.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-router.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-actions.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/giav-reservas-map.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-voucher.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-profile.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-loyalty.php';
  
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-db.php'; 
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-intents.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-redsys.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-mail.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-mail-templates.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-mail-events.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-settings.php';

    require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-actions.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-tpv.php';
  require_once CASANOVA_GIAV_PLUGIN_PATH . 'includes/portal-payments-cron.php';

});
// Toast notice (post-redirect feedback)
add_action('wp_footer', function () {
  if (is_admin()) return;

  $notice = isset($_GET['casanova_notice']) ? sanitize_key($_GET['casanova_notice']) : '';
  if (!$notice) return;

  $map = [
    'address_saved' => 'Dirección actualizada.',
    'address_error' => 'No se pudo actualizar la dirección.',
  ];
  if (!isset($map[$notice])) return;

  $msg = esc_html($map[$notice]);
  ?>
  <div class="casanova-toast" role="status" aria-live="polite"><?php echo $msg; ?></div>
  <script>
  (function(){
    const toast = document.querySelector('.casanova-toast');
    if (!toast) return;
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    setTimeout(() => toast.classList.remove('is-visible'), 3500);

    // limpia el parámetro para que no reaparezca al refrescar
    try {
      const url = new URL(window.location.href);
      url.searchParams.delete('casanova_notice');
      window.history.replaceState({}, document.title, url.toString());
    } catch (e) {}
  })();
  </script>
  <?php
}, 50);