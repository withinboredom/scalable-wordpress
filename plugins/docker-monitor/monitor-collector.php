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
		add_action( 'docker_monitor_update', [ $this, 'update' ] );
		add_filter( 'cron_schedules', function ( $schedules ) {
			$schedules['every_second'] = [
				'interval' => 10,
				'display'  => __( 'Every Second', 'textdomain' )
			];

			return $schedules;
		} );

		add_action( 'init', function () {
			//var_dump( wp_get_schedule( 'docker_monitor_update' ) );
			//die();

			if ( wp_get_schedule( 'docker_monitor_update' ) === false ) {
				wp_schedule_event( time(), 'every_second', 'docker_monitor_update' );
			}

			register_post_type( 'metric', [
				'label'    => 'metrics',
				'supports' => [
					'custom-fields'
				]
			] );
		} );

		add_action( 'rest_api_init', function () {
			register_rest_route( 'monitor/v1', '/monitor', [
				'methods'   => 'GET',
				'callbacks' => [ $this, 'collect' ]
			] );
			register_rest_route( 'monitor/v1', '/swarm', [
				'methods'  => 'GET',
				'callback' => [ $this, 'isSwarm' ]
			] );
		} );
	}

	function requestMetrics( $ip ) {
		$metrics = json_decode( wp_remote_get( "http://$ip/phpsysinfo/xml.php?plugin=complete&json" )['body'], JSON_OBJECT_AS_ARRAY );

		return $metrics;
	}

	/**
	 * Perform a GET operation on the local docker socket
	 *
	 * @param $url string The relative url to GET
	 * @param string $parameters The query parameters to use
	 *
	 * @return array|mixed|object|string
	 */
	function get( $url, $parameters = "" ) {
		$socket = fsockopen( 'unix:///var/run/docker.sock' );

		$http = "GET $url$parameters HTTP/1.0\r\nConnection: Close\r\n\r\n";
		fwrite( $socket, $http );
		$data = "";
		while ( ! feof( $socket ) ) {
			$data .= fgets( $socket, 128 );
		}
		fclose( $socket );

		$lines = explode( "\r\n", $data );
		$data  = [];
		$ready = false;
		foreach ( $lines as $line ) {
			if ( ! $ready && empty( $line ) ) {
				$ready = true;
			} else if ( $ready ) {
				$data[] = $line;
			}
		}
		unset( $line );
		unset( $lines );

		$data = json_decode( implode( "\r\n", $data ) );

		return $data;
	}

	/**
	 * POST to the local Docker socket
	 *
	 * @param $url string The relative url
	 * @param $data array The object to post
	 *
	 * @return array|mixed|object|string|void
	 */
	function post( $url, $data ) {
		$data   = json_encode( $data );
		$length = strlen( $data );
		$socket = fsockopen( 'unix:///var/run/docker.sock' );

		$http = "POST $url HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: ${$length}\r\nConnection: Close\r\n\r\n$data";

		fwrite( $socket, $http );
		$data = "";
		while ( ! feof( $socket ) ) {
			$data .= fgets( $socket, 128 );
		}
		fclose( $socket );

		$lines = explode( "\r\n", $data );
		$data  = [];
		$ready = false;
		foreach ( $lines as $line ) {
			if ( ! $ready && empty( $line ) ) {
				$ready = true;
			} else if ( $ready ) {
				$data [] = $line;
			}
		}
		unset( $line );
		unset( $lines );

		$data = json_decode( implode( "\r\n", $data ) );

		return $data;
	}

	/**
	 * Finds a list of service ids with a partial name match
	 *
	 * @param $class string The service name to partially match
	 *
	 * @return array An array of service ids
	 */
	function findServiceWithClass( $class ) {
		$services = $this->get( '/services' );
		$targets  = [];
		$len      = 0 - strlen( $class );
		foreach ( $services as $service ) {
			if ( substr( $service->Spec->Name, $len ) === $class ) {
				$targets[] = $service->ID;
			}
		}

		return $targets;
	}

	/**
	 * Gets a list of ips to a service by node
	 *
	 * @param $class string The service name to partially match
	 *
	 * @return array A map of nodes to containers
	 */
	function findContainerWithClassByNode( $class ) {
		$serviceId = $this->findServiceWithClass( $class )[0];
		$tasks     = $this->get( '/tasks' );

		$containers = [];

		foreach ( $tasks as $task ) {
			if ( $task->ServiceID == $serviceId ) {
				if ( ! isset( $containers[ $task->NodeID ] ) ) {
					$containers[ $task->NodeID ] = [];
				}

				$containers[ $task->NodeID ] = array_merge( $containers[ $task->NodeID ], $task->NetworksAttachments[0]->Addresses );
			}
		}

		return $containers;
	}

	function recordMetrics() {
		if ( ! $this->isSwarm() ) {
			return false;
		}

		$nodes = $this->findContainerWithClassByNode( 'phpsysinfo' );
		foreach ( $nodes as $node => $ips ) {
			foreach ( $ips as $ip ) {
				$ip = explode( '/', $ip )[0];

				$metrics = $this->requestMetrics( $ip );
				$post    = [
					'post_author'    => 0,
					'post_title'     => $node,
					'post_status'    => 'published',
					'post_type'      => 'metric',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
					'meta_input'     => [
						'vitals'     => $metrics['Vitals']['@attributes'],
						'hardware'   => $metrics['Hardware']['CPU']['CpuCore'],
						'memory'     => $metrics['Memory'],
						'filesystem' => $metrics['Filesystem']['Mount']
					]
				];

				wp_insert_post( $post );
			}
		}

		return true;
	}

	/**
	 * Determines if the current docker client is a swarm master
	 */
	function isSwarm() {
		if ( ! $this->requestFromContainer() ) {
			die();
		}

		try {
			$result = $this->docker->getServiceManager()->findAll( [], null );
			update_option( 'isDockerSwarm', true );

			return true;
		} catch ( Exception $exception ) {
			update_option( 'isDockerSwarm', false );

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

	/**
	 * Determines if a request came from a private network
	 * @return bool
	 */
	private function requestFromContainer() {
		$remoteIp = $_SERVER['HTTP_X_FORWARDED_FOR'];

		if ( empty( $remoteIp ) ) {
			$remoteIp = $_SERVER['REMOTE_ADDR'];
		}

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

	function collect() {
		// todo: determine if request came from container
		return true;
	}

	/**
	 * Called on a schedule to auto-update stats
	 */
	function update() {
		wp_remote_get( 'http://master/wp-json/docker/v1/monitor' );
	}
}

new DockerCollector();
