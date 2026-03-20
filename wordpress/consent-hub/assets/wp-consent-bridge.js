/**
 * ConsentHub — WP Consent API Bridge
 * Syncs ConsentHub consent state with the WordPress Consent API.
 */
(function(){
  var map = {
    analytics: 'statistics',
    marketing: 'marketing',
    preferences: 'preferences'
  };

  function sync(consent) {
    if (!consent || !consent.categories || typeof wp_set_consent !== 'function') return;
    for (var cat in map) {
      if (consent.categories.hasOwnProperty(cat)) {
        wp_set_consent(map[cat], consent.categories[cat] ? 'allow' : 'deny');
      }
    }
    wp_set_consent('functional', 'allow');
  }

  document.addEventListener('consenthub:consent', function(e) { sync(e.detail); });
  document.addEventListener('consenthub:consent:existing', function(e) { sync(e.detail); });
  document.addEventListener('consenthub:consent:reset', function() {
    if (typeof wp_set_consent !== 'function') return;
    var cats = ['statistics', 'marketing', 'preferences'];
    for (var i = 0; i < cats.length; i++) wp_set_consent(cats[i], 'deny');
  });
})();
