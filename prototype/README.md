# Áron Ritók-Filip founder site: HTML prototype

This is step 1 of the build approach in the *HelloProVision — Áron Ritók-Filip Founder Brand Website Blueprint*: approve the design as static HTML first, then port it into the GeneratePress child theme.

## Build

```bash
python3 prototype/build.py
```

No dependencies beyond Python 3. The script produces:

- `prototype/dist/`: the multi-page static site, using the blueprint's URL structure (`/services/local-seo/`, `/case-studies/imperial-kitchens/` and so on). Links are relative, so it works when opened from disk or served by any static server:
  `cd prototype/dist && python3 -m http.server 8000`
- `prototype/dist/preview.html`: every page in one file, hash-routed, for sharing a clickable preview.

## Pages (17)

| URL | Source |
|---|---|
| `/` | `pages/home.html` (all 14 homepage blocks) |
| `/about/` | `pages/about.html` |
| `/services/` | `pages/services.html` |
| `/services/website-design-development/` | `pages/service-website.html` |
| `/services/local-seo/` | `pages/service-local-seo.html` |
| `/services/digital-growth-strategy/` | `pages/service-strategy.html` |
| `/case-studies/` | `pages/case-studies.html` (with industry filter) |
| `/case-studies/imperial-kitchens/` | `pages/case-study-imperial-kitchens.html` (case study template) |
| `/insights/` | `pages/insights.html` (3 clusters) |
| `/insights/website-traffic-no-calls/` | `pages/article-traffic-no-calls.html` (sample article) |
| `/strategy-call/` | `pages/strategy-call.html` (two-step form, then the booking step) |
| `/strategy-call/thank-you/` | noindex |
| `/growth-scorecard/` | working 15-question scorecard with results |
| `/privacy-policy/`, `/terms/`, `/accessibility/` | drafts that need legal review |
| `/404.html` | |

## What's in place

- Type scale, 12-column grid and components from blueprint §2 and §6, with Instrument Serif and Inter self-hosted as WOFF2 (OFL licensed).
- Palette: green pastel, replacing the blueprint's sand and khaki at the client's request. Mint White `#F2F6EF`, Sage Mist `#E3EDE1`, Pastel Green `#CFE2CF`, Soft Mint `#B7D6C0`, Eucalyptus `#8FB89C` (lines and dots only), Deep Green Charcoal `#1D2A23`, Forest `#3B6A4C` for accent text (5.7:1 on the background), and `#4E6256` for captions. All text colors pass WCAG AA on the surfaces they sit on.
- Project imagery: `<x-project slug="…">` and `<x-photo src="…">` tags in the page sources. The build swaps each one for the real image if its file exists in `src/assets/img/`, and otherwise shows a placeholder frame. See `src/assets/img/README.md` for the expected file names.
- Title, meta description, canonical and OG tags per page (§8), plus a JSON-LD `@graph` with Person, ProfessionalService, WebSite, WebPage or ProfilePage, BreadcrumbList, and Service or BlogPosting depending on the page.
- GA4-ready `dataLayer` events: `cta_click`, `form_start`, `form_submit`, `scorecard_start`, `scorecard_complete`, `booking_open`, `click_to_call`, `click_to_email` (§10).
- Accessibility: visible focus, 44px tap targets, labelled fields with inline errors, native `details` FAQ, and no motion under `prefers-reduced-motion`.
- Forms use a honeypot and a time check (no CAPTCHA). In the prototype they don't send data anywhere. In WordPress, `inc/forms.php` will handle them.

## Facts to verify

Every unverified fact is wrapped in `<span class="tbd">[…]</span>` and highlighted. The dark bar at the bottom right counts them on each page and can hide the highlighting. Before launch, each one must be replaced with a verified fact or removed. See the blueprint's "Open inputs before launch" list.

Photo frames stay placeholders until the files arrive: project screenshots from helloprovision.com, and portraits from the photo shoot. The blueprint rules out stock photos of people.

`SITE_URL` in `build.py` stays `https://example.com` until the domain is chosen.
