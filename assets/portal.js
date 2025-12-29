(function () {
  function closest(el, sel) {
    return el && el.closest ? el.closest(sel) : null;
  }

  function isModifiedClick(e) {
    // No enseñamos overlay si el usuario abre en nueva pestaña/ventana o hace click raro.
    return !!(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1);
  }

  function getDetailContainer() {
    // Preferimos cargar solo la zona derecha / tabs
    var detail = document.querySelector('.casanova-detail');
    if (detail) return detail;

    // Fallback: portal entero
    var portal = document.querySelector('.casanova-portal');
    if (portal) return portal;

    return null;
  }

  function scrollDetailIntoView(detail) {
    try {
      if (!detail) return;

      // En escritorio esto provoca un "salto" de scroll bastante incómodo.
      // Solo lo hacemos en móvil para que el overlay no quede fuera de pantalla.
      var isMobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
      if (!isMobile) return;

      var rect = detail.getBoundingClientRect();
      // Si el header/tabs están fuera de pantalla, subimos para que el overlay sea visible
      if (rect.top < 0 || rect.top > (window.innerHeight * 0.35)) {
        window.scrollTo(0, Math.max(0, window.scrollY + rect.top - 16));
      }
    } catch (_) {}
  }

  document.addEventListener('click', function (e) {
    // Toggle details (botón "Detalle" dentro del summary)
    var toggle = closest(e.target, '[data-casanova-toggle-details]');
    if (toggle) {
      var det = closest(toggle, 'details');
      if (det) det.open = !det.open;
      e.preventDefault();
      e.stopPropagation();
      return;
    }

    // Open drawer (mobile)
    var openBtn = closest(e.target, '[data-casanova-open-drawer]');
    if (openBtn) {
      document.documentElement.classList.add('casanova-drawer-open');
      e.preventDefault();
      return;
    }

    // Close drawer (backdrop / close button)
    var closeBtn = closest(e.target, '[data-casanova-close-drawer]');
    if (closeBtn) {
      document.documentElement.classList.remove('casanova-drawer-open');
      e.preventDefault();
      return;
    }

    // Clicking on a sidebar menu item shows an inline spinner
    var navLink = closest(e.target, '[data-casanova-nav-link]');
    if (navLink) {
      if (isModifiedClick(e) || (navLink.getAttribute('target') === '_blank')) {
        return;
      }
      // No spinner si ya está activo
      if (!navLink.classList.contains('is-active')) {
        navLink.classList.add('is-loading');
      }
      document.documentElement.classList.remove('casanova-drawer-open');
      return;
    }

    // Clicking on an expediente triggers a lightweight loading overlay
    var expLink = closest(e.target, '[data-casanova-expediente-link]');
    if (expLink) {
      if (isModifiedClick(e) || (expLink.getAttribute('target') === '_blank')) {
        return; // dejamos navegar sin overlay
      }

      var target = getDetailContainer();
      if (target) {
        scrollDetailIntoView(target);
        target.classList.add('is-loading');
      }

      // En móvil, cerramos el drawer antes de navegar
      document.documentElement.classList.remove('casanova-drawer-open');

      // Dejamos que el navegador navegue normal
      return;
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.classList.remove('casanova-drawer-open');

    var detail = document.querySelector('.casanova-detail');
    if (detail) detail.classList.remove('is-loading');

    var portal = document.querySelector('.casanova-portal');
    if (portal) portal.classList.remove('is-loading');
  });

  // Si el navegador usa bfcache (volver atrás), reseteamos el estado.
  window.addEventListener('pageshow', function () {
    document.documentElement.classList.remove('casanova-drawer-open');
    var detail = document.querySelector('.casanova-detail');
    if (detail) detail.classList.remove('is-loading');
    var portal = document.querySelector('.casanova-portal');
    if (portal) portal.classList.remove('is-loading');
  });

})();

document.addEventListener('DOMContentLoaded', function () {

  // Solo formularios del portal / perfil
  const forms = document.querySelectorAll(
    'form[action*="casanova_update_address"], .casanova-portal form'
  );

  forms.forEach(function (form) {
    form.addEventListener('submit', function () {

      const btn =
        form.querySelector('.casanova-btn-submit') ||
        form.querySelector('button[type="submit"], input[type="submit"]');

      if (!btn) return;

      btn.classList.add('is-loading');
      btn.disabled = true;

      const label = btn.querySelector('.label');
      if (label) label.textContent = (window.casanovaPortalI18n && casanovaPortalI18n.saving) ? casanovaPortalI18n.saving : 'Guardando…';
    });
  });

});

// === Persistencia de Bricks Nestable Tabs en detalle de expediente ===
// Para activar: asigna al bloque de Tabs (contenedor) el ID: casanova-exp-tabs
(function () {
  const WRAP_ID = "casanova-exp-tabs";
  const STORAGE_KEY = "casanova_active_tab_" + WRAP_ID;

  function wrap(){ return document.getElementById(WRAP_ID); }
  function buttons(w){ return w ? Array.from(w.querySelectorAll('[role="tab"]')) : []; }
  function tabId(btn){
    if (!btn) return "";
    return btn.getAttribute("aria-controls")
      || (btn.getAttribute("href") || "").replace("#","")
      || "";
  }
  function activateById(id){
    const w = wrap(); if (!w || !id) return;
    const btn = buttons(w).find(b => tabId(b) === id);
    if (btn) btn.click();
  }

  document.addEventListener("DOMContentLoaded", function(){
    const w = wrap(); if (!w) return;
    const fromHash = (location.hash || "").replace("#","").trim();
    const fromStore = sessionStorage.getItem(STORAGE_KEY) || "";
    if (fromHash) activateById(fromHash);
    else if (fromStore) activateById(fromStore);
  });

  document.addEventListener("click", function(e){
    const w = wrap(); if (!w) return;
    const btn = e.target.closest('#' + WRAP_ID + ' [role="tab"]');
    if (!btn) return;
    const id = tabId(btn); if (!id) return;
    sessionStorage.setItem(STORAGE_KEY, id);
    if (history && history.replaceState) history.replaceState(null, "", "#" + id);
    else location.hash = id;
  });

  // Cuando el usuario pincha un expediente (recarga por ?expediente=...), arrastramos el hash del tab activo
  document.addEventListener("click", function(e){
    const a = e.target.closest('a[href]'); if (!a) return;
    const href = a.getAttribute("href") || "";
    if (!href || href.includes("#")) return;
    if (!href.includes("expediente=")) return;

    const id = (location.hash || "").replace("#","").trim()
      || sessionStorage.getItem(STORAGE_KEY) || "";
    if (!id) return;
    a.setAttribute("href", href + "#" + id);
  }, true);
})();
