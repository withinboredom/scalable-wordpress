<?php

/*
Plugin Name: Docker Monitor: Docker Swarm Adapter
Plugin URI:  https://developer.wordpress.org/plugins/the-basics/
Description: Monitors a docker host (Requires docker-creator)
Version:     0.0.1
Author:      withinboredom
Author URI:  https://www.withinboredom.info/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: docker-updater
Domain Path: /languages
*/

class docker_swarm_adapter {
	function __construct() {
		add_filter( 'all_docker_nodes', [ $this, 'all_docker_nodes' ] );
		add_filter( 'all_docker_tasks', [ $this, 'all_docker_tasks' ] );
		add_filter( 'docker_node_info', [ $this, 'docker_node_info' ] );
		add_filter( 'node_task_cpu_reserved', [ $this, 'node_task_cpu_reserved' ] );
		add_filter( 'node_task_mem_reserved', [ $this, 'node_task_mem_reserved' ] );
		add_filter( 'node_task_cpu_limits', [ $this, 'node_task_cpu_limits' ] );
		add_filter( 'node_task_mem_limits', [ $this, 'node_task_mem_limits' ] );
		add_filter( 'node_tasks', [ $this, 'node_tasks' ] );
	}

	function all_docker_nodes() {
		$nodes = get_transient( 'nodes' );
		if ( empty( $nodes ) ) {
			$nodes = $this->findContainerWithClassByNode( apply_filters( 'default_monitor_service_name', 'phpsysinfo' ) );
			set_transient( 'nodes', $nodes, 60 );
		}

		return $nodes;
	}

	function all_docker_tasks() {
		$tasks = $this->get( '/tasks' );

		return $tasks;
	}

	function docker_node_info( $node ) {
		$docker = $this->get( "/nodes/$node" );

		return [
			'Hostname'     => $docker['Description']['Hostname'],
			'Ip'           => $docker['Status']['Addr'],
			'Role'         => $docker['Spec']['Role'],
			'Availability' => $docker['Spec']['Availability'],
			'NanoCPU'      => $docker['Description']['Resources']['NanoCPUs'],
			'MemoryBytes'  => $docker['Description']['Resources']['MemoryBytes'],
			'Leader'       => $docker['ManagerStatus']['Leader']
		];
	}

	function node_task_cpu_reserved( $tasks ) {
		return array_reduce( $tasks, function ( $carry, $current ) {
			return $carry + $current['Spec']['Resources']['Reservations']['NanoCPUs'] ?: 0;
		}, 0 );
	}

	function node_task_mem_reserved( $tasks ) {
		return array_reduce( $tasks, function ( $carry, $current ) {
			return $carry + $current['Spec']['Resources']['Reservations']['MemoryBytes'] ?: 0;
		}, 0 );
	}

	function node_task_cpu_limits( $tasks ) {
		return array_reduce( $tasks, function ( $carry, $current ) {
			return $carry + $current['Spec']['Resources']['Limits']['NanoCPUs'] ?: 0;
		}, 0 );
	}

	function node_task_mem_limits($tasks) {
		return array_reduce( $tasks, function ( $carry, $current ) {
			return $carry + $current['Spec']['Resources']['Limits']['MemoryBytes'] ?: 0;
		}, 0 );
	}

	function node_tasks( $tasks ) {
		return array_filter( $tasks['tasks'], function ( $task ) use ( $tasks ) {
			return $task['NodeID'] === $tasks['node'];
		} );
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

		$data = json_decode( implode( "\r\n", $data ), JSON_OBJECT_AS_ARRAY );

		return $data;
	}

	/**
	 * POST to the local Docker socket
	 *
	 * @param $url string The relative url
	 * @param $data array The object to post
	 *
	 * @return array|mixed|object|string
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
			if ( substr( $service['Spec']['Name'], $len ) === $class ) {
				$targets[] = $service['ID'];
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
			if ( $task['ServiceID'] == $serviceId ) {
				if ( ! isset( $containers[ $task['NodeID'] ] ) ) {
					$containers[ $task['NodeID'] ] = [];
				}

				$containers[ $task['NodeID'] ] = array_merge( $containers[ $task['NodeID'] ], $task['NetworksAttachments'][0]['Addresses'] );
			}
		}

		return $containers;
	}
}

new docker_swarm_adapter();
