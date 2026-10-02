<?php
avitar_explainer_register_routes();
$routes = $GLOBALS['avitar_test']['routes'];
check( 'plugin hooks rest_api_init', in_array( 'avitar_explainer_register_routes', $GLOBALS['avitar_test']['actions']['rest_api_init'], true ) );
check( 'nonce route is GET', 'GET' === $routes['avitar-explainer/v1/nonce']['methods'] );
check( 'explain route is POST', 'POST' === $routes['avitar-explainer/v1/explain']['methods'] );
check( 'explain route is guarded by the nonce check', 'avitar_explainer_verify_nonce' === $routes['avitar-explainer/v1/explain']['permission_callback'] );

$r = avitar_explainer_handle_nonce();
check( 'nonce endpoint returns a nonce', 'nonce-avitar_explainer' === $r->get_data()['nonce'] );
check( 'nonce endpoint is not cacheable', false !== strpos( $r->headers['Cache-Control'], 'no-store' ) );

$e = avitar_explainer_verify_nonce( new WP_REST_Request() );
check( 'missing nonce gives 403', is_wp_error( $e ) && 403 === $e->get_error_data()['status'] );
check( 'wrong nonce gives 403', is_wp_error( avitar_explainer_verify_nonce( new WP_REST_Request( array(), array( 'X-Avitar-Nonce' => 'bogus' ) ) ) ) );
check( 'valid nonce passes', true === avitar_explainer_verify_nonce( new WP_REST_Request( array(), array( 'X-Avitar-Nonce' => 'nonce-avitar_explainer' ) ) ) );

$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
$GLOBALS['avitar_test']['http_response'] = api_response( 200, array(
	'stop_reason' => 'end_turn',
	'content'     => array( array( 'type' => 'text', 'text' => 'Explained.' ) ),
) );
$r = avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => 'CU*LLA' ) ) );
check( 'explain returns the explanation', $r instanceof WP_REST_Response && 'Explained.' === $r->get_data()['explanation'] );
check( 'explain response does not contain the key', false === strpos( json_encode( $r->get_data() ), 'sk-ant' ) );
check( 'explain counted toward the daily cap', 1 === get_transient( 'avitar_explainer_day_' . wp_date( 'Ymd', time() ) ) );

$calls = count( $GLOBALS['avitar_test']['http'] );
$e     = avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => '  ' ) ) );
check( 'empty notes rejected without calling the API', is_wp_error( $e ) && $calls === count( $GLOBALS['avitar_test']['http'] ) );

$GLOBALS['avitar_test']['transients'][ 'avitar_explainer_ip_' . md5( '203.0.113.50' . 'test-salt' ) ] = array( 'start' => time(), 'count' => 30 );
$e = avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => 'CU' ) ) );
check( 'rate-limited request gets 429 without calling the API', is_wp_error( $e ) && 429 === $e->get_error_data()['status'] && $calls === count( $GLOBALS['avitar_test']['http'] ) );

check( 'shortcode is registered', isset( $GLOBALS['avitar_test']['shortcodes']['avitar_explainer'] ) );
$html = avitar_explainer_shortcode();
check( 'shortcode renders a textarea', false !== strpos( $html, '<textarea' ) );
check( 'shortcode renders the button', false !== strpos( $html, '>Explain</button>' ) );
check( 'shortcode passes the REST base URL', false !== strpos( $html, 'data-rest="https://example.test/wp-json/avitar-explainer/v1/"' ) );
check( 'shortcode includes the AI disclaimer', false !== strpos( $html, 'may contain mistakes' ) );
check( 'shortcode never outputs the API key', false === strpos( $html, 'sk-ant' ) );
check( 'shortcode queues the footer script', in_array( 'avitar_explainer_print_script', $GLOBALS['avitar_test']['actions']['wp_footer'], true ) );
check( 'second shortcode on a page omits duplicate styles', false === strpos( avitar_explainer_shortcode(), '<style' ) );

ob_start();
avitar_explainer_print_script();
$js = ob_get_clean();
check( 'script fetches a fresh nonce', false !== strpos( $js, "rest + 'nonce'" ) );
check( 'script sends the nonce header', false !== strpos( $js, 'X-Avitar-Nonce' ) );
check( 'script renders output as text, not HTML', false !== strpos( $js, 'textContent' ) && false === strpos( $js, 'innerHTML' ) );
check( 'script never contains the API key', false === strpos( $js, 'sk-ant' ) );
