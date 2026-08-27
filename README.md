# Person schema for The Carrot Underground (Yoast)

Adds one stable `Person` entity for **Connie Edwards McGaughy** to Yoast SEO's existing
schema `@graph`, and makes the About page's `ProfilePage` declare her as its `mainEntity`.

Yoast's `Organization`, `WebSite`, `ProfilePage`, `BreadcrumbList` and `ImageObject` pieces
are left as they are. Nothing is replaced, no second schema plugin is involved, and no
duplicate `Person` / `Organization` / `ProfilePage` entity is created.

It also gives each cookbook a `Book` entity on its own dedicated page, seven press articles
on the About page, and keeps the personal profiles on the `Person` while the brand profiles
stay on the `Organization`.

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
| `TCU_SPLIT_ORG_SAMEAS` | `true` | Removes the personal profiles from the Organization's `sameAs`. See below. |
| `TCU_ADD_ORG_PROFILES` | `true` | Adds the brand's own WikiData item to the Organization's `sameAs`. The additive counterpart to the line above. |
| `TCU_LINK_SIBLING_BOOKS` | `true` | Ties the two volumes together as a `BookSeries`. |
| `TCU_BOOK_SERIES_ID` | `…/#/schema/series/carrot-underground-cookbook` | The series identifier. |

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

## Person profiles vs brand profiles

A profile listed on both entities corroborates neither of them cleanly. Google is being
asked "which of these two things is this account?" and getting the answer "both".

So the split is now explicit. `tcu_person_schema_personal_profiles()` is a single list that
does two jobs — it *is* the `Person`'s `sameAs`, and it is what gets removed from the
`Organization`'s `sameAs`. One list, so the two can never drift apart.

| Profile | Belongs to | Why |
| --- | --- | --- |
| `wikidata.org/wiki/Q138577229` | Person | The item is `instance of: human`. |
| `linkedin.com/in/connie-edwards-mcgaughy` | Person | An `/in/` URL is a personal profile; a company would be `/company/`. |
| `goodreads.com/author/show/71756303…` | Person | An author profile. |
| `x.com/veganconnie` | Person | The handle names a person. **See the caveat below.** |
| `facebook.com/thecarrotunderground/` | Organization | Brand page. |
| `instagram.com/thecarrotunderground/` | Organization | Brand handle. |
| `pinterest.com/thecarrotunderground` | Organization | Brand handle. |
| `youtube.com/channel/UC0l81mHV9MdJXko-yrVphug` | Organization | The channel is titled "The Carrot Underground" — confirmed from its feed. |
| `wikidata.org/wiki/Q141171005` | Organization | The item is `instance of: website`, `founder: Q138577229`. Added by this file — see below. |

The two entities remain firmly connected regardless, through `founder`, `worksFor` and
`affiliation`. Separating the profile lists does not weaken that; it sharpens it.

**The one judgement call is `x.com/veganconnie`.** The handle names a person, so it is
treated as hers. That account renders entirely in JavaScript, so its display name could not
be read from the server the way the YouTube channel's could. If it actually posts as the
brand, move that one line out of `tcu_person_schema_personal_profiles()` and into Yoast's
Other profiles, and it swaps over.

### How the Organization side is enforced

Yoast emits its **Settings → Site representation → Other profiles** list as the
Organization's `sameAs` on *every* page, so the filter tidies it on every page too, not
just on the About page. It is purely subtractive and only ever removes URLs this file
publishes on the `Person` — nothing can go missing from the graph as a whole, and there is
a test asserting exactly that.

The tidier fix is to delete those two lines in the Yoast settings screen, after which this
does nothing at all and simply stops them coming back. Until then, be aware that the Yoast
settings screen will list a profile the page no longer prints.

There is an additive half as well — `tcu_person_schema_add_org_profiles()`, controlled by
`TCU_ADD_ORG_PROFILES`. It puts the brand's own WikiData item on the Organization, which is
a profile Yoast has no field for. It never adds a URL the node already carries, compared the
same slash-and-case-insensitive way, so adding it to Yoast's Other profiles later will not
produce the same profile twice. Facebook, Instagram, Pinterest and YouTube are deliberately
*not* in that list — Yoast already publishes them, and duplicating them here would only give
the two lists a chance to disagree later.

Both WikiData items now point at each other in both systems at once: `Q141171005` has
`founder → Q138577229`, and in the markup the `Organization` carries `Q141171005` while the
`Person` carries `Q138577229`. That mutual agreement between the site and WikiData is the
part the Knowledge Graph actually reads.

---

## The cookbooks

Both published e-cookbooks are emitted as `Book` nodes authored by that same `@id`.
They are defined in `tcu_person_schema_books()`.

Each one now lives on its **own dedicated page**, which is where its full definition is
emitted and what its `url` points at:

| | Page | ID |
| --- | --- | --- |
| Volume One | `/the-carrot-underground-cookbook-vol-1/` | `28270` |
| Volume Two | `/the-carrot-underground-cookbook-vol-2/` | `28306` |

On its own page a Book becomes that page's `mainEntity`, and carries `mainEntityOfPage`
back — the link is closed in both directions. It also reuses the cover `ImageObject` Yoast
already emits for the page rather than adding a second node for the same file, and a minimal
`Person` stub (name and url only) is emitted alongside so the `author` reference resolves.

That stub is not a second Connie. JSON-LD merges nodes by `@id`, so the stub and the full
About-page profile are read as one entity, described in more detail in one place than the
other. There is a test asserting the stub carries no profile detail of its own.

### `url` and `shopUrl` are two different facts

The Book's `url` is its page on this site — the canonical home of the work. The **Offer**'s
url is the Shopify listing, because that is where the transaction actually happens. Both
are true and they are not the same claim; the previous version used the Shopify URL for
both, which is what this release fixes.

### The About page still refers to both

The About page copy says *"I recently published my first two vegan e-cookbooks"*, so it
keeps a short **reference** to each under `mentions` — name, url and author only, not a
second copy of the definition. Connie remains that page's `mainEntity`.

Each cookbook page also links to the other volume ("Looking for Volume Two?"), so each one
carries a reference to its sibling too.

### The series

Both volumes are `isPartOf` a `BookSeries` — *The Carrot Underground Cookbook* — which in
turn lists them under `hasPart` and is credited to the same author `@id`.

`isRelatedTo` is the obvious-looking way to link two books and it is **wrong**: it is
defined on `Product` and `Service`, not on `CreativeWork`, so on a `Book` it is an unknown
field. The schema.org validator flags it. A series is both valid and a truer description —
these genuinely are Volume One and Volume Two of one thing.

### Moving a book to a different page later

Change that book's `page` value to the new page's ID. Nothing else. The `@id` is rooted at
the domain (`https://thecarrotunderground.com/#/schema/book/...`) rather than at whichever
page emits it, so moving the node does not change what the Book *is* — which is exactly why
moving both off the About page cost nothing that Google had already read.

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
| Recipes | 45 | 35 |
| Price | $9.99 USD | $9.99 USD |
| Goodreads | [256792999](https://www.goodreads.com/book/show/256792999-the-carrot-underground-cookbook---volume-one) | [256793045](https://www.goodreads.com/book/show/256793045-the-carrot-underground-cookbook---volume-two) |
| Google Play Books | `RzH1EQAAQBAJ` | `OzX1EQAAQBAJ` |
| WikiData | [Q141124581](https://www.wikidata.org/wiki/Q141124581) | [Q141170741](https://www.wikidata.org/wiki/Q141170741) |

Publication dates and page counts match the **Book Details** panel printed on each cookbook
page, and were originally taken from Goodreads. Note these are *not* the Shopify
`published_at` dates (2024-10-23 and 2024-11-25) — those are when the products were listed
in the store, which is a different fact from when the books were published.

Two things deliberately left out:

- **Amazon.** Goodreads lists ASINs `B0HDZ35VLC` and `B0HDZ959VG`, but both
  `amazon.com/dp/…` URLs return **404**. They are Goodreads-internal identifiers, not live
  listings. Adding them to `sameAs` would have published two dead links.
- **The bundle product.** `…/products/the-carrot-underground-cookbook-volumes-one-two-bundle`
  is an offer covering both titles rather than a third distinct work, so it is not its own
  `Book`. It could be added as a second `Offer` if wanted.

**Google Play Books.** Both listings are in `sameAs`. Each id was confirmed by fetching the
Play page and reading its title rather than trusting the order they arrived in — a swapped
pair would have told Google each book was the other one. A test asserts the two volumes
carry different Play ids, since pasting the same one twice is the easy mistake here and it
would merge the two books into one entity.

---

## Press coverage

Seven articles are emitted as `Article` nodes, defined in `tcu_person_schema_press()`.
All seven are already linked from the About page, so the markup describes something a
reader can see and follow.

### Connie did not write any of them

This is the important part, and it is worth being blunt about because the natural
assumption is the opposite. Every article on the supplied list carries a different
journalist's byline. Connie is a **quoted expert source**, not the author:

| Article | Actually written by | Publisher |
| --- | --- | --- |
| Clever Ways to Cook Carrots | Leslie Quander Wooldridge | AARP |
| Flavorful, Protein-Packed White Bean Recipes | Leslie Quander Wooldridge | AARP |
| There's No Exact Baking Substitute For Eggs | Jamie Davis Smith | HuffPost |
| 15 Mistakes You're Making When Creating An Original Recipe | Sarah Moore | Chowhound |
| How To Use 14 Popular Herbs To Their Full Potential | Sarah Moore | Chowhound |
| 18 Staple Ingredients You Need For Vegan Baking | Sarah Moore | Chowhound |
| Top Football Game Party Ideas | Freda Nkrumah | Apartment Guide |

So each node credits the real journalist in `author`, the outlet in `publisher`, and
links to Connie with `mentions` — the article references her; it was not written by her
and it is not about her.

Putting her in `author` would be false structured data about a named third party's work,
and false markup is the fastest way to get an entity distrusted, which is precisely what
this whole file exists to avoid. There is an explicit test asserting that no press node
is ever credited to her.

Being a repeatedly-cited expert source in AARP, HuffPost and Chowhound is a strong
credibility signal in its own right — arguably stronger than a byline, because someone
else vouched for the expertise.

### Two articles from the list are deliberately excluded

The USA Today *Ted Lasso shortbread biscuits* piece and the CNN *6 inexpensive ways to
eat healthy at home* piece are **not** marked up.

Neither one mentions Connie. Searching the full HTML of each gives zero hits for
"Connie" and zero for "McGaughy". What each actually contains is a link to one of her
recipes — the vegan shortbread on USA Today, the vegan bolognese on CNN.

That is a link to her work, not coverage of her. They are genuinely valuable backlinks
and worth keeping on the About page, but listing them as articles that mention the person
would assert something the pages do not say. Say the word and I will add them, but I would
not.

The page node gets `citation` pointing at all seven, which is what connects the press to
the rest of the graph.

---

## What this produces

The About page graph gains ten nodes — the Person, a reference to each of the two Books, and
seven press Articles — plus the edges that tie them to what Yoast already outputs:

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
  "@type": "Book",                                                      // added, x2 (reference only)
  "@id": "https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one",
  "name": "The Carrot Underground Cookbook - Volume One: How to Host Plant-Based Parties Everyone Will Love",
  "url": "https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-1/",
  "author": { "@id": "…#/schema/person/connie-edwards-mcgaughy" }
}
```

On each cookbook page the full `Book` definition appears instead:

```jsonc
{
  "@type": ["WebPage", "ItemPage"],
  "@id": "https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-1/",
  "mainEntity": { "@id": "…#/schema/book/carrot-underground-cookbook-volume-one" },  // added
  "mentions": [ { "@id": "…#/schema/book/carrot-underground-cookbook-volume-two" } ] // added
},
{
  "@type": "Book",                                                      // added
  "@id": "https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one",
  "name": "The Carrot Underground Cookbook - Volume One: How to Host Plant-Based Parties Everyone Will Love",
  "url": "https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-1/",
  "mainEntityOfPage": { "@id": "https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-1/" },
  "image": { "@id": "…/the-carrot-underground-cookbook-vol-1/#primaryimage" },
  "author": { "@id": "…#/schema/person/connie-edwards-mcgaughy" },
  "publisher": { "@id": "https://thecarrotunderground.com/#organization" },
  "isPartOf": { "@id": "…#/schema/series/carrot-underground-cookbook" },
  "bookFormat": "https://schema.org/EBook",
  "datePublished": "2024-10-01",
  "numberOfPages": 63,
  "offers": { "@type": "Offer", "price": "9.99", "priceCurrency": "USD", "url": "…myshopify.com/products/…" },
  "sameAs": ["https://www.wikidata.org/wiki/Q141124581", "https://www.goodreads.com/…", "https://play.google.com/…"]
},
{
  "@type": "BookSeries",                                                // added
  "@id": "https://thecarrotunderground.com/#/schema/series/carrot-underground-cookbook",
  "name": "The Carrot Underground Cookbook",
  "author": { "@id": "…#/schema/person/connie-edwards-mcgaughy" },
  "hasPart": [ { "@id": "…volume-one" }, { "@id": "…volume-two" } ]
},
{
  "@type": "Person",                                                    // added (stub)
  "@id": "https://thecarrotunderground.com/about-connie-edwards-mcgaughy/#/schema/person/connie-edwards-mcgaughy",
  "name": "Connie Edwards McGaughy",
  "url": "https://thecarrotunderground.com/about-connie-edwards-mcgaughy/"
}
```

The full expected output for all three pages is checked in:

- [`output-about-page-schema.json`](output-about-page-schema.json) — 15 nodes
- [`output-book-page-1-schema.json`](output-book-page-1-schema.json) — 9 nodes
- [`output-book-page-2-schema.json`](output-book-page-2-schema.json) — 9 nodes

The Person node is emitted **only** on the About page. That page is the entity's canonical
home; everywhere else references it by `@id`. Repeating the full definition site-wide would
hand Google the same entity on every URL with no single place that owns it.

---

## Notes on the source data

- **Goodreads.** `https://www.goodreads.com/veganconnie` is a 301 redirect. The canonical
  author URL `https://www.goodreads.com/author/show/71756303.Connie_Edwards_McGaughy` is
  used instead, so `sameAs` points at the destination rather than a hop.
- **LinkedIn.** `linkedin.com/in/connie-edwards-mcgaughy` is a personal profile and lives on
  the `Person`. It is removed from the Organization's `sameAs` as the graph is built — see
  *Person profiles vs brand profiles* above.
- **WikiData.** `https://www.wikidata.org/wiki/Q138577229`, located on wikidata.org and
  listed first in `sameAs`. It is the one entry Google's Knowledge Graph reads directly
  rather than merely as corroboration. The item already carries `instance of: human`,
  occupations, US citizenship, San Diego as residence, and `official website` →
  `thecarrotunderground.com`.

  The earlier round of external-ID suggestions was partly superseded once the brand got its
  own item. **The brand handles belong on `Q141171005`, not on the person** — P2003, P2013,
  P2397 and P3836 are all on the Organization item, which is correct.

  The person-level identifiers were added on 2026-08-27 and verified against the live item:

  | Property | Value | State |
  | --- | --- | --- |
  | P2963 Goodreads author ID | `71756303` | Present. Closes the loop on `goodreads.com/author/show/71756303…` in her `sameAs`. |
  | P6634 LinkedIn personal profile ID | `connie-edwards-mcgaughy` | Present. |
  | P973 described at URL | `https://thecarrotunderground.com/about-connie-edwards-mcgaughy/` | Present, with an English language qualifier. Points the item at the page that carries the `Person` entity. |
  | P800 notable work | `Q141124581` and `Q141170741` | Both volumes present, both with references. |
  | P2002 X/Twitter username | `veganconnie` | Still open, deliberately. Only add it if that account posts as Connie rather than as the brand — see the caveat under *Person profiles vs brand profiles*. |

  **P1830 owner of / P108 employer were considered and left off, which is the right call.**
  `Q141171005 → founder → Q138577229` already states the relationship, and WikiData is queried
  in both directions, so a reciprocal statement adds no reachability. P108 would be actively
  wrong: *employer* asserts an employment relationship with a separate legal employer, which
  is not what a self-published personal brand is. If the *ownership* fact is ever wanted
  specifically, the cleaner way to say it is `P127 owned by` on the Organization item, because
  the site is the thing being owned — not `P1830` on the person.

  Worth knowing: WikiData items about living people need a serious, publicly available
  source to survive a notability challenge. Only a few statements on the item carry references
  at the moment. If the item is ever deleted, the `sameAs` entry becomes a dead link — so the
  published cookbooks are worth citing on it.

- **WikiData, the book items.** `Q141124581` is *Volume One* — `instance of: written work`,
  `author: Q138577229`, title and subtitle as separate statements, published 2024-10-01,
  Goodreads work ID `301149132`, official website the Volume One page.

  `Q141170741` is *Volume Two*, created 2026-08-26 — `instance of: cookbook`,
  `author: Q138577229`, published 2024-11-01, 70 pages, Goodreads work ID `301149201` added
  2026-08-27. Both are now in their book's `sameAs`, and a test asserts the two volumes carry
  *different* Q numbers, the same way it asserts they carry different Play Books ids.

  **The two items are not symmetric, and each is missing something the other has.** Nothing is
  broken — this is a completeness gap, and it matters because a sparse item is the kind that
  loses a notability challenge:

  | | Volume One `Q141124581` | Volume Two `Q141170741` |
  | --- | --- | --- |
  | P1476 title | present | **missing** |
  | P1680 subtitle | present | **missing** — `How to Bake the Best Vegan Desserts and Treats` |
  | P495 country of origin | present (United States) | **missing** |
  | P856 official website | present | **missing** — should be the Volume Two page |
  | P1104 number of pages | **missing** — 63 | present (70) |

  Two more things are worth tidying on the pair:

  - **The Goodreads identifier is `P8383 Goodreads work ID`, not `P2969`.** That is the whole
    explanation for the edition constraint. `P2969` is *Goodreads version/edition ID* and its
    subject-type constraint is `version, edition or translation` — so on a work item it
    correctly complains that it wants `edition or translation of`. `P8383` has a subject-type
    constraint of `written work`, which `cookbook → book → written work` satisfies, so it
    applies cleanly with no edition item needed. Volume One already uses `P8383`.
    **Volume Two's Goodreads work ID is `301149201`** (read off its Goodreads page; the same
    method returns `301149132` for Volume One, which is exactly what is already on
    `Q141124581` — that agreement is the control). Added to `Q141170741` on 2026-08-27 and
    confirmed present.
  - **Google Books `P675` is genuinely edition-level** and is the one to leave off. It carries
    two conflicting subject-type constraints, one of which is edition-only, so it will warn on
    a work item no matter what. Nothing is lost by skipping it: the Play Books URL is already
    published directly in the book's `sameAs` in the markup, which is where Google reads it.
  - The two volumes are typed differently — Volume One is `instance of: written work`
    (`Q47461344`), Volume Two is `instance of: cookbook` (`Q605076`). Both are valid, but they
    are not equally useful: `cookbook → book → written work` is a subclass chain, so `cookbook`
    says everything `written work` says *and* what kind of book it is. Volume One is the one to
    change, not Volume Two.

- **WikiData, the Organization.** `Q141171005`, created 2026-08-26 — `instance of: website`,
  `founder: Q138577229`, official website, inception 2018, United States, English, and the
  Facebook, Instagram, Pinterest and YouTube handles. It is now published in the
  Organization's `sameAs` on every page.

  **`P2397 YouTube channel ID` on that item was wrong and has been corrected** (2026-08-27).
  It read `UC0I81mHV9MdJXko-yrVphug` with a capital letter `I`; the real channel is
  `UC0l81mHV9MdJXko-yrVphug` with a lowercase letter `L`. The two are indistinguishable in
  most fonts, so this was found by resolving both rather than by reading them: fetching
  `youtube.com/feeds/videos.xml?channel_id=…` returns a 404 for the capital-I version and a
  feed titled "The Carrot Underground" for the lowercase-L one. Both were re-run after the fix
  — the item now carries the value that resolves, and the 404 on the old value is the control
  proving the two really are different strings. The markup always used the correct one.

---

## Tests

```
php tests/test-person-schema.php
```

226 assertions, no WordPress or Yoast install required. (`tests/test-book-on-own-page.php`
runs as a second process because it has to redefine the book list before the plugin loads;
the main suite invokes it and folds in its results.) The WordPress functions the snippet
calls are stubbed and the filter is run against three fixtures — the real `@graph` captured
from the live About page and from both live cookbook pages — so the assertions run against
the exact structure Yoast is producing on the site today, not an idealised version of it.

| Fixture | Captured from | On |
| --- | --- | --- |
| `fixture-live-graph.json` | `/about-connie-edwards-mcgaughy/` | 2026-08-17 |
| `fixture-book-page-1-graph.json` | `/the-carrot-underground-cookbook-vol-1/` | 2026-08-24 |
| `fixture-book-page-2-graph.json` | `/the-carrot-underground-cookbook-vol-2/` | 2026-08-24 |

Covered:

- the Person node is added, correctly typed, and wired to the ProfilePage in both directions
- `worksFor` / `affiliation` resolve to Yoast's existing Organization
- `image` reuses the existing `ImageObject` rather than adding a second one
- Yoast's `WebSite`, `BreadcrumbList` and `ImageObject` pieces come out byte-for-byte identical
- `founder` and `sameAs` are the *only* changes to the Organization node; `mainEntity`,
  `mentions` and `citation` the *only* changes to the ProfilePage
- no personal profile is left on the Organization and no brand profile is claimed by the
  Person — the two now share nothing at all. A **control** asserts a personal profile really
  was on the Organization to begin with, otherwise "none left" would pass on an empty set
- every profile removed from the Organization reappears on the Person; nothing is dropped
- the Organization `sameAs` is separated *site-wide*, not just on the About page, and that
  is the **only** difference off the About page
- every `@id` reference in the finished graph resolves to a node in that graph — no dangling references
- no two nodes share an `@id`
- running the filter twice cannot produce a second Connie, on any of the three pages
- degraded input (no ImageObject, no Organization, empty graph) still produces valid output
  rather than broken references
- both Books reference the author by `@id` rather than restating her inline — there is a
  specific assertion for this, because inlining the author is the exact mistake that would
  silently create a second Connie
- each Book keeps the same `@id` it had when it lived on the About page — the identity
  survived the move
- each Book's `url` is its page on this site while the Offer's url is the shop
- each cookbook page declares its Book as `mainEntity` and the Book points back
- the cover `ImageObject` is reused, so there is still only one on the page
- the About page carries a *reference* to each book, not a second copy of the definition
- the two volumes carry different Play Books ids and different titles — pasting the same one
  twice is the easy mistake, and it would merge the two books into one entity
- each volume carries its own WikiData item, and the two Q numbers differ
- the brand's WikiData item is added to the Organization and never listed twice
- both book pages are typed `ItemPage` in Yoast, asserted on the captured fixture — this one
  guards a WordPress setting rather than this file's own output
- no `isRelatedTo` on a `Book`; the series link is `isPartOf` / `hasPart`
- no press article is ever credited to Connie as `author`, each is linked to her via
  `mentions`, and the two articles that do not mention her stay out of the graph
- the journalists named in press bylines do not leak into the graph as extra `Person` nodes

All three generated graphs were run through `validator.schema.org`: **0 errors, 0 warnings**
each, with the `Person` resolving from the `ProfilePage`'s `mainEntity` and each `Book` from
its own page's.

---

## Site-side observations

Not schema, and not changed by this file — but found while checking the new pages, and all
easy wins:

- **The About page still links "my first two vegan e-cookbooks" to the Shopify store root.**
  Now that both cookbooks have pages on the site, that phrase should link to them instead.
  An internal link from the profile page to each book page is exactly the path Google
  follows to connect the entities.
- ~~**Volume Two's page is not set as an Item Page in Yoast.**~~ Fixed 2026-08-27. Both book
  pages now come out as `["WebPage", "ItemPage"]`, and the validator types both as
  *WebPage / ItemPage*. The Volume Two fixture has been recaptured to match, and a test now
  asserts the `ItemPage` type on the fixture so a future capture from a page whose *Page type*
  has been reset back to a plain Web Page fails loudly instead of silently.
- **A tracking parameter on the Volume One page's Goodreads link.** The "Goodreads ↗" link
  ends `?utm_source=chatgpt.com`. Worth stripping — the clean URL is what is in `sameAs`.
- **Cache.** The site is on BigScoots O2O with an `s-maxage` of a year in front of Cloudflare.
  After replacing the plugin file, purge from the BigScoots menu in the WP admin bar before
  validating anything, or you will be reading a stale page.

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
