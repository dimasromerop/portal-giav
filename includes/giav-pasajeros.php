<?php
function casanova_giav_pasajeros_por_reserva(int $idReserva, int $idExpediente = 0): array {
  if ($idReserva <= 0) return [];

  $cache_key = 'casanova_pasajeros_reserva_' . $idReserva;
  $cached = get_transient($cache_key);
  if (is_array($cached)) return $cached;

  $res = casanova_giav_call('PasajeroReserva_SEARCH', [
    'idsExpediente'              => $idExpediente > 0 ? [$idExpediente] : null,
    'idsReservas'                => [$idReserva],
    'idsPasajeros'               => null,
    'idsPerfilesPlazaaPlaza'     => null,
    'idsRelacion'                => null,
    'fechaHoraModificacionDesde' => null,
    'fechaHoraModificacionHasta' => null,
    'pageSize'                   => 100,
    'pageIndex'                  => 0,
  ]);

  if (is_wp_error($res)) {
    set_transient($cache_key, [], 15 * MINUTE_IN_SECONDS);
    return [];
  }

  $result = $res->PasajeroReserva_SEARCHResult ?? null;

  // normaliza ArrayOfWsPasajeroReserva -> [WsPasajeroReserva, ...]
  $items = casanova_giav_normalize_list($result, 'WsPasajeroReserva');

  set_transient($cache_key, $items, 12 * HOUR_IN_SECONDS);
  return $items;
}

function casanova_giav_pasajeros_por_expediente(int $idExpediente): array {
  if ($idExpediente <= 0) return [];

  $cache_key = 'casanova_pasajeros_expediente_' . $idExpediente;
  $cached = get_transient($cache_key);
  if (is_array($cached)) return $cached;

  // IMPORTANTE: aquí usa el formato de arrays que ya te funciona con otros SEARCH.
  // Si tu builder convierte arrays a <int> automáticamente, esto basta.
  $res = casanova_giav_call('PasajeroExpediente_SEARCH', [
    'idsExpediente' => [$idExpediente],
    'idsPasajeros'  => null,
    'idsRelacion'   => null,
    'pageSize'      => 100,
    'pageIndex'     => 0,
  ]);

  if (is_wp_error($res) || !is_object($res)) {
    set_transient($cache_key, [], 30 * MINUTE_IN_SECONDS);
    return [];
  }

  $root = $res->PasajeroExpediente_SEARCHResult ?? null;
  if (!$root) {
    set_transient($cache_key, [], 30 * MINUTE_IN_SECONDS);
    return [];
  }

  // Normaliza a lista de WsPasajeroExpediente
  $items = casanova_giav_normalize_list($root, 'WsPasajeroExpediente');

  // Limpia objetos vacíos GIAV
  $items = array_values(array_filter($items, function($x) {
    return is_object($x) && !empty(get_object_vars($x));
  }));

  set_transient($cache_key, $items, 6 * HOUR_IN_SECONDS);
  return $items;
}


function casanova_giav_pasajeros_para_bono(int $idReserva, int $idExpediente): array {
  $pas = casanova_giav_pasajeros_por_reserva($idReserva, $idExpediente);
  if (is_array($pas) && !empty($pas)) return $pas;

  return casanova_giav_pasajeros_por_expediente($idExpediente);
  }