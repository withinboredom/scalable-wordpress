<?php

use Docker\Docker;
use Docker\DockerClient;

/**
 * Class DockerCollector
 *
 * Determines the mode of Docker -- either native or swarm. Then calls phpsysinfo to get monitor information
 */
class DockerCollector {

	/**
	 * @var Docker
	 */
	private $docker;

	/**
	 * DockerCollector constructor.
	 */
	function __construct() {
		$client = new DockerClient( [
			'remote_socket' => 'unix:///var/run/docker.sock'
		] );

		$this->docker = new Docker( $client );

		add_action( 'rest_api_init', function () {
			register_rest_route( 'monitor/v1', '/monitor', [
				'methods'   => 'POST',
				'callbacks' => [ $this, 'collect' ]
			] );
			register_rest_route( 'monitor/v1', '/swarm', [
				'methods'  => 'GET',
				'callback' => [ $this, 'isSwarm' ]
			] );
		} );

		add_action( 'docker_monitor_update', [ $this, 'update' ] );

		if ( ! wp_get_schedule( 'docker_monitor_update' ) ) {

		}
	}

	/**
	 * Determines if the current docker client is a swarm master
	 */
	function isSwarm( WP_REST_Request $data ) {
		if ( ! $this->requestFromContainer() ) {
			die();
		}

		try {
			$result = $this->docker->getServiceManager()->findAll( [], null );

			return true;
		} catch ( Exception $exception ) {
			return false;
		}
	}

	function cidr_match( $ip, $cidr ) {
		list( $subnet, $mask ) = explode( '/', $cidr );

		if ( ( ip2long( $ip ) & ~( ( 1 << ( 32 - $mask ) ) - 1 ) ) == ip2long( $subnet ) ) {
			return true;
		}

		return false;
	}

	private function requestFromContainer() {
		$remoteIp = $_SERVER['HTTP_X_FORWARDED_FOR'];

		if ( $this->cidr_match( $remoteIp, '10.0.0.0/8' ) ) {
			return true;
		}

		if ( $this->cidr_match( $remoteIp, '172.16.0.0/12' ) ) {
			return true;
		}

		if ( $this->cidr_match( $remoteIp, '192.168.0.0/16' ) ) {
			return true;
		}

		return false;
	}

	private function getContainerFromService( $service ) {

	}

	function collect( WP_REST_Request $data ) {
		// todo: determine if request came from container
	}

	function update() {

	}
}

new DockerCollector();
