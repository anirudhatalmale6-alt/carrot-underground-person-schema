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
unset( $org_after['founder'] );
is_same( $org_after, $org_before, 'founder is the ONLY change to the Organization node' );

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

heading( '6. Every other page is left alone' );

$GLOBALS['stub_current_page'] = array( 55, 'some-recipe-page', 'Some Recipe' );

$other_before = live_graph();
$other_after  = tcu_person_schema_filter_graph( $other_before );

is_same( $other_after, $other_before, 'graph is returned completely unmodified off the About page' );
ok( null === node_by_type( $other_after, 'Person' ), 'no Person node is emitted site-wide' );

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
is_same( count( $empty_after ), 10, 'an empty graph still yields the Person, both Books and all seven press articles' );
ok( ! isset( $empty_after[0]['mainEntityOfPage'] ), 'and no reference to a page that is not there' );
ok( ! isset( $empty_after[1]['publisher'] ), 'with no Organization present, the Books omit publisher rather than dangling' );
is_same( dangling_refs( $empty_after ), array(), 'and the Book author references still resolve' );

heading( '9. The cookbooks' );

$GLOBALS['stub_current_page'] = array( 1003, 'about-connie-edwards-mcgaughy', 'About Connie Edwards McGaughy' );

$with_books = tcu_person_schema_filter_graph( live_graph() );

$books = array();
foreach ( $with_books as $piece ) {
	if ( in_array( 'Book', (array) $piece['@type'], true ) ) {
		$books[] = $piece;
	}
}

is_same( count( $books ), 2, 'both cookbooks are in the graph' );

$GLOBALS['play_ids'] = array();

foreach ( $books as $book ) {
	$label = 'Volume ' . ( false !== strpos( $book['@id'], 'volume-one' ) ? 'One' : 'Two' );

	is_same( $book['author'], array( '@id' => TCU_PERSON_ID ), "$label: author references the one Connie by @id, not restated inline" );
	is_same( $book['publisher'], array( '@id' => TCU_ORGANIZATION_ID ), "$label: publisher references the existing Organization" );
	is_same( $book['bookFormat'], 'https://schema.org/EBook', "$label: bookFormat is an EBook" );
	ok( ! empty( $book['datePublished'] ), "$label: datePublished is set" );
	ok( is_int( $book['numberOfPages'] ), "$label: numberOfPages is a number, not a string" );
	is_same( $book['offers']['priceCurrency'], 'USD', "$label: offer currency is set" );
	is_same( $book['offers']['seller'], array( '@id' => TCU_ORGANIZATION_ID ), "$label: the seller is the Organization" );

	// Each volume must carry its OWN Play Books id. Pasting the same one twice is the
	// obvious mistake here and it would merge the two books in Google's eyes.
	$play = array_values( array_filter( $book['sameAs'], function ( $u ) { return false !== strpos( $u, 'play.google.com' ); } ) );
	is_same( count( $play ), 1, "$label: has exactly one Google Play Books listing" );
	$GLOBALS['play_ids'][] = $play[0];

	$gr = array_values( array_filter( $book['sameAs'], function ( $u ) { return false !== strpos( $u, 'goodreads.com' ); } ) );
	is_same( count( $gr ), 1, "$label: has exactly one Goodreads listing" );

	// A Book whose author is spelled out inline instead of referenced would create a
	// second Connie. This is the assertion that catches that regression.
	ok( ! isset( $book['author']['name'] ), "$label: the author is not duplicated as a literal Person" );
}

is_same(
	count( array_unique( $GLOBALS['play_ids'] ) ),
	2,
	'the two volumes point at DIFFERENT Play Books listings, not the same id pasted twice'
);

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
