<?php
/**
 * Plugin Name: Avitar Explainer
 * Description: Adds the [avitar_explainer] shortcode, which explains Avitar Associates tax-card notes in plain English using Claude.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AVITAR_EXPLAINER_NONCE_ACTION = 'avitar_explainer';
const AVITAR_EXPLAINER_REST_NS      = 'avitar-explainer/v1';

/**
 * Returns a setting, letting an AVITAR_EXPLAINER_* constant in wp-config.php override the default.
 */
function avitar_explainer_config( $name ) {
	$defaults = array(
		'MODEL'         => 'claude-opus-5-5',
		'IP_LIMIT'      => 30,
		'DAILY_LIMIT'   => 500,
		'MAX_CHARS'     => 4000,
		'TRUSTED_PROXY' => false,
	);
	$constant = 'AVITAR_EXPLAINER_' . $name;
	return defined( $constant ) ? constant( $constant ) : $defaults[ $name ];
}

/**
 * Visitor IP. Forwarded headers are only trusted when the site sits behind a proxy
 * (e.g. Cloudflare) that sets them; otherwise any visitor could spoof them.
 */
function avitar_explainer_client_ip( array $server, $trust_proxy ) {
	if ( $trust_proxy ) {
		$candidates = array();
		if ( ! empty( $server['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = $server['HTTP_CF_CONNECTING_IP'];
		}
		if ( ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded    = explode( ',', $server['HTTP_X_FORWARDED_FOR'] );
			$candidates[] = $forwarded[0];
		}
		foreach ( $candidates as $candidate ) {
			$candidate = trim( $candidate );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}
	}
	$remote = isset( $server['REMOTE_ADDR'] ) ? trim( $server['REMOTE_ADDR'] ) : '';
	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';
}

/**
 * Returns the trimmed notes, or a 400 WP_Error the visitor can act on.
 */
function avitar_explainer_validate_notes( $notes ) {
	if ( ! is_string( $notes ) || '' === trim( $notes ) ) {
		return new WP_Error( 'avitar_empty', 'Please paste some tax-card notes first.', array( 'status' => 400 ) );
	}
	$notes = trim( $notes );
	$max   = (int) avitar_explainer_config( 'MAX_CHARS' );
	if ( mb_strlen( $notes, 'UTF-8' ) > $max ) {
		return new WP_Error(
			'avitar_too_long',
			sprintf( 'Those notes are too long. Please paste at most %d characters.', $max ),
			array( 'status' => 400 )
		);
	}
	return $notes;
}

/**
 * Enforces the per-IP hourly limit and the site-wide daily cap, counting the
 * request only when both allow it. Many visitors share CGNAT addresses, so the
 * per-IP limit is generous and the daily cap is the real ceiling. The read-then-
 * write isn't atomic; an occasional extra request under load is acceptable.
 */
function avitar_explainer_check_rate_limit( $ip, $now ) {
	$ip_key  = 'avitar_explainer_ip_' . md5( $ip . wp_salt( 'nonce' ) );
	$day_key = 'avitar_explainer_day_' . wp_date( 'Ymd', $now );

	$window = get_transient( $ip_key );
	if ( ! is_array( $window ) || $now - $window['start'] >= HOUR_IN_SECONDS ) {
		$window = array( 'start' => $now, 'count' => 0 );
	}
	$window_ends = $window['start'] + HOUR_IN_SECONDS;

	if ( $window['count'] >= (int) avitar_explainer_config( 'IP_LIMIT' ) ) {
		$minutes = max( 1, (int) ceil( ( $window_ends - $now ) / 60 ) );
		return new WP_Error(
			'avitar_ip_limit',
			sprintf( 'Too many requests from your network. Please try again in about %d %s.', $minutes, 1 === $minutes ? 'minute' : 'minutes' ),
			array( 'status' => 429 )
		);
	}

	$day_count = (int) get_transient( $day_key );
	if ( $day_count >= (int) avitar_explainer_config( 'DAILY_LIMIT' ) ) {
		return new WP_Error(
			'avitar_daily_limit',
			'The explainer has reached its limit for today. Please try again tomorrow.',
			array( 'status' => 429 )
		);
	}

	$window['count']++;
	set_transient( $ip_key, $window, max( 1, $window_ends - $now ) );
	set_transient( $day_key, $day_count + 1, DAY_IN_SECONDS + HOUR_IN_SECONDS );
	return true;
}
