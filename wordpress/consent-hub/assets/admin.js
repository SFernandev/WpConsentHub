(function () {
  'use strict';

  /* ── Constants ── */

  var LS_KEY      = 'ch_admin_section';
  var DEFAULT_SEC = 'dashboard';
  var FADE_DELAY  = 16; // ms — one rAF tick is enough for the browser to paint display:block before adding the class

  /* ── DOM references (resolved on DOMContentLoaded) ── */

  var navItems;
  var sections;
  var saveRow;

  /* ── Core: activate a section ── */

  function activateSection(target) {
    var found = false;

    // Validate target exists as a real section; fall back to default
    for (var i = 0; i < sections.length; i++) {
      if (sections[i].getAttribute('data-section') === target) {
        found = true;
        break;
      }
    }
    if (!found) target = DEFAULT_SEC;

    // Update nav items
    for (var j = 0; j < navItems.length; j++) {
      var item = navItems[j];
      if (item.getAttribute('data-section') === target) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    }

    // Update sections with fade-in transition
    for (var k = 0; k < sections.length; k++) {
      var sec = sections[k];
      if (sec.getAttribute('data-section') === target) {
        sec.style.display = 'block';
        // Defer adding the visible class so the browser registers the display
        // change first, allowing the CSS opacity transition to fire
        (function (el) {
          setTimeout(function () {
            el.classList.add('ch-visible');
          }, FADE_DELAY);
        }(sec));
      } else {
        sec.classList.remove('ch-visible');
        sec.style.display = 'none';
      }
    }

    // Show / hide the save button row
    if (saveRow) {
      saveRow.style.display = (target === DEFAULT_SEC) ? 'none' : '';
    }

    // Persist
    try {
      localStorage.setItem(LS_KEY, target);
    } catch (e) { /* quota or private mode — silently ignore */ }

    // Update URL hash without triggering a scroll or reload
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', '#' + target);
    }
  }

  /* ── Resolve the initial section to show ── */

  function resolveInitialSection() {
    var hash = window.location.hash.replace('#', '').trim();

    // After a WordPress settings-updated redirect the hash is preserved in the
    // URL if the browser kept it, but the user may also have landed without one.
    // Priority: URL hash → localStorage fallback → hard default.
    if (hash) return hash;

    try {
      var stored = localStorage.getItem(LS_KEY);
      if (stored) return stored;
    } catch (e) { /* ignore */ }

    return DEFAULT_SEC;
  }

  /* ── Click handler ── */

  function onNavClick(e) {
    e.preventDefault();
    var section = this.getAttribute('data-section');
    if (section) activateSection(section);
  }

  /* ── Boot ── */

  function init() {
    navItems = document.querySelectorAll('.ch-nav-item[data-section]');
    sections = document.querySelectorAll('.ch-section[data-section]');
    saveRow  = document.querySelector('.ch-save-row');

    // Nothing to manage if there are no nav items or sections
    if (!navItems.length || !sections.length) return;

    // Attach click listeners
    for (var i = 0; i < navItems.length; i++) {
      navItems[i].addEventListener('click', onNavClick);
    }

    // Activate the correct section on load
    activateSection(resolveInitialSection());
  }

  /* ── Entry point ── */

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

}());
