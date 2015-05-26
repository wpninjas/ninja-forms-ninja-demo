<?php
/*
Plugin Name: Ninja Forms - Ninja Demo
Plugin URI: http://ninjademo.com
Description: Create Ninja Demo sandboxes with a Ninja Form. Requires the Ninja Demo plugin.
Version: 1.0
Author: The WP Ninjas
Author URI: http://wpninjas.com
Text Domain: ninja-forms-ninja-demo
Domain Path: /lang/

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Portions of this plugin are derived from NS Cloner, which is released under the GPL2.
These unmodified sections are Copywritten 2012 Never Settle
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class NF_Ninja_Demo {

	/**
	 * Get things started
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	function __construct() {
		// Add our settings
		add_action( 'admin_init', array( $this, 'add_settings' ), 12 );

		// Add our pre processing functions
		add_action( 'ninja_forms_pre_process', array( $this, 'deactivate_emails' ) );

		// Add our post processing functions
		add_action( 'ninja_forms_post_process', array( $this, 'create_sandbox' ), 1001 );

		// Check all of our forms to see if we should prevent any from being copied.
		add_action( 'nd_create_sandbox', array( $this, 'remove_forms' ) );
	}

	/**
	 * Add our settings to the Form Settings tab
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	function add_settings() {
		if ( class_exists( 'Ninja_Demo' ) && Ninja_Demo()->is_admin_user() ) {
			$sites = wp_get_sites();
			$tmp_array = array();
			foreach ( $sites as $site ) {
				if ( ! Ninja_Demo()->is_sandbox( $site['blog_id'] ) ) {
					$tmp_array[] = array( 'name' => $site['path'], 'value' => $site['blog_id'] );
				}
			}
			// Register our PayPal Settings metabox.
		    $args = array(
				'page' => 'ninja-forms',
				'tab' => 'form_settings',
				'slug' => 'ninja_demo',
				'title' => __( 'Ninja Demo Settings' ),
				'display_function' => '',
				'state' => 'closed',
				'settings' => array(
					array(
						'name' 		=> 'nd_create_sandbox',
						'type' 		=> 'checkbox',
						'label' 	=> __( 'Create a demo with this form' ),
					),
					array(
						'name' 		=> 'nd_source_id',
						'type'		=> 'select',
						'label'		=> 'Select An Origin',
						'options'	=> $tmp_array,
					),
					array(
						'name' => 'nd_prevent_clone',
						'type' => 'checkbox',
						'label' => __( 'Prevent this form from being added to sandboxes' ),
					),
		      	),
		    );
		    if ( function_exists( 'ninja_forms_register_tab_metabox' ) ) {
		      ninja_forms_register_tab_metabox($args);
		    }
		}
	}

	/**
	 * Deactivate all our email notifications for sending later.
	 * @since  1.0
	 * @return void
	 */
	function deactivate_emails() {
		global $ninja_forms_processing;
		$form_id = $ninja_forms_processing->get_form_ID();
		$notifications = nf_get_notifications_by_form_id( $form_id );
		foreach ($notifications as $id => $n ) {
			if ( 'email' == $n['type'] ) {
				Ninja_Forms()->notification( $id )->active = false;
			}
		}
	}

	/**
	 * Create our sandbox
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	function create_sandbox() {
		global $ninja_forms_processing;

		if ( $ninja_forms_processing->get_form_setting( 'nd_create_sandbox' ) == 1 && class_exists( 'Ninja_Demo' ) ) {
			if ( 1 == Ninja_Demo()->settings['offline'] || 1 == Ninja_Demo()->settings['prevent_clones'] ) {
				$ninja_forms_processing->add_error( 'nd_demo', 'This demo is currently offline' );
			} else {
				add_action( 'nd_create_sandbox', array( $this, 'send_emails' ) );
				Ninja_Demo()->sandbox->create( $ninja_forms_processing->get_form_setting( 'nd_source_id' ) );
			}
		}
	}

	/**
	 * Check to see if this form should exist in the sandbox or not.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	function remove_forms( $blog_id ) {
		global $wpdb;

		$all_forms = ninja_forms_get_all_forms( true );

		foreach ( $all_forms as $form ) {
			if ( isset ( $form['data']['nd_prevent_clone'] ) && $form['data']['nd_prevent_clone'] == 1 ) {
				$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix . "ninja_forms WHERE id = %d", $form['id']));
				$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix . "ninja_forms_fields WHERE form_id = %d", $form['id']));
			}
		}
	}

	/**
	 * Fire any email notifications.
	 *
	 * @access public
	 * @since  1.0
	 * @return  void
	 */
	public function send_emails( $blog_id ) {
		global $ninja_forms_processing;
		$this->blog_id = $blog_id;
		add_shortcode( 'nd_sandbox_url', array( $this, 'process_shortcode' ) );
		
		$form_id = $ninja_forms_processing->get_form_ID();
		$notifications = nf_get_notifications_by_form_id( $form_id );
		foreach ($notifications as $id => $n ) {
			if ( 'email' == $n['type'] ) {
				Ninja_Forms()->notification( $id )->process();
			}
		}
	}

	/**
	 * Process our blog URL shortcode
	 *
	 * @access public
	 * @since  1.0
	 * @return $url string
	 */
	public function process_shortcode( $atts ) {
		return get_blog_details( $this->blog_id )->siteurl;
	}
}

function NF_Ninja_Demo() {
	return new NF_Ninja_Demo();
}

NF_Ninja_Demo();
