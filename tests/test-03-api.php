<?php
// Must run before ANTHROPIC_API_KEY is defined below.
$e = avitar_explainer_call_api( 'CU' );
check( 'missing API key gives 500 without calling the API', is_wp_error( $e ) && 500 === $e->get_error_data()['status'] && 0 === count( $GLOBALS['avitar_test']['http'] ) );
check( 'missing key is logged for the admin', false !== strpos( read_log(), 'ANTHROPIC_API_KEY' ) );
check( 'missing key message to visitors does not mention config', is_wp_error( $e ) && false === strpos( $e->get_error_message(), 'ANTHROPIC' ) );

$_SERVER['REMOTE_ADDR'] = '203.0.113.60';
$e = avitar_explainer_handle_explain( new WP_REST_Request( array( 'notes' => 'CU*LLA' ) ) );
check( 'missing key does not use up the daily quota', is_wp_error( $e ) && 500 === $e->get_error_data()['status'] && false === get_transient( 'avitar_explainer_day_' . wp_date( 'Ymd', time() ) ) );

define( 'ANTHROPIC_API_KEY', 'sk-ant-test-secret' );

$body = avitar_explainer_build_request_body( 'CU*LLA' );
check( 'request uses the configured model', 'claude-opus-5-5' === $body['model'] );
check( 'request caps max_tokens at 6000', 6000 === $body['max_tokens'] );
check( 'request sets explicit medium effort', 'medium' === $body['output_config']['effort'] );
check( 'request enables default fallbacks', 'default' === $body['fallbacks'] );
check( 'system prompt defines the Uncertain marker', false !== strpos( $body['system'], '⚠ Uncertain:' ) );
check( 'system prompt treats input as data', false !== strpos( $body['system'], 'not instructions' ) );
check( 'user message wraps the notes', false !== strpos( $body['messages'][0]['content'], "<notes>\nCU*LLA\n</notes>" ) );

$ok = api_response( 200, array(
	'stop_reason' => 'end_turn',
	'content'     => array(
		array( 'type' => 'thinking', 'thinking' => 'private reasoning' ),
		array( 'type' => 'text', 'text' => "1. \"CU\"\nCurrent Use." ),
	),
) );
check( 'text blocks extracted, thinking ignored', "1. \"CU\"\nCurrent Use." === avitar_explainer_parse_response( $ok ) );

$cut  = api_response( 200, array( 'stop_reason' => 'max_tokens', 'content' => array( array( 'type' => 'text', 'text' => 'Partial' ) ) ) );
$text = avitar_explainer_parse_response( $cut );
check( 'max_tokens keeps text and adds a cut-off note', 0 === strpos( $text, 'Partial' ) && false !== strpos( $text, 'cut off' ) );

$e = avitar_explainer_parse_response( api_response( 200, array( 'stop_reason' => 'refusal', 'content' => array() ) ) );
check( 'refusal gives 422', is_wp_error( $e ) && 422 === $e->get_error_data()['status'] );
$e = avitar_explainer_parse_response( api_response( 200, array( 'stop_reason' => 'end_turn', 'content' => array() ) ) );
check( 'no text blocks gives 502', is_wp_error( $e ) && 502 === $e->get_error_data()['status'] );
$e = avitar_explainer_parse_response( api_response( 200, 'not json' ) );
check( 'malformed JSON gives 502', is_wp_error( $e ) && 502 === $e->get_error_data()['status'] );
$e = avitar_explainer_parse_response( api_response( 529, array( 'type' => 'error', 'error' => array( 'type' => 'overloaded_error', 'message' => 'Overloaded' ) ) ) );
check( 'overloaded gives 503 busy', is_wp_error( $e ) && 503 === $e->get_error_data()['status'] );
$e = avitar_explainer_parse_response( api_response( 401, array( 'type' => 'error', 'error' => array( 'type' => 'authentication_error', 'message' => 'invalid x-api-key' ) ) ) );
check( 'auth error gives generic 502', is_wp_error( $e ) && 502 === $e->get_error_data()['status'] && false === strpos( $e->get_error_message(), 'x-api-key' ) );
check( 'API error details are logged', false !== strpos( read_log(), 'invalid x-api-key' ) );
$e = avitar_explainer_parse_response( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) );
check( 'network timeout gives 502', is_wp_error( $e ) && 502 === $e->get_error_data()['status'] );
check( 'network error is logged', false !== strpos( read_log(), 'cURL error 28' ) );

$GLOBALS['avitar_test']['http_response'] = $ok;
$result = avitar_explainer_call_api( 'CU*LLA' );
$call   = $GLOBALS['avitar_test']['http'][0];
check( 'call_api posts to the Messages API', 'https://api.anthropic.com/v1/messages' === $call['url'] );
check( 'call_api sends the key header', 'sk-ant-test-secret' === $call['args']['headers']['x-api-key'] );
check( 'call_api sends the API version', '2023-06-01' === $call['args']['headers']['anthropic-version'] );
check( 'call_api sends the fallback beta header', 'server-side-fallback-2026-07-01' === $call['args']['headers']['anthropic-beta'] );
check( 'call_api uses a 120 s timeout', 120 === $call['args']['timeout'] );
check( 'call_api sends the JSON body', false !== strpos( json_decode( $call['args']['body'], true )['messages'][0]['content'], 'CU*LLA' ) );
check( 'call_api returns the parsed text', "1. \"CU\"\nCurrent Use." === $result );
