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
			register_rest_route( 'docker/v1', 'monitor', [
				'methods'   => 'POST',
				'callbacks' => [ $this, 'collect' ]
			] );
			register_rest_route( 'docker/v1', 'swarm', [
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
	function isSwarm() {
		// todo: determine if request came from container
		try {
			$result = $this->docker->getServiceManager()->findAll();

			return true;
		} catch ( Exception $exception ) {
			return false;
		}
	}

	private function requestFromContainer() {
		return true;
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