<?php
/**
 * Standalone test harness for tcu-person-schema.php.
 *
 * There is no WordPress and no Yoast here. Instead the handful of WordPress
 * functions the snippet actually calls are stubbed, and the filter is fed the
 * real @graph captured from the live About page
 * (tests/fixture-live-graph.json, pulled 2026-08-17) so the assertions run
 * against the exact structure Yoast is producing on the site today.
 *
 * Run:  php tests/test-person-schema.php
 */

define( 'ABSPATH', __DIR__ . '/' );

/* ---------------------------------------------------------------- *
 * WordPress stubs
 * ---------------------------------------------------------------- */

/**
 * The page currently being rendered, as WordPress would see it: ID, slug, title.
 * The real is_page() matches any of the three, so the stub does too.
 */
$GLOBALS['stub_current_page'] = array( 1003, 'about-connie-edwards-mcgaughy', 'About Connie Edwards McGaughy' );
$GLOBALS['stub_filters']      = array();

function is_page( $page ) {
	foreach ( (array) $page as $candidate ) {
		if ( in_array( $candidate, $GLOBALS['stub_current_page'], true ) ) {
			return true;
		}
	}
	return false;
}

function get_permalink() {
	return 'https://thecarrotunderground.com/' . $GLOBALS['stub_current_page'][1] . '/';
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['stub_filters'][ $hook ][] = $callback;
	return true;
}

require __DIR__ . '/../tcu-person-schema.php';

/* ---------------------------------------------------------------- *
 * Tiny assertion helpers
 * ---------------------------------------------------------------- */

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function ok( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pass']++;
		echo "  PASS  $label\n";
		return;
	}
	$GLOBALS['fail']++;
	echo "  FAIL  $label\n";
}

function is_same( $actual, $expected, $label ) {
	if ( $actual === $expected ) {
		$GLOBALS['pass']++;
		echo "  PASS  $label\n";
		return;
	}
	$GLOBALS['fail']++;
	echo "  FAIL  $label\n";
	echo "        expected: " . json_encode( $expected ) . "\n";
	echo "        actual:   " . json_encode( $actual ) . "\n";
}

function heading( $text ) {
	echo "\n$text\n";
}

function live_graph() {
	return json_decode( file_get_contents( __DIR__ . '/fixture-live-graph.json' ), true );
}

function node_by_id( array $graph, $id ) {
	foreach ( $graph as $piece ) {
		if ( isset( $piece['@id'] ) && $piece['@id'] === $id ) {
			return $piece;
		}
	}
	return null;
}

function node_by_type( array $graph, $type ) {
	foreach ( $graph as $piece ) {
		if ( in_array( $type, (array) $piece['@type'], true ) ) {
			return $piece;
		}
	}
	return null;
}

/**
 * Walk the whole structure and collect every { "@id": "..." } that is used as a
 * pure reference (an object whose only key is @id).
 */
function collect_references( $node, array &$found ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['@id'] ) && 1 === count( $node ) ) {
		$found[] = $node['@id'];
		return;
	}
	foreach ( $node as $value ) {
		collect_references( $value, $found );
	}
}

/* ================================================================ *
 * 1. The About page
 * ================================================================ */

heading( '1. On the About page' );

$GLOBALS['stub_current_page'] = array( 1003, 'about-connie-edwards-mcgaughy', 'About Connie Edwards McGaughy' );

$before = live_graph();
$after  = tcu_person_schema_filter_graph( $before );

ok( null === node_by_type( $before, 'Person' ), 'live graph has no Person today (the problem being fixed)' );

$person = node_by_id( $after, TCU_PERSON_ID );
ok( null !== $person, 'a Person node is added' );
is_same( count( $after ), count( $before ) + 10, 'ten nodes are added - the Person, two Books, seven press articles - and nothing is dropped' );
is_same( $person['@type'], 'Person', 'it is typed Person' );
is_same( $person['name'], 'Connie Edwards McGaughy', 'name is set' );
ok( ! empty( $person['jobTitle'] ), 'jobTitle is set' );
is_same( $person['worksFor'], array( '@id' => TCU_ORGANIZATION_ID ), 'worksFor references the existing Organization' );
is_same( $person['affiliation'], array( '@id' => TCU_ORGANIZATION_ID ), 'affiliation references the existing Organization' );

is_same(
	$person['url'],
	'https://thecarrotunderground.com/about-connie-edwards-mcgaughy/',
	'url is the clean page URL with no #fragment'
);

is_same(
	$person['image'],
	array( '@id' => 'https://thecarrotunderground.com/about-connie-edwards-mcgaughy/#primaryimage' ),
	'image reuses the ImageObject Yoast already emits (no duplicate ImageObject)'
);

$image_nodes = 0;
foreach ( $after as $piece ) {
	if ( in_array( 'ImageObject', (array) $piece['@type'], true ) ) {
		$image_nodes++;
	}
}
is_same( $image_nodes, 1, 'still only one ImageObject in the graph' );

heading( '2. The ProfilePage points at the Person' );

$profile = node_by_type( $after, 'ProfilePage' );
ok( null !== $profile, 'the ProfilePage piece is still there' );
is_same( $profile['mainEntity'], array( '@id' => TCU_PERSON_ID ), 'ProfilePage.mainEntity references the Person' );
is_same(
	$person['mainEntityOfPage'],
	array( '@id' => 'https://thecarrotunderground.com/about-connie-edwards-mcgaughy/' ),
	'Person.mainEntityOfPage points back at the page (link closed in both directions)'
);

heading( '3. Yoast\'s own pieces survive untouched' );

foreach ( array( 'WebSite', 'BreadcrumbList', 'ImageObject' ) as $type ) {
	is_same(
		node_by_type( $after, $type ),
		node_by_type( $before, $type ),
		"$type piece is byte-for-byte unchanged"
	);
}

$org_before = node_by_id( $before, TCU_ORGANIZATION_ID );
$org_after  = node_by_id( $after, TCU_ORGANIZATION_ID );

is_same( $org_after['founder'], array( '@id' => TCU_PERSON_ID ), 'Organization gains founder -> Person' );

$org_compare            = $org_after;
$org_expected           = $org_before;
$org_expected['sameAs'] = $org_after['sameAs'];
unset( $org_compare['founder'] );
is_same( $org_compare, $org_expected, 'founder and sameAs are the ONLY changes to the Organization node' );

heading( '3b. Personal profiles and brand profiles are separated' );

ok(
	! empty( $org_before['sameAs'] ),
	'the fixture Organization does carry a sameAs list (otherwise this proves nothing)'
);

$personal = tcu_person_schema_personal_profiles();

// A control: at least one personal profile really was on the Organization to begin
// with. Without this the "removed" assertions below would pass on an empty set.
$was_on_org = array_values( array_intersect( $org_before['sameAs'], $personal ) );
ok( ! empty( $was_on_org ), 'at least one personal profile was on the Organization before (control)' );

$still_on_org = array_values( array_intersect( $org_after['sameAs'], $personal ) );
is_same( $still_on_org, array(), 'no personal profile is left on the Organization' );

foreach ( array(
	'https://www.facebook.com/thecarrotunderground/',
	'https://www.instagram.com/thecarrotunderground/',
	'https://www.pinterest.com/thecarrotunderground',
	'https://www.youtube.com/channel/UC0l81mHV9MdJXko-yrVphug',
) as $brand ) {
	$host = parse_url( $brand, PHP_URL_HOST );
	ok( in_array( $brand, $org_after['sameAs'], true ), "$host stays on the Organization" );
	ok( ! in_array( $brand, $person['sameAs'], true ), "$host is not also claimed by the Person" );
}

is_same(
	array_values( array_intersect( $org_after['sameAs'], $person['sameAs'] ) ),
	array(),
	'the Person and the Organization now share no profile at all'
);

// Subtractive only: nothing may vanish from the graph as a whole.
$lost = array_values( array_diff( $org_before['sameAs'], $org_after['sameAs'], $person['sameAs'] ) );
is_same( $lost, array(), 'every profile removed from the Organization reappears on the Person - none is simply dropped' );

$profile_before = node_by_type( $before, 'ProfilePage' );
$profile_after  = node_by_type( $after, 'ProfilePage' );
unset( $profile_after['mainEntity'], $profile_after['mentions'], $profile_after['citation'] );
is_same( $profile_after, $profile_before, 'mainEntity, mentions and citation are the ONLY changes to the ProfilePage node' );

$types_before = array();
$types_after  = array();
foreach ( $before as $p ) { $types_before[] = json_encode( $p['@type'] ); }
foreach ( $after as $p )  { $types_after[]  = json_encode( $p['@type'] ); }
is_same( count( array_diff( $types_before, $types_after ) ), 0, 'no existing entity type disappeared' );

heading( '4. No dangling @id references' );

$ids = array();
foreach ( $after as $piece ) {
	if ( isset( $piece['@id'] ) ) {
		$ids[] = $piece['@id'];
	}
	// Nested nodes that declare their own @id (Yoast's logo) count too.
	foreach ( $piece as $value ) {
		if ( is_array( $value ) && isset( $value['@id'], $value['@type'] ) ) {
			$ids[] = $value['@id'];
		}
	}
}

$refs = array();
collect_references( $after, $refs );
$dangling = array_values( array_unique( array_diff( $refs, $ids ) ) );

ok( ! empty( $refs ), 'the graph does use @id references' );
is_same( $dangling, array(), 'every @id reference resolves to a node in the same graph' );
is_same( count( $ids ), count( array_unique( $ids ) ), 'no two nodes share an @id' );

heading( '5. sameAs hygiene' );

is_same(
	count( $person['sameAs'] ),
	count( array_unique( $person['sameAs'] ) ),
	'no duplicate sameAs entries'
);

$bad = array();
foreach ( $person['sameAs'] as $url ) {
	if ( 0 !== strpos( $url, 'https://' ) || false !== strpos( $url, ' ' ) ) {
		$bad[] = $url;
	}
}
is_same( $bad, array(), 'every sameAs is an absolute https URL' );

ok(
	in_array( 'https://www.goodreads.com/author/show/71756303.Connie_Edwards_McGaughy', $person['sameAs'], true ),
	'the Goodreads author profile is present, as the canonical URL rather than the redirect'
);
ok(
	in_array( 'https://www.linkedin.com/in/connie-edwards-mcgaughy', $person['sameAs'], true ),
	'the personal LinkedIn profile now lives on the Person'
);
ok(
	in_array( 'https://www.wikidata.org/wiki/Q138577229', $person['sameAs'], true ),
	'the WikiData item is present'
);
is_same(
	$person['sameAs'][0],
	'https://www.wikidata.org/wiki/Q138577229',
	'WikiData is listed first - the strongest signal in the list'
);

is_same(
	$person['description'],
	'Connie Edwards McGaughy is a longtime vegan recipe developer, cookbook author, and founder of The Carrot Underground, based in San Diego, California.',
	'description uses the wording Connie asked for - "cookbook author" and "founder", not "author" and "creator"'
);

heading( '6. Every other page keeps its own graph' );

$GLOBALS['stub_current_page'] = array( 55, 'some-recipe-page', 'Some Recipe' );

$other_before = live_graph();
$other_after  = tcu_person_schema_filter_graph( $other_before );

ok( null === node_by_type( $other_after, 'Person' ), 'no Person node is emitted site-wide' );
ok( null === node_by_type( $other_after, 'Book' ), 'no Book node is emitted site-wide' );
is_same( count( $other_after ), count( $other_before ), 'no node is added or removed off the About page' );

// The Organization node travels on every page, so its sameAs is tidied everywhere -
// otherwise a recipe page would still hand Google the mixed list.
$other_org = node_by_id( $other_after, TCU_ORGANIZATION_ID );
is_same(
	array_values( array_intersect( $other_org['sameAs'], tcu_person_schema_personal_profiles() ) ),
	array(),
	'the Organization sameAs is separated site-wide, not just on the About page'
);

$normalised = $other_after;
foreach ( $normalised as $i => $piece ) {
	if ( isset( $piece['@id'] ) && TCU_ORGANIZATION_ID === $piece['@id'] ) {
		$normalised[ $i ]['sameAs'] = node_by_id( $other_before, TCU_ORGANIZATION_ID )['sameAs'];
	}
}
is_same( $normalised, $other_before, 'and that is the only difference - nothing else on the page is touched' );

heading( '7. Running twice cannot duplicate the entity' );

$GLOBALS['stub_current_page'] = array( 1003, 'about-connie-edwards-mcgaughy', 'About Connie Edwards McGaughy' );

$once  = tcu_person_schema_filter_graph( live_graph() );
$twice = tcu_person_schema_filter_graph( $once );

is_same( $twice, $once, 'a second pass is a no-op' );

$people = 0;
foreach ( $twice as $piece ) {
	if ( in_array( 'Person', (array) $piece['@type'], true ) ) {
		$people++;
	}
}
is_same( $people, 1, 'exactly one Person in the graph' );

heading( '8. Degraded input does not produce broken output' );

$no_image = array_values(
	array_filter(
		live_graph(),
		function ( $piece ) {
			return ! in_array( 'ImageObject', (array) $piece['@type'], true );
		}
	)
);
$no_image_after  = tcu_person_schema_filter_graph( $no_image );
$no_image_person = node_by_id( $no_image_after, TCU_PERSON_ID );

is_same( $no_image_person['image']['@type'], 'ImageObject', 'with no #primaryimage to reuse, a self-contained ImageObject is emitted instead' );

/**
 * This fixture is deliberately broken - the ImageObject was ripped out from under
 * Yoast's own WebPage node, which still references #primaryimage. So the question
 * is not "are there dangling references" (there are, and they are not ours) but
 * "did we ADD any". The set of broken references must not grow.
 */
function dangling_refs( array $graph ) {
	$ids = array();
	foreach ( $graph as $piece ) {
		if ( isset( $piece['@id'] ) ) { $ids[] = $piece['@id']; }
		foreach ( $piece as $value ) {
			if ( is_array( $value ) && isset( $value['@id'], $value['@type'] ) ) { $ids[] = $value['@id']; }
		}
	}
	$refs = array();
	collect_references( $graph, $refs );
	return array_values( array_unique( array_diff( $refs, $ids ) ) );
}

is_same(
	array_values( array_diff( dangling_refs( $no_image_after ), dangling_refs( $no_image ) ) ),
	array(),
	'the Person node introduces no new dangling reference into the degraded graph'
);

$no_org = array_values(
	array_filter(
		live_graph(),
		function ( $piece ) {
			return ! in_array( 'Organization', (array) $piece['@type'], true );
		}
	)
);
$no_org_person = node_by_id( tcu_person_schema_filter_graph( $no_org ), TCU_PERSON_ID );
ok( ! isset( $no_org_person['worksFor'] ), 'if the Organization is ever removed, worksFor is omitted rather than left dangling' );

$empty_after = tcu_person_schema_filter_graph( array() );
is_same( count( $empty_after ), 10, 'an empty graph still yields the Person, both Book references and all seven press articles' );
ok( ! isset( $empty_after[0]['mainEntityOfPage'] ), 'and no reference to a page that is not there' );
is_same( dangling_refs( $empty_after ), array(), 'and the Book author references still resolve' );

heading( '9. The cookbooks, as referred to from the About page' );

$GLOBALS['stub_current_page'] = array( 1003, 'about-connie-edwards-mcgaughy', 'About Connie Edwards McGaughy' );

$with_books = tcu_person_schema_filter_graph( live_graph() );

$books = array();
foreach ( $with_books as $piece ) {
	if ( in_array( 'Book', (array) $piece['@type'], true ) ) {
		$books[] = $piece;
	}
}

is_same( count( $books ), 2, 'both cookbooks are referenced from the About page' );

foreach ( $books as $book ) {
	$label = 'Volume ' . ( false !== strpos( $book['@id'], 'volume-one' ) ? 'One' : 'Two' );

	is_same( $book['author'], array( '@id' => TCU_PERSON_ID ), "$label: author references the one Connie by @id, not restated inline" );
	ok( ! isset( $book['author']['name'] ), "$label: the author is not duplicated as a literal Person" );

	// This is a reference, not a second copy of the definition. The full node lives
	// on the book's own page; repeating it here would describe the same work twice.
	ok( ! isset( $book['description'] ), "$label: the About page carries a reference, not the full description" );
	ok( ! isset( $book['offers'] ), "$label: and not a second copy of the offer" );

	is_same(
		$book['url'],
		'https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-' . ( false !== strpos( $book['@id'], 'volume-one' ) ? '1' : '2' ) . '/',
		"$label: url is the cookbook page on this site, not the Shopify listing"
	);
}

$people = 0;
foreach ( $with_books as $piece ) {
	if ( in_array( 'Person', (array) $piece['@type'], true ) ) {
		$people++;
	}
}
is_same( $people, 1, 'still exactly one Person in the graph despite three nodes pointing at her' );

$profile_with_books = node_by_type( $with_books, 'ProfilePage' );
is_same( count( $profile_with_books['mentions'] ), 2, 'the page mentions both books' );
is_same(
	$profile_with_books['mainEntity'],
	array( '@id' => TCU_PERSON_ID ),
	'mainEntity is still Connie - the books are mentioned, she is what the page is about'
);

is_same( dangling_refs( $with_books ), array(), 'no dangling references once the books are added' );

heading( '10. Press coverage' );

$press = array();
foreach ( $with_books as $piece ) {
	if ( in_array( 'Article', (array) $piece['@type'], true ) ) {
		$press[] = $piece;
	}
}

is_same( count( $press ), 7, 'seven press articles - the two that never mention her are excluded' );

$urls = array();
foreach ( $press as $article ) {
	$urls[] = $article['url'];

	$label = basename( parse_url( $article['url'], PHP_URL_PATH ) );

	// The assertion that matters most in this whole file.
	ok(
		isset( $article['author']['name'] ) && 'Connie Edwards McGaughy' !== $article['author']['name'],
		"$label: credited to the real journalist, NOT to Connie"
	);
	is_same( $article['mentions'], array( '@id' => TCU_PERSON_ID ), "$label: linked to Connie via mentions, not author" );
	ok( ! empty( $article['publisher']['name'] ), "$label: publisher is named" );
	ok( ! empty( $article['datePublished'] ), "$label: datePublished is set" );
	ok( 0 === strpos( $article['@id'], 'https://thecarrotunderground.com/#/schema/press/' ), "$label: @id is a local fragment, not a claim on the publisher's own URL" );
}

foreach ( array( 'ftw.usatoday.com', 'cnn.com' ) as $excluded ) {
	$found = false;
	foreach ( $urls as $u ) {
		if ( false !== strpos( $u, $excluded ) ) {
			$found = true;
		}
	}
	ok( ! $found, "$excluded is not marked up - the page never mentions her" );
}

$page_with_press = node_by_type( $with_books, 'ProfilePage' );
is_same( count( $page_with_press['citation'] ), 7, 'the page cites all seven' );
is_same( dangling_refs( $with_books ), array(), 'no dangling references once press is added' );

$people = 0;
foreach ( $with_books as $piece ) {
	if ( in_array( 'Person', (array) $piece['@type'], true ) && isset( $piece['@id'] ) ) {
		$people++;
	}
}
is_same( $people, 1, 'the journalists do not become extra Person nodes in the graph' );

heading( '11. The output is serialisable JSON-LD' );

$json = json_encode(
	array( '@context' => 'https://schema.org', '@graph' => $once ),
	JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);

ok( false !== $json, 'the graph encodes to JSON without error' );
ok( null !== json_decode( $json, true ), 'and decodes back cleanly' );

file_put_contents( __DIR__ . '/../output-about-page-schema.json', $json . "\n" );

/* ================================================================ *
 * 12. Each cookbook on its own page
 *
 * The fixtures here are the real @graph Yoast emits on the two cookbook pages,
 * captured 2026-08-24, so these assertions run against the live structure rather
 * than an invented one.
 * ================================================================ */

heading( '12. Each cookbook on its own dedicated page' );

$book_pages = array(
	array(
		'label'    => 'Volume One',
		'page'     => array( 28270, 'the-carrot-underground-cookbook-vol-1', 'The Carrot Underground Cookbook - Vol. 1' ),
		'fixture'  => 'fixture-book-page-1-graph.json',
		'book'     => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one',
		'sibling'  => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-two',
		'url'      => 'https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-1/',
		'pages'    => 63,
		'wikidata' => true,
	),
	array(
		'label'    => 'Volume Two',
		'page'     => array( 28306, 'the-carrot-underground-cookbook-vol-2', 'The Carrot Underground Cookbook – Vol. 2' ),
		'fixture'  => 'fixture-book-page-2-graph.json',
		'book'     => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-two',
		'sibling'  => 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one',
		'url'      => 'https://thecarrotunderground.com/the-carrot-underground-cookbook-vol-2/',
		'pages'    => 70,
		'wikidata' => false,
	),
);

$GLOBALS['play_ids']  = array();
$GLOBALS['book_names'] = array();

foreach ( $book_pages as $case ) {
	$label = $case['label'];

	$GLOBALS['stub_current_page'] = $case['page'];

	$page_before = json_decode( file_get_contents( __DIR__ . '/' . $case['fixture'] ), true );
	$page_after  = tcu_person_schema_filter_graph( $page_before );

	ok( null === node_by_type( $page_before, 'Book' ), "$label: the live page has no Book entity today (the problem being fixed)" );

	$book = node_by_id( $page_after, $case['book'] );
	ok( null !== $book, "$label: the Book node is emitted on its own page" );

	// The identity did not change when the book moved off the About page. If this
	// string had been derived from the page it was emitted on, moving it would have
	// thrown away everything Google had already associated with it.
	is_same( $book['@id'], $case['book'], "$label: keeps the same @id it had on the About page" );

	is_same( $book['url'], $case['url'], "$label: url is this page, not the Shopify listing" );
	is_same( $book['author'], array( '@id' => TCU_PERSON_ID ), "$label: author references the one Connie by @id" );
	is_same( $book['publisher'], array( '@id' => TCU_ORGANIZATION_ID ), "$label: publisher references the existing Organization" );
	is_same( $book['bookFormat'], 'https://schema.org/EBook', "$label: bookFormat is an EBook" );
	is_same( $book['numberOfPages'], $case['pages'], "$label: numberOfPages matches the Book Details panel on the page" );
	ok( ! empty( $book['datePublished'] ), "$label: datePublished is set" );

	// The complete title - main title and subtitle - as Connie asked.
	ok( false !== strpos( $book['name'], ': How to ' ), "$label: name carries the full title including the subtitle" );
	$GLOBALS['book_names'][] = $book['name'];

	// The page and the book agree about each other, in both directions.
	$page_node = node_by_type( $page_after, 'WebPage' );
	is_same( $page_node['mainEntity'], array( '@id' => $case['book'] ), "$label: the page declares the Book as its mainEntity" );
	is_same( $book['mainEntityOfPage'], array( '@id' => $case['url'] ), "$label: and the Book points back at the page" );

	// The cover already on the page is reused rather than a second ImageObject added.
	is_same( $book['image'], array( '@id' => $case['url'] . '#primaryimage' ), "$label: reuses the cover ImageObject Yoast already emits" );
	$image_nodes = 0;
	foreach ( $page_after as $piece ) {
		if ( in_array( 'ImageObject', (array) $piece['@type'], true ) ) { $image_nodes++; }
	}
	is_same( $image_nodes, 1, "$label: still only one ImageObject on the page" );

	// Book url and Offer url are two different facts.
	ok( false !== strpos( $book['offers']['url'], 'myshopify.com' ), "$label: the Offer still points at the shop, where you actually buy it" );
	is_same( $book['offers']['priceCurrency'], 'USD', "$label: offer currency is set" );
	is_same( $book['offers']['price'], '9.99', "$label: offer price matches the Shopify listing" );
	is_same( $book['offers']['seller'], array( '@id' => TCU_ORGANIZATION_ID ), "$label: the seller is the Organization" );

	// Each volume must carry its OWN Play Books id. Pasting the same one twice is the
	// obvious mistake here and it would merge the two books in Google's eyes.
	$play = array_values( array_filter( $book['sameAs'], function ( $u ) { return false !== strpos( $u, 'play.google.com' ); } ) );
	is_same( count( $play ), 1, "$label: has exactly one Google Play Books listing" );
	$GLOBALS['play_ids'][] = $play[0];

	$gr = array_values( array_filter( $book['sameAs'], function ( $u ) { return false !== strpos( $u, 'goodreads.com' ); } ) );
	is_same( count( $gr ), 1, "$label: has exactly one Goodreads listing" );

	$wd = array_values( array_filter( $book['sameAs'], function ( $u ) { return false !== strpos( $u, 'wikidata.org' ); } ) );
	is_same( count( $wd ), $case['wikidata'] ? 1 : 0, "$label: WikiData listed only where an item actually exists" );

	// The sibling volume: linked from the page, so linked in the graph.
	$sibling = node_by_id( $page_after, $case['sibling'] );
	ok( null !== $sibling, "$label: the other volume is present as a reference" );
	ok( ! isset( $sibling['offers'] ), "$label: the other volume is a reference, not a second full definition" );
	is_same( $page_node['mentions'], array( array( '@id' => $case['sibling'] ) ), "$label: the page mentions the other volume" );

	// The two volumes are tied together through the series, not through isRelatedTo -
	// which is a Product/Service property and an unknown field on a Book.
	is_same( $book['isPartOf'], array( '@id' => TCU_BOOK_SERIES_ID ), "$label: is part of the cookbook series" );
	ok( ! isset( $book['isRelatedTo'] ), "$label: no isRelatedTo - it is not a CreativeWork property" );

	$series = node_by_id( $page_after, TCU_BOOK_SERIES_ID );
	ok( null !== $series, "$label: the BookSeries node is present" );
	is_same( $series['@type'], 'BookSeries', "$label: typed BookSeries" );
	is_same( count( $series['hasPart'] ), 2, "$label: the series has both volumes as parts" );
	is_same( $series['author'], array( '@id' => TCU_PERSON_ID ), "$label: the series is credited to the same author @id" );
	ok( ! isset( $sibling['isPartOf'] ), "$label: the stub volume stays minimal - the series declares it instead" );

	// A stub Connie, so the author reference has something to resolve to.
	$stub = node_by_id( $page_after, TCU_PERSON_ID );
	ok( null !== $stub, "$label: a Person stub is emitted so the author reference is not dangling" );
	ok( ! isset( $stub['jobTitle'] ), "$label: the stub carries no profile detail - the About page owns that" );
	ok( ! isset( $stub['sameAs'] ), "$label: and no second copy of the sameAs list" );

	$org = node_by_id( $page_after, TCU_ORGANIZATION_ID );
	ok( ! isset( $org['founder'] ), "$label: the founder edge is only added on the About page, not site-wide" );
	is_same(
		array_values( array_intersect( $org['sameAs'], tcu_person_schema_personal_profiles() ) ),
		array(),
		"$label: the Organization sameAs is separated here too"
	);

	is_same( dangling_refs( $page_after ), array(), "$label: no dangling references" );
	is_same( tcu_person_schema_filter_graph( $page_after ), $page_after, "$label: a second pass is a no-op" );

	// Yoast's own pieces are untouched apart from the two edges added to the page node.
	foreach ( array( 'WebSite', 'BreadcrumbList', 'ImageObject' ) as $type ) {
		is_same( node_by_type( $page_after, $type ), node_by_type( $page_before, $type ), "$label: $type piece is byte-for-byte unchanged" );
	}

	file_put_contents(
		__DIR__ . '/../output-' . str_replace( 'fixture-', '', str_replace( '-graph.json', '', $case['fixture'] ) ) . '-schema.json',
		json_encode(
			array( '@context' => 'https://schema.org', '@graph' => $page_after ),
			JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		) . "\n"
	);
}

is_same(
	count( array_unique( $GLOBALS['play_ids'] ) ),
	2,
	'the two volumes point at DIFFERENT Play Books listings, not the same id pasted twice'
);
is_same(
	count( array_unique( $GLOBALS['book_names'] ) ),
	2,
	'and carry different titles, not the same string pasted twice'
);

/* ---------------------------------------------------------------- *
 * The "book on its own page" scenario needs a different book list defined before
 * the plugin loads, which cannot be done twice in one process. Run it separately
 * and fold its results in.
 * ---------------------------------------------------------------- */

$sub_output = array();
$sub_status = 0;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/test-book-on-own-page.php' ) . ' 2>&1', $sub_output, $sub_status );

foreach ( $sub_output as $line ) {
	if ( preg_match( '/^(\d+) passed, (\d+) failed$/', trim( $line ), $m ) ) {
		$GLOBALS['pass'] += (int) $m[1];
		$GLOBALS['fail'] += (int) $m[2];
		continue;
	}
	echo $line . "\n";
}

/* ---------------------------------------------------------------- */

echo "\n" . str_repeat( '-', 60 ) . "\n";
printf( "%d passed, %d failed\n", $GLOBALS['pass'], $GLOBALS['fail'] );
echo str_repeat( '-', 60 ) . "\n";

exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
