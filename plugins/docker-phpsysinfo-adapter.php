<?php

/*
Plugin Name: Docker Monitor: phpsysinfo adapter
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

class phpsysinfo_adapter {
	function __construct() {
		add_filter( 'default_monitor_service_name', function () {
			return 'phpsysinfo';
		} );
		add_filter( 'docker_node_vitals', [ $this, 'docker_node_vitals' ] );
	}

	function docker_node_vitals( $ip ) {
		$metrics = json_decode( wp_remote_get( "http://$ip/phpsysinfo/xml.php?plugin=complete&json" )['body'], JSON_OBJECT_AS_ARRAY );

		$load = explode( ' ', $metrics['Vitals']['@attributes']['LoadAvg'] );

		return [
			'Uptime'        => $metrics['Vitals']['@attributes']['Uptime'],
			'LoadAvg5'      => $load[0],
			'LoadAvg10'     => $load[1],
			'LoadAvg15'     => $load[2],
			'CurrentLoad'   => $metrics['Vitals']['@attributes']['CPULoad'],
			'NumCPUs'       => count( $metrics['Hardware']['CPU']['CpuCore'] ),
			'MemoryFree'    => $metrics['Memory']['@attributes']['Free'],
			'MemoryUsed'    => $metrics['Memory']['@attributes']['Used'],
			'MemoryTotal'   => $metrics['Memory']['@attributes']['Total'],
			'MemoryApp'     => $metrics['Memory']['Details']['@attributes']['App'],
			'MemoryBuffers' => $metrics['Memory']['Details']['@attributes']['Buffers'],
			'MemoryCache'   => $metrics['Memory']['Details']['@attributes']['Cached'],
			'SwapTotal'     => $metrics['Memory']['Swap']['@attributes']['Total'],
			'SwapUsed'      => $metrics['Memory']['Swap']['@attributes']['Used']
		];
	}
}

new phpsysinfo_adapter();
