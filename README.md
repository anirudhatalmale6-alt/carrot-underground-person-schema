# Person schema for The Carrot Underground (Yoast)

Adds one stable `Person` entity for **Connie Edwards McGaughy** to Yoast SEO's existing
schema `@graph`, and makes the About page's `ProfilePage` declare her as its `mainEntity`.

Yoast's `Organization`, `WebSite`, `ProfilePage`, `BreadcrumbList` and `ImageObject` pieces
are left as they are. Nothing is replaced, no second schema plugin is involved, and no
duplicate `Person` / `Organization` / `ProfilePage` entity is created.

---

## Install

Upload `tcu-person-schema.php` to:

```
wp-content/mu-plugins/tcu-person-schema.php
```

`mu-plugins` loads automatically — there is nothing to activate. It also survives theme
updates and can be deleted over FTP if anything ever needs backing out, which is not true
of `functions.php`.

If you would rather use a normal plugin, put it in
`wp-content/plugins/tcu-person-schema/tcu-person-schema.php` and activate it.

Then flush any page cache and re-check the About page.

## Configuration

Everything editable is in one block at the top of the file:

| Constant | Value | Notes |
| --- | --- | --- |
| `TCU_PERSON_PAGE` | `1003` | The About page ID, confirmed against the site's REST API. The slug works too. |
| `TCU_PERSON_ID` | `…/about-connie-edwards-mcgaughy/#/schema/person/connie-edwards-mcgaughy` | The permanent entity identifier. See below. |
| `TCU_ORGANIZATION_ID` | `https://thecarrotunderground.com/#organization` | Yoast's existing Organization, confirmed from live output. |
| `TCU_LINK_ORGANIZATION_FOUNDER` | `true` | Adds `founder` to the Organization node. Purely additive. |

Job titles match the line printed on the About page itself ("Longtime Vegan Recipe
Developer • Cookbook Author • Photographer • Founder of The Carrot Underground"). If that
line is reworded, reword `jobTitle` to match — structured data should describe what a reader
can see. "Founder" is not repeated in `jobTitle` because it is already expressed properly as
the `founder` edge on the Organization.

The Person's name, job titles, description, `knowsAbout` and `sameAs` list live in
`tcu_person_schema_definition()` directly below that.

### Why `TCU_PERSON_ID` is a hardcoded string

An `@id` is an identity, not an address. Once Google has associated this string with
Connie, it needs to keep meaning "Connie" even if the About page is renamed or moved.
Deriving it from `get_permalink()` at request time would quietly mint a brand new entity
the day the slug changes, throwing away everything the old one had accumulated — which is
the opposite of the goal here.

Every `Book`, `Article` or `Recipe` entity references this exact string:

```php
'author' => array( '@id' => TCU_PERSON_ID ),
```

Restating name / image / sameAs inline on each cookbook page would create a second and
third Connie as far as Google is concerned, splitting the entity signal between them.

---

## The cookbooks

Both published e-cookbooks are emitted as `Book` nodes authored by that same `@id`.
They are defined in `tcu_person_schema_books()`.

They currently sit on the About page. That is deliberate and it is honest: the page's own
copy says *"I recently published my first two vegan e-cookbooks"* and links to the shop, so
the markup describes something a reader can actually see. Structured data for content that
is not on the page is a guidelines violation, not a shortcut.

The page node gets `mentions` pointing at both books. That is what earns them their place in
the graph — the page mentions them; Connie remains the page's `mainEntity`.

### Moving a book to its own page later

Change that book's `page` value to the new page's ID. Nothing else. The `@id` is rooted at
the domain (`https://thecarrotunderground.com/#/schema/book/...`) rather than at the About
page, so moving where the node is *emitted* does not change what the Book *is*.

When a Book lands on a page that has no full profile, a minimal `Person` stub (name and url
only) is emitted alongside it so the `author` reference resolves. That is not a second
Connie — JSON-LD merges nodes by `@id`, so the stub and the full About-page profile are read
as one entity described in more detail in one place. There is a test covering exactly this.

### What Book markup will and will not do

Being straight about this: this will not produce a book rich result on its own. Google's
Books structured data feature is a gated programme with its own onboarding, and it wants
`workExample` editions with ISBNs, which self-published e-books without ISBNs cannot supply.

The value here is entity resolution — it tells Google that the author entity it is being
asked to recognise has actually published two titles, corroborated by Goodreads. For a
Knowledge Panel bid, that is the point.

### Book source data

| | Volume One | Volume Two |
| --- | --- | --- |
| Published | 2024-10-01 | 2024-11-01 |
| Pages | 63 | 70 |
| Price | $9.99 | $9.99 |
| Goodreads | [256792999](https://www.goodreads.com/book/show/256792999-the-carrot-underground-cookbook---volume-one) | [256793045](https://www.goodreads.com/book/show/256793045-the-carrot-underground-cookbook---volume-two) |

Publication dates and page counts are from Goodreads. Note these are *not* the Shopify
`published_at` dates (2024-10-23 and 2024-11-25) — those are when the products were listed
in the store, which is a different fact from when the books were published.

Two things deliberately left out:

- **Amazon.** Goodreads lists ASINs `B0HDZ35VLC` and `B0HDZ959VG`, but both
  `amazon.com/dp/…` URLs return **404**. They are Goodreads-internal identifiers, not live
  listings. Adding them to `sameAs` would have published two dead links.
- **The bundle product.** `…/products/the-carrot-underground-cookbook-volumes-one-two-bundle`
  is an offer covering both titles rather than a third distinct work, so it is not its own
  `Book`. It could be added as a second `Offer` if wanted.

**Google Play Books is missing.** Connie's email says the e-books were accepted to Google
Play. Those URLs are not in the code because I could not verify them — the Google Books API
was returning HTTP 429 quota errors on every query, including a control query for a
well-known title, so a nil result there proves nothing either way. There are commented
placeholders in both `sameAs` arrays; send the two URLs and they go straight in.

---

## What this produces

The About page graph gains three nodes — the Person and the two Books — plus the references
that tie them to what Yoast already outputs:

```jsonc
{
  "@type": ["WebPage", "ProfilePage"],
  "@id": "https://thecarrotunderground.com/about-connie-edwards-mcgaughy/",
  "mainEntity": { "@id": "…#/schema/person/connie-edwards-mcgaughy" },  // added
  "mentions": [                                                         // added
    { "@id": "https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one" },
    { "@id": "https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-two" }
  ]
},
{
  "@type": "Organization",
  "@id": "https://thecarrotunderground.com/#organization",
  "founder": { "@id": "…#/schema/person/connie-edwards-mcgaughy" }      // added
},
{
  "@type": "Person",                                                    // added
  "@id": "https://thecarrotunderground.com/about-connie-edwards-mcgaughy/#/schema/person/connie-edwards-mcgaughy",
  "name": "Connie Edwards McGaughy",
  "jobTitle": ["Vegan Recipe Developer", "Cookbook Author", "Food Blogger", "Photographer"],
  "url": "https://thecarrotunderground.com/about-connie-edwards-mcgaughy/",
  "mainEntityOfPage": { "@id": "https://thecarrotunderground.com/about-connie-edwards-mcgaughy/" },
  "image": { "@id": "…#primaryimage" },
  "worksFor": { "@id": "https://thecarrotunderground.com/#organization" },
  "affiliation": { "@id": "https://thecarrotunderground.com/#organization" },
  "knowsAbout": [...],
  "sameAs": [...]
},
{
  "@type": "Book",                                                      // added, x2
  "@id": "https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one",
  "name": "The Carrot Underground Cookbook - Volume One: How to Host Plant-Based Parties Everyone Will Love",
  "author": { "@id": "…#/schema/person/connie-edwards-mcgaughy" },
  "publisher": { "@id": "https://thecarrotunderground.com/#organization" },
  "bookFormat": "https://schema.org/EBook",
  "datePublished": "2024-10-01",
  "numberOfPages": 63,
  "offers": { "@type": "Offer", "price": "9.99", "priceCurrency": "USD", … },
  "sameAs": ["https://www.goodreads.com/book/show/256792999-…"]
}
```

The full expected output is in [`output-about-page-schema.json`](output-about-page-schema.json).

The Person node is emitted **only** on the About page. That page is the entity's canonical
home; everywhere else references it by `@id`. Repeating the full definition site-wide would
hand Google the same entity on every URL with no single place that owns it.

---

## Notes on the source data

- **Goodreads.** `https://www.goodreads.com/veganconnie` is a 301 redirect. The canonical
  author URL `https://www.goodreads.com/author/show/71756303.Connie_Edwards_McGaughy` is
  used instead, so `sameAs` points at the destination rather than a hop.
- **LinkedIn.** `linkedin.com/in/connie-edwards-mcgaughy` is a personal profile and is now
  on the `Person`. It is still on Yoast's `Organization` `sameAs` as well — that list is
  managed in the Yoast admin UI and this snippet deliberately does not touch it. Removing
  it there is a one-click change if wanted.
- **WikiData.** `https://www.wikidata.org/wiki/Q138577229`, located on wikidata.org and
  listed first in `sameAs`. It is the one entry Google's Knowledge Graph reads directly
  rather than merely as corroboration. The item already carries `instance of: human`,
  occupations, US citizenship, San Diego as residence, and `official website` →
  `thecarrotunderground.com`.

  The link is currently one-way in places. To strengthen it, add these external-ID
  statements to the WikiData item so it corroborates the same profile list the site
  publishes:

  | Property | Value |
  | --- | --- |
  | P973 described at URL | `https://thecarrotunderground.com/about-connie-edwards-mcgaughy/` |
  | P2002 X/Twitter username | `veganconnie` |
  | P2003 Instagram username | `thecarrotunderground` |
  | P2013 Facebook ID | `thecarrotunderground` |
  | P2397 YouTube channel ID | `UC0l81mHV9MdJXko-yrVphug` |
  | P2963 Goodreads author ID | `71756303` |
  | P6634 LinkedIn personal profile ID | `connie-edwards-mcgaughy` |

  Worth knowing: WikiData items about living people need a serious, publicly available
  source to survive a notability challenge. The item has no references on it at the moment.
  If it is ever deleted, the `sameAs` entry becomes a dead link — so the published cookbooks
  are worth citing on it.

---

## Tests

```
php tests/test-person-schema.php
```

75 assertions, no WordPress or Yoast install required. (`tests/test-book-on-own-page.php`
runs as a second process because it has to redefine the book list before the plugin loads;
the main suite invokes it and folds in its results.) The WordPress functions the snippet
calls are stubbed and the filter is run against `tests/fixture-live-graph.json` — the real
`@graph` captured from the live About page on 2026-08-17 — so the assertions run against the
exact structure Yoast is producing on the site today, not an idealised version of it.

Covered:

- the Person node is added, correctly typed, and wired to the ProfilePage in both directions
- `worksFor` / `affiliation` resolve to Yoast's existing Organization
- `image` reuses the existing `ImageObject` rather than adding a second one
- Yoast's `WebSite`, `BreadcrumbList` and `ImageObject` pieces come out byte-for-byte identical
- `founder` is the *only* change to the Organization node; `mainEntity` the *only* change to the ProfilePage
- every `@id` reference in the finished graph resolves to a node in that graph — no dangling references
- no two nodes share an `@id`
- every other page on the site is returned completely unmodified
- running the filter twice cannot produce a second Connie
- degraded input (no ImageObject, no Organization, empty graph) still produces valid output
  rather than broken references
- both Books reference the author by `@id` rather than restating her inline — there is a
  specific assertion for this, because inlining the author is the exact mistake that would
  silently create a second Connie
- a Book moved to its own page keeps its identity, gets a Person stub so nothing dangles,
  does not claim to be that page's `mainEntity`, and does not drag the other book with it

The generated graph was also run through `validator.schema.org`: **0 errors, 0 warnings**,
with the `Person` correctly resolving from the `ProfilePage`'s `mainEntity`.

---

## About the snippet that was supplied

The `wpseo_schema_webpage` version in `PHP Code for MainEntity 20260817.txt` was not used.
Three reasons, in order of severity:

1. **It does not parse.** The `linkedin.com` line in the `sameAs` array is missing its
   trailing comma:

   ```
   PHP Parse error: syntax error, unexpected single-quoted string
   "https://www.pinterest.com/thec...", expecting "]" on line 31
   ```

   Pasted into `functions.php` this takes the entire site down — front end and wp-admin —
   with a white screen, recoverable only over FTP.

2. **The page condition never matches.** `is_page()` compares against a page's ID, slug or
   title. It was passed a full URL, which matches none of those. Even after fixing the comma
   the snippet would have run on every request and done nothing, silently.

3. **It nests the Person instead of referencing it.** Assigning the whole Person array to
   `$data['mainEntity']` buries it inside the WebPage node. Valid JSON-LD, but it is not a
   top-level graph node, which makes it awkward to point the planned cookbook `Book`
   entities at — and that is the entire reason for establishing a stable `@id` now.

It was also missing any relationship to The Carrot Underground Organization, which was one
of the explicit requirements.
