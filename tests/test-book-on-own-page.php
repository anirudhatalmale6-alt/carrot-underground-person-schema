<?php
/**
 * Forward-looking test: what happens when a cookbook gets its own page.
 *
 * This is a separate process from the main suite because it has to define a
 * different book list *before* the plugin loads, and PHP will not let a function
 * be declared twice. The plugin's tcu_person_schema_books() is wrapped in
 * function_exists precisely so it can be replaced like this.
 *
 * Run:  php tests/test-book-on-own-page.php   (the main suite runs it too)
 */

define( 'ABSPATH', __DIR__ . '/' );

/** The cookbook page that does not exist yet. */
$GLOBALS['stub_current_page'] = array( 2001, 'cookbook-volume-one', 'Cookbook Volume One' );

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
	return true;
}

const TCU_BOOK_VOLUME_ONE_ID = 'https://thecarrotunderground.com/#/schema/book/carrot-underground-cookbook-volume-one';

/** Volume One reassigned to its own page. Declared before the plugin loads. */
function tcu_person_schema_books() {
	return array(
		array(
			'page'          => 2001,
			'@id'           => TCU_BOOK_VOLUME_ONE_ID,
			'name'          => 'The Carrot Underground Cookbook - Volume One',
			'url'           => 'https://qf01dx-q0.myshopify.com/products/the-carrot-underground-digital-cookbook-volume-one',
			'datePublished' => '2024-10-01',
			'numberOfPages' => 63,
			'price'         => '9.99',
		),
	);
}

require __DIR__ . '/../tcu-person-schema.php';

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

function node_by_id( array $graph, $id ) {
	foreach ( $graph as $piece ) {
		if ( isset( $piece['@id'] ) && $piece['@id'] === $id ) {
			return $piece;
		}
	}
	return null;
}

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

/* ---------------------------------------------------------------- */

echo "\nA cookbook moved to its own page\n";

// The fixture is the About page's graph, but is_page() now reports the cookbook
// page - so this stands in for "some other page on the site, running Yoast".
$graph = json_decode( file_get_contents( __DIR__ . '/fixture-live-graph.json' ), true );
$after = tcu_person_schema_filter_graph( $graph );

$book = node_by_id( $after, TCU_BOOK_VOLUME_ONE_ID );
ok( null !== $book, 'the Book is emitted on its new page' );
is_same( $book['author'], array( '@id' => TCU_PERSON_ID ), 'and still points at the same author @id - the identity survived the move' );

$person = node_by_id( $after, TCU_PERSON_ID );
ok( null !== $person, 'a Person stub is emitted so the author reference is not dangling' );
is_same( $person['name'], 'Connie Edwards McGaughy', 'the stub names her' );
ok( ! isset( $person['sameAs'] ), 'the stub is a reference, not a second full profile' );
ok( ! isset( $person['jobTitle'] ), 'the stub carries no profile detail - the About page owns that' );

$page = null;
foreach ( $after as $piece ) {
	if ( in_array( 'WebPage', (array) $piece['@type'], true ) ) {
		$page = $piece;
		break;
	}
}
ok( ! isset( $page['mainEntity'] ), 'a book page does not claim Connie is its mainEntity - only the About page does that' );
is_same( count( $page['mentions'] ), 1, 'the page mentions the one book that lives on it' );

$org = node_by_id( $after, TCU_ORGANIZATION_ID );
ok( ! isset( $org['founder'] ), 'the founder edge is only added on the About page, not site-wide' );

is_same( dangling_refs( $after ), array(), 'no dangling references' );

$books = 0;
foreach ( $after as $piece ) {
	if ( in_array( 'Book', (array) $piece['@type'], true ) ) { $books++; }
}
is_same( $books, 1, 'only the book assigned to this page is emitted, not the whole catalogue' );

echo "\n";
printf( "%d passed, %d failed\n", $GLOBALS['pass'], $GLOBALS['fail'] );

exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
