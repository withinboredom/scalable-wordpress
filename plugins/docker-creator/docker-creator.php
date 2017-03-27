<?php
/*
Plugin Name: Docker Creator
Plugin URI:  https://developer.wordpress.org/plugins/the-basics/
Description: Creates Dockerfiles
Version:     0.0.1
Author:      withinboredom
Author URI:  https://www.withinboredom.info/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: docker-creator
Domain Path: /languages
*/

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

require_once( 'vendor/autoload.php' );

use Docker\Docker;
use Docker\DockerClient;

class DockerDeployment {
	function __construct() {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'docker/v1', 'file', [
				'methods'  => 'GET',
				'callback' => [ $this, 'BaseFile' ]
			] );
			register_rest_route( 'docker/v1', 'file', [
				'methods'  => 'POST',
				'callback' => [ $this, 'Extend' ]
			] );
			register_rest_route( 'docker/v1', 'build', [
				'methods'  => 'POST',
				'callback' => [ $this, 'BuildAndPush' ]
			] );
		} );

		
	}

	function BaseFile() {
		$wpVersion = getenv( 'WORDPRESS_VERSION' );

		return [
			'contents' => "
FROM withinboredom/scalable-wordpress:$wpVersion-apache
"
		];
	}

	function getUrlFromSlug( $plugin ) {
		$url = null;
		switch ( $plugin['type'] ) {
			case 'wporg':
				$slug     = $plugin['slug'] ?: die( 'invalid slug' );
				$version  = $plugin['version'] ?: 'latest';
				$response = wp_remote_get( "https://api.wordpress.org/plugins/info/1.0/$slug.json" );
				$response = json_decode( $response['body'] );

				if ( $response === null ) {
					die( 'invalid slug' );
				}

				$url = $response->download_link;

				if ( $version !== 'latest' ) {
					$url = explode( '.', $url )[0] . ".$version" . '.zip';
				}

				break;
			case 'url':
				$url = $plugin['url'];
				break;
		}

		return $url;
	}

	function BuildAndPush( WP_REST_Request $params ) {
		$key = wp_generate_password( 12, false, false );
		set_transient( 'docker_' . $key, $params, DAY_IN_SECONDS );
		$repo = $params->get_param( 'repo' );
		//update_option( 'docker_' . $key, serialize( $params ) );
		$client = new DockerClient( [
			'remote_socket' => 'unix:///var/run/docker.sock'
		] );

		$docker = new Docker( $client );

		$tar        = new PharData( ABSPATH . "/wp-content/$key.tar" );
		$dockerfile = $this->Extend( $params )['contents'];

		$tar->addFromString( 'Dockerfile', $dockerfile );
		unset( $tar );

		$build = [
			'dockerfile' => 'Dockerfile',
			't'          => "$repo:$key",
			'pull'       => true
		];

		$tar     = ABSPATH . "/wp-content/$key.tar";
		$tarball = file_get_contents( $tar );
		unlink( $tar );

		try {
			$results = $docker->getImageManager()->build( $tarball, $build );
			unset( $tarball );
			$output = [];
			foreach ( $results as $result ) {
				$output[] = $result->getStream();
			}

			$results = $docker->getImageManager()->push( "$repo:$key", [
				'X-Registry-Auth' => base64_encode( json_encode( [
					'username' => $params->get_param( 'username' ),
					'password' => $params->get_param( 'password' ),
					'email'    => $params->get_param( 'email' )
				] ) )
			] );
			foreach ( $results as $result ) {
				$output[] = $result->getProgress() . ' :: ' . $result->getStatus();
			}

			return [
				'output' => $output,
				'image'  => "$repo:$key"
			];
		} catch ( Exception $exception ) {
			return [
				$exception->getMessage(),
				$exception->getTrace()
			];
		}
	}

	function Extend( WP_REST_Request $params ) {
		$plugins = $params->get_param( 'plugins' );
		$theme   = $params->get_param( 'themes' );

		$base = $this->BaseFile()['contents'];

		$base .= "USER www-data\n";

		foreach ( $plugins as $plugin ) {
			$url = $this->getUrlFromSlug( $plugin );

			if ( empty( $url ) ) {
				continue;
			}

			$base .= "RUN curl $url > /var/www/html/wp-content/plugins/$(basename $url) && cd /var/www/html/wp-content/plugins && unzip $(basename $url)\n";
		}
		unset( $plugin );

		$themeUrl = $this->getUrlFromSlug( $theme );
		if ( ! empty( $themeUrl ) ) {
			$base .= "RUN curl $themeUrl > /var/www/html/wp-content/themes/$(basename $themeUrl) && cd /var/www/html/wp-content/themes && unzip \n";
		}

		$base .= "USER root\n";

		return [
			"contents" => $base
		];
	}
}

/*
Expects json of the form:
{
	"plugins": [
		{
			"type": "wporg",
			"slug": "jetpack",
			"version": "latest"
		},
		{
			"type": "url",
			"url": "http://url/to/plugin.zip"
		}
	],
	"theme": {
		"type": "wporg",
		"slug": "twentysixteen",
		"version": "latest"
	}
}
*/

$GLOBALS['DOCKER'] = new DockerDeployment();
