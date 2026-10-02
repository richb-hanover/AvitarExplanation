<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/avitar-explainer/avitar-explainer.php';

$avitar_checks   = 0;
$avitar_failures = 0;

function check( $name, $condition ) {
	global $avitar_checks, $avitar_failures;
	$avitar_checks++;
	if ( $condition ) {
		echo "  ok   $name\n";
	} else {
		$avitar_failures++;
		echo "  FAIL $name\n";
	}
}

function reset_state() {
	$GLOBALS['avitar_test']['transients']    = array();
	$GLOBALS['avitar_test']['transient_ttl'] = array();
	$GLOBALS['avitar_test']['http']          = array();
	$GLOBALS['avitar_test']['http_response'] = null;
	file_put_contents( $GLOBALS['avitar_test']['log_file'], '' );
}

function read_log() {
	return (string) file_get_contents( $GLOBALS['avitar_test']['log_file'] );
}

function api_response( $code, $data ) {
	return array(
		'response' => array( 'code' => $code ),
		'body'     => is_string( $data ) ? $data : json_encode( $data ),
	);
}

foreach ( glob( __DIR__ . '/test-*.php' ) as $file ) {
	echo basename( $file ) . "\n";
	reset_state();
	require $file;
}

echo "\n$avitar_checks checks, $avitar_failures failures\n";
exit( $avitar_failures ? 1 : 0 );
