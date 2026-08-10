# YS Store — a WooCommerce storefront

A complete WordPress store built from the design language of three sites in this
account:

| Source | What was carried over |
| --- | --- |
| [`My-Portfolio`](https://github.com/yvssen-ai/My-Portfolio) | The void-black shell, the cyan → violet → amber conic gradient, the particle constellation behind the hero, the film grain, and the split-text headline reveals. |
| [`UNO`](https://github.com/yvssen-ai/UNO) | The semantic colour-zone system (`--surface` / `--fg` / `--accent` redefined per section rather than hardcoded), the seamless marquee ticker, the preloader, and the fluid `clamp()` type scale. |
| [`Solis`](https://github.com/yvssen-ai/Solis) | The ordering model: a single slide-over drawer that carries the cart and the route to checkout, so a customer never loses the page they were reading. Plus the long-settle house easing and the reduced-motion discipline. |

Two pieces ship here:

```
wordpress/
├── themes/ys-store/                 the storefront
└── plugins/ys-commerce-insights/    the sales dashboard and tracking
```

They are independent. The theme works without the plugin; the plugin works under
any theme.

---

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+ (tested against 9.8)
- PHP 7.4+ (8.1–8.4 recommended)

---

## Install

1. **Copy the folders in.**

   ```bash
   cp -r wordpress/themes/ys-store            /path/to/wp-content/themes/
   cp -r wordpress/plugins/ys-commerce-insights /path/to/wp-content/plugins/
   ```

   Or zip each folder and upload it through *Appearance → Themes → Add New →
   Upload* and *Plugins → Add New → Upload*.

2. **Activate WooCommerce first**, then the YS Store theme, then YS Commerce
   Insights. Activating the plugin creates its events table; activating it
   before WooCommerce leaves it idle with a notice until WooCommerce is on.

3. **Run the WooCommerce setup wizard** if you have not already. It creates the
   Shop, Cart, Checkout and My Account pages that the theme's templates target.

4. **Set the front page.** *Settings → Reading → Your homepage displays →
   Your latest posts* uses `front-page.php`, which is the storefront. If you
   would rather build the home page in the block editor, pick *A static page*
   and the theme steps out of the way.

5. **Create the menus** at *Appearance → Menus*. Four locations are registered:
   `Primary (header)`, `Shop categories`, `Help & policies`, and `Legal`. With
   no menu assigned the header falls back to a Shop and Cart link, so nothing
   is broken while you set up.

6. **Regenerate thumbnails** if you are switching from another theme — the
   catalogue image is 600px square and the single-product image 1200px wide.
   The *Regenerate Thumbnails* plugin does this, or WooCommerce offers it after
   a theme switch.

---

## Configuring the storefront

Everything is under *Appearance → Customise → YS Store*.

**Hero** — eyebrow, headline, supporting line, and two buttons. Leave a button
URL blank and it points at the shop.

**Colour** — three gradient stops. Every accent, button, focus ring, chart line
and heading on the store is derived from them, so changing these three retints
the whole site. They are the only colours exposed; the greys and surfaces are
computed from them in `assets/css/tokens.css`.

**Shop** — products per row, products per page, whether the cart drawer opens
on add (off shows a toast instead, which suits stores where people add several
things in a row), and the three reassurance lines under the buy button.

**Motion** — the intro animation, the custom cursor, and the promise strip can
each be switched off. Anyone whose operating system asks for reduced motion
gets a still page regardless of these settings.

### Shop filters

Add WooCommerce's *Filter by price*, *Filter by attribute* and *Product
categories* widgets to the **Shop filters** widget area. They render as a panel
above the product grid. Leave it empty and the panel does not appear.

### Newsletter

The block on the front page is inert by design — connect your existing list
rather than collecting addresses into a table nobody exports:

```php
add_filter( 'ys_store_newsletter_shortcode', function () {
	return '[mc4wp_form id="123"]';
} );
```

### GSAP

Loaded from jsDelivr by default, matching how the source sites do it. For a
store under a strict CSP, or one that would rather not hand a CDN its visitors'
IP addresses:

1. Drop `gsap.min.js` and `ScrollTrigger.min.js` into
   `themes/ys-store/assets/js/vendor/`.
2. `add_filter( 'ys_store_gsap_source', fn() => 'local' );`

`'none'` drops the motion layer entirely. The store still works — the reveal
animations simply do not run, because `assets/css/animations.css` only hides
elements while JavaScript has confirmed it is going to animate them.

---

## The insights dashboard

*WooCommerce → YS Insights*.

**Headline figures**, each against the equivalent preceding period: revenue,
orders, average order value, conversion rate, items sold, unique customers,
net of refunds, and refunds. A metric with no prior data says so rather than
printing "+100%".

**Revenue over time** — daily revenue and order count. Days with no sales are
plotted at zero rather than skipped, so a gap in the line means missing data
and nothing else.

**The funnel** — sessions → viewed a product → added to cart → reached
checkout → purchased, with the drop-off between each pair of steps. This is the
part WooCommerce cannot tell you on its own: it knows what was bought, not how
many people looked and left.

**Tables** — top products by revenue (not by units: a cheap accessory always
wins a unit count), stock about to run out, recent orders including the failed
ones, and where the revenue came from.

### Where the numbers come from

Sales figures are read from WooCommerce orders through `wc_get_orders()` and
the order CRUD, so they are correct under both the legacy post storage and
High-Performance Order Storage. There is not one query against `wp_posts` for
order data, and the plugin declares HPOS compatibility.

Only `processing` and `completed` orders count as revenue. `on-hold` does not —
a bank transfer that never arrives would inflate every figure on the page. To
change that:

```php
add_filter( 'ys_insights_paid_statuses', function ( $statuses ) {
	return array( 'wc-processing', 'wc-completed', 'wc-my-custom-status' );
} );
```

Figures are cached in transients for five minutes, and flushed immediately
whenever an order is created, changes status, or is refunded — so marking an
order complete updates the dashboard on the next load, not five minutes later.
Ranges that have already ended are cached for a day, because they cannot change.

### The first-party funnel

Conversion rate needs traffic data, and the plugin collects it itself into
`{prefix}_ys_insights_events` rather than depending on a third party.

**What is stored:** a random session key, an event name, a product or order id,
a value, the traffic source, and whether the device is mobile or desktop.

**What is not:** IP addresses, user agents, user ids, names, or anything else
that identifies a person. The session key is random per visit and expires with
its 30-minute cookie — it cannot be used to recognise the same person on a
later visit.

Browsers sending `DNT: 1` are skipped, as is anyone logged in who can edit
orders (otherwise your own browsing dominates a small store's funnel). Rows
older than the retention period — 365 days by default — are deleted by a daily
job.

Switch it off in *YS Insights → Settings* and the dashboard keeps working;
it just says conversion rate cannot be calculated instead of showing a made-up
one.

### GA4 and the Meta Pixel

Optional, off, and nothing is sent until you enter an ID.

What you get over a generic tag setup is correctness at the two points that
matter:

- **`purchase` is built from the real `WC_Order`** — its true total, tax,
  shipping, coupons and line items — and it is guarded by a flag on the order,
  so a customer who refreshes the thank-you page does not report the sale
  twice. Double-counted purchases corrupt ad-platform bidding, not just a
  report.
- **`add_to_cart` fires after WooCommerce confirms the add**, not on the click,
  so an add that failed validation is never reported as a sale signal.

`view_item`, `view_item_list` and `begin_checkout` are built server-side from
prices WordPress already knows, rather than scraped back out of formatted
markup.

**Consent.** Turn on *Wait for consent* and neither vendor script is requested
until something calls `window.ysConsentGranted()` — which is what your cookie
banner should call when a visitor accepts. Events fired before that are queued
and replayed after. The first-party funnel is unaffected either way.

To push your own event through the same gate:

```js
window.ysTrackEvent( 'select_item', { items: [ { item_id: 'SKU-1' } ] } );
```

---

## Notes on the build

**Why WooCommerce's stylesheets are dequeued.** `woocommerce-general` and
`woocommerce-layout` between them set the grid, buttons, tables and notices —
all of which `assets/css/woocommerce.css` defines from scratch. Loading both
means every rule here fights a specificity war it should not need to, and the
shopper downloads ~40KB of CSS to have it overridden. The PhotoSwipe styles
stay, because they carry the lightbox's behaviour rather than its skin.

**Why cart and checkout are wrapped, not overridden.** `cart.php` and
`form-checkout.php` change between WooCommerce releases and carry the logic
that actually takes money. Copies in a theme go stale silently. The layout comes
from wrappers printed around them on `woocommerce_before_cart` /
`woocommerce_after_checkout_form` instead, which gives the CSS the grid parents
it needs while leaving WooCommerce's markup alone.

**Why the motion layer stands down on checkout.** Nothing on the cart or
checkout screens waits on a scroll trigger. A customer with their card out
should never be looking at an element that has not arrived yet.

**Why `animations.css` keys off `.ys-js`.** The class is written by an inline
script in `header.php` before first paint, so there is no flash of
already-positioned content. A visitor without JavaScript — or one whose bundle
is blocked — never gets it, and sees the whole page. A four-second timer removes
it if `app.js` has not booted, so a CDN timeout cannot leave the store looking
empty.

**Scale.** The dashboard's revenue queries page through orders 200 at a time
via a generator, so peak memory is flat regardless of how much history a range
covers. On a store with hundreds of thousands of orders, an "all time" range is
still a lot of rows; use WooCommerce's own Analytics for that scale and this
dashboard for the day-to-day.

---

## Files

```
themes/ys-store/
├── style.css                    theme header; the design system is in assets/css/
├── theme.json                   block editor palette and type scale
├── functions.php                bootstrap only
├── inc/
│   ├── setup.php                theme supports, menus, widget areas, image sizes
│   ├── assets.php               enqueues, Woo stylesheet removal, free-shipping threshold
│   ├── template-tags.php        product card, section head, marquee, pagination
│   ├── customizer.php           hero, colour ramp, shop and motion settings
│   ├── class-ys-nav-walker.php  header menu walker
│   ├── woocommerce.php          hooks, badges, stock lines, cart fragments, wrappers
│   └── ajax.php                 drawer quantity / remove endpoints
├── template-parts/              hero, stats, newsletter, cart drawer
├── woocommerce/                 archive, card, single product, sale flash, empty cart
└── assets/
    ├── css/  tokens · base · components · woocommerce · animations
    └── js/   app.js · cart.js · customize-preview.js

plugins/ys-commerce-insights/
├── ys-commerce-insights.php     bootstrap, HPOS declaration, activation
├── includes/
│   ├── class-ys-insights-data.php       order queries, caching, ranges
│   ├── class-ys-insights-collector.php  first-party funnel + events table
│   ├── class-ys-insights-rest.php       /wp-json/ys-insights/v1/report
│   ├── class-ys-insights-admin.php      dashboard screen
│   ├── class-ys-insights-settings.php   settings screen
│   └── class-ys-insights-tracking.php   GA4 / Meta Pixel events
├── assets/  admin.css · admin.js · track.js
└── uninstall.php                removes the table, options and order flags
```

---

## Licence

GPL-2.0-or-later, the same as WordPress and WooCommerce.
