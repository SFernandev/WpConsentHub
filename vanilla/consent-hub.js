/**
 * ConsentHub v1.3.0
 * Motor de consentimiento de cookies universal.
 * Zero dependencias · CSS nativo externo · Compatible con cualquier web.
 * Google Consent Mode v2 · Script blocker · Geolocalización.
 *
 * Requiere: consent-hub.css
 *
 * API:
 *   ConsentHub.init(config)
 *   ConsentHub.hasConsent(category)
 *   ConsentHub.getConsent()
 *   ConsentHub.showBanner()
 *   ConsentHub.showPreferences()
 *   ConsentHub.reset()
 *   ConsentHub.on(event, fn)
 *   ConsentHub.off(event, fn)
 */
;(function (root) {
  'use strict';

  var VERSION = '1.4.0';

  var defaults = {
    categories: {
      analytics: { label: 'Analítica', description: 'Cookies de medición y estadísticas del sitio.', default: false },
      marketing: { label: 'Marketing', description: 'Cookies para campañas publicitarias y remarketing.', default: false },
      preferences: { label: 'Preferencias', description: 'Cookies que recuerdan ajustes como idioma o región.', default: false }
    },
    texts: {
      banner: {
        title: 'Este sitio utiliza cookies',
        description: 'Usamos cookies para mejorar tu experiencia, analizar el tráfico y personalizar contenido. Puedes aceptar todas, rechazarlas o configurar tus preferencias.',
        acceptAll: 'Aceptar todas',
        rejectAll: 'Rechazar todas',
        customize: 'Configurar'
      },
      preferences: {
        title: 'Preferencias de cookies',
        description: 'Selecciona qué categorías de cookies deseas permitir. Las cookies funcionales son siempre necesarias.',
        functional: 'Funcionales',
        functionalDesc: 'Necesarias para el funcionamiento básico del sitio. Siempre activas.',
        save: 'Guardar preferencias',
        acceptAll: 'Aceptar todas'
      },
      revisit: 'Cookies'
    },
    position: 'bottom',
    theme: {
      primary: '#1a1a1a',
      primaryText: '#ffffff',
      background: '#ffffff',
      text: '#1a1a1a',
      textSecondary: '#555555',
      border: '#e0e0e0',
      toggleOn: '#1a1a1a',
      toggleOff: '#cccccc',
      radius: '12px'
    },
    gcm: {
      enabled: false,
      mode: 'advanced',           // 'advanced' | 'basic'
      urlPassthrough: true,
      adsDataRedaction: true,
      waitForUpdate: 500,
      categoryMap: {
        analytics_storage: 'analytics',
        ad_storage: 'marketing',
        ad_user_data: 'marketing',
        ad_personalization: 'marketing'
      }
    },
    blocker: {
      enabled: false,
      patterns: {
        analytics: [
          'googletagmanager.com/gtm.js',
          'google-analytics.com/analytics.js',
          'googletagmanager.com/gtag/js',
          'static.hotjar.com',
          'clarity.ms/tag',
          'plausible.io',
          'cdn.mxpnl.com'
        ],
        marketing: [
          'connect.facebook.net',
          'snap.licdn.com',
          'ads.linkedin.com',
          'googleadservices.com/pagead',
          'googlesyndication.com',
          'doubleclick.net',
          'tiktok.com/i18n/pixel',
          'ads-twitter.com',
          'pinterest.com/ct.html'
        ],
        preferences: []
      }
    },
    cookieName: 'ch_consent',
    cookieDays: 365,
    cookieDomain: '',
    geo: {
      enabled: false,
      region: '',                 // 'eu' | 'ccpa' | 'other' | '' (auto-detect)
      rules: {
        eu:    'optin',           // Show banner, require explicit consent
        ccpa:  'optout',          // Show banner with opt-out, allow by default
        other: 'hide'             // 'hide' = no banner | 'optin' | 'optout'
      }
    },
    logging: {
      enabled: false,
      endpoint: '/wp-admin/admin-ajax.php',
      nonce: ''
    },
    onConsent: null,
    autoShow: true
  };

  var config = {}, consent = null, listeners = {}, els = {};

  function merge(t, s) {
    var r = {};
    for (var k in t) {
      if (t.hasOwnProperty(k)) {
        if (s && s.hasOwnProperty(k) && typeof t[k] === 'object' && t[k] !== null && !Array.isArray(t[k]))
          r[k] = merge(t[k], s[k]);
        else r[k] = (s && s.hasOwnProperty(k)) ? s[k] : t[k];
      }
    }
    if (s) { for (var j in s) { if (s.hasOwnProperty(j) && !t.hasOwnProperty(j)) r[j] = s[j]; } }
    return r;
  }

  function setCookie(n, v, d, dm) {
    var dt = new Date(); dt.setTime(dt.getTime() + (d * 864e5));
    var p = n + '=' + encodeURIComponent(JSON.stringify(v)) + ';expires=' + dt.toUTCString() + ';path=/;SameSite=Lax';
    if (dm) p += ';domain=' + dm;
    document.cookie = p;
  }

  function getCookie(n) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + n + '=([^;]*)'));
    if (!m) return null;
    try { return JSON.parse(decodeURIComponent(m[1])); } catch (e) { return null; }
  }

  function deleteCookie(n, dm) {
    document.cookie = n + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax' + (dm ? ';domain=' + dm : '');
  }

  function emit(type, detail) {
    if (listeners[type]) {
      for (var i = 0; i < listeners[type].length; i++) { try { listeners[type][i](detail); } catch (e) {} }
    }
    try { document.dispatchEvent(new CustomEvent('consenthub:' + type, { detail: detail })); } catch (e) {}
  }

  function h(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      for (var k in attrs) {
        if (k === 'events') { for (var e in attrs[k]) node.addEventListener(e, attrs[k][e]); }
        else node.setAttribute(k, attrs[k]);
      }
    }
    if (children) {
      if (typeof children === 'string') node.textContent = children;
      else if (Array.isArray(children)) {
        for (var i = 0; i < children.length; i++) {
          if (children[i]) {
            if (typeof children[i] === 'string') node.appendChild(document.createTextNode(children[i]));
            else node.appendChild(children[i]);
          }
        }
      }
    }
    return node;
  }

  function raf(fn) { requestAnimationFrame(function () { requestAnimationFrame(fn); }); }
  function rm(el) { if (el && el.parentNode) el.parentNode.removeChild(el); }

  function applyTheme() {
    var t = config.theme, r = document.documentElement;
    r.style.setProperty('--ch-primary', t.primary);
    r.style.setProperty('--ch-primary-text', t.primaryText);
    r.style.setProperty('--ch-bg', t.background);
    r.style.setProperty('--ch-text', t.text);
    r.style.setProperty('--ch-text-secondary', t.textSecondary);
    r.style.setProperty('--ch-border', t.border);
    r.style.setProperty('--ch-toggle-on', t.toggleOn);
    r.style.setProperty('--ch-toggle-off', t.toggleOff);
    r.style.setProperty('--ch-radius', t.radius);
  }

  /* ── Google Consent Mode v2 ── */

  function gcmInit() {
    if (!config.gcm.enabled) return;

    root.dataLayer = root.dataLayer || [];
    if (typeof root.gtag !== 'function') {
      root.gtag = function () { root.dataLayer.push(arguments); };
    }

    var state = gcmBuildState(consent);

    if (config.gcm.waitForUpdate > 0) {
      state.wait_for_update = config.gcm.waitForUpdate;
    }

    root.gtag('consent', 'default', state);

    if (config.gcm.urlPassthrough) {
      root.gtag('set', 'url_passthrough', true);
    }
    if (config.gcm.adsDataRedaction) {
      root.gtag('set', 'ads_data_redaction', true);
    }

    emit('gcm:default', state);
  }

  function gcmUpdate() {
    if (!config.gcm.enabled) return;
    if (typeof root.gtag !== 'function') return;

    var state = gcmBuildState(consent);
    root.gtag('consent', 'update', state);

    root.dataLayer = root.dataLayer || [];
    root.dataLayer.push({ event: 'consent_update', consent_state: state });

    emit('gcm:update', state);
  }

  function gcmBuildState(consentData) {
    var map = config.gcm.categoryMap;
    var params = ['analytics_storage', 'ad_storage', 'ad_user_data', 'ad_personalization'];
    var state = {};

    for (var i = 0; i < params.length; i++) {
      var cat = map[params[i]];
      state[params[i]] = (consentData && consentData.categories && consentData.categories[cat]) ? 'granted' : 'denied';
    }

    state.functionality_storage = 'granted';
    state.security_storage = 'granted';

    return state;
  }

  /* ── Banner ── */

  function renderBanner() {
    if (els.banner) return;
    var txt = config.texts.banner;
    els.banner = h('div', { id: 'ch-banner', class: 'ch-' + config.position }, [
      h('div', { class: 'ch-box', role: 'dialog', 'aria-label': txt.title }, [
        h('h3', { class: 'ch-t' }, '🍪 ' + txt.title),
        h('p', { class: 'ch-d' }, txt.description),
        h('div', { class: 'ch-btns' }, [
          h('button', { class: 'ch-btn ch-bp', events: { click: acceptAll } }, txt.acceptAll),
          h('button', { class: 'ch-btn ch-bs', events: { click: rejectAll } }, txt.rejectAll),
          h('button', { class: 'ch-btn ch-bs', events: { click: openPrefs } }, txt.customize)
        ])
      ])
    ]);
    document.body.appendChild(els.banner);
    if (config.position === 'center') {
      els.overlay = h('div', { id: 'ch-overlay' });
      document.body.appendChild(els.overlay);
    }
    raf(function () {
      if (els.banner) els.banner.classList.add('ch-v');
      if (els.overlay) els.overlay.classList.add('ch-v');
    });
    emit('banner:show');
  }

  function hideBanner() {
    if (!els.banner) return;
    els.banner.classList.remove('ch-v');
    if (els.overlay) els.overlay.classList.remove('ch-v');
    setTimeout(function () { rm(els.banner); rm(els.overlay); els.banner = els.overlay = null; }, 350);
  }

  function catRow(label, desc, key, checked, disabled) {
    var inp = h('input', { type: 'checkbox', 'data-category': key, 'aria-label': label });
    if (checked) inp.checked = true;
    if (disabled) inp.disabled = true;
    return h('div', { class: 'ch-cat' }, [
      h('div', { class: 'ch-ci' }, [h('p', { class: 'ch-cl' }, label), h('p', { class: 'ch-cd' }, desc)]),
      h('label', { class: 'ch-tg' }, [inp, h('span', { class: 'ch-tr' }), h('span', { class: 'ch-tk' })])
    ]);
  }

  function renderPrefs() {
    if (els.prefs) return;
    var txt = config.texts.preferences, cats = config.categories, items = [];
    items.push(catRow(txt.functional, txt.functionalDesc, 'functional', true, true));
    for (var key in cats) {
      if (cats.hasOwnProperty(key)) {
        items.push(catRow(cats[key].label, cats[key].description, key,
          consent ? consent.categories[key] : cats[key].default, false));
      }
    }
    var inner = h('div', { class: 'ch-pbox', role: 'dialog', 'aria-label': txt.title, 'aria-modal': 'true' },
      [h('h3', { class: 'ch-t' }, '🍪 ' + txt.title), h('p', { class: 'ch-d' }, txt.description)]
        .concat(items)
        .concat([h('div', { class: 'ch-pbtns' }, [
          h('button', { class: 'ch-btn ch-bp', events: { click: savePrefs } }, txt.save),
          h('button', { class: 'ch-btn ch-bs', events: { click: acceptAllPrefs } }, txt.acceptAll)
        ])])
    );
    els.prefsOv = h('div', { id: 'ch-overlay', events: { click: hidePrefs } });
    els.prefs = h('div', { id: 'ch-prefs' }, [inner]);
    document.body.appendChild(els.prefsOv);
    document.body.appendChild(els.prefs);
    raf(function () {
      if (els.prefs) els.prefs.classList.add('ch-v');
      if (els.prefsOv) els.prefsOv.classList.add('ch-v');
    });
    emit('preferences:show');
  }

  function hidePrefs() {
    if (!els.prefs) return;
    els.prefs.classList.remove('ch-v');
    if (els.prefsOv) els.prefsOv.classList.remove('ch-v');
    setTimeout(function () { rm(els.prefs); rm(els.prefsOv); els.prefs = els.prefsOv = null; }, 350);
  }

  function savePrefs() {
    var categories = {}, inputs = document.querySelectorAll('#ch-prefs input[data-category]');
    for (var i = 0; i < inputs.length; i++) {
      var cat = inputs[i].getAttribute('data-category');
      if (cat !== 'functional') categories[cat] = inputs[i].checked;
    }
    saveConsent(categories); hidePrefs();
  }

  function acceptAllPrefs() {
    var c = {}; for (var k in config.categories) { if (config.categories.hasOwnProperty(k)) c[k] = true; }
    saveConsent(c); hidePrefs();
  }

  function renderRevisit() {
    if (els.revisit) return;
    els.revisit = h('button', { id: 'ch-revisit', 'aria-label': config.texts.revisit,
      events: { click: openPrefs } }, ['\uD83C\uDF6A ', h('span', { class: 'ch-revisit-text' }, config.texts.revisit)]);
    document.body.appendChild(els.revisit);
  }

  function showRevisit() {
    if (!els.revisit) renderRevisit();
    raf(function () { if (els.revisit) els.revisit.classList.add('ch-v'); });
  }

  function hideRevisit() { if (els.revisit) els.revisit.classList.remove('ch-v'); }

  function acceptAll() {
    var c = {}; for (var k in config.categories) { if (config.categories.hasOwnProperty(k)) c[k] = true; }
    saveConsent(c);
  }

  function rejectAll() {
    var c = {}; for (var k in config.categories) { if (config.categories.hasOwnProperty(k)) c[k] = false; }
    saveConsent(c);
  }

  function saveConsent(categories) {
    consent = { categories: categories, timestamp: new Date().toISOString(), version: VERSION };
    setCookie(config.cookieName, consent, config.cookieDays, config.cookieDomain);
    hideBanner(); showRevisit(); applyConsent(); gcmUpdate();
    emit('consent', consent);
    if (typeof config.onConsent === 'function') config.onConsent(consent);

    // Log consent (fire and forget)
    logConsent(categories);
  }

  function logConsent(categories) {
    if (!config.logging.enabled || typeof fetch === 'undefined') return;

    // Determine consent type
    var type = 'partial';
    var allAccepted = true, allRejected = true;
    for (var k in categories) {
      if (categories.hasOwnProperty(k)) {
        if (categories[k]) allRejected = false;
        else allAccepted = false;
      }
    }
    if (allAccepted) type = 'accepted';
    else if (allRejected) type = 'rejected';

    var payload = {
      action: 'ch_log_consent',
      type: type,
      categories: JSON.stringify(Object.keys(categories).filter(function(k) { return categories[k]; })),
      region: config.geo && config.geo.region ? config.geo.region : 'other',
      nonce: config.logging.nonce
    };

    var body = Object.keys(payload).map(function(k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]);
    }).join('&');

    // Don't block UI
    setTimeout(function() {
      fetch(config.logging.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body,
        credentials: 'same-origin'
      }).catch(function() {});
    }, 0);
  }

  function applyConsent() {
    if (!consent) return;
    for (var key in consent.categories) {
      if (consent.categories.hasOwnProperty(key) && consent.categories[key]) unblockScripts(key);
    }
  }

  function unblockScripts(category) {
    var scripts = document.querySelectorAll('script[type="text/plain"][data-consent="' + category + '"]');
    for (var i = 0; i < scripts.length; i++) {
      var orig = scripts[i], nu = document.createElement('script');
      if (orig.src) nu.src = orig.src; else nu.textContent = orig.textContent;
      for (var j = 0; j < orig.attributes.length; j++) {
        var a = orig.attributes[j].name;
        if (a !== 'type' && a !== 'data-consent') nu.setAttribute(a, orig.attributes[j].value);
      }
      orig.parentNode.replaceChild(nu, orig);
    }
    emit('scripts:unblocked', { category: category });

    // Also unblock iframes for this category
    var iframes = document.querySelectorAll('iframe[data-consent="' + category + '"][data-src]');
    for (var k = 0; k < iframes.length; k++) {
      iframes[k].src = iframes[k].getAttribute('data-src');
      iframes[k].removeAttribute('data-src');
    }
  }

  /* ── Intelligent script blocker (MutationObserver) ── */

  var observer = null;
  var blockedQueue = [];

  function matchPattern(src, patterns) {
    if (!src) return false;
    for (var i = 0; i < patterns.length; i++) {
      if (src.indexOf(patterns[i]) !== -1) return true;
    }
    return false;
  }

  function categorizeScript(src) {
    if (!src || !config.blocker.enabled) return null;
    var pats = config.blocker.patterns;
    for (var cat in pats) {
      if (pats.hasOwnProperty(cat) && matchPattern(src, pats[cat])) return cat;
    }
    return null;
  }

  function interceptNode(node) {
    // Handle scripts
    if (node.tagName === 'SCRIPT') {
      // Skip if already managed by data-consent
      if (node.getAttribute('data-consent')) return;
      // Skip inline scripts without src
      if (!node.src) return;

      var cat = categorizeScript(node.src);
      if (!cat) return;

      // If consent already granted, let it through
      if (consent && consent.categories && consent.categories[cat]) return;

      // Block: replace with inert placeholder
      var placeholder = document.createElement('script');
      placeholder.type = 'text/plain';
      placeholder.setAttribute('data-consent', cat);
      placeholder.setAttribute('data-blocked-src', node.src);
      placeholder.src = '';

      // Copy non-critical attributes
      for (var i = 0; i < node.attributes.length; i++) {
        var a = node.attributes[i].name;
        if (a !== 'src' && a !== 'type' && a !== 'data-consent' && a !== 'data-blocked-src') {
          placeholder.setAttribute(a, node.attributes[i].value);
        }
      }

      if (node.parentNode) {
        node.parentNode.replaceChild(placeholder, node);
      }

      blockedQueue.push({ category: cat, src: node.src, node: placeholder });
      emit('blocker:blocked', { category: cat, src: node.src });
      return;
    }

    // Handle iframes (YouTube, Facebook, etc.)
    if (node.tagName === 'IFRAME') {
      if (node.getAttribute('data-consent')) return;
      var iframeSrc = node.src || node.getAttribute('data-src');
      if (!iframeSrc) return;

      var iCat = categorizeScript(iframeSrc);
      if (!iCat) return;
      if (consent && consent.categories && consent.categories[iCat]) return;

      node.setAttribute('data-src', iframeSrc);
      node.setAttribute('data-consent', iCat);
      node.removeAttribute('src');
      emit('blocker:blocked', { category: iCat, src: iframeSrc, type: 'iframe' });
    }
  }

  function blockerInit() {
    if (!config.blocker.enabled) return;
    if (typeof MutationObserver === 'undefined') return;

    observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var node = added[j];
          if (node.nodeType !== 1) continue;
          interceptNode(node);
          // Also check children (e.g. a div with scripts inside)
          if (node.querySelectorAll) {
            var nested = node.querySelectorAll('script[src], iframe[src]');
            for (var k = 0; k < nested.length; k++) interceptNode(nested[k]);
          }
        }
      }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
    emit('blocker:init', { patterns: Object.keys(config.blocker.patterns) });
  }

  function flushBlockedQueue(category) {
    var remaining = [];
    for (var i = 0; i < blockedQueue.length; i++) {
      var item = blockedQueue[i];
      if (item.category === category) {
        // Re-inject as real script
        var nu = document.createElement('script');
        nu.src = item.src;
        var placeholder = item.node;
        for (var j = 0; j < placeholder.attributes.length; j++) {
          var a = placeholder.attributes[j].name;
          if (a !== 'type' && a !== 'data-consent' && a !== 'data-blocked-src' && a !== 'src') {
            nu.setAttribute(a, placeholder.attributes[j].value);
          }
        }
        if (placeholder.parentNode) {
          placeholder.parentNode.replaceChild(nu, placeholder);
        }
        emit('blocker:released', { category: category, src: item.src });
      } else {
        remaining.push(item);
      }
    }
    blockedQueue = remaining;
  }

  // Patch applyConsent to also flush blocked queue
  var _origApplyConsent = applyConsent;
  applyConsent = function () {
    _origApplyConsent();
    if (!consent || !config.blocker.enabled) return;
    for (var key in consent.categories) {
      if (consent.categories.hasOwnProperty(key) && consent.categories[key]) {
        flushBlockedQueue(key);
      }
    }
  };

  function openPrefs() { hideBanner(); hideRevisit(); renderPrefs(); }

  /* ── Geo-based consent behavior ── */

  var geoRegion = '';

  function getGeoRule() {
    if (!config.geo.enabled) return 'optin';
    var region = config.geo.region || 'other';
    geoRegion = region;
    var rule = config.geo.rules[region];
    return rule || 'optin';
  }

  function geoAutoConsent(rule) {
    // For opt-out and hide: grant all by default
    if (rule === 'optout' || rule === 'hide') {
      var c = {};
      for (var k in config.categories) {
        if (config.categories.hasOwnProperty(k)) c[k] = true;
      }
      consent = { categories: c, timestamp: new Date().toISOString(), version: VERSION, geo: geoRegion, auto: true };
      setCookie(config.cookieName, consent, config.cookieDays, config.cookieDomain);
      applyConsent(); gcmUpdate();
      emit('geo:auto', { region: geoRegion, rule: rule, consent: consent });
      logConsent(c);
      return true;
    }
    return false;
  }

  root.ConsentHub = {
    init: function (userConfig) {
      config = merge(defaults, userConfig || {});
      applyTheme();
      consent = getCookie(config.cookieName);
      gcmInit();
      blockerInit();

      function run() {
        if (consent && consent.version === VERSION) {
          // Existing consent — apply and show revisit
          applyConsent(); gcmUpdate(); showRevisit();
          emit('consent:existing', consent);
        } else {
          // No consent yet — check geo rules
          var rule = getGeoRule();
          emit('geo:detected', { region: geoRegion, rule: rule });

          if (rule === 'hide') {
            // No banner, grant all silently
            geoAutoConsent(rule);
          } else if (rule === 'optout') {
            // Grant all by default, but show banner with opt-out option
            geoAutoConsent(rule);
            if (config.autoShow) renderBanner();
            showRevisit();
          } else {
            // opt-in (default): show banner, deny until explicit consent
            if (config.autoShow) renderBanner();
          }
        }
      }

      if (document.body) run();
      else document.addEventListener('DOMContentLoaded', run);
    },
    hasConsent: function (cat) {
      if (!consent) return false;
      if (cat === 'functional') return true;
      return consent.categories[cat] === true;
    },
    getConsent: function () { return consent ? JSON.parse(JSON.stringify(consent)) : null; },
    showBanner: function () { hideRevisit(); renderBanner(); },
    showPreferences: openPrefs,
    reset: function () {
      deleteCookie(config.cookieName, config.cookieDomain);
      consent = null; hideBanner(); hidePrefs(); hideRevisit(); gcmUpdate();
      setTimeout(renderBanner, 400);
      emit('consent:reset');
    },
    on: function (evt, fn) { if (!listeners[evt]) listeners[evt] = []; listeners[evt].push(fn); },
    off: function (evt, fn) { if (!listeners[evt]) return; listeners[evt] = listeners[evt].filter(function (f) { return f !== fn; }); },
    getRegion: function () { return geoRegion; },
    version: VERSION
  };

})(typeof window !== 'undefined' ? window : this);
