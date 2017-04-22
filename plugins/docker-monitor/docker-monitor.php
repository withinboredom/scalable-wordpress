<?php
/*
Plugin Name: Docker Monitor
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

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

require_once( 'vendor/autoload.php' );

require_once 'monitor-collector.php';

function secondsToTime( $seconds ) {
	$dtF = new \DateTime( '@0' );
	$dtT = new \DateTime( "@$seconds" );

	return $dtF->diff( $dtT )->format( '%a days, %h hours, %i minutes and %s seconds' );
}

function cluster_manager() {
	do_action( 'docker_monitor_update' );
	$post = get_posts( [
		'post_type'      => 'metric',
		'posts_per_page' => 1,
		'post_status'    => 'published'
	] )[0];
	$meta = get_post_meta( $post->ID );
	foreach ( $meta as $node => $data ) {
		foreach ( $data as $datum ) {
			$meta[ $node ] = unserialize( $datum );
		}
	}
	?>
	<style>
		.nodes {
			width: 50%;
			margin-top: 20px;
		}

		.node {
			border: 1px solid black;
			border-radius: 20px;
		}

		.node_header {
			padding: 3px;
			text-align: center;
			background-color: #4F800D;
			border-top-left-radius: 20px;
			border-top-right-radius: 20px;
			color: #a8bece;
			font-weight: bold;
			font-size: large;
		}

		.node_body {
			padding: 10px;
		}

		.text_stat {
			border-bottom: 1px solid black;
		}

		.bar_stat {
			height: 20px;
			position: relative;
			background: #555;
			-moz-border-radius: 10px;
			-webkit-border-radius: 10px;
			border-radius: 10px;
			padding: 10px;
			box-shadow: inset 0 -1px 1px rgba(255, 255, 255, 0.3);
			margin: 5px;
			line-height: 26px;
		}

		.bar_fill {
			display: block;
			height: 100%;
			border-top-right-radius: 8px;
			border-bottom-right-radius: 8px;
			border-top-left-radius: 20px;
			border-bottom-left-radius: 20px;
			background-color: rgb(43, 194, 83);
			background-image: linear-gradient(to bottom, #f1a165, #f36d0a);
			box-shadow: inset 0 2px 9px rgba(255, 255, 255, 0.3),
			inset 0 -2px 6px rgba(0, 0, 0, 0.4);
			position: relative;
			overflow: hidden;
			text-align: center;
			color: whitesmoke;

		}

		.bar_label {
			position: absolute;
			left: 0;
			top: 0;
			text-align: center;
			vertical-align: middle;
			color: whitesmoke;
			padding: .5em;
		}
	</style>
	<div class="metric_count">Current number of metric points: <?= wp_count_posts( 'metric' )->published ?></div>
	<div class="nodes">
		<?php foreach ( $meta as $node => $data ): ?>
			<div class="node">
				<div class="node_header"><?= $node ?></div>
				<div class="node_body">
					<div class="text_stat">
						<div class="stat_label">Uptime:</div>
						<div class="stat_stat"><?= secondsToTime( (int) $data['Uptime'] ) ?></div>
					</div>
					<div class="bar_stat">
						<span class="bar_fill"
						      style="width: <?= $data['CurrentLoad'] / (float) count( $data['NumCPUs'] ) ?>%">
							</span>
						<span class="bar_label">
							CPU Load: <?= $data['LoadAvg5'] ?>
						</span>
					</div>
					<div class="bar_stat">
						<span class="bar_fill" style="width: <?= $data['Memory'][''] ?>%"></span>
						<span class="bar_label">
							App: <?= $data['Memory'] ?>%,
							Buffers: <?= $data['memory']['Details']['@attributes']['BuffersPercent'] ?>%,
							Cached: <?= $data['memory']['Details']['@attributes']['CachedPercent'] ?>%
						</span>
					</div>
					<?php foreach ( $data['filesystem'] as $mountpoint ): ?>
						<div class="bar_stat">
							<span class="bar_fill" style="width: <?= $mountpoint['@attributes']['Percent'] ?>%"></span>
							<span class="bar_label">
								Mounted: <?= $mountpoint['@attributes']['MountPoint'] ?>, <?= $mountpoint['@attributes']['Percent'] ?>% Used
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<pre><?php var_dump( $meta ); ?></pre>
	<?php
}

add_action( 'admin_menu', function () {
	add_management_page( 'Server Monitor', 'Cluster Monitor', 'administrator', 'cluster-admin', 'cluster_manager' );
} );
