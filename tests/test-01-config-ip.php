<?php
check( 'model defaults to Opus 5.5', 'claude-opus-5-5' === avitar_explainer_config( 'MODEL' ) );
check( 'IP limit defaults to 30', 30 === avitar_explainer_config( 'IP_LIMIT' ) );
check( 'daily limit defaults to 500', 500 === avitar_explainer_config( 'DAILY_LIMIT' ) );
check( 'max chars defaults to 4000', 4000 === avitar_explainer_config( 'MAX_CHARS' ) );
check( 'trusted proxy defaults off', false === avitar_explainer_config( 'TRUSTED_PROXY' ) );

$server = array(
	'REMOTE_ADDR'           => '203.0.113.7',
	'HTTP_X_FORWARDED_FOR'  => '198.51.100.1',
	'HTTP_CF_CONNECTING_IP' => '198.51.100.2',
);
check( 'uses REMOTE_ADDR when proxy not trusted', '203.0.113.7' === avitar_explainer_client_ip( $server, false ) );
check( 'cloudflare mode uses CF-Connecting-IP', '198.51.100.2' === avitar_explainer_client_ip( $server, 'cloudflare' ) );
check(
	'cloudflare mode ignores X-Forwarded-For',
	'10.0.0.1' === avitar_explainer_client_ip( array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1' ), 'cloudflare' )
);
check(
	'proxy mode uses the rightmost X-Forwarded-For entry, ignoring a spoofed leftmost one',
	'198.51.100.1' === avitar_explainer_client_ip( array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.1' ), 'proxy' )
);
check(
	'proxy mode ignores CF-Connecting-IP',
	'10.0.0.1' === avitar_explainer_client_ip( array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_CF_CONNECTING_IP' => '6.6.6.6' ), 'proxy' )
);
check(
	'true behaves as proxy mode',
	'198.51.100.1' === avitar_explainer_client_ip( array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.1' ), true )
);
check(
	'falls back to REMOTE_ADDR when the rightmost forwarded entry is garbage',
	'10.0.0.1' === avitar_explainer_client_ip( array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1, not-an-ip' ), 'proxy' )
);
check( 'returns "unknown" with no usable address', 'unknown' === avitar_explainer_client_ip( array(), false ) );
check( 'accepts IPv6', '2001:db8::1' === avitar_explainer_client_ip( array( 'REMOTE_ADDR' => '2001:db8::1' ), false ) );
