<?php
/**
 * Plugin Name:  The Carrot Underground - Person Schema for Yoast
 * Description:  Adds one stable Person entity (Connie Edwards McGaughy) to Yoast SEO's
 *               existing schema @graph and makes the About page's ProfilePage point at it
 *               as its mainEntity. Yoast's Organization, WebSite, ProfilePage, BreadcrumbList
 *               and ImageObject pieces are left exactly as they are - nothing is replaced,
 *               nothing is duplicated.
 * Version:      1.0.0
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

		'description' => 'Connie Edwards McGaughy is a longtime vegan recipe developer, author, and creator of The Carrot Underground, based in San Diego, California.',

		// Topical signals - what this person is an authority on.
		'knowsAbout'  => array(
			'Vegan cooking',
			'Plant-based baking',
			'Vegan recipe development',
			'Vegan desserts',
		),

		// Profiles that identify Connie as a person / author.
		//
		// WikiData first: it is the one entry here that Google's Knowledge Graph reads
		// directly rather than merely as corroboration, so it carries the most weight.
		// Q138577229 was located on wikidata.org on 2026-08-17 - instance of human,
		// official website already pointing at thecarrotunderground.com.
		//
		// The Goodreads entry is the canonical author URL rather than the
		// /veganconnie vanity URL, which is a 301 redirect to this address.
		'sameAs'      => array(
			'https://www.wikidata.org/wiki/Q138577229',
			'https://www.facebook.com/thecarrotunderground/',
			'https://x.com/veganconnie',
			'https://www.instagram.com/thecarrotunderground/',
			'https://www.linkedin.com/in/connie-edwards-mcgaughy',
			'https://www.pinterest.com/thecarrotunderground',
			'https://www.youtube.com/channel/UC0l81mHV9MdJXko-yrVphug',
			'https://www.goodreads.com/author/show/71756303.Connie_Edwards_McGaughy',
		),

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
 * 'page' is where each Book's node is emitted. Both currently sit on the About
 * page, which is honest - that page's copy says "I recently published my first two
 * vegan e-cookbooks" and links to the shop, so the markup matches what a reader
 * can actually see. When the dedicated cookbook pages go live, change 'page' to
 * that page's ID and the Book moves with it. Nothing else needs touching: the
 * author link travels with the node.
 *
 * The @ids are rooted at the domain rather than at the About page, precisely so
 * that move does not change the identity of the Book.
 *
 * Wrapped in function_exists so the list can be overridden from elsewhere without
 * editing this file.
 */
if ( ! function_exists( 'tcu_person_schema_books' ) ) :
function tcu_person_schema_books() {
	return array(

		array(
			'page'           => TCU_PERSON_PAGE,
			'@id'            => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one',
			'name'           => 'The Carrot Underground Cookbook - Volume One: How to Host Plant-Based Parties Everyone Will Love',
			'description'    => 'Your essential companion for hosting unforgettable, 100% plant-based gatherings that everyone, vegan or not, will love. Connie Edwards McGaughy shares her favorite recipes, top tips and ideas for creating entirely plant-based parties.',
			'url'            => 'https://qf01dx-q0.myshopify.com/products/the-carrot-underground-digital-cookbook-volume-one',
			'image'          => 'https://cdn.shopify.com/s/files/1/0720/4886/9602/files/TCU-Cookbook-Vol-1-Cover.png?v=1729902018',
			'datePublished'  => '2024-10-01',
			'numberOfPages'  => 63,
			'genre'          => 'Vegan cookbook',
			'about'          => 'Vegan entertaining',
			'price'          => '9.99',
			'sameAs'         => array(
				'https://www.goodreads.com/book/show/256792999-the-carrot-underground-cookbook---volume-one',
				// Google Play Books link goes here once you send it.
			),
		),

		array(
			'page'           => TCU_PERSON_PAGE,
			'@id'            => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-two',
			'name'           => 'The Carrot Underground Cookbook - Volume Two: How to Bake the Best Vegan Desserts and Treats',
			'description'    => 'Everything you need for creating mouth-watering vegan desserts that everyone, even non-vegans, will adore. Connie Edwards McGaughy guides seasoned and first-time bakers through the art of baking without eggs or dairy.',
			'url'            => 'https://qf01dx-q0.myshopify.com/products/the-carrot-underground-cookbook-volume-two-how-to-bake-the-best-vegan-desserts-treats',
			'image'          => 'https://cdn.shopify.com/s/files/1/0720/4886/9602/files/TCU-Cookbook-Vol-Two-Cover.jpg?v=1732579285',
			'datePublished'  => '2024-11-01',
			'numberOfPages'  => 70,
			'genre'          => 'Vegan cookbook',
			'about'          => 'Vegan baking',
			'price'          => '9.99',
			'sameAs'         => array(
				'https://www.goodreads.com/book/show/256793045-the-carrot-underground-cookbook---volume-two',
				// Google Play Books link goes here once you send it.
			),
		),

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
 */
function tcu_person_schema_build_book( array $book, array $graph ) {
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

	// Self-published under the Carrot Underground name - the Shopify listings give
	// the vendor as "The Carrot Underground", so the brand is the publisher of record.
	if ( tcu_person_schema_has_node( $graph, TCU_ORGANIZATION_ID ) ) {
		$node['publisher'] = array( '@id' => TCU_ORGANIZATION_ID );
	}

	if ( ! empty( $book['price'] ) && ! empty( $book['url'] ) ) {
		$node['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $book['price'],
			'priceCurrency' => 'USD',
			'availability'  => 'https://schema.org/InStock',
			'url'           => $book['url'],
		);

		if ( tcu_person_schema_has_node( $graph, TCU_ORGANIZATION_ID ) ) {
			$node['offers']['seller'] = array( '@id' => TCU_ORGANIZATION_ID );
		}
	}

	return $node;
}

/**
 * Which Books belong on the page being rendered right now?
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

	$is_profile = tcu_person_schema_is_target_page();
	$books      = tcu_person_schema_books_for_this_page();

	if ( ! $is_profile && empty( $books ) ) {
		return $graph;
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

	$added = array();

	foreach ( $books as $book ) {
		if ( tcu_person_schema_has_node( $graph, $book['@id'] ) ) {
			continue;
		}

		$graph[] = tcu_person_schema_build_book( $book, $graph );
		$added[] = array( '@id' => $book['@id'] );
	}

	if ( ! empty( $added ) ) {
		// The Book nodes reference the author by @id, so she has to be in this graph.
		if ( ! $person_present ) {
			$graph[] = tcu_person_schema_build_person_stub();
		}

		// The page is what mentions these books - that is why their markup is allowed
		// to be here at all, and it is what connects them to the rest of the graph.
		if ( null !== $page_id ) {
			$existing = isset( $graph[ $page_id ]['mentions'] ) ? (array) $graph[ $page_id ]['mentions'] : array();
			$graph[ $page_id ]['mentions'] = array_merge( $existing, $added );
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
