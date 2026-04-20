# MD-Translate

A lightweight WordPress plugin that automatically translates your pages and posts into multiple languages using Google Translate. Includes a customizable language switcher widget, per-language glossary for fine-tuned translations, and cookie-based language persistence.

## Features

- **Automatic Translation** — Translates all page content (including Elementor widgets, nav menus, buttons) without modifying your posts/pages
- **Full-Page Coverage** — Uses output buffering to translate everything in `<body>`, including dynamically-added elements
- **Language Switcher Widget** — Three display styles: list, dropdown, or flags only. Fully customizable with CSS properties
- **Language Persistence** — Remembers user's language choice across sessions via cookie
- **Per-Language Glossary** — Override specific translations for brand names, technical terms, or custom wording
- **Title Translation** — Translates the `<title>` tag for SEO
- **Shortcode Support** — `[md_translate_switcher]` for manual placement with custom options
- **Translation Caching** — Cache layer with configurable TTL to reduce API calls
- **Multiple Providers** — Unofficial Google Translate (no API key) or official Google Cloud Translation API v2
- **Responsive Design** — Switcher widget uses CSS custom properties for theme-independent styling
- **Admin Settings** — Three-tab interface: translation settings, glossary management, and switcher customization

## Requirements

- WordPress 5.4+
- PHP 7.2+
- No external dependencies (uses WordPress HTTP API)

## Installation

1. Download the plugin or clone the repository:
   ```bash
   git clone https://github.com/DiasMazhenov/md-translate.git md-translate
   ```

2. Place the `md-translate` folder in your WordPress plugins directory (`/wp-content/plugins/`)

3. Go to **Plugins** in the WordPress admin and click **Activate** for MD-Translate

4. Navigate to **Settings → MD-Translate** to configure

## Quick Start

### 1. Enable Translation

1. Go to **Settings → MD-Translate → Settings**
2. Select **Source Language** (leave as "Auto" to auto-detect)
3. Check the **Target Languages** you want to support (e.g., English, Russian, German)
4. Click **Save**

### 2. Add the Language Switcher

Choose one of these methods:

**Method A: Widget (Recommended)**
- Go to **Appearance → Widgets**
- Add "MD-Translate Switcher" widget to your sidebar or custom location
- Configure display style, show flags, show language names, etc.

**Method B: Shortcode**
- Add this to any page/post:
  ```
  [md_translate_switcher style="list" show_flags="1" show_names="1"]
  ```

**Method C: Theme Code**
- Add to your template where you want the switcher to appear:
  ```php
  <?php
  if ( class_exists( 'MDT_Widget' ) ) {
      echo MDT_Widget::render( array(
          'style'      => 'list',      // 'list', 'dropdown', 'flags'
          'show_names' => true,
          'show_flags' => true,
          'show_codes' => false,
          'icon_size'  => 20,
      ) );
  }
  ?>
  ```

### 3. Test Translation

1. View any page in a different language by clicking the switcher
2. Verify all text is translated (including headings, buttons, nav menus)
3. Language choice persists when navigating to other pages

## Configuration

### Settings Tab

- **Source Language** — Original language of your content (default: auto-detect)
- **Target Languages** — Which languages to translate to
- **Cache Enabled** — Toggle translation caching (recommended: on)
- **Cache Lifetime** — How long (seconds) to cache translations (default: 24 hours)
- **API Provider** — "Unofficial Google Translate" (no key needed) or "Official Google Cloud API v2" (requires API key)
- **Google API Key** — Required only for official API provider

### Switcher & Widget Customization

**Widget Styles:**
- **List** — Vertical or horizontal list of language options with optional flags
- **Dropdown** — Select menu for compact placement
- **Flags** — Flag emojis only (names/codes hidden)

**Options:**
- Show language names (e.g., "English", "Русский")
- Show flag emojis
- Show language codes (e.g., "EN", "RU")
- Customize font size, padding, colors, border radius, and gaps

### CSS Customization

In **Settings → MD-Translate → Settings**, scroll to **Custom Styling** to customize:

- `--mdt-font-size` — Font size of switcher text
- `--mdt-padding` — Internal padding
- `--mdt-radius` — Border radius
- `--mdt-gap` — Space between items
- `--mdt-color` — Text color
- `--mdt-bg` — Background color
- `--mdt-border-color` — Border color
- `--mdt-active-color` — Active item text color
- `--mdt-active-bg` — Active item background
- `--mdt-hover-color` — Hover text color
- `--mdt-hover-bg` — Hover background
- **Custom CSS** — Write raw CSS for advanced styling

Example:
```css
.mdt-switcher {
  --mdt-font-size: 14px;
  --mdt-padding: 8px 12px;
  --mdt-bg: #f5f5f5;
  --mdt-color: #333;
  --mdt-active-bg: #007cba;
  --mdt-active-color: white;
}
```

### Glossary (Per-Language Overrides)

Override translations for specific words or phrases per language:

1. Go to **Settings → MD-Translate → Glossary**
2. Click **Add New Entry**
3. Enter:
   - **Source Text** — Original word/phrase (e.g., "Mazhenov.kz")
   - **Target Language** — Which language this override applies to
   - **Translated Text** — What it should become (e.g., "Мазенов.кз")
   - **Case Sensitive** — Match exact case only
   - **Whole Word** — Don't match as substring

4. Click **Save** and it will apply to all future translations in that language

**Use Cases:**
- Brand names that should not be translated
- Technical terms with specific terminology
- Names of locations or people
- Company-specific jargon

## Shortcode Reference

```
[md_translate_switcher]
```

**Attributes:**
- `style="list"` — Display style: `list`, `dropdown`, or `flags`
- `show_names="1"` — Show language names (0 = hide)
- `show_flags="1"` — Show flag emojis (0 = hide)
- `show_codes="0"` — Show language codes (0 = hide)
- `icon_size="20"` — Flag emoji size in pixels
- `class="custom-class"` — Add custom CSS class

**Examples:**

Compact dropdown:
```
[md_translate_switcher style="dropdown" show_names="1" show_flags="0"]
```

Flags only:
```
[md_translate_switcher style="flags" show_flags="1" show_names="0"]
```

With custom class:
```
[md_translate_switcher style="list" class="my-switcher"]
```

## How It Works

1. **Detection** — When a visitor lands on your site, the plugin checks if they have a saved language preference (cookie)
2. **Redirection** — If they have a preference, they're transparently redirected to the same page with `?lang=XX` parameter
3. **Output Buffering** — All page output is captured and translated in real-time (no database modifications)
4. **Link Patching** — JavaScript adds `?lang=XX` to internal links so language preference is maintained across navigation
5. **Caching** — Translations are cached to minimize API requests
6. **Glossary** — Custom glossary terms are applied after machine translation for final touches

## Supported Languages

Any language Google Translate supports. Common examples:
- English, Russian, German, French, Spanish
- Chinese (Simplified & Traditional), Japanese, Korean
- Arabic, Hindi, Portuguese, Italian, Polish
- And 100+ more

See Google Translate language list for full support.

## API Providers

### Unofficial Google Translate (Default)
- **Pros:** No API key needed, free
- **Cons:** Rate limited, less reliable, may break if Google changes their service
- **Best for:** Small to medium sites

### Official Google Cloud Translation API v2
- **Pros:** Reliable, support, better performance
- **Cons:** Requires paid Google Cloud account and API key
- **Best for:** High-traffic sites, mission-critical translations

To use the official API:
1. Create a Google Cloud project and enable Translation API v2
2. Create a service account and get your API key
3. In **Settings → MD-Translate**, select "Official Google Cloud API v2"
4. Paste your API key and save

## Troubleshooting

**Issue: Some text isn't translating (e.g., Elementor widgets)**
- Solution: This plugin uses output buffering to catch all text. If text still isn't translating, check browser console for JavaScript errors, or verify the element isn't hidden with CSS `display: none`.

**Issue: Language switcher not appearing**
- Solution: Make sure you've added the widget, shortcode, or theme code. Check **Appearance → Widgets** or your page for `[md_translate_switcher]`.

**Issue: Translations are slow**
- Solution: Enable caching in settings (default: enabled). If using official API, check your API quota and rate limits.

**Issue: Specific words have wrong translations**
- Solution: Use the **Glossary** tab to override translations for those words per language.

**Issue: Title tag not translating**
- Solution: This is intentional in some versions. Update to the latest version (1.2.0+) which translates titles.

## FAQ

**Q: Will translation modify my posts/pages?**
A: No. Translation happens in real-time on page output. Your database is never modified.

**Q: Can I use this on a multisite?**
A: Yes, each site can have its own settings and glossary.

**Q: Does it work with WooCommerce?**
A: Yes, product titles, descriptions, and prices are all translated.

**Q: Can I exclude certain pages/posts from translation?**
A: Not yet. You can use the glossary to control specific terms, but full exclusion isn't built-in.

**Q: What about SEO?**
A: The plugin includes hreflang tags for language variants, helping search engines understand your multi-language setup.

**Q: Can I translate the switcher widget itself?**
A: Yes, language names come from WordPress language data, and you can customize text in the glossary.

## Performance

- **Caching:** Translations are cached per language/text to minimize API calls
- **Output buffering:** Minimal overhead, only translates when language is active
- **Link patching:** JavaScript (MutationObserver) watches for dynamically added links and patches them lazily

Typical page load with translation: +200-500ms (depending on page size and API response time)

## License

GPL-2.0+ (same as WordPress)

## Support

For issues, feature requests, or bug reports, visit the GitHub repository:
https://github.com/DiasMazhenov/md-translate

## Changelog

### 1.2.0
- Full-page output buffering for comprehensive translation coverage
- Title tag translation
- Per-language glossary with regex-based matching
- Cookie-based language persistence
- Three language switcher styles (list, dropdown, flags)
- CSS custom properties for theme-independent styling
- Admin AJAX handlers for glossary management
- Official Google Cloud API v2 support

### 1.1.0
- Initial release
- Basic Google Translate integration
- Language switcher widget
- Simple caching layer

---

**Made with ❤️ by [Mazhenov.kz](https://mazhenov.kz)**
