# Brand Guide — Amelia's by EAT

> **v6 (2026-06-04)** — locks the public visual direction. Reconciles two
> extractions of the live site and adds the art-direction + design system the
> v3 mockups are built on. Supersedes the v1 (2026-06-02) Playwright extraction.
> The platform design spec (`docs/superpowers/specs/2026-06-02-amelias-platform-design.md`)
> is the single source of truth; this guide is the brand detail behind it.

## Sources (reconciled)
- **v1 — Playwright** (2026-06-02): computed styles + linked CSS + logo download from rendered `https://ameliasaz.com/`. Site is **WordPress + WooCommerce + Kadence**.
- **v6 — live HTML/CSS re-extraction** (2026-06-04): hex values + served font-family declarations.
- **Client photo set received** (2026-06-04): real photography staged in `images/` (17 files).

Where the two extractions differ, the reconciliation is noted inline. Both agree on the **ink (`#313530`)**, **Montserrat body**, and **Audrey** display face.

## Brand positioning
- **Concept:** conscious, made-from-scratch, all-day eating place + curated market (and wine shop).
- **Named for** founder/chef **Stacey Weber's grandmother, Amelia**.
- **Philosophy:** "mindfully curated foods, from source to plate"; locally sourced; food as nourishment + community. Clean-food guardrails: **scratch made, locally sourced, seed-oil free, no preservatives, grass-fed & organic**.
- **Voice / tone:** warm, health-conscious, community-focused, conversational ("The neighborhood just got better"; "When you eat well, you feel good"; "See you in sunny Scottsdale!"). Wellness-forward, not preachy.

## Colors — verified earth-tone palette (no navy in the public brand)

| Role | Hex | Notes |
|------|-----|-------|
| **Ink** (primary text) | `#313530` | Warm charcoal-olive — dominant text; **primary button fill** |
| Ink alt | `#292B2C` | Secondary text |
| Cream (ground) | `#F9F6E4` | Dominant warm background |
| Cream 2 / paper | `#FAFAE1` / `#FCFAF3` | Soft surfaces |
| Stone / taupe | `#CCC6B9` | Borders, muted fills |
| Sage-grey | `#9A9A8D` | Secondary muted text |
| **Olive-green** (accent) | `#5F8219` | Primary accent — links/details (large) |
| **Wheat-gold** (accent) | `#E1C188` | Warm highlight accent (decorative) |

**AA-legibility tokens** (accents are too light for body-size text on cream): use **olive-ink `#46600F`** for links/details and **gold-ink `#9C7833`** for accent text; primary buttons use the **ink `#313530`** (high contrast on cream).

> **Navy `#171A2D`** appeared as a minor UI accent in the v1 Playwright extraction; the v6 live-CSS pass surfaced the **olive/wheat-gold** accents instead. **Design decision:** the public brand palette is the warm earth-tone set above — **navy is dropped from the public brand** and kept only as the *admin* tool's primary (an internal-tool choice). No AI-slop indigo/violet/teal.
>
> The palette is built as a bespoke 3-layer token set (primitive → semantic → component) per `public-website-conventions`. The `--wp--preset--*` Gutenberg/Woo variables are framework defaults, NOT brand colors.

## Typography

| Role | Font | Notes |
|------|------|-------|
| Display / headings | **Audrey** | Confirmed in both extractions; the brand display face. **San-Diego** (v1 Playwright computed styles) is the likely licensed heading face — absent from served static HTML because fonts load dynamically, so unconfirmed in v6. |
| Body | **Montserrat** (400/500/600) | Google Font (free) — the most-used family, confirmed both passes. |
| (Legacy/secondary) | Old Standard TT, Source Sans Pro | Seen in v1 only; treat as legacy. |

- **Mockup stand-in:** until licensing is confirmed, the v3 mockups use **Fraunces** (display) + **Montserrat** (body). Fraunces is a provisional stand-in for Audrey.
- **Licensing — open (Q13a):** confirm rights to **Audrey / San-Diego** for the rebuild, or choose close free alternatives (Fraunces + Public Sans is the Aslan fallback). **Admin typography seam:** the internal tool uses the body sans only — the public display serif never appears inside the admin (`internal-tool-typography`).

## Photography — art direction (the brand's biggest visual lever)

The client's real photos have a genuinely distinctive, ownable look in **two modes**. Keep them; do not replace with stock.

1. **High-key sun-drenched daytime** — cream-linen grounds, hard natural-light shadows, pastel glassware + edible-flower pops, fresh and bright. *Keep these bright — do NOT crush with a dark/matte LUT.*
2. **B&W heritage** — the 1940s portrait of grandmother **Amelia** + Stacey laughing with her son in the kitchen; grainy, intimate wine-night moments. Used for Our Story, Wine Club, Sunday Supper.

**Establishing material:** the real interior — slatted-wood wall, woven-leather chairs, globe sconces, potted cacti, the floor-to-ceiling **white wine wall**. **Source-to-plate device:** caption food with the **named purveyor** (Noble Bread, Steadfast Farm, Crow's Dairy, Fra'Mani, Sweet Republic).

**On-page treatment:** full-bleed ↔ contained rhythm; art-directed responsive crops (`<picture>`, AVIF/WebP, `width`/`height` to avoid CLS, `fetchpriority` on the hero); mobile hero = full-bleed image + dark scrim + overlaid headline/CTA.

**Real photo set** in `images/`: interior (`5I8A7492…`), bright food flatlay (`banner-alternate…`), market goods (`themarket.jpg`), wine wall (`WineWall-2…`), B&W heritage (`new-about-hero.jpg`), B&W toast (`glasses-clinking.jpg`), deviled eggs (`D0A7915-2.jpg`), parfaits (`boutique-catering-2.jpg`), grab-and-go cooler (`Cooler…`), + more.

## Logo
- **Primary (color, vector):** `docs/assets/brand/AmeliasbyEatLogo-Final-Color.svg` (282×119 viewBox) ✓
- **Sub-logo / mark:** `docs/assets/brand/AmeliasbyEat-sublogo.png` (2000×1778) ✓
- Wordmark: "Amelia's by EAT".

## Public design system (v3)
- **`docs/superpowers/specs/mockups/brand-v3.css`** — shared public design system (tokens, nav, buttons, page-hero, sections, menu-list, forms, cards, footer, a11y). Production folds it into the `frontend-css-architecture` file set.
- **`home-v3.html`** = approved home; **`home-v2.html`** = earlier critiqued draft (retained). 19 public pages + `purveyors.html` + `careers.html` build on the system.
- **Anti-"AI look" principles (enforced):** real place-derived palette; real photography; editorial layout (asymmetry, oversized display type, full-bleed↔contained, paper-grain substrate, named-purveyor captions). **Banned:** numbered section labels (01/02), tiny uppercase eyebrow-over-heading, scrolling marquees, the italic-single-word gimmick, navy/indigo/purple.

## Brand-inputs gate status
- [x] Logo (SVG + sub-logo) — have
- [x] Color palette — verified (earth-tone; navy dropped from public)
- [x] Type families — Audrey + Montserrat confirmed; Fraunces stand-in
- [x] **Food/restaurant photography — received** (real set in `images/`)
- [x] Visual direction — built + approved (v3 / `home-v3.html`)
- [ ] **Font licensing for Audrey / San-Diego** (open — Q13a; Fraunces stands in until confirmed)
- [ ] Confirm with client these are still the intended brand vs a refresh
