# LP Consultora — Landing Page

Static marketing landing page for **LP Consultora**, a Human Resources / talent-selection
consultancy based in Córdoba, Argentina.

The site is a single self-contained `index.html` with a Spanish/English language toggle. It has
**no build system and no third-party runtime dependencies** — everything (styles, fonts, icons,
images) is served from this project's own `assets/` folder. It renders correctly offline or if any
external CDN is unavailable.

> This page replaced an earlier React/Vite single-page app. The old compiled bundle
> (`assets/index-*.js`, `assets/index-*.css`, and related images) is no longer referenced and is
> **not** part of the deploy — see "Deploying" below.

---

## Deploying

Upload the **contents of `dist/`** to the web root (so `index.html` sits at the top level and
`assets/` beside it). Paths are absolute (`/assets/…`), so they resolve from the domain root.

- Do a **clean, complete upload** — replace the whole tree rather than patching single files. In
  particular the `assets/fonts/` folder must upload in full (12 `.woff2` files + `fonts.css`); a
  partial upload shows up as `404` on individual font files.
- After uploading, hard-refresh (**Ctrl+Shift+R**) and purge any host/CDN cache.
- `dist/` is ~840 KB total.

`dist/` currently contains:

```
index.html
.htaccess                     woff2/svg MIME types, caching, gzip
.well-known/                  acme-challenge, pki-validation (Let's Encrypt; usually host-managed)
contact.php                   contact form endpoint (+ contact-config.php, created on the server)
lib/PHPMailer/                SMTP library used by contact.php
assets/
  tailwind.css                compiled Tailwind (static)
  fonts/                      fonts.css + f0..f11.woff2 (Inter, Montserrat subsets)
  favicon.svg
  og-image.jpg                1200x630 social share image
  hero.jpg, map.png
  testimonial-adrian.jpg, testimonial-matias.jpg, testimonial-denise.jpg
  logo LP-final-02 1-8ff22ebb.svg   header + drawer logo
  Logo footer-85ce6ab7.svg          footer logo
  clients/                    10 client logo PNGs
```

---

## Contact form

The form in the footer (`#contacto`) posts to `contact.php`, which emails the message via SMTP
(PHPMailer, vendored in `lib/PHPMailer/`) or PHP `mail()` as a fallback. Requires PHP 7.4+ (cPanel
hosting). Antispam: honeypot field, minimum fill time, per-IP rate limit, Origin check.

**One-time setup on the server (cPanel):**

1. Use the existing `lara@lp-consultora.com` mailbox as sender and recipient. It is a **GoDaddy
   Professional Email (Titan)** mailbox, so SMTP is `smtpout.secureserver.net`, 465/`ssl` (per GoDaddy's
   help page for Professional Email powered by Titan), with the full email as username and the same
   password as the webmail. (If it were a cPanel
   mailbox, *Email Accounts → Connect Devices* lists the host, usually `mail.lp-consultora.com`.) If the mailbox is **Microsoft 365** use
   `smtp.office365.com`, 587/`tls` (SMTP AUTH must be enabled for the mailbox); if **Google
   Workspace** use `smtp.gmail.com`, 465/`ssl` with an App Password.
2. Copy `contact-config.sample.php` to `contact-config.php` **on the server** and fill in the
   recipient, sender and SMTP password. This file is git-ignored and blocked by `.htaccess`.
3. Upload `contact.php`, `lib/` and the updated `index.html` and `.htaccess`.
4. Send a test message from the live site and check the inbox (and spam folder).

Form copy lives in the `cf.*` keys of the `I18N` dictionary.

---

## Architecture

| Concern | Approach |
| --- | --- |
| **Markup** | Single `index.html`. Spanish is the raw default so no-JS visitors and crawlers get a valid page. |
| **Styling** | Tailwind CSS **compiled to a static file** (`assets/tailwind.css`). A small custom `<style>` block in the `<head>` holds the brand gradient, blob shapes, and scrollbar-hiding helpers. |
| **Design system** | Material-3 color tokens + custom `spacing`, `fontFamily`, `fontSize`, and `maxWidth` scales, defined in `tailwind.config.js`. Containers cap at `max-w-container` (1280px). |
| **Fonts** | Self-hosted **Inter** (400/600) and **Montserrat** (600/700) via `assets/fonts/fonts.css` (`@font-face` with `unicode-range` subsetting). |
| **Icons** | **Inline SVG `<path>`** per icon (Material Symbols artwork), `fill="currentColor"`, sized via inline `width`/`height`. No icon font, no `<use>`/sprite. |
| **i18n** | Dependency-free. `data-i18n` / `data-i18n-html` / `data-i18n-aria` attributes + a JS dictionary in the bottom `<script>`. Auto-detect order: `localStorage` → `navigator.language` → Spanish. |
| **SEO** | `<title>`, meta description, canonical, Open Graph, Twitter card, geo tags, and JSON-LD `ProfessionalService`. Title/description/`<html lang>`/`og:locale` update on language switch. |

### Internationalization

- Switcher: an **ES / EN** toggle in the top bar and in the nav drawer (both stay in sync).
- All user-visible strings live in the `I18N = { es: {…}, en: {…} }` dictionary near the end of
  `index.html`. To edit copy, change both language entries for the relevant key.
- **The three testimonials are translated to English for comprehension.** They are real quotes from
  real clients; the English versions are faithful translations, not the clients' literal words.

### Outbound links (intentional external URLs)

Not runtime dependencies — these are navigation targets:

- **Cargá tu CV** → `https://hiringroom.com/jobs/get_vacancy/62d86434b9f90f34c1221820/candidates/new#step2`
- **Ofertas laborales** → `https://lpconsultora.hiringroom.com/jobs`
- WhatsApp `+54 351 241-1979`, `lara@lp-consultora.com`, and LinkedIn / Instagram / Facebook.

---

## Rebuilding assets

There is no `package.json`; the following are one-off recipes (require Node + network). Run them
from a scratch folder, not the project root, and copy outputs into `assets/`.

**Tailwind CSS** — after changing classes in `index.html` or `tailwind.config.js`:

```bash
npx --yes tailwindcss@3 -c ./tailwind.config.js -i ./input.css -o assets/tailwind.css --minify
```

`input.css` is just the three `@tailwind base; @tailwind components; @tailwind utilities;` lines.
`tailwind.config.js` mirrors the design-system tokens and has a `content: ["index.html"]` entry plus
a `safelist` for classes toggled at runtime by the language switcher/drawer
(`bg-primary`, `text-on-primary`, `text-on-surface-variant`, `opacity-0`).

**Fonts** — fetch the Google Fonts CSS with a modern User-Agent (so it serves `woff2`), download
every referenced `woff2` into `assets/fonts/`, and rewrite the URLs in `fonts.css` to local
relative paths.

**Icons** — each icon's path data comes from Google's Material Symbols per-icon SVG endpoint
(`https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/<name>/<variant>/24px.svg`,
where `<variant>` is `default`, `fill1`, or e.g. `wght200`). All icons use
`viewBox="0 -960 960 960"`. To add an icon, fetch its path and inline a new
`<svg viewBox="0 -960 960 960" fill="currentColor" …><path d="…"/></svg>`.

**OG image** (`assets/og-image.jpg`, 1200×630) — composited on a canvas from the logo, brand
gradient, and tagline.

---

## Known items / TODO

- **`og-image.jpg`** is a generated placeholder (logo + tagline on brand background). Replace with a
  designed asset if desired — keep it 1200×630.
- **Location spelling:** visible footer text says "Cordoba" (verbatim from the original site); the
  SEO metadata and English strings use the correct "Córdoba". Unify if wanted.
- **`.htaccess`** currently sets MIME types, caching, and gzip only. Add HTTPS / canonical-host
  redirects there if the host doesn't handle them.
- **Meta description** (~186 chars) runs slightly past Google's ~155–160 display limit; trim if you
  want it fully shown in results.
- **Privacy / Terms** footer links point to `#` (no such pages exist yet).
- **English SEO** is client-side only (JS swaps the content). For true multilingual ranking, a
  separate crawlable `/en/` page with `hreflang` would be needed.

---

## Content provenance

Body copy, the three testimonials (and their role captions), client logos + links, contact details,
and CTA destinations were recovered from the previous production site. Hero, testimonial-avatar, and
map images were generated during the redesign and are placeholders.
