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
