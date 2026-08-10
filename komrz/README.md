# NUQTA storefront — style layer

A paste-in restyle for the NUQTA store on Komrz
(`ys-studios.mykomrz.com`), running the **StepMart** block theme.

Two files, both optional to each other:

| File | What it is | Where it goes |
| --- | --- | --- |
| `nuqta-storefront.css` | The whole design system | Site Editor → **Styles** → **Additional CSS** |
| `nuqta-motion.html` | One entrance animation | Site Editor → Patterns → `my-footer` → a **Custom HTML** block |

The CSS is complete on its own. The motion file only adds a scroll-in reveal.

---

## Install

### 1. The stylesheet

1. Go to **Appearance → Editor** (or `/wp-admin/site-editor.php`).
2. In the top-right toolbar, click the **half-filled circle** — that's Styles.
3. Inside the Styles panel, open the **⋮** menu → **Additional CSS**.
4. Paste all of `nuqta-storefront.css` in. **Save**.

> This is *not* the same box as the template code editor you were in. That one
> holds block markup — `<!-- wp:template-part … -->` — and pasting CSS there
> would either be stripped or break the template.
>
> If the ⋮ menu doesn't show Additional CSS on your WordPress version, this URL
> reaches the same field directly:
> `/wp-admin/customize.php?autofocus[section]=custom_css`

### 2. The fonts

`nuqta-storefront.css` asks for **Archivo** and **DM Mono**. Both are on Google
Fonts, and WordPress 6.5+ can install them with no plugin:

**Site Editor → Styles → Typography → Manage fonts → Install fonts**, search
for each, tick the weights (Archivo 400/500/600, DM Mono 400/500), install.

Until you do, the stack falls back to your system font. The store still looks
deliberate — just less specific to NUQTA.

### 3. The motion (optional)

**Site Editor → Patterns → Template parts → `my-footer`**, add a **Custom HTML**
block at the very bottom, paste `nuqta-motion.html`, Save.

### 4. The cart drawer (recommended)

Don't let me hand-write one. WooCommerce ships a **Mini-Cart** block that already
does it properly — reads the live cart, updates on Woo's own events, can't fall
out of step with the totals a customer is about to pay.

**Site Editor → Patterns → Template parts → `my-header`** → add the **Mini-Cart**
block. §5 of the stylesheet already styles it.

---

## Rolling back

Select everything in the Additional CSS box, delete, Save. The store returns to
exactly how it looked before. Same for the Custom HTML block — delete the block.

Nothing here is a template override, a hook, or a line of PHP.

---

## What this touches

| Part of the store | Status |
| --- | --- |
| Colour, type, spacing, motion | Rewritten — that's the job |
| Add-to-cart AJAX and its nonces | Unchanged |
| Cart, checkout, payment gateways | Unchanged |
| Order creation and status | Unchanged |
| Orders / Sales / Analytics dashboards | Unchanged |
| Stock, pricing, tax, shipping rules | Unchanged |

The JS never calls `preventDefault()`, never binds a listener to a WooCommerce
control, and only ever adds one CSS class to decorative elements. There is no
path through it that reaches an order.

---

## The design, in short

**Light, not dark.** NUQTA has twelve products. A dark neon storefront makes
twelve products look like a catalogue someone abandoned halfway; a light one
with real air makes the same twelve look curated. Small hardware also
photographs better on light neutral ground, which is why every brand selling
objects this size does it that way.

There's a commented **dark variant** at the bottom of the stylesheet — six token
values — if you disagree. Worth revisiting once the catalogue passes ~30 products.

**One accent.** نقطة means *point*. Ultramarine `#2536D8` appears at two scales
and nowhere else: as small filled dots (stock indicators, list bullets) and as
button fills. Because nothing else on the page is that colour, a customer finds
the thing to click without reading.

**Prices are the ledger.** Every figure is monospaced and tabular, so a column
of prices lines up digit for digit.

**Tight radii.** 4px, not pills. Precision instruments aren't rounded.

---

## Known issue: the Riyal symbol

Your prices use **⃁** (U+20C1, the new Saudi Riyal sign). That character was
only added to Unicode in 2025 and most fonts don't carry the glyph yet — for a
lot of your customers it renders as an empty box.

The stylesheet works around it with a font fallback chain on
`.woocommerce-Price-currencySymbol`. Check it on an actual phone after you
paste. If it's still showing a box, the reliable fix is
**WooCommerce → Settings → General → Currency options** and set the symbol to
the text `SAR` until font support catches up.

---

## After you paste

I'm writing against WooCommerce's standard markup, which is a well-founded bet
but still a bet — I can't reach `mykomrz.com` from where I'm working, so I
haven't seen your storefront's actual HTML.

Expect one round of adjustment. Send a screenshot of anything that looks wrong
and I'll correct the selectors. The most likely candidates:

- **StepMart's own styles winning.** Fix is a `body` prefix or `!important` on
  the specific rule — tell me which element and I'll patch it.
- **The grid coming from a Product Collection block** rather than the classic
  archive, in which case §3's block selectors take over and may need the exact
  class names from your page source.
- **Card padding** doubling up if StepMart already pads `li.product`.
