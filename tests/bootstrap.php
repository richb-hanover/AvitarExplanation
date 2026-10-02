<?php
// Minimal WordPress stand-ins so the plugin's functions can run under plain PHP.
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['avitar_test'] = array(
	'transients'     => array(),
	'transient_ttl'  => array(),
	'http'           => array(),
	'http_response'  => null,
	'actions'        => array(),
	'shortcodes'     => array(),
	'routes'         => array(),
	'log_file'       => sys_get_temp_dir() . '/avitar-explainer-test.log',
);
ini_set( 'log_errors', '1' );
ini_set( 'error_log', $GLOBALS['avitar_test']['log_file'] );

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Request {
	private $params;
	private $headers = array();
	public function __construct( array $params = array(), array $headers = array() ) {
		$this->params = $params;
		foreach ( $headers as $key => $value ) {
			$this->headers[ strtolower( str_replace( '-', '_', $key ) ) ] = $value;
		}
	}
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
	public function get_header( $key ) {
		$key = strtolower( str_replace( '-', '_', $key ) );
		return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : null;
	}
}

class WP_REST_Response {
	public $headers = array();
	private $data;
	private $status;
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $key, $value ) { $this->headers[ $key ] = $value; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function add_action( $hook, $callback ) { $GLOBALS['avitar_test']['actions'][ $hook ][] = $callback; }
function add_shortcode( $tag, $callback ) { $GLOBALS['avitar_test']['shortcodes'][ $tag ] = $callback; }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['avitar_test']['routes'][ $ns . $route ] = $args; }
function get_transient( $key ) {
	return isset( $GLOBALS['avitar_test']['transients'][ $key ] ) ? $GLOBALS['avitar_test']['transients'][ $key ] : false;
}
function set_transient( $key, $value, $expiration ) {
	$GLOBALS['avitar_test']['transients'][ $key ]    = $value;
	$GLOBALS['avitar_test']['transient_ttl'][ $key ] = $expiration;
	return true;
}
function wp_salt( $scheme = 'auth' ) { return 'test-salt'; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, null === $timestamp ? time() : $timestamp ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function wp_verify_nonce( $nonce, $action ) { return 'nonce-' . $action === $nonce ? 1 : false; }
function wp_remote_post( $url, $args ) {
	$GLOBALS['avitar_test']['http'][] = array( 'url' => $url, 'args' => $args );
	return $GLOBALS['avitar_test']['http_response'];
}
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? $response['response']['code'] : ''; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? $response['body'] : ''; }
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
function esc_url( $url ) { return $url; }
function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
