<?php
   /*
   Plugin Name: Twoface
   Plugin URI: https://github.com/wrktg/twoface
   Description: a plugin that allows to show different theme and content to unauthenticated visitors
   Version: 1.0
   Author: Taras Mankovski
   Author URI: http://taras.cc
   License: GPL2
   */

// Don't load directly
if ( !defined('ABSPATH') ) { die('-1'); }

if( !class_exists( 'WRKTG_TwoFace' ) ) {
	class WRKTG_TwoFace {

        function WRKTG_TwoFace() {

            // hook to init because is_user_logged_in is not available before init
            add_action( 'init', array( $this, 'init') );
            add_action( 'admin_init', array($this, 'admin_init'));

            add_filter('template', array($this, 'show_public_template'));
            add_filter('stylesheet', array($this, 'show_public_stylesheet'));

        }

        /**
         *
         */
        public function init() {

            add_action( 'pre_get_posts', array( $this, 'pre_get_posts') );
            add_action( 'save_post', array($this, 'save_post') );

            // Add new taxonomy, make it hierarchical (like categories)
            $labels = array(
                'name' => _x( 'Visibility Options', 'taxonomy general name' ),
                'singular_name' => _x( 'Visibility Option', 'taxonomy singular name' ),
                'search_items' =>  __( 'Search Visibility Options' ),
                'all_items' => __( 'All Visibility Options' ),
                'parent_item' => __( 'Parent Visibility Option' ),
                'parent_item_colon' => __( 'Parent Visibility Option:' ),
                'edit_item' => __( 'Edit Visibility Option' ),
                'update_item' => __( 'Update Visibility Option' ),
                'add_new_item' => __( 'Add New Visibility Option' ),
                'new_item_name' => __( 'New Visibility Option Name' ),
                'menu_name' => __( 'Visibility Options' ),
            );

            # Visibility taxonomy determines if the post is exposed to the public
            register_taxonomy('visibility', array('post'), array(
                'hierarchical' => false,
                'labels' => $labels,
                'show_ui' => false
              ));

            global $wp_roles;

            if ( isset($wp_roles) ) {
                $wp_roles->add_cap( 'administrator', 'publicize' );
                $wp_roles->add_cap( 'administrator', 'un-publicize' );
            }

        }

        /**
         * Modify the main query to only show content that's in Visibility->Public
         * @param $query
         */
        public function pre_get_posts( $query ) {

            global $wp_the_query;

            if ( !is_user_logged_in() && !is_admin() && is_main_query() && !is_page() && $wp_the_query === $query ) {

                $query->set('tax_query', array(
                    array('taxonomy'=>'visibility', 'field'=>'slug', 'terms' => array('public'))
                ));

            }

        }

        /**
         *
         */
        public function admin_init() {

            add_action( 'add_meta_boxes', array($this, 'admin_metabox') );

        }

        /**
         * Add metabox to Post Edit screen
         */
        public function admin_metabox () {

            add_meta_box( 'wrktg_twoface_admin_metabox', 'Visibility', array($this, 'admin_metabox_content'), 'post', 'normal' );

        }

        /* Prints the box content */
        public function admin_metabox_content( $post, $metabox ) {

          // Use nonce for verification
          wp_nonce_field( plugin_basename( __FILE__ ), 'wrktg_twoface_noncename' );

          // The actual fields for data entry
          printf('<input type="checkbox" id="wrktg_twoface_public" name="wrktg_twoface_public" %s />%s',
              (wp_get_object_terms($post->ID, 'visibility'))?'checked':'',
              _e("Make this post public: ", 'wrktg_twoface' ));

        }

        /**
         * Add post to taxonomy if its marked public
         */
        public function save_post( $post_id ) {

            // verify if this is an auto save routine.
            // If it is our form has not been submitted, so we dont want to do anything
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
                return;

            // verify this came from the our screen and with proper authorization,
            // because save_post can be triggered at other times

            if ( array_key_exists('wrktg_twoface_noncename', $_POST ) ) {
                $nonce = $_POST['wrktg_twoface_noncename'];
            } else {
                $nonce = '';
            }

            if ( !wp_verify_nonce( $nonce, plugin_basename( __FILE__ ) ) )
                return;

            if ( array_key_exists('wrktg_twoface_public', $_POST) && $_POST['wrktg_twoface_public'] == 'on' ) {
                wp_set_object_terms( $post_id, 'public', 'visibility', false );
            } else {
                wp_set_object_terms( $post_id, null, 'visibility', false );
            }

        }

        public function show_public_template($template) {

            if ( !is_user_logged_in() && !is_admin() && defined('TWOFACE_PUBLIC_TEMPLATE') ) {
                return TWOFACE_PUBLIC_TEMPLATE;
            }

            return $template;
        }

        public function show_public_stylesheet($template) {

            if ( !is_user_logged_in() && !is_admin() && defined('TWOFACE_PUBLIC_STYLESHEET') ) {
                return TWOFACE_PUBLIC_STYLESHEET;
            }

            return $template;
        }

    }

}

new WRKTG_TwoFace();