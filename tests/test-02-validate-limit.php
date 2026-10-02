<?php
$e = avitar_explainer_validate_notes( "   \n" );
check( 'whitespace-only notes rejected with 400', is_wp_error( $e ) && 400 === $e->get_error_data()['status'] );
check( 'array notes rejected', is_wp_error( avitar_explainer_validate_notes( array( 'CU' ) ) ) );
check( 'missing notes rejected', is_wp_error( avitar_explainer_validate_notes( null ) ) );
check( 'notes are trimmed', 'CU*LLA' === avitar_explainer_validate_notes( "  CU*LLA \n" ) );
check( 'exactly 4000 multibyte characters accepted', is_string( avitar_explainer_validate_notes( str_repeat( 'é', 4000 ) ) ) );
$e = avitar_explainer_validate_notes( str_repeat( 'a', 4001 ) );
check( '4001 characters rejected with 400', is_wp_error( $e ) && 400 === $e->get_error_data()['status'] );
check( 'too-long message states the limit', is_wp_error( $e ) && false !== strpos( $e->get_error_message(), '4000' ) );

$now    = 1790000000; // mid-day UTC
$ip_key = 'avitar_explainer_ip_' . md5( '203.0.113.7' . 'test-salt' );
$day_key = 'avitar_explainer_day_' . gmdate( 'Ymd', $now );

for ( $i = 0; $i < 30; $i++ ) {
	$result = avitar_explainer_check_rate_limit( '203.0.113.7', $now + $i );
}
check( '30 requests from one IP within the hour allowed', true === $result );
check( 'IP window does not slide forward', 3600 - 29 === $GLOBALS['avitar_test']['transient_ttl'][ $ip_key ] );
$e = avitar_explainer_check_rate_limit( '203.0.113.7', $now + 60 );
check( '31st request from the same IP gets 429', is_wp_error( $e ) && 'avitar_ip_limit' === $e->get_error_code() && 429 === $e->get_error_data()['status'] );
check( 'IP limit message gives a wait time', is_wp_error( $e ) && false !== strpos( $e->get_error_message(), 'minutes' ) );
check( 'a different IP is still allowed', true === avitar_explainer_check_rate_limit( '203.0.113.8', $now + 60 ) );
check( 'same IP allowed again after the hour', true === avitar_explainer_check_rate_limit( '203.0.113.7', $now + 3600 ) );
check( 'rejected requests do not count toward the daily cap', 32 === get_transient( $day_key ) );

reset_state();
$GLOBALS['avitar_test']['transients'][ $day_key ] = 500;
$e = avitar_explainer_check_rate_limit( '198.51.100.9', $now );
check( 'daily cap of 500 returns 429', is_wp_error( $e ) && 'avitar_daily_limit' === $e->get_error_code() && 429 === $e->get_error_data()['status'] );
check( 'daily cap does not count the rejected IP', false === get_transient( 'avitar_explainer_ip_' . md5( '198.51.100.9' . 'test-salt' ) ) );
check( 'daily cap resets on a new date', true === avitar_explainer_check_rate_limit( '198.51.100.9', $now + DAY_IN_SECONDS ) );
