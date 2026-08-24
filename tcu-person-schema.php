<?php
/**
 * Plugin Name:  The Carrot Underground - Person Schema for Yoast
 * Description:  Adds one stable Person entity (Connie Edwards McGaughy) to Yoast SEO's
 *               existing schema @graph and makes the About page's ProfilePage point at it
 *               as its mainEntity. Yoast's Organization, WebSite, ProfilePage, BreadcrumbList
 *               and ImageObject pieces are left exactly as they are - nothing is replaced,
 *               nothing is duplicated.
 *
 *               Also gives each cookbook a Book entity on its own page, and keeps the
 *               personal profiles on the Person and the brand profiles on the Organization.
 * Version:      1.1.0
 * Author:       PonyTechSolutions
 * Requires PHP: 7.4
 *
 * WHERE THIS GOES
 * ---------------
 * Preferred:  wp-content/mu-plugins/tcu-person-schema.php  (loads automatically, survives
 *             theme updates, and can be removed over FTP if anything ever goes wrong).
 * Also fine:  wp-content/plugins/tcu-person-schema/tcu-person-schema.php, then activate it.
 * Last resort: paste everything below the "CONFIGURATION" banner into the child theme's
 *             functions.php (drop the ABSPATH guard, keep the rest as-is).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * CONFIGURATION - this is the only block you should normally need to edit.
 * ====================================================================== */

/**
 * The About page that owns the Person entity.
 *
 * 1003 is the ID of the About page, confirmed against the site's REST API on
 * 2026-08-17. An ID is used rather than the slug because it survives the page
 * being renamed. The slug 'about-connie-edwards-mcgaughy' works just as well if
 * you prefer something readable.
 *
 * Do NOT put a full URL here. is_page() matches on ID / slug / title only, so a
 * URL silently matches nothing, the condition is never true, and the snippet
 * quietly does nothing at all while looking perfectly correct.
 */
define( 'TCU_PERSON_PAGE', 1003 );

/**
 * The permanent identifier for the Person entity.
 *
 * This is deliberately a hardcoded string rather than something derived from
 * get_permalink() at runtime. An @id is an identity, not an address: once Google
 * has associated this string with Connie, it must keep meaning "Connie" even if
 * the About page is later renamed or moved. Deriving it from the live permalink
 * would silently mint a brand new entity the day the slug changes and throw away
 * everything the old one had accumulated.
 *
 * Every Book / Article / Recipe entity added later must reference this exact string.
 */
define( 'TCU_PERSON_ID', 'https://thecarrotunderground.com/about-connie-edwards-mcgaughy/#/schema/person/connie-edwards-mcgaughy' );

/**
 * Yoast's existing Organization node @id. Confirmed against the live output of
 * https://thecarrotunderground.com/about-connie-edwards-mcgaughy/ on 2026-08-17.
 */
define( 'TCU_ORGANIZATION_ID', 'https://thecarrotunderground.com/#organization' );

/**
 * Add founder: {Connie} to Yoast's Organization node on the About page.
 *
 * This is purely additive - it does not remove or rewrite anything Yoast outputs.
 * It gives the Person <-> Organization relationship a second, opposite-direction
 * edge, which is what ties the two entities together for a Knowledge Panel.
 * Set to false if you would rather leave the Organization node completely untouched.
 */
define( 'TCU_LINK_ORGANIZATION_FOUNDER', true );

/**
 * Keep personal profiles on the Person and brand profiles on the Organization.
 *
 * Yoast's "Other profiles" list (Settings -> Site representation) is emitted as the
 * Organization's sameAs on every page of the site, and it currently contains two
 * profiles that identify Connie rather than the brand. When this is true, those
 * entries are removed from the Organization node as the graph is built, so each
 * profile corroborates exactly one entity.
 *
 * The list of what counts as personal is tcu_person_schema_personal_profiles()
 * below - which is the same list the Person publishes, so the two can never drift.
 *
 * THE TIDIER FIX is to delete those two lines in Yoast -> Settings -> Site
 * representation -> Other profiles, after which this does nothing at all and simply
 * stops them coming back. Doing it here as well means the output is right either way,
 * but be aware that while both are true the Yoast settings screen will list a profile
 * that the page no longer prints.
 */
define( 'TCU_SPLIT_ORG_SAMEAS', true );

/**
 * Tie the cookbooks together as volumes of one series.
 *
 * Each cookbook page carries a "Looking for Volume Two?" block linking to the other
 * volume, so this describes something a reader can actually see and follow.
 *
 * A BookSeries is the right shape for this rather than a direct book-to-book link:
 * isRelatedTo, the obvious-looking choice, is defined on Product and Service and NOT
 * on CreativeWork, so putting it on a Book is an unknown field. Volume One and Volume
 * Two genuinely are parts of one series, and isPartOf / hasPart says exactly that.
 *
 * Set to false to leave the two Books unconnected.
 */
define( 'TCU_LINK_SIBLING_BOOKS', true );

/** The series both cookbooks belong to. Domain-rooted, like the Book @ids. */
define( 'TCU_BOOK_SERIES_ID', 'https://thecarrotunderground.com/#/schema/series/carrot-underground-cookbook' );
define( 'TCU_BOOK_SERIES_NAME', 'The Carrot Underground Cookbook' );

/**
 * Profiles that identify Connie the person, as opposed to The Carrot Underground
 * the brand.
 *
 * This list does two jobs: it is the Person's sameAs, and it is what gets stripped
 * out of the Organization's sameAs. Keeping it in one place is deliberate - if the
 * two lists were maintained separately they would drift, and a profile sitting on
 * both entities is exactly the problem this is meant to solve.
 *
 * WikiData first: it is the one entry here that Google's Knowledge Graph reads
 * directly rather than merely as corroboration, so it carries the most weight.
 * Q138577229 - instance of human, official website pointing at thecarrotunderground.com.
 *
 * The Goodreads entry is the canonical author URL rather than the /veganconnie vanity
 * URL, which is a 301 redirect to this address.
 *
 * The brand accounts - Facebook, Instagram, Pinterest and the YouTube channel, which
 * is titled "The Carrot Underground" - are deliberately NOT here. They stay on the
 * Organization, where Yoast already publishes them.
 *
 * x.com/veganconnie is the one judgement call. The handle names a person, so it is
 * treated as hers; if that account actually posts as the brand, move this one line
 * into Yoast's Other profiles instead and it will swap over.
 */
function tcu_person_schema_personal_profiles() {
	return array(
		'https://www.wikidata.org/wiki/Q138577229',
		'https://www.linkedin.com/in/connie-edwards-mcgaughy',
		'https://www.goodreads.com/author/show/71756303.Connie_Edwards_McGaughy',
		'https://x.com/veganconnie',
	);
}

/**
 * The Person entity itself.
 */
function tcu_person_schema_definition() {
	return array(
		'name'        => 'Connie Edwards McGaughy',

		// An array is used rather than one long string so each title is a discrete
		// value. To go back to a single line, replace the array with the string
		// 'Vegan Recipe Developer, Author and Food Blogger'.
		//
		// These match the titles printed on the About page itself - "Longtime Vegan
		// Recipe Developer, Cookbook Author, Photographer, Founder of The Carrot
		// Underground". Structured data should describe what a reader can actually
		// see on the page, so if that line is reworded, reword this to match.
		// "Founder" is not repeated here because it is already expressed properly as
		// the founder edge on the Organization node.
		'jobTitle'    => array(
			'Vegan Recipe Developer',
			'Cookbook Author',
			'Food Blogger',
			'Photographer',
		),

		'description' => 'Connie Edwards McGaughy is a longtime vegan recipe developer, cookbook author, and founder of The Carrot Underground, based in San Diego, California.',

		// Topical signals - what this person is an authority on.
		'knowsAbout'  => array(
			'Vegan cooking',
			'Plant-based baking',
			'Vegan recipe development',
			'Vegan desserts',
		),

		// Profiles that identify Connie as a person / author. See the note on
		// tcu_person_schema_personal_profiles() for why the brand accounts are not here.
		'sameAs'      => tcu_person_schema_personal_profiles(),

		// Uncomment to publish a coarse location. Nothing more precise than
		// city/region should ever go in schema for a private individual.
		// 'homeLocation' => array(
		//     '@type'   => 'Place',
		//     'address' => array(
		//         '@type'           => 'PostalAddress',
		//         'addressLocality' => 'San Diego',
		//         'addressRegion'   => 'CA',
		//         'addressCountry'  => 'US',
		//     ),
		// ),

		// Uncomment if Connie wants her handle published as an alternate name.
		// 'alternateName' => 'Vegan Connie',
	);
}

/**
 * The published cookbooks.
 *
 * Each entry becomes a Book node authored by the Person above. Because both point
 * at TCU_PERSON_ID rather than restating her details, Google reads them as three
 * facts about one author rather than as three unrelated pages.
 *
 * 'page' is the Book's home - the page where its full definition is emitted, where
 * it becomes that page's mainEntity, and which its url points at. Both books now
 * live on their own dedicated pages (28270 and 28306, confirmed against the site's
 * REST API on 2026-08-24). The About page still says "I recently published my first
 * two vegan e-cookbooks", so it keeps a short reference to each under mentions -
 * a reference, not a second copy of the definition.
 *
 * The @ids are rooted at the domain rather than at any one page, precisely so that
 * this move did not change the identity of either Book. They are the same strings
 * that were published from the About page, so nothing Google has already read is
 * invalidated.
 *
 * 'url' is the book's page on this site - the canonical home for the work.
 * 'shopUrl' is where you actually buy it, and is used for the Offer only. Those are
 * two different facts and conflating them is what the previous version did.
 *
 * Wrapped in function_exists so the list can be overridden from elsewhere without
 * editing this file.
 */
if ( ! function_exists( 'tcu_person_schema_books' ) ) :
function tcu_person_schema_books() {
	return array(

		array(
			'page'           => 28270,
			'@id'            => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one',

			// The complete title, main title and subtitle together, exactly as the
			// "Book Details" panel on the page states it. schema.org has no separate
			// subtitle property on Book, so the two are one string.
			'name'           => 'The Carrot Underground Cookbook - Volume One: How to Host Plant-Based Parties Everyone Will Love',

			// Taken from the cookbook page's own opening paragraph, so the description
			// in the markup and the description a reader sees are the same claim.
			'description'    => 'The Carrot Underground Cookbook - Volume One is a 63-page digital e-book featuring 45 vegan recipes, party-planning tips, and creative ideas for hosting gatherings everyone can enjoy.',

			'url'            => 'https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-1/',
			'shopUrl'        => 'https://qf01dx-q0.myshopify.com/products/the-carrot-underground-digital-cookbook-volume-one',

			// Fallback only. On the book's own page the cover ImageObject that Yoast
			// already emits is reused instead of adding a second one for the same file.
			'image'          => 'https://thecarrotunderground.com/wp-content/uploads/2026/08/the-carrot-underground-cookbook-volume-one-cover.jpg',

			'datePublished'  => '2024-10-01',
			'numberOfPages'  => 63,
			'genre'          => 'Vegan cookbook',
			'about'          => 'Vegan entertaining',
			'price'          => '9.99',
			'sameAs'         => array(
				// Q141124581 - the WikiData item for this volume. Its author statement
				// already points at Q138577229, so the two sides agree.
				'https://www.wikidata.org/wiki/Q141124581',
				'https://www.goodreads.com/book/show/256792999-the-carrot-underground-cookbook---volume-one',
				'https://play.google.com/store/books/details?id=RzH1EQAAQBAJ',
			),
		),

		array(
			'page'           => 28306,
			'@id'            => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-two',
			'name'           => 'The Carrot Underground Cookbook - Volume Two: How to Bake the Best Vegan Desserts and Treats',
			'description'    => 'The Carrot Underground Cookbook - Volume Two is a 70-page digital vegan baking cookbook of 35 dessert recipes, baking tips, and practical guides for creating treats without eggs or dairy.',
			'url'            => 'https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-2/',
			'shopUrl'        => 'https://qf01dx-q0.myshopify.com/products/the-carrot-underground-cookbook-volume-two-how-to-bake-the-best-vegan-desserts-treats',
			'image'          => 'https://thecarrotunderground.com/wp-content/uploads/2026/08/TCU-cookbook-volume-two-cover.jpg',
			'datePublished'  => '2024-11-01',
			'numberOfPages'  => 70,
			'genre'          => 'Vegan cookbook',
			'about'          => 'Vegan baking',
			'price'          => '9.99',

			// No WikiData item for this volume yet - Volume One has one, this does not.
			// Add it here once it exists.
			'sameAs'         => array(
				'https://www.goodreads.com/book/show/256793045-the-carrot-underground-cookbook---volume-two',
				'https://play.google.com/store/books/details?id=OzX1EQAAQBAJ',
			),
		),

	);
}
endif;

/**
 * Press coverage: articles by other journalists that quote Connie as an expert.
 *
 * READ THIS BEFORE ADDING TO THE LIST.
 *
 * Connie is NOT the author of any of these. Every one carries another journalist's
 * byline and quotes her as a source. So each node credits the real author and
 * publisher, and links to Connie with "mentions" - the article references her, it was
 * not written by her and it is not about her.
 *
 * Claiming authorship of someone else's article would be false structured data. It is
 * also the single easiest way to get an entity distrusted, which is the opposite of
 * what this whole file is for. If you add a row here, open the article first and read
 * the byline.
 *
 * Every URL below is already linked from the About page, so this markup describes
 * something a reader of that page can actually see and follow.
 *
 * Headlines and dates are the publishers' own, taken from their pages rather than from
 * the summary list, so they match what is actually published.
 */
if ( ! function_exists( 'tcu_person_schema_press' ) ) :
function tcu_person_schema_press() {
	return array(

		array(
			'slug'          => 'aarp-clever-ways-to-cook-carrots',
			'url'           => 'https://www.aarp.org/home-living/clever-ways-to-cook-carrots/',
			'headline'      => 'Clever Ways to Cook Carrots',
			'datePublished' => '2026-01-23',
			'author'        => 'Leslie Quander Wooldridge',
			'publisher'     => 'AARP',
		),

		array(
			'slug'          => 'aarp-white-bean-recipes',
			'url'           => 'https://www.aarp.org/home-living/white-bean-recipes/',
			'headline'      => 'Flavorful, Protein-Packed White Bean Recipes',
			'datePublished' => '2025-11-19',
			'author'        => 'Leslie Quander Wooldridge',
			'publisher'     => 'AARP',
		),

		array(
			'slug'          => 'huffpost-egg-substitutes',
			'url'           => 'https://www.huffpost.com/entry/best-egg-substitutes_l_67b78b16e4b01f172607fc6b',
			'headline'      => 'There’s No Exact Baking Substitute For Eggs — But These Are Close In A Pinch',
			'datePublished' => '2025-02-27',
			'author'        => 'Jamie Davis Smith',
			'publisher'     => 'HuffPost',
		),

		array(
			'slug'          => 'chowhound-original-recipe-mistakes',
			'url'           => 'https://www.chowhound.com/1848295/mistakes-creating-original-recipe/',
			'headline'      => '15 Mistakes You\'re Making When Creating An Original Recipe',
			'datePublished' => '2025-05-06',
			'author'        => 'Sarah Moore',
			'publisher'     => 'Chowhound',
		),

		array(
			'slug'          => 'chowhound-popular-herbs',
			'url'           => 'https://www.chowhound.com/1891439/how-to-use-different-herbs/',
			'headline'      => 'How To Use 14 Popular Herbs To Their Full Potential',
			'datePublished' => '2025-06-24',
			'author'        => 'Sarah Moore',
			'publisher'     => 'Chowhound',
		),

		array(
			'slug'          => 'chowhound-vegan-baking-staples',
			'url'           => 'https://www.chowhound.com/1797237/staple-vegan-baking-ingredients/',
			'headline'      => '18 Staple Ingredients You Need For Vegan Baking',
			'datePublished' => '2025-03-02',
			'author'        => 'Sarah Moore',
			'publisher'     => 'Chowhound',
		),

		array(
			'slug'          => 'apartmentguide-football-party-ideas',
			'url'           => 'https://www.apartmentguide.com/blog/top-football-game-party-ideas/',
			'headline'      => 'Top Football Game Party Ideas to Score Big with Your Guests',
			'datePublished' => '2024-11-22',
			'author'        => 'Freda Nkrumah',
			'publisher'     => 'Apartment Guide',
		),

		/*
		 * DELIBERATELY NOT LISTED - the USA Today "Ted Lasso shortbread biscuits" piece
		 * and the CNN "6 inexpensive ways to eat healthy at home" piece.
		 *
		 * Both were on the supplied list, and both are worth having as backlinks, but
		 * neither one mentions Connie. I searched the full HTML of each: zero hits for
		 * "Connie", zero for "McGaughy". What each actually contains is a link to one of
		 * her recipes - the vegan shortbread on USA Today, the vegan bolognese on CNN.
		 *
		 * That is a link to her work, not coverage of her. Listing them as articles that
		 * mention the person would be asserting something the pages do not say.
		 */

	);
}
endif;

/* =========================================================================
 * IMPLEMENTATION - no edits needed below this line.
 * ====================================================================== */

/**
 * Is this the request that owns the Person entity?
 *
 * The Person node is defined once, on the About page, and referenced by @id from
 * anywhere else. Emitting the full definition on every page of the site would give
 * Google the same entity over and over with no single canonical home.
 */
function tcu_person_schema_is_target_page() {
	return is_page( TCU_PERSON_PAGE );
}

/**
 * Build the Person node.
 *
 * @param array $graph The graph Yoast has assembled so far, used to reuse existing
 *                     nodes (the About page's ImageObject) instead of duplicating them.
 */
function tcu_person_schema_build_node( array $graph ) {
	$person = array_merge(
		array(
			'@type' => 'Person',
			'@id'   => TCU_PERSON_ID,
		),
		tcu_person_schema_definition()
	);

	$page_id = tcu_person_schema_find_page_id( $graph );

	// url points at the human-readable page, not at the #fragment identifier.
	$person['url'] = tcu_person_schema_page_url( $graph, $page_id );

	if ( null !== $page_id ) {
		$person['mainEntityOfPage'] = array( '@id' => $graph[ $page_id ]['@id'] );
	}

	// Reuse the ImageObject Yoast already emits for this page rather than adding a
	// second ImageObject pointing at the same file.
	$image_id = tcu_person_schema_find_primary_image_id( $graph );
	if ( null !== $image_id ) {
		$person['image'] = array( '@id' => $graph[ $image_id ]['@id'] );
	} else {
		$person['image'] = array(
			'@type'      => 'ImageObject',
			'@id'        => TCU_PERSON_ID . '/image',
			'url'        => 'https://thecarrotunderground.com/wp-content/uploads/2025/02/Copy-of-con-in-kitchen-2c.jpg',
			'contentUrl' => 'https://thecarrotunderground.com/wp-content/uploads/2025/02/Copy-of-con-in-kitchen-2c.jpg',
		);
	}

	// The relationship to The Carrot Underground. worksFor and affiliation are both
	// emitted because they answer slightly different questions and Google reads both.
	if ( tcu_person_schema_has_node( $graph, TCU_ORGANIZATION_ID ) ) {
		$person['worksFor']    = array( '@id' => TCU_ORGANIZATION_ID );
		$person['affiliation'] = array( '@id' => TCU_ORGANIZATION_ID );
	}

	return $person;
}

/**
 * Build a Book node from one entry in tcu_person_schema_books().
 *
 * @param array $book     One entry from the list above.
 * @param array $graph    The graph so far, used to reuse nodes rather than duplicate them.
 * @param bool  $own_page True when this is the book's own dedicated page, in which case
 *                        the page's cover image is reused and mainEntityOfPage is set.
 */
function tcu_person_schema_build_book( array $book, array $graph, $own_page = false ) {
	$node = array(
		'@type'         => 'Book',
		'@id'           => $book['@id'],
		'name'          => $book['name'],
		'author'        => array( '@id' => TCU_PERSON_ID ),
		'bookFormat'    => 'https://schema.org/EBook',
		'inLanguage'    => 'en',
	);

	foreach ( array( 'description', 'url', 'image', 'datePublished', 'numberOfPages', 'genre', 'sameAs' ) as $key ) {
		if ( ! empty( $book[ $key ] ) ) {
			$node[ $key ] = $book[ $key ];
		}
	}

	if ( ! empty( $book['about'] ) ) {
		$node['about'] = array(
			'@type' => 'Thing',
			'name'  => $book['about'],
		);
	}

	$page_index = tcu_person_schema_find_page_id( $graph );

	if ( $own_page && null !== $page_index && ! empty( $graph[ $page_index ]['@id'] ) ) {
		// This page is about this book, and the book says so back.
		$node['mainEntityOfPage'] = array( '@id' => $graph[ $page_index ]['@id'] );

		// Reuse the cover ImageObject Yoast already emits for the page rather than
		// adding a second node pointing at the same file.
		$image_index = tcu_person_schema_find_primary_image_id( $graph );
		if ( null !== $image_index ) {
			$node['image'] = array( '@id' => $graph[ $image_index ]['@id'] );
		}
	}

	// Self-published under the Carrot Underground name - the Shopify listings give
	// the vendor as "The Carrot Underground", so the brand is the publisher of record.
	if ( tcu_person_schema_has_node( $graph, TCU_ORGANIZATION_ID ) ) {
		$node['publisher'] = array( '@id' => TCU_ORGANIZATION_ID );
	}

	// The Offer points at the shop, because that is where the transaction happens.
	// The Book's own url points at the page on this site. Same book, two different
	// facts - the previous version used the Shopify URL for both.
	$buy = ! empty( $book['shopUrl'] ) ? $book['shopUrl'] : ( ! empty( $book['url'] ) ? $book['url'] : '' );

	if ( ! empty( $book['price'] ) && '' !== $buy ) {
		$node['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $book['price'],
			'priceCurrency' => 'USD',
			'availability'  => 'https://schema.org/InStock',
			'url'           => $buy,
		);

		if ( tcu_person_schema_has_node( $graph, TCU_ORGANIZATION_ID ) ) {
			$node['offers']['seller'] = array( '@id' => TCU_ORGANIZATION_ID );
		}
	}

	return $node;
}

/**
 * A reference-only Book, for pages that link to a cookbook without being its home.
 *
 * Same principle as the Person stub below: JSON-LD merges nodes by @id, so this and
 * the full definition on the book's own page are read as one Book described in more
 * detail in one place than the other. It exists so that a page which mentions a book
 * has something to point at instead of a reference that goes nowhere.
 */
function tcu_person_schema_build_book_stub( array $book ) {
	return array(
		'@type'  => 'Book',
		'@id'    => $book['@id'],
		'name'   => $book['name'],
		'url'    => $book['url'],
		'author' => array( '@id' => TCU_PERSON_ID ),
	);
}

/**
 * Build an Article node for one press mention.
 *
 * The @id is a fragment on this domain rather than the article's own URL. These are
 * other publishers' pages; describing them from here is fine, but claiming to be the
 * canonical definition of their entity is not, and it would collide with whatever
 * markup they already publish. The real address goes in url.
 */
function tcu_person_schema_build_press( array $item ) {
	$node = array(
		'@type'    => 'Article',
		'@id'      => 'https://thecarrotunderground.com/#/schema/press/' . $item['slug'],
		'url'      => $item['url'],
		'headline' => $item['headline'],

		// The journalist who wrote it. Never Connie - see the note on
		// tcu_person_schema_press().
		'author'   => array(
			'@type' => 'Person',
			'name'  => $item['author'],
		),

		'publisher' => array(
			'@type' => 'Organization',
			'name'  => $item['publisher'],
		),

		// The relationship that is actually true: the article references her.
		'mentions' => array( '@id' => TCU_PERSON_ID ),
	);

	if ( ! empty( $item['datePublished'] ) ) {
		$node['datePublished'] = $item['datePublished'];
	}

	return $node;
}

/**
 * Which Books call the page being rendered right now their home?
 */
function tcu_person_schema_books_for_this_page() {
	$books = array();

	foreach ( tcu_person_schema_books() as $book ) {
		$page = isset( $book['page'] ) ? $book['page'] : TCU_PERSON_PAGE;

		if ( is_page( $page ) ) {
			$books[] = $book;
		}
	}

	return $books;
}

/**
 * The other books - the ones whose home is some other page.
 *
 * These get a stub and a mentions edge on the About page (whose copy says she has
 * published two cookbooks) and on each cookbook page (which links to the other volume).
 */
function tcu_person_schema_books_elsewhere() {
	$books = array();

	foreach ( tcu_person_schema_books() as $book ) {
		$page = isset( $book['page'] ) ? $book['page'] : TCU_PERSON_PAGE;

		if ( ! is_page( $page ) ) {
			$books[] = $book;
		}
	}

	return $books;
}

/**
 * Keep the personal profiles off the Organization node.
 *
 * Yoast emits its "Other profiles" list as the Organization's sameAs on every page.
 * Two of those entries identify Connie rather than the brand, and a profile listed
 * on both entities corroborates neither of them cleanly. This removes them.
 *
 * Purely subtractive, and only ever removes URLs that this file publishes on the
 * Person - so nothing can go missing from the graph as a whole.
 */
function tcu_person_schema_split_org_sameas( array $graph ) {
	if ( ! TCU_SPLIT_ORG_SAMEAS ) {
		return $graph;
	}

	$index = tcu_person_schema_find_node_index( $graph, TCU_ORGANIZATION_ID );

	if ( null === $index || empty( $graph[ $index ]['sameAs'] ) ) {
		return $graph;
	}

	$personal = array_map( 'tcu_person_schema_normalise_url', tcu_person_schema_personal_profiles() );
	$current  = array_values( (array) $graph[ $index ]['sameAs'] );

	$kept = array_values(
		array_filter(
			$current,
			function ( $url ) use ( $personal ) {
				return ! in_array( tcu_person_schema_normalise_url( $url ), $personal, true );
			}
		)
	);

	if ( $kept === $current ) {
		return $graph;
	}

	if ( empty( $kept ) ) {
		unset( $graph[ $index ]['sameAs'] );
	} else {
		$graph[ $index ]['sameAs'] = $kept;
	}

	return $graph;
}

/**
 * Compare URLs without tripping over a trailing slash or a capital letter in the host.
 */
function tcu_person_schema_normalise_url( $url ) {
	return rtrim( strtolower( trim( (string) $url ) ), '/' );
}

/**
 * A reference-only Person, for pages that carry a Book but not the full profile.
 *
 * When a cookbook eventually gets its own page, that page's Book node still points
 * at TCU_PERSON_ID. Without this, that would be a reference to a node which is not
 * in the page's graph. Emitting a minimal stub is not a second Connie: JSON-LD
 * merges nodes by @id, so the stub and the full profile on the About page are read
 * as one entity, described in more detail in one place than the other.
 */
function tcu_person_schema_build_person_stub() {
	$definition = tcu_person_schema_definition();

	return array(
		'@type' => 'Person',
		'@id'   => TCU_PERSON_ID,
		'name'  => $definition['name'],
		'url'   => strtok( TCU_PERSON_ID, '#' ),
	);
}

/**
 * Add the Person and any Books to Yoast's graph, and wire the page to them.
 *
 * Priority 11 so this runs after Yoast's own pieces are in place.
 */
function tcu_person_schema_filter_graph( $graph, $context = null ) {
	if ( ! is_array( $graph ) ) {
		return $graph;
	}

	// The Organization node is emitted on every page, so this runs on every page.
	$graph = tcu_person_schema_split_org_sameas( $graph );

	$is_profile = tcu_person_schema_is_target_page();
	$books      = tcu_person_schema_books_for_this_page();

	if ( ! $is_profile && empty( $books ) ) {
		return array_values( $graph );
	}

	$page_id = tcu_person_schema_find_page_id( $graph );

	/* ---- the Person ---- */

	// Never add the Person twice - another plugin, or a second copy of this file,
	// may have already put it there.
	$person_present = tcu_person_schema_has_node( $graph, TCU_PERSON_ID );

	if ( $is_profile && ! $person_present ) {
		$person = tcu_person_schema_build_node( $graph );

		// The ProfilePage declares Connie as the thing the page is actually about.
		if ( null !== $page_id ) {
			$graph[ $page_id ]['mainEntity'] = array( '@id' => TCU_PERSON_ID );
		}

		// The opposite-direction edge on the Organization.
		if ( TCU_LINK_ORGANIZATION_FOUNDER ) {
			$org_id = tcu_person_schema_find_node_index( $graph, TCU_ORGANIZATION_ID );
			if ( null !== $org_id && ! isset( $graph[ $org_id ]['founder'] ) ) {
				$graph[ $org_id ]['founder'] = array( '@id' => TCU_PERSON_ID );
			}
		}

		$graph[]        = $person;
		$person_present = true;
	}

	/* ---- the Books ---- */

	// Full definitions for the books whose home this page is.
	$added = array();

	foreach ( $books as $book ) {
		if ( tcu_person_schema_has_node( $graph, $book['@id'] ) ) {
			continue;
		}

		$graph[] = tcu_person_schema_build_book( $book, $graph, ! $is_profile );
		$added[] = array( '@id' => $book['@id'] );
	}

	// Stubs for the books this page merely refers to: the other volume on a cookbook
	// page, both volumes on the About page.
	$referenced = array();

	foreach ( tcu_person_schema_books_elsewhere() as $book ) {
		if ( tcu_person_schema_has_node( $graph, $book['@id'] ) ) {
			continue;
		}

		$graph[]      = tcu_person_schema_build_book_stub( $book );
		$referenced[] = array( '@id' => $book['@id'] );
	}

	if ( ! empty( $added ) || ! empty( $referenced ) ) {
		// Every Book node references the author by @id, so she has to be in this graph.
		if ( ! $person_present ) {
			$graph[]        = tcu_person_schema_build_person_stub();
			$person_present = true;
		}

		$mentions = array_merge( $added, $referenced );

		if ( null !== $page_id ) {
			// On a cookbook's own page, the book is what the page is about. On the
			// About page mainEntity is already Connie, and the books are mentioned.
			if ( ! $is_profile && ! empty( $added ) && ! isset( $graph[ $page_id ]['mainEntity'] ) ) {
				$graph[ $page_id ]['mainEntity'] = array_shift( $mentions );
			}

			if ( ! empty( $mentions ) ) {
				$existing = isset( $graph[ $page_id ]['mentions'] ) ? (array) $graph[ $page_id ]['mentions'] : array();
				$graph[ $page_id ]['mentions'] = array_merge( $existing, $mentions );
			}
		}

		// "Looking for Volume Two?" - the cross-link the page already shows a reader,
		// expressed as the series the two volumes both belong to.
		$all_books = array_merge( $added, $referenced );

		if ( TCU_LINK_SIBLING_BOOKS && ! empty( $added ) && count( $all_books ) > 1
			&& ! tcu_person_schema_has_node( $graph, TCU_BOOK_SERIES_ID ) ) {

			$graph[] = array(
				'@type'   => 'BookSeries',
				'@id'     => TCU_BOOK_SERIES_ID,
				'name'    => TCU_BOOK_SERIES_NAME,
				'author'  => array( '@id' => TCU_PERSON_ID ),
				'hasPart' => array_values( $all_books ),
			);

			// Only the full definitions declare membership; the stubs stay minimal and
			// pick it up from the series' own hasPart.
			foreach ( $added as $ref ) {
				$index = tcu_person_schema_find_node_index( $graph, $ref['@id'] );

				if ( null !== $index ) {
					$graph[ $index ]['isPartOf'] = array( '@id' => TCU_BOOK_SERIES_ID );
				}
			}
		}
	}

	/* ---- press coverage ---- */

	// Press lives with the profile: it is evidence about the person, so it belongs
	// where the person is described, not scattered across the site.
	if ( $is_profile ) {
		$cited = array();

		foreach ( tcu_person_schema_press() as $item ) {
			$node = tcu_person_schema_build_press( $item );

			if ( tcu_person_schema_has_node( $graph, $node['@id'] ) ) {
				continue;
			}

			$graph[] = $node;
			$cited[] = array( '@id' => $node['@id'] );
		}

		if ( ! empty( $cited ) && null !== $page_id ) {
			$existing = isset( $graph[ $page_id ]['citation'] ) ? (array) $graph[ $page_id ]['citation'] : array();
			$graph[ $page_id ]['citation'] = array_merge( $existing, $cited );
		}
	}

	return array_values( $graph );
}
add_filter( 'wpseo_schema_graph', 'tcu_person_schema_filter_graph', 11, 2 );

/* ------------------------------------------------------------------ *
 * Small graph helpers.
 * ------------------------------------------------------------------ */

/**
 * Index of the node representing this page. ProfilePage wins over plain WebPage.
 */
function tcu_person_schema_find_page_id( array $graph ) {
	$fallback = null;

	foreach ( $graph as $i => $piece ) {
		$types = isset( $piece['@type'] ) ? (array) $piece['@type'] : array();

		if ( in_array( 'ProfilePage', $types, true ) ) {
			return $i;
		}

		if ( null === $fallback && in_array( 'WebPage', $types, true ) ) {
			$fallback = $i;
		}
	}

	return $fallback;
}

function tcu_person_schema_find_primary_image_id( array $graph ) {
	foreach ( $graph as $i => $piece ) {
		$types = isset( $piece['@type'] ) ? (array) $piece['@type'] : array();

		if ( in_array( 'ImageObject', $types, true )
			&& isset( $piece['@id'] )
			&& false !== strpos( $piece['@id'], '#primaryimage' ) ) {
			return $i;
		}
	}

	return null;
}

function tcu_person_schema_find_node_index( array $graph, $id ) {
	foreach ( $graph as $i => $piece ) {
		if ( isset( $piece['@id'] ) && $piece['@id'] === $id ) {
			return $i;
		}
	}

	return null;
}

function tcu_person_schema_has_node( array $graph, $id ) {
	return null !== tcu_person_schema_find_node_index( $graph, $id );
}

/**
 * A clean page URL with no #fragment, for the Person's url property.
 */
function tcu_person_schema_page_url( array $graph, $page_id ) {
	if ( null !== $page_id && ! empty( $graph[ $page_id ]['url'] ) ) {
		return $graph[ $page_id ]['url'];
	}

	if ( null !== $page_id && ! empty( $graph[ $page_id ]['@id'] ) ) {
		return strtok( $graph[ $page_id ]['@id'], '#' );
	}

	$permalink = get_permalink();

	return $permalink ? $permalink : strtok( TCU_PERSON_ID, '#' );
}

/* =========================================================================
 * REFERENCING CONNIE FROM OTHER ENTITIES LATER
 * -------------------------------------------------------------------------
 * When the two cookbook pages go live, their Book entities must point at the
 * SAME identifier rather than restating the author inline:
 *
 *     'author' => array( '@id' => TCU_PERSON_ID ),
 *
 * That is the whole point of pinning the @id above. Restating name/image/sameAs
 * on each Book page would create a second, third and fourth Connie as far as
 * Google is concerned, and the entity signal gets split between them.
 * ====================================================================== */
