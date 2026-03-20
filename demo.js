/**
 * ConsentHub Demo — UI logic and event handlers.
 */
(function(){
  var clog = document.getElementById('clog');
  var blockedListEl = document.getElementById('blocked-list');
  var dlCount = 0, blockedItems = [];

  // Get geo from URL param for simulation
  var urlParams = new URLSearchParams(window.location.search);
  var simGeo = urlParams.get('geo') || '';

  function log(e, d, type) {
    var t = new Date();
    var ts = [t.getHours(), t.getMinutes(), t.getSeconds()].map(function(n){ return String(n).padStart(2,'0'); }).join(':');
    var div = document.createElement('div');
    var clsMap = { gcm:'lg', blocker:'lr', geo:'lp' };
    var cls = clsMap[type] || 'le';
    div.innerHTML = '<span class="lt">' + ts + '</span> <span class="' + cls + '">' + e + '</span>' + (d ? ' <span class="ld">' + JSON.stringify(d) + '</span>' : '');
    clog.appendChild(div);
    clog.scrollTop = clog.scrollHeight;
  }

  function updateConsentUI() {
    var cats = ['analytics', 'marketing', 'preferences'];
    var c = ConsentHub.getConsent();
    for (var i = 0; i < cats.length; i++) {
      var el = document.getElementById('st-' + cats[i]);
      if (!c) { el.textContent = 'Pendiente'; el.className = 'vl wt'; }
      else if (c.categories[cats[i]]) { el.textContent = 'Permitido'; el.className = 'vl ok'; }
      else { el.textContent = 'Denegado'; el.className = 'vl no'; }
    }
  }

  function updateGCMUI(state) {
    var map = { analytics_storage:'gcm-as', ad_storage:'gcm-ads', ad_user_data:'gcm-aud', ad_personalization:'gcm-ap' };
    for (var p in map) {
      if (state && state[p]) {
        var el = document.getElementById(map[p]);
        el.textContent = state[p]; el.className = 'state ' + state[p];
      }
    }
  }

  function updateGeoUI(region, rule) {
    var badge = document.getElementById('geo-badge');
    var ruleEl = document.getElementById('geo-rule');
    var behEl = document.getElementById('geo-behavior');
    var sel = document.getElementById('geo-select');

    if (!region) {
      badge.textContent = 'Geo desactivado'; badge.className = 'geo-badge geo-off';
      ruleEl.textContent = 'Regla: optin (default)';
      behEl.textContent = 'Banner visible, todo denegado hasta consentimiento';
    } else if (region === 'eu') {
      badge.textContent = 'UE/EEA'; badge.className = 'geo-badge geo-eu';
      ruleEl.textContent = 'Regla: optin (GDPR)';
      behEl.textContent = 'Banner visible, todo denegado hasta consentimiento';
    } else if (region === 'ccpa') {
      badge.textContent = 'EE.UU. (CCPA)'; badge.className = 'geo-badge geo-ccpa';
      ruleEl.textContent = 'Regla: optout';
      behEl.textContent = 'Todo permitido, banner con opción de rechazar';
    } else {
      badge.textContent = 'Resto del mundo'; badge.className = 'geo-badge geo-other';
      ruleEl.textContent = 'Regla: hide';
      behEl.textContent = 'Sin banner, todo permitido automáticamente';
    }
    sel.value = region;
  }

  function renderBlockedList() {
    if (blockedItems.length === 0) {
      blockedListEl.innerHTML = '<div class="ch-empty-state">Ningún script interceptado.</div>';
      return;
    }
    var html = '';
    for (var i = 0; i < blockedItems.length; i++) {
      var item = blockedItems[i];
      var dotCls = item.released ? 'green' : 'red';
      html += '<div class="blocked-item"><span class="blocked-dot ' + dotCls + '"></span><span class="blocked-src">' + item.src + '</span><span class="blocked-cat ' + (item.released ? 'ok' : 'no') + '">' + (item.released ? 'Liberado' : 'Bloqueado') + '</span></div>';
    }
    blockedListEl.innerHTML = html;
  }

  // Event listeners
  ['banner:show','preferences:show','consent','consent:existing','consent:reset','scripts:unblocked'].forEach(function(evt) {
    document.addEventListener('consenthub:' + evt, function(e) { log(evt, e.detail); updateConsentUI(); });
  });
  ['gcm:default','gcm:update'].forEach(function(evt) {
    document.addEventListener('consenthub:' + evt, function(e) { log(evt, e.detail, 'gcm'); updateGCMUI(e.detail); });
  });
  document.addEventListener('consenthub:blocker:init', function(e) { log('blocker:init', e.detail, 'blocker'); });
  document.addEventListener('consenthub:blocker:blocked', function(e) {
    log('blocker:blocked', e.detail, 'blocker');
    blockedItems.push({ src: e.detail.src, category: e.detail.category, released: false }); renderBlockedList();
  });
  document.addEventListener('consenthub:blocker:released', function(e) {
    log('blocker:released', e.detail, 'blocker');
    for (var i = 0; i < blockedItems.length; i++) { if (blockedItems[i].src === e.detail.src) blockedItems[i].released = true; }
    renderBlockedList();
  });
  document.addEventListener('consenthub:geo:detected', function(e) { log('geo:detected', e.detail, 'geo'); updateGeoUI(e.detail.region, e.detail.rule); });
  document.addEventListener('consenthub:geo:auto', function(e) { log('geo:auto', e.detail, 'geo'); });

  // Monitor dataLayer
  window.dataLayer = window.dataLayer || [];
  var origPush = Array.prototype.push;
  window.dataLayer.push = function() { origPush.apply(this, arguments); dlCount++; document.getElementById('gcm-dl-count').textContent = 'dataLayer: ' + dlCount; };

  // Build config
  var initConfig = {
    position: 'bottom',
    theme: { primary: '#1a1a1a', primaryText: '#ffffff', radius: '12px' },
    gcm: { enabled: true, mode: 'advanced', urlPassthrough: true, adsDataRedaction: true, waitForUpdate: 500 },
    blocker: { enabled: true }
  };

  // Add geo if simulated
  if (simGeo) {
    initConfig.geo = {
      enabled: true,
      region: simGeo,
      rules: { eu: 'optin', ccpa: 'optout', other: 'hide' }
    };
  }

  ConsentHub.init(initConfig);
  log('init', { version: ConsentHub.version, geo: simGeo || 'off' });
  updateConsentUI();
  if (!simGeo) updateGeoUI('', '');

  // Bind event handlers (replacing inline onclick/onchange)
  document.getElementById('geo-select').addEventListener('change', function() {
    reloadWithGeo(this.value);
  });
  document.getElementById('btn-show-banner').addEventListener('click', function() {
    ConsentHub.showBanner();
  });
  document.getElementById('btn-show-prefs').addEventListener('click', function() {
    ConsentHub.showPreferences();
  });
  document.getElementById('btn-reset').addEventListener('click', function() {
    ConsentHub.reset();
  });
  document.getElementById('btn-inject-analytics').addEventListener('click', function() {
    simulateInject('analytics');
  });
  document.getElementById('btn-inject-marketing').addEventListener('click', function() {
    simulateInject('marketing');
  });

  // Expose helpers
  window.simulateInject = function(type) {
    var urls = { analytics: 'https://static.hotjar.com/c/hotjar-999999.js', marketing: 'https://connect.facebook.net/en_US/fbevents.js' };
    var s = document.createElement('script'); s.src = urls[type]; document.body.appendChild(s);
    log('demo:inject', { type: type, src: s.src });
  };

  window.reloadWithGeo = function(region) {
    // Clear consent cookie so geo takes effect
    document.cookie = 'ch_consent=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax';
    var url = window.location.pathname;
    if (region) url += '?geo=' + region;
    window.location.href = url;
  };
})();
