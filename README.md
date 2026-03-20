# ConsentHub

**Open-source, self-hosted cookie consent manager** for WordPress and static websites. Zero external dependencies, no SaaS, full GDPR compliance.

![Version](https://img.shields.io/badge/version-1.4.0--beta-blue)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple)

---

## Features

✅ **Universal Consent Engine**
- Banner + Preference Center with customizable categories
- Accept All / Reject All / Customize options
- Cookie persistence (first-party, SameSite=Lax)
- Zero dependencies, vanilla ES5 JavaScript

✅ **Google Consent Mode v2**
- Full GCM v2 support (advanced & basic modes)
- 4 required parameters: analytics_storage, ad_storage, ad_user_data, ad_personalization
- URL passthrough & ads data redaction
- Configurable category mapping

✅ **Intelligent Script Blocker**
- MutationObserver watches for injected scripts
- Pre-configured patterns: GTM, GA4, Hotjar, Clarity, Facebook Pixel, LinkedIn, TikTok, etc.
- Blocks & queues scripts until consent given
- Also blocks iframes (YouTube, Facebook, etc.)

✅ **Geolocation & Regional Rules**
- Auto-detect region via CDN headers (Cloudflare, Vercel, CloudFront, Sucuri, etc.)
- Regional rules: EU (opt-in), CCPA (opt-out), Other (hide/show)
- Manual override option

✅ **Dashboard & Analytics** *(v1.4.0-beta)*
- Consent metrics: accepted, rejected, partial
- 7-day trend chart (Chart.js)
- Local database logging (no SaaS)
- IP & User-Agent hashing (non-reversible)

---

## Installation

### WordPress Plugin

1. Download `consent-hub-wp.zip` from [Releases](https://github.com/sfernandev/consent-hub/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Select `consent-hub-wp.zip` and activate
4. Configure in **Settings → ConsentHub**

### Static Website / Non-WordPress

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="consent-hub.css">
</head>
<body>
    <script src="consent-hub.min.js"></script>
    <script>
        ConsentHub.init({
            categories: {
                analytics: { label: 'Analytics', description: 'Usage statistics' },
                marketing: { label: 'Marketing', description: 'Ad campaigns' },
                preferences: { label: 'Preferences', description: 'Remember your choices' }
            },
            texts: {
                banner: {
                    title: 'We use cookies',
                    description: 'This site uses cookies for analytics and marketing.',
                    acceptAll: 'Accept All',
                    rejectAll: 'Reject All',
                    customize: 'Customize'
                }
            },
            gcm: {
                enabled: true,
                mode: 'advanced'
            }
        });
    </script>
</body>
</html>
```

---

## Configuration

### WordPress Admin Panel

Navigate to **Settings → ConsentHub** to configure:

- **Banner** — Position, text, button labels
- **Categories** — Add/rename consent categories
- **Styling** — Colors, borders, border radius
- **Google Consent Mode** — Enable, mode, URL passthrough, ads redaction
- **Script Blocker** — Enable, add custom patterns
- **Geolocation** — Enable, set regional rules
- **Dashboard** — Enable logging, set retention

### JavaScript API

```javascript
// Initialize
ConsentHub.init(config);

// Check consent
ConsentHub.hasConsent('analytics');  // true/false
ConsentHub.getConsent();             // { categories: {...}, timestamp: '...' }

// Show UI
ConsentHub.showBanner();
ConsentHub.showPreferences();

// Clear consent
ConsentHub.reset();

// Listen for events
ConsentHub.on('consent', function(data) {
    console.log('User gave consent:', data);
});
```

---

## Architecture

```
ConsentHub = Consent Engine (JS) + Adapters per Platform

Layer 1: Consent Engine (JS vanilla)
  • consent-hub.js — Universal motor (zero dependencies)
  • consent-hub.css — Styling (CSS custom properties)

Layer 2: Platform Adapters
  • WordPress plugin (PHP) — WP Settings API, admin panel
  • HTML/JS (vanilla) — for static sites
  • React/Next/Vue — future npm package
```

### Database (WordPress)

Table: `wp_ch_consent_log` (created on activation)

| Column | Type | Purpose |
|--------|------|---------|
| id | BIGINT | Primary key |
| consent_type | VARCHAR(20) | accepted \| rejected \| partial |
| categories | JSON | Selected categories |
| geo_region | VARCHAR(10) | eu \| ccpa \| other |
| ip_hash | VARCHAR(64) | SHA256 of masked IP (non-reversible) |
| user_agent_hash | VARCHAR(64) | SHA256 of masked User-Agent |
| created_at | DATETIME | UTC timestamp |

---

## Performance

| Metric | Value |
|--------|-------|
| JS File Size | 24KB source / 12KB minified / 4.3KB gzipped |
| CSS Size | 6.3KB / 1.6KB gzipped |
| Network Requests | 1 JS + 1 CSS on first load |
| Logging POST | ~200 bytes (fire-and-forget, non-blocking) |
| Dashboard Load | Admin only, <100ms DB query |

**No external calls in production** (except Google Consent Mode v2 to gtag.js, which is your choice).

---

## Development

### Project Structure

```
consent-hub/
├── wordpress/consent-hub/          # Plugin source
│   ├── consent-hub.php             # Main plugin file
│   ├── includes/
│   │   ├── class-frontend.php      # Asset enqueueing + config
│   │   ├── class-admin.php         # WP admin panel
│   │   ├── class-dashboard.php     # Metrics dashboard
│   │   ├── class-database.php      # Logging table
│   │   ├── class-ajax.php          # Logging endpoint
│   │   ├── class-geo.php           # Geolocation detection
│   │   └── class-wp-consent.php    # WP Consent API bridge
│   └── assets/
│       ├── consent-hub.js          # Main engine
│       ├── consent-hub.css         # Styles
│       ├── dashboard.js            # Chart.js init
│       ├── dashboard.css           # Dashboard styles
│       └── admin.css               # WP admin styles
│
├── consent-hub.js                  # Standalone engine
├── consent-hub.min.js              # Minified version
├── consent-hub.css                 # Standalone styles
├── consent-hub-wp.zip              # WordPress plugin package
└── demo.html                       # Standalone demo
```

### Building

```bash
# Regenerate .zip from source
python3 -c "
import zipfile, os
with zipfile.ZipFile('consent-hub-wp.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk('wordpress/consent-hub'):
        for f in files:
            path = os.path.join(root, f)
            z.write(path, path)
"

# Minify JS (using any JS minifier, e.g., terser, uglify-js)
npx terser consent-hub.js -o consent-hub.min.js -c -m
```

### Requirements

- **PHP:** 7.4 or higher
- **WordPress:** 5.0 or higher (for plugin)
- **Browser:** ES5 compatible (IE11+)
- **Dependencies:** Zero

---

## Security

✅ **No SaaS, no data collection**
- Logs stored in your own database
- IP/User-Agent hashed (non-reversible SHA256)
- No external API calls

✅ **GDPR/CCPA Compliant**
- Explicit consent required (EU)
- Opt-out option (CCPA)
- Consent records persistent
- Right to withdraw/reset

✅ **Code Security**
- No inline JavaScript execution
- Nonce validation for AJAX
- Sanitized/escaped all outputs
- No eval() or dynamic code generation

---

## License

GNU General Public License v2.0 or later — See [LICENSE](LICENSE) file.

Free for commercial use, but you must:
- Keep source code available
- Disclose modifications
- Use compatible license

---

## Contributing

We welcome contributions! Please:

1. Fork this repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'Add feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

### Code Standards

- PHP: PSR-2
- JavaScript: ES5, no transpilation
- CSS: BEM naming, CSS custom properties
- No frameworks, no dependencies

---

## Changelog

### v1.4.0-beta (2026-03-20)
- ✨ Dashboard with consent metrics & 7-day chart
- ✨ Logging system (local BD, non-reversible hashing)
- 🐛 Fixed class-frontend.php syntax error
- 📦 Reorganized folder structure (wordpress/ + standalone)

### v1.3.0 (2026-03-19)
- ✨ Geolocation detection (Cloudflare, Vercel, etc.)
- ✨ Regional rules (EU opt-in, CCPA opt-out, etc.)
- 🐛 Fixed WP Rocket JS delay issue

### v1.2.0
- ✨ Google Consent Mode v2 support
- 🐛 Script blocker MutationObserver improvements

### v1.1.0
- ✨ Intelligent script blocker
- ✨ Pre-configured patterns (GTM, GA4, Hotjar, etc.)

### v1.0.0
- 🎉 Initial release
- ✨ Banner, preference center, category toggles
- ✨ Cookie storage, frontend events API

---

## Support

- 📖 [Documentation](https://github.com/sfernandev/consent-hub/wiki)
- 🐛 [Issues](https://github.com/sfernandev/consent-hub/issues)
- 💬 [Discussions](https://github.com/sfernandev/consent-hub/discussions)

---

