<?php

namespace ACFCustomizer\AutoUpdate;

if ( ! defined('ABSPATH') ) {
	die('FU!');
}


use ACFCustomizer\Core;

abstract class AutoUpdate extends Core\Singleton {


	/**
	 *	@var array Current release info
	 */
	protected $release_info = null;

	/**
	 *	@inheritdoc
	 */
	protected function __construct() {

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pre_set_transient' ), 10, 3 );

		add_filter( 'upgrader_source_selection', array( $this, 'source_selection' ), 10, 4 );

		add_action( 'upgrader_process_complete', array( $this, 'upgrade_completed' ), 10, 2 );

		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );

	}

	/**
	 *	@action upgrader_process_complete
	 */
	public function upgrade_completed( $wp_upgrader, $hook_extra ) {

		$plugin = plugin_basename( ACF_CUSTOMIZER_FILE );

		if ( $hook_extra['action'] === 'update' && $hook_extra['type'] === 'plugin' && in_array( $plugin, $hook_extra['plugins'] ) ) {

			$plugin_info = get_plugin_data( ACF_CUSTOMIZER_FILE );

			$old_version = get_option( 'acf-customizer_version' );
			$new_version = $plugin_info['Version'];

			do_action( 'acf-customizer_upgraded', $new_version, $old_version );

		//	update_option( 'acf-customizer_version', $plugin_info['Version'] );

		}
	}


	/**
	 *	@filter plugin_api
	 */
	public function plugins_api( $res, $action, $args ) {
		$slug = basename(ACF_CUSTOMIZER_DIRECTORY);
		if ( isset($_REQUEST['plugin']) && $_REQUEST['plugin'] === $slug ) {
			/*

			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',


			*/


			$plugin_info	= get_plugin_data( ACF_CUSTOMIZER_FILE );
			$release_info	= $this->get_release_info();

			$plugin_api = array(
				'name'						=> $plugin_info['Name'],
				'slug'						=> $slug,
//				'version'					=> $release_info, // release
				'author'					=> $plugin_info['Author'],
				'author_profile'			=> $plugin_info['AuthorURI'],
//				'contributors'				=> array(),
//				'requires'					=> '',
//				'tested'					=> '',
//				'requires_php'				=> '',
				'compatibility'				=> array(),
				'rating'					=> 0,
				'num_ratings'				=> 0,
				'support_threads'			=> 0,
				'support_threads_resolved'	=> 0,
//				'active_installs'			=> 0,
//				'last_updated'				=> '2017-11-22 2:41pm GMT', // format!
//				'added'						=> '2017-11-22', // format!
				'homepage'					=> $plugin_info['PluginURI'],
				'sections'					=> $this->get_plugin_sections(),
//				'download_link'	=> '',
//				'screenshots'				=> array(),
//				'tags'						=> array(),
//				'versions'					=> array(),	// releases?
//				'donate_link'				=> '',
				'banners'					=> $this->get_plugin_banners(),
				'external'					=> true,
			) + $release_info;

			return (object) $plugin_api;
		}
		return $res;
	}
	/**
	 *	Will make sure that the downloaded directory name and our plugins dirname are the same.
	 *	@filter upgrader_source_selection
	 */
	public function source_selection( $source, $remote_source, $wp_upgrader, $hook_extra ) {
		if ( isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === plugin_basename( ACF_CUSTOMIZER_FILE ) ) {
			// $source: filepath
			// $remote_source download dir
			$source_dirname = pathinfo( $source, PATHINFO_FILENAME);
			$plugin_dirname = pathinfo( $hook_extra['plugin'], PATHINFO_DIRNAME );

			if ( $source_dirname !== $plugin_dirname ) {

				$new_source = pathinfo( $remote_source, PATHINFO_DIRNAME )  . '/' . $plugin_dirname;

				if ( rename( $source, $new_source ) ) {
					$source = $new_source;
				}
			}

		}
		return $source;
	}
	/**
	 *	@action admin_init
	 */
	public function admin_init() {

		//$this->pre_set_transient( get_site_transient('update_plugins') );
	}

	/**
	 *	Preprocess download.
	 *	Should return false if nothing shall happen
	 *
	 *	@filter upgrader_pre_download
	 */
	public function preprocess_download( $return, $package, $wp_upgrader ) {
		return $return;
	}

	/**
	 *	@filter	pre_set_site_transient_update_plugins
	 */
	public function pre_set_transient( $transient ) {

		if ( ! is_object( $transient ) || ! isset( $transient->response ) ) {
			return $transient;
		}

		// get own version
		if ( $release_info = $this->get_release_info() ) {
			$plugin 		= plugin_basename( ACF_CUSTOMIZER_FILE );
			$slug			= basename(ACF_CUSTOMIZER_DIRECTORY);
			$plugin_info	= get_plugin_data( ACF_CUSTOMIZER_FILE );

			if ( version_compare( $release_info['version'], $plugin_info['Version'] , '>' ) ) {

				$transient->response[ $plugin ] = (object) array(
					'id'			=> $release_info['id'],
					'slug'			=> $slug,
					'plugin'		=> $plugin,
					'new_version'	=> $release_info['version'],
					'url'			=> $plugin_info['PluginURI'],
					'package'		=> $release_info['download_link'],
					'icons'			=> array(),
					'banners'		=> array(),
					'banners_rtl'	=> array(),
					'tested'		=> $release_info['tested'],
					'compatibility'	=> (object) array(),
				);
				if ( isset( $transient->no_update ) && isset( $transient->no_update[$plugin] ) ) {
					unset( $transient->no_update[$plugin] );
				}
			}
		}

		return $transient;
	}

	/**
	 *	Should return info for current release
	 *
	 *	@return array(
	 *		'id'			=> '...'
	 *		'version'		=> '...'
	 *		'download_url'	=> 'https://...'
	 *	)
	 */
	protected function get_release_info() {

		if ( is_null( $this->release_info ) ) {
			$this->release_info = $this->get_remote_release_info();
		}

		return $this->release_info;
	}

	/**
	 *	Should fetch info for current release and return it
	 *
	 *	@return array(
	 *		'id'			=> '...'
	 *		'version'		=> '...'
	 *		'download_link'	=> 'https://...'
	 *		'tested'		=> <WP version>
	 *		'requires'		=> <Min WP version>
	 *		'requires_php'	=> <Min PHP version>
	 *		'last_updated'	=> <date>
	 *	)
	 */
	abstract function get_remote_release_info();

	/**
	 *	Should return plugin page sections
	 *
	 *	@return array(
	 *		'section title'		=> '<Section html>',
	 *		'another section'	=> '...',
	 *		'...'
	 *	)
	 */
	protected function get_plugin_sections() {
		return array();
	}

	/**
	 *	Return plugin banners
	 *
	 *	@return array(
	 *		'low'		=> '<banner URL 772x250px>',
	 *		'high'		=> '<banner URL 1544x500px>',
	 *	)
	 */
	protected function get_plugin_banners() {
		return array();
	}

	/**
	 *	Like WP‘s get_file_data() but with a String
	 *
	 *	@param	string	$data
	 *	@param	array	$info
	 *	@return array
	 */
	protected function extract_info( $data, $info ) {

		// normalize LFs
		$data = str_replace( "\r", "\n", $data );

		foreach ( $info as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $data, $match ) && $match[1] ) {
				$info[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$info[ $field ] = '';
			}
		}
		return array_filter( $info );
	}

}
