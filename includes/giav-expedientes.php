<?php

/**
 * GIAV: buscar expedientes de un cliente
 * Usa Expediente_SEARCH con idsCliente (ArrayOfInt) :contentReference[oaicite:3]{index=3}
 */
function casanova_giav_expedientes_por_cliente(int $idCliente, int $pageSize = 50, int $pageIndex = 0) {
  // GIAV limita pageSize a 100 (hard cap).
  $pageSize  = max(1, min(100, (int)$pageSize));
  $pageIndex = max(0, (int)$pageIndex);


  // Cache corta (mejora UX y reduce llamadas SOAP)
  if (function_exists('casanova_cache_remember')) {
    return casanova_cache_remember(
      'giav:expedientes_por_cliente:' . $idCliente . ':' . $pageSize . ':' . $pageIndex,
      defined('CASANOVA_CACHE_TTL') ? (int)CASANOVA_CACHE_TTL : 90,
      function () use ($idCliente, $pageSize, $pageIndex) {
        return casanova_giav_expedientes_por_cliente_uncached($idCliente, $pageSize, $pageIndex);
      }
    );
  }

  return casanova_giav_expedientes_por_cliente_uncached($idCliente, $pageSize, $pageIndex);
}

/**
 * Implementación real (sin cache). Separada para poder envolver con transients.
 */
function casanova_giav_expedientes_por_cliente_uncached(int $idCliente, int $pageSize = 50, int $pageIndex = 0) {

  $p = new stdClass();
  $p->apikey = CASANOVA_GIAV_APIKEY;

  // ✅ ArrayOfInt en formato SOAP: <idsCliente><int>...</int></idsCliente>
  $p->idsCliente = (object) ['int' => [$idCliente]];

  $p->pageSize  = $pageSize;
  $p->pageIndex = $pageIndex;

  // Obligatorio por encoding + valores válidos
  $p->modoMultiFiltroFecha  = 'Salida';
  $p->multiFiltroFechaDesde = null;
  $p->multiFiltroFechaHasta = null;

  $p->facturacionPendiente = 'NoAplicar';
  $p->cobroPendiente       = 'NoAplicar';
  $p->estadoCierre         = 'NoAplicar';
  $p->tipoExpediente       = 'NoAplicar';
  $p->recepcionCosteTotal  = 'NoAplicar';

  $resp = casanova_giav_call('Expediente_SEARCH', $p);
  if (is_wp_error($resp)) return $resp;

  $result = $resp->Expediente_SEARCHResult ?? null;
  if (empty($result)) return [];

  if (is_object($result) && isset($result->WsExpediente)) {
    $items = $result->WsExpediente;
    if (is_array($items)) return $items;
    if (is_object($items)) return [$items];
  }

  return [];
}


/**
 * Shortcode: lista expedientes del usuario logueado
 */
add_shortcode('casanova_expedientes', function($atts) {
  if (!is_user_logged_in()) return '<p>' . esc_html__('Debes iniciar sesión.', 'casanova-portal') . '</p>';

  $user_id   = get_current_user_id();
  $idCliente = (int) get_user_meta($user_id, 'casanova_idcliente', true);

  if (!$idCliente) {
    return '<p>' . esc_html__('No tienes la cuenta vinculada todavía.', 'casanova-portal') . '</p>';
  }

  $items = casanova_giav_expedientes_por_cliente($idCliente, 50, 0);

  if (is_wp_error($items)) {
    return '<p>' . esc_html__('No se han podido cargar tus expedientes. Inténtalo más tarde.', 'casanova-portal') . '</p>';
  }

  if (empty($items)) {
    return '<p>' . esc_html__('No hay expedientes asociados a tu cuenta.', 'casanova-portal') . '</p>';
  }

  // Ordenar por FechaCreacion desc (si viene)
  usort($items, function($a, $b) {
    $da = isset($a->FechaCreacion) ? strtotime((string)$a->FechaCreacion) : 0;
    $db = isset($b->FechaCreacion) ? strtotime((string)$b->FechaCreacion) : 0;
    return $db <=> $da;
  });

  $active_id = isset($_GET['expediente']) ? (int)$_GET['expediente'] : 0;

  ob_start();
  // Drawer-ready wrapper (mobile). Safe even if you render it inside Bricks columns.
  echo '<div class="casanova-portal casanova-expedientes casanova-expedientes--drawer">';
  foreach ($items as $e) {
  $id      = (int) ($e->Id ?? 0);
  $codigo  = esc_html($e->Codigo ?? '');
  $titulo  = esc_html($e->Titulo ?? '');
  $destino = esc_html($e->Destino ?? '');
  $cerrado = !empty($e->Cerrado) ? 'Cerrado' : 'Abierto';
  $state_cls = !empty($e->Cerrado) ? 'is-closed' : 'is-open';

  $desde = !empty($e->FechaDesde) ? date_i18n('d/m/Y', strtotime((string)$e->FechaDesde)) : '';
  $hasta = !empty($e->FechaHasta) ? date_i18n('d/m/Y', strtotime((string)$e->FechaHasta)) : '';
  $rango = trim($desde . ($hasta ? ' – ' . $hasta : ''));

  $base = function_exists('casanova_portal_base_url') ? casanova_portal_base_url() : home_url('/area-usuario/');

  // Si estamos usando el router (?view=...), al pinchar en un expediente
  // debemos mantener/forzar la vista de "expedientes". Si no, el router
  // cae al default (Principal) y parece que "no carga nada".
  $view = isset($_GET['view']) ? sanitize_key((string)$_GET['view']) : '';
  if ($view === '') $view = 'expedientes';

  $url  = add_query_arg(['view' => $view, 'expediente' => $id], $base);


  $is_active = ($active_id > 0 && $id === $active_id);
  $cls = 'casanova-expediente-card' . ($is_active ? ' is-active' : '');

  echo '<a class="' . esc_attr($cls) . '" data-casanova-expediente-link href="' . esc_url($url) . '">';
    echo '<div class="casanova-expediente-card__row">';
      echo '<div class="casanova-expediente-card__main">';
        echo '<div class="casanova-expediente-card__title">' . esc_html($titulo ?: (sprintf(__('Expediente %s', 'casanova-portal'), $codigo))) . '</div>';
        echo '<div class="casanova-expediente-card__meta">' . $codigo . ($destino ? ' · ' . $destino : '') . ($rango ? ' · ' . $rango : '') . '</div>';
      echo '</div>';
      echo '<div class="casanova-expediente-card__right">';
        echo '<div class="casanova-expediente-card__state ' . esc_attr($state_cls) . '">' . esc_html($cerrado) . '</div>';
        echo '<div class="casanova-expediente-card__chev" aria-hidden="true">›</div>';
      echo '</div>';
    echo '</div>';
  echo '</a>';
}

  echo '</div>';

  return ob_get_clean();
});

/**
 * Header del expediente activo (pensado para la columna derecha / tabs)
 * - Móvil: botón abre el drawer de expedientes
 * - Desktop: muestra el contexto (código/título)
 */
add_shortcode('casanova_expediente_header', function() {
  if (!is_user_logged_in()) return '';

  $user_id   = (int) get_current_user_id();
  $idCliente = (int) get_user_meta($user_id, 'casanova_idcliente', true);

  $idExp = isset($_GET['expediente']) ? (int)$_GET['expediente'] : 0;

  $title = '' . esc_html__('Selecciona un expediente para ver su detalle.', 'casanova-portal') . '';
  $meta  = '';

  if ($idExp > 0 && function_exists('casanova_giav_expediente_get')) {
    $exp = casanova_giav_expediente_get($idExp);
    if (!is_wp_error($exp) && is_object($exp)) {
      // Seguridad básica si viene IdCliente
      if (!isset($exp->IdCliente) || (int)$exp->IdCliente === $idCliente) {
        $codigo = trim((string)($exp->Codigo ?? ''));
        $titulo = trim((string)($exp->Titulo ?? ''));
        $dest   = trim((string)($exp->Destino ?? ''));

        $desde = !empty($exp->FechaDesde) ? date_i18n('d/m/Y', strtotime((string)$exp->FechaDesde)) : '';
        $hasta = !empty($exp->FechaHasta) ? date_i18n('d/m/Y', strtotime((string)$exp->FechaHasta)) : '';
        $rango = trim($desde . ($hasta ? ' – ' . $hasta : ''));

        $title = $titulo !== '' ? $titulo : ($codigo !== '' ? (sprintf(__('Expediente %s', 'casanova-portal'), $codigo)) : (sprintf(__('Expediente %s', 'casanova-portal'), $idExp)));
        $meta_parts = array_filter([$codigo, $dest, $rango]);
        $meta = !empty($meta_parts) ? implode(' · ', $meta_parts) : '';
      }
    }
  }

  $is_empty = ($idExp <= 0);

  $html  = '<div class="casanova-exp-header'.($is_empty ? ' casanova-exp-header--empty' : '').'">';
  $html .= '  <button type="button" class="casanova-exp-header__btn" data-casanova-open-drawer aria-label="Abrir lista de expedientes">'
        . ($is_empty ? 'Elegir expediente' : '' . esc_html__('Expedientes', 'casanova-portal') . '')
        . ' <span class="casanova-exp-header__chev" aria-hidden="true">▾</span>'
        . '</button>';
  $html .= '  <div class="casanova-exp-header__text">';
  $html .= '    <div class="casanova-exp-header__title">' . esc_html($title) . '</div>';
  if ($meta !== '') {
    $html .= '    <div class="casanova-exp-header__meta">' . esc_html($meta) . '</div>';
  }
  if ($is_empty) {
    $html .= '    <div class="casanova-exp-header__meta">' . esc_html__('Usa el botón para ver tu lista de expedientes.', 'casanova-portal') . '</div>';
  }
  $html .= '  </div>';
  $html .= '</div>';

  // Backdrop for mobile drawer. (El overlay de loading lo montas en Bricks para cubrir solo las tabs.)
  $html .= '<div class="casanova-drawer-backdrop" data-casanova-close-drawer></div>';

  return $html;
});

function casanova_giav_expediente_get(int $idExpediente) {

  $p = new stdClass();
  $p->apikey = CASANOVA_GIAV_APIKEY;

  // Filtramos por id de expediente
 $p->idsExpediente = (object) ['int' => [(int)$idExpediente]];

  // Campos obligatorios/enums (para evitar el SOAP “me falta propiedad”)
  $p->modoMultiFiltroFecha   = 'Salida';
  $p->multiFiltroFechaDesde  = null;
  $p->multiFiltroFechaHasta  = null;

  $p->facturacionPendiente = 'NoAplicar';
  $p->cobroPendiente       = 'NoAplicar';
  $p->estadoCierre         = 'NoAplicar';
  $p->tipoExpediente       = 'NoAplicar';
  $p->recepcionCosteTotal  = 'NoAplicar';

  $p->pageSize  = 10;
  $p->pageIndex = 0;

  $resp = casanova_giav_call('Expediente_SEARCH', $p);
  if (is_wp_error($resp)) return $resp;

  $result = $resp->Expediente_SEARCHResult ?? null;
  if (empty($result)) return null;

  if (is_object($result) && isset($result->WsExpediente)) {
    $items = $result->WsExpediente;
    if (is_array($items)) return $items[0] ?? null;
    if (is_object($items)) return $items;
  }

  return null;
}

add_shortcode('casanova_expediente_detalle', function() {

  if (!is_user_logged_in()) return '<p>' . esc_html__('Debes iniciar sesión.', 'casanova-portal') . '</p>';

  $user_id   = get_current_user_id();
  $idCliente = (int) get_user_meta($user_id, 'casanova_idcliente', true);

  if (!$idCliente) return '<p>' . esc_html__('No tienes la cuenta vinculada todavía.', 'casanova-portal') . '</p>';

  $idExp = isset($_GET['expediente']) ? (int) $_GET['expediente'] : 0;
  if (!$idExp) return ''; // no muestra nada si no hay expediente seleccionado

  $exp = casanova_giav_expediente_get($idExp);

  if (is_wp_error($exp)) {
    return '<p>' . esc_html__('No se ha podido cargar el expediente. Inténtalo más tarde.', 'casanova-portal') . '</p>';
  }

  if (!$exp) {
    return '<p>' . esc_html__('Expediente no encontrado.', 'casanova-portal') . '</p>';
  }

  // Seguridad básica: asegurarnos de que el expediente es del cliente logueado
  // (si viene el IdCliente en la respuesta, lo comprobamos)
  if (isset($exp->IdCliente) && (int)$exp->IdCliente !== $idCliente) {
    return '<p>' . esc_html__('No tienes acceso a este expediente.', 'casanova-portal') . '</p>';
  }

  $codigo  = esc_html($exp->Codigo ?? '');
  $titulo  = esc_html($exp->Titulo ?? '');
  $destino = esc_html($exp->Destino ?? '');
  $cerrado = !empty($exp->Cerrado) ? 'Cerrado' : 'Abierto';

  $desde = !empty($exp->FechaDesde) ? date_i18n('d/m/Y', strtotime((string)$exp->FechaDesde)) : '';
  $hasta = !empty($exp->FechaHasta) ? date_i18n('d/m/Y', strtotime((string)$exp->FechaHasta)) : '';
  $rango = trim($desde . ($hasta ? ' – ' . $hasta : ''));

  $obs = '';
  if (!empty($exp->Observaciones)) {
    $obs = wp_kses_post(nl2br(esc_html((string)$exp->Observaciones)));
  }

$backUrl = remove_query_arg('expediente', function_exists('casanova_portal_base_url') ? casanova_portal_base_url() : home_url('/area-usuario/'));


  ob_start();
  echo '<div class="casanova-portal">';
  echo '<section class="casanova-card casanova-expediente-detalle">';
    echo '<div class="casanova-expediente-head">';
      echo '<div class="casanova-expediente-head__main">';
        echo '<div class="casanova-expediente-head__title">' . ($titulo ?: (sprintf(__('Expediente %s', 'casanova-portal'), $codigo))) . '</div>';
        echo '<div class="casanova-expediente-head__meta">' . $codigo . ($destino ? ' · ' . $destino : '') . ($rango ? ' · ' . $rango : '') . '</div>';
      echo '</div>';
      echo '<div class="casanova-expediente-head__state">' . esc_html($cerrado) . '</div>';
    echo '</div>';

    if ($obs) {
      echo '<div class="casanova-divider"></div>';
      echo '<div class="casanova-subtitle">' . esc_html__('Observaciones', 'casanova-portal') . '</div>';
      echo '<div class="casanova-expediente-obs">' . $obs . '</div>';
    }

    echo '<div class="casanova-expediente-back">';
      echo '<a class="casanova-link" href="' . esc_url($backUrl) . '">' . esc_html__('← Volver a mis expedientes', 'casanova-portal') . '</a>';
    echo '</div>';
  echo '</section>';
  echo '</div>';

  return ob_get_clean();
});