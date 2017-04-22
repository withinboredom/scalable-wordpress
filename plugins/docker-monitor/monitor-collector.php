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
				'interval' => 5,
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
			register_rest_route( 'monitor/v1', '/collect', [
				'methods'  => 'GET',
				'callback' => [ $this, 'collect' ]
			] );
			register_rest_route( 'monitor/v1', '/swarm', [
				'methods'  => 'GET',
				'callback' => [ $this, 'isSwarm' ]
			] );
		} );
	}

	function recordMetrics() {
		if ( ! $this->isSwarm() ) {
			return new WP_Error( 500, 'not a swarm' );
		}

		$nodes = apply_filters( 'all_docker_nodes', [] );

		$tasks = apply_filters( 'all_docker_tasks', [] );

		$post = [
			'post_author'    => 0,
			'post_title'     => time(),
			'post_status'    => 'published',
			'post_type'      => 'metric',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'meta_input'     => []
		];

		foreach ( $nodes as $node => $ips ) {
			$docker = apply_filters( 'docker_node_info', $node );
			foreach ( $ips as $ip ) {
				$ip = explode( '/', $ip )[0];

				$metrics = apply_filters( 'docker_node_vitals', $ip );

				$node_tasks = apply_filters( 'node_tasks', [ 'tasks' => $tasks, 'node' => $node ] );

				$post['meta_input'][ $node ] = [
					'Hostname'    => $docker['Hostname'],
					'Ip'          => $docker['Ip'],
					'Uptime'      => $metrics['Uptime'],
					'LoadAvg5'    => $metrics['LoadAvg5'],
					'LoadAvg10'   => $metrics['LoadAvg10'],
					'LoadAvg15'   => $metrics['LoadAvg15'],
					'CurrentLoad' => $metrics['CurrentLoad'],
					'NumCPUs'     => $metrics['NumCPUs'],
					'Memory'      => [
						'Free'      => $metrics['MemoryFree'],
						'Used'      => $metrics['MemoryUsed'],
						'Total'     => $metrics['MemoryTotal'],
						'App'       => $metrics['MemoryApp'],
						'Buffers'   => $metrics['MemoryBuffers'],
						'Cache'     => $metrics['MemoryCache'],
						'SwapTotal' => $metrics['SwapTotal'],
						'SwapUsed'  => $metrics['SwapUsed']
					],
					'Docker'      => [
						'Role'         => $docker['Role'],
						'Availability' => $docker['Availability'],
						'NanoCPU'      => $docker['NanoCPU'],
						'MemoryBytes'  => $docker['MemoryBytes'],
						'Leader'       => $docker['Leader'],
						'ReservedCPU'  => apply_filters( 'node_task_cpu_reserved', $node_tasks ),
						'ReservedMem'  => apply_filters( 'node_task_mem_reserved', $node_tasks ),
						'LimitsCPU'    => apply_filters( 'node_task_cpu_limits', $node_tasks ),
						'LimitsMem'    => apply_filters( 'node_task_mem_limits', $node_tasks )
					]
				];
			}
		}

		/*
				foreach ( $nodes as $node => $ips ) {

					foreach ( $ips as $ip ) {
						$ip = explode( '/', $ip )[0];

						$metrics                     = $this->requestMetrics( $ip );
						$post['meta_input'][ $node ] = [
							'vitals'     => $metrics['Vitals']['@attributes'],
							'hardware'   => $metrics['Hardware']['CPU']['CpuCore'],
							'memory'     => $metrics['Memory'],
							'filesystem' => $metrics['FileSystem']['Mount'],
							'docker'     => $docker,
							'tasks'      => array_filter( $tasks, function ( $task ) use ( $node ) {
								return $task->NodeID == $node;
							} ),
						];
					}
				}
		*/
		wp_insert_post( $post );

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
		if ( ! $this->requestFromContainer() ) {
			return new WP_Error( 500, 'not a container' );
		}

		return $this->recordMetrics();
	}

	/**
	 * Called on a schedule to auto-update stats
	 */
	function update() {
		wp_remote_get( 'http://master/wp-json/monitor/v1/collect', [
			'headers' => [
				'httpversion' => '1.0',
				'blocking'    => true
			]
		] );
	}
}

new DockerCollector();
