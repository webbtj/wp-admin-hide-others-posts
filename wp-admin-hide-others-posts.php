<?php

/**
 * Plugin Name: WP Admin Hide Other's Posts
 * Author: TJ Webb
 * Author URI: http://webb.tj/
 * Version: 1.1
 * Description: Hides posts by other authors in the admin area, manageable with a "view_others_posts" permission
 */

class WPHideOthersPosts{
	public static function filter_post_type($post_type){
		return array_key_exists($post_type, get_option('wp_hide_others_posts_enabled'));
	}

	public static function filter($query){
		if($query->is_admin && !current_user_can('view_others_posts') && WPHideOthersPosts::filter_post_type($query->query['post_type'])) {
			global $user_ID;
			$query->set('author',  $user_ID);
		}
		return $query;
	}

	public static function activate(){
		$role = get_role('administrator');
		$role->add_cap('view_others_posts');
		if(false===get_option('wp_hide_others_posts_enabled')){
            update_option('wp_hide_others_posts_enabled', array('post'=>1, 'page'=>1));
        }
	}

	public static function get_views_filters(){
		if(!current_user_can('view_others_posts')){
			$post_types = get_post_types();
			foreach($post_types as $post_type){
				add_filter('views_edit-'.$post_type, array('WPHideOthersPosts', 'filter_views'));
			}
		}
	}

	public static function filter_views($view){
		global $post_type_object;
		if(WPHideOthersPosts::filter_post_type($post_type_object->name))
			return WPHideOthersPosts::get_views();
		return $view;
	}

	//copied and adapted from WP_Post_List_Table
	public static function get_views() {
		global $locked_post_status, $avail_post_stati, $post_type_object, $wpdb;

		$post_type = $post_type_object->name;
		$post_type_object = get_post_type_object( $post_type );
		if ( 'post' == $post_type && $sticky_posts = get_option( 'sticky_posts' ) ) {
			$sticky_posts = implode( ', ', array_map( 'absint', (array) $sticky_posts ) );
			$sticky_posts_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( 1 ) FROM $wpdb->posts WHERE post_type = %s AND post_status != 'trash' AND ID IN ($sticky_posts) AND post_author = %d", $post_type, get_current_user_id() ) );
		}

		if ( !empty($locked_post_status) )
			return array();

		$status_links = array();
		$num_posts = WPHideOthersPosts::count_posts( $post_type, 'readable' );
		$class = '';
		$allposts = '';

		$current_user_id = get_current_user_id();
		$total_posts = array_sum( (array) $num_posts );

		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array('show_in_admin_all_list' => false) ) as $state )
			$total_posts -= $num_posts->$state;

		$class = empty( $class ) && empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['show_sticky'] ) ? ' class="current"' : '';
		$status_links['all'] = "<a href='edit.php?post_type=$post_type{$allposts}'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';

		foreach ( get_post_stati(array('show_in_admin_status_list' => true), 'objects') as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( !in_array( $status_name, $avail_post_stati ) )
				continue;

			if ( empty( $num_posts->$status_name ) )
				continue;

			if ( isset($_REQUEST['post_status']) && $status_name == $_REQUEST['post_status'] )
				$class = ' class="current"';

			$status_links[$status_name] = "<a href='edit.php?post_status=$status_name&amp;post_type=$post_type'$class>" . sprintf( translate_nooped_plural( $status->label_count, $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
		}

		if ( ! empty( $sticky_posts_count ) ) {
			$class = ! empty( $_REQUEST['show_sticky'] ) ? ' class="current"' : '';

			$sticky_link = array( 'sticky' => "<a href='edit.php?post_type=$post_type&amp;show_sticky=1'$class>" . sprintf( _nx( 'Sticky <span class="count">(%s)</span>', 'Sticky <span class="count">(%s)</span>', $sticky_posts_count, 'posts' ), number_format_i18n( $sticky_posts_count ) ) . '</a>' );

			// Sticky comes after Publish, or if not listed, after All.
			$split = 1 + array_search( ( isset( $status_links['publish'] ) ? 'publish' : 'all' ), array_keys( $status_links ) );
			$status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
		}

		return $status_links;
	}

	//copied and adapted from wp_count_posts
	public static function count_posts( $type = 'post', $perm = '' ) {
		global $wpdb;

		$user = wp_get_current_user();

		$cache_key = $type . '-HOP-' . $user->ID . rand();

		$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d ";
		if ( 'readable' == $perm && is_user_logged_in() ) {
			$post_type_object = get_post_type_object($type);
			if ( !current_user_can( $post_type_object->cap->read_private_posts ) ) {
				$cache_key .= '_' . $perm . '_' . $user->ID;
				$query .= " AND (post_status != 'private' OR ( post_author = '$user->ID' AND post_status = 'private' ))";
			}
		}
		$query .= ' GROUP BY post_status';

		$count = wp_cache_get($cache_key, 'counts');
		if ( false !== $count )
			return $count;

		$count = $wpdb->get_results( $wpdb->prepare( $query, $type, $user->ID ), ARRAY_A );

		$stats = array();
		foreach ( get_post_stati() as $state )
			$stats[$state] = 0;

		foreach ( (array) $count as $row )
			$stats[$row['post_status']] = $row['num_posts'];

		$stats = (object) $stats;
		wp_cache_set($cache_key, $stats, 'counts');

		return $stats;
	}

	public static function settings_enabled_callback() {
        $post_types = get_post_types();
        $options = get_option('wp_hide_others_posts_enabled');
        $banned_post_types = array('attachment', 'revision', 'nav_menu_item');
        foreach($post_types as $post_type){
            if(!in_array($post_type, $banned_post_types))
                echo '<input name="wp_hide_others_posts_enabled['.$post_type.']" id="wp_hide_others_posts_enabled-'.$post_type.'" type="checkbox" value="1" class="code" ' . checked( 1, $options[$post_type], false ) . ' /> ' . get_post_type_object($post_type)->labels->name . '<br>';
        }
    }

    public static function settings_enabled_section_callback() {
        echo '<p>Check off any post types that will be filtered and only allow users to see posts they have authored.</p>';
    }

    public static function settings_enabled_init(){
        add_settings_section('wp_hide_others_posts_enabled_setting_section',
            'Enable/disable which post types will be filtered',
            array('WPHideOthersPosts', 'settings_enabled_section_callback'),
            'wp_hide_others_posts_enabled');

        add_settings_field('wp_hide_others_posts_enabled',
            'Filtered Post Types',
            array('WPHideOthersPosts', 'settings_enabled_callback'),
            'wp_hide_others_posts_enabled',
            'wp_hide_others_posts_enabled_setting_section');

        register_setting('wp_hide_others_posts_enabled','wp_hide_others_posts_enabled');
    }

    public static function settings_enabled_submenu(){
        add_options_page('Hide Other\'s Post Options', 'Hide Other\'s Post Options', 'manage_options', 'wp_hide_others_posts_enabled', array('WPHideOthersPosts', 'settings_enabled_page'));
    }

    public static function settings_enabled_page(){
        ?>
        <div class="wrap">
            <h2><?php echo __('WP Hide Other\'s Posts Settings'); ?></h2>
            <form method="post" action="options.php"> 
                <?php if (current_user_can('manage_options')) { ?>
                    <?php settings_fields('wp_hide_others_posts_enabled'); ?>
                    <?php do_settings_sections( 'wp_hide_others_posts_enabled' ); ?>
                    <?php submit_button(); ?>
                <?php } ?>
            </form>
        </div>
        <?php
    }
}

add_filter('pre_get_posts', array('WPHideOthersPosts', 'filter'));
add_action('admin_init', array('WPHideOthersPosts', 'get_views_filters'));
register_activation_hook(__FILE__, array('WPHideOthersPosts', 'activate'));



add_action('admin_init', array('WPHideOthersPosts', 'settings_enabled_init'));
add_action('admin_menu', array('WPHideOthersPosts', 'settings_enabled_submenu'));