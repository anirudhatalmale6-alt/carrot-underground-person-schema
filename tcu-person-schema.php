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
		'jobTitle'    => array(
			'Vegan Recipe Developer',
			'Cookbook Author',
			'Food Blogger',
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
		// Note the Goodreads entry: the canonical author URL is used rather than the
		// /veganconnie vanity URL, which is a 301 redirect to this address.
		'sameAs'      => array(
			'https://www.facebook.com/thecarrotunderground/',
			'https://x.com/veganconnie',
			'https://www.instagram.com/thecarrotunderground/',
			'https://www.linkedin.com/in/connie-edwards-mcgaughy',
			'https://www.pinterest.com/thecarrotunderground',
			'https://www.youtube.com/channel/UC0l81mHV9MdJXko-yrVphug',
			'https://www.goodreads.com/author/show/71756303.Connie_Edwards_McGaughy',
			// Add the WikiData entry here once you have its Q-number URL, e.g.
			// 'https://www.wikidata.org/wiki/Q123456789',
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
 * Add the Person to Yoast's graph and wire the ProfilePage to it.
 *
 * Priority 11 so this runs after Yoast's own pieces are in place.
 */
function tcu_person_schema_filter_graph( $graph, $context = null ) {
	if ( ! is_array( $graph ) || ! tcu_person_schema_is_target_page() ) {
		return $graph;
	}

	// Never add the Person twice - another plugin, or a second copy of this file,
	// may have already put it there.
	if ( tcu_person_schema_has_node( $graph, TCU_PERSON_ID ) ) {
		return $graph;
	}

	$person   = tcu_person_schema_build_node( $graph );
	$page_id  = tcu_person_schema_find_page_id( $graph );

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

	$graph[] = $person;

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
