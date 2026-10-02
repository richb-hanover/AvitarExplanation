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
check( 'explain returns structured parts', array() === $r->get_data()['items'] && array( 'Explained.' ) === $r->get_data()['extra'] && '' === $r->get_data()['summary'] );
check( 'explain response does not contain the key', false === strpos( json_encode( $r->get_data() ), 'sk-ant' ) );
check( 'explain counted toward the daily cap', 1 === get_transient( 'avitar_explainer_day_' . wp_date( 'Ymd', time() ) ) );

$calls = count( $GLOBALS['avitar_test']['http'] );
$e     = avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => '  ' ) ) );
check( 'empty notes rejected without calling the API', is_wp_error( $e ) && $calls === count( $GLOBALS['avitar_test']['http'] ) );

$GLOBALS['avitar_test']['transients'][ 'avitar_explainer_ip_' . md5( '203.0.113.50' . 'test-salt' ) ] = array( 'start' => time(), 'count' => 30 );
$e = avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => 'CU' ) ) );
check( 'rate-limited request gets 429 without calling the API', is_wp_error( $e ) && 429 === $e->get_error_data()['status'] && $calls === count( $GLOBALS['avitar_test']['http'] ) );

reset_state();
$_SERVER['REMOTE_ADDR'] = '203.0.113.51';
$GLOBALS['avitar_test']['http_response'] = api_response( 529, array( 'type' => 'error', 'error' => array( 'type' => 'overloaded_error', 'message' => 'Overloaded' ) ) );
avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => 'CU' ) ) );
$e = avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => 'CU' ) ) );
check( 'busy API returns 503', is_wp_error( $e ) && 503 === $e->get_error_data()['status'] );
check( 'busy API attempts are refunded from the daily cap', 0 === (int) get_transient( 'avitar_explainer_day_' . wp_date( 'Ymd', time() ) ) );
$window = get_transient( 'avitar_explainer_ip_' . md5( '203.0.113.51' . 'test-salt' ) );
check( 'busy API attempts are refunded from the IP limit', is_array( $window ) && 0 === $window['count'] );
$GLOBALS['avitar_test']['http_response'] = api_response( 401, array( 'type' => 'error', 'error' => array( 'type' => 'authentication_error', 'message' => 'bad key' ) ) );
avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => 'CU' ) ) );
check( 'other API failures still count', 1 === (int) get_transient( 'avitar_explainer_day_' . wp_date( 'Ymd', time() ) ) );

check( 'shortcode is registered', isset( $GLOBALS['avitar_test']['shortcodes']['avitar_explainer'] ) );
$html = avitar_explainer_shortcode();
check( 'shortcode renders a textarea', false !== strpos( $html, '<textarea' ) );
check( 'text box accepts a whole-page paste of 20000 characters', false !== strpos( $html, 'maxlength="20000"' ) );
check( 'shortcode renders the button', false !== strpos( $html, '>Explain</button>' ) );
check( 'Explain button comes before the text box', strpos( $html, '>Explain</button>' ) < strpos( $html, '<textarea' ) );
check( 'label and button share a row above the text box', 1 === preg_match( '/<div class="avitar-explainer__bar">\s*<label[^>]*>[^<]*<\/label>\s*<button[^>]*>Explain<\/button>\s*<\/div>\s*<textarea/', $html ) );
check( 'the row pushes the button to the right edge', false !== strpos( $html, '.avitar-explainer__bar { display: flex; justify-content: space-between;' ) );
check( 'scrolling to the message leaves room for the WordPress admin bar', 1 === preg_match( '/\.avitar-explainer__result \{[^}]*scroll-margin-top: calc\(var\(--wp-admin--admin-bar--height, 0px\) \+ 0\.5em\)/', $html ) );
check( 'spinner is a CSS animation', false !== strpos( $html, '@keyframes avitar-explainer-spin' ) && false !== strpos( $html, 'animation: avitar-explainer-spin' ) );
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
check( 'script renders output as text, never assigning HTML', false !== strpos( $js, 'textContent' ) && ! preg_match( '/(innerHTML|outerHTML)\s*\+?=(?!=)|insertAdjacentHTML|document\.write/', $js ) );
check( 'script adds the "Notes for this property" heading', false !== strpos( $js, 'Notes for this property' ) );
check( 'script shows the response time', false !== strpos( $js, 'Response time' ) );
check( 'CSS outlines only the working and error states', false !== strpos( $html, '.avitar-explainer__result.is-working, .avitar-explainer__result.is-error { padding: 1em; border:' ) );
preg_match( '/\.avitar-explainer__result\.is-done \{([^}]*)\}/', $html, $done_rule );
check( 'finished explanation has a light grey background and no outline', ! empty( $done_rule ) && false !== strpos( $done_rule[1], 'background: #eeeeee' ) && false === strpos( $done_rule[1], 'border:' ) );
check( 'script switches between working and done states', false !== strpos( $js, "'working')" ) && false !== strpos( $js, 'is-done' ) );
check( 'script never contains the API key', false === strpos( $js, 'sk-ant' ) );
