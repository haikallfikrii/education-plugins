<?php
/*
Plugin Name: CAAST Access Guard
Description: Restricts the content editors to staff accounts and keeps login pages out of the page cache so their security tokens never go stale.
Version: 1.0.0
Author: Muhamad Fikri
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CAAST_ACCESS_GUARD_VERSION', '1.0.0' );

/**
 * Editor shortcodes that must only render for staff.
 */
function caast_ag_editor_shortcodes() {
	return apply_filters( 'caast_access_guard_editor_shortcodes', array(
		'my_editor_dashboard',
		'my_author_editor',
		'my_author_editor_test',
		'author_dashboard_v3',
		'mae_v3_author_dashboard',
		'my_book_editor',
		'my_article_editor',
		'my_lesson_editor',
		'my_video_editor',
		'content_editor',
		'my_content_automation',
		'content_admin_dashboard',
		'frontend_content_form',
		'my_post_dashboard',
		'gpu_control_panel',
	) );
}

/**
 * AJAX / admin-post action prefixes and names that belong to the editors.
 */
function caast_ag_is_editor_action( $action ) {
	$prefixes = apply_filters( 'caast_access_guard_action_prefixes', array(
		'mae_v3_',
		'my_author_editor_',
		'my_book_editor_',
		'mbe_',
		'my_article_editor_',
		'my_lesson_editor_',
		'mle_',
		'my_video_editor_',
		'my_content_editor_',
		'my_content_automation_',
		'gpucp_',
		'caast_load_author_data',
		'caast_submit_post',
		'caast_delete_item',
	) );
	$exact = apply_filters( 'caast_access_guard_actions', array(
		'update_post_status',
		'delete_generated_draft',
		'download_generated_draft',
		'load_generated_draft',
		'load_generated_draft_list',
	) );

	if ( in_array( $action, $exact, true ) ) {
		return true;
	}
	foreach ( $prefixes as $prefix ) {
		if ( 0 === strpos( $action, $prefix ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Academy LMS grants edit_posts to students and instructors, so edit_posts cannot tell staff apart.
 */
function caast_ag_is_staff( $user_id = 0 ) {
	$is_staff = $user_id ? user_can( $user_id, 'edit_others_posts' ) : current_user_can( 'edit_others_posts' );
	return (bool) apply_filters( 'caast_access_guard_is_staff', $is_staff, $user_id );
}

function caast_ag_no_access_url() {
	return apply_filters( 'caast_access_guard_redirect_url', home_url( '/dashboard/' ) );
}

add_filter( 'pre_do_shortcode_tag', function ( $output, $tag ) {
	if ( false !== $output || ! in_array( $tag, caast_ag_editor_shortcodes(), true ) || caast_ag_is_staff() ) {
		return $output;
	}
	return '<div class="caast-access-denied" style="padding:24px;border:1px solid #e5e7eb;background:#f9fafb;">'
		. esc_html__( 'You do not have permission to access the content editor.', 'caast-access-guard' )
		. ' <a href="' . esc_url( caast_ag_no_access_url() ) . '">' . esc_html__( 'Go to your dashboard', 'caast-access-guard' ) . '</a></div>';
}, 1, 2 );

add_action( 'template_redirect', function () {
	if ( ! is_singular() || caast_ag_is_staff() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || '' === $post->post_content ) {
		return;
	}
	$pattern = '/\[(' . implode( '|', array_map( 'preg_quote', caast_ag_editor_shortcodes() ) ) . ')[\s\]\/]/';
	if ( preg_match( $pattern, $post->post_content ) ) {
		wp_safe_redirect( is_user_logged_in() ? caast_ag_no_access_url() : wp_login_url( get_permalink( $post ) ) );
		exit;
	}
}, 1 );

// admin_init runs before admin-ajax.php / admin-post.php dispatch the action.
add_action( 'admin_init', function () {
	$is_admin_post = isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'];
	if ( ! wp_doing_ajax() && ! $is_admin_post ) {
		return;
	}
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( '' === $action || ! caast_ag_is_editor_action( $action ) || caast_ag_is_staff() ) {
		return;
	}
	if ( wp_doing_ajax() ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'caast-access-guard' ) ), 403 );
	}
	wp_die( esc_html__( 'You do not have permission to perform this action.', 'caast-access-guard' ), '', array( 'response' => 403 ) );
}, 0 );

add_filter( 'wp_nav_menu_objects', function ( $items ) {
	if ( caast_ag_is_staff() ) {
		return $items;
	}
	$hidden_paths = apply_filters( 'caast_access_guard_staff_menu_paths', array( '/editor-dashboard/' ) );
	return array_values( array_filter( $items, function ( $item ) use ( $hidden_paths ) {
		$path = trailingslashit( (string) wp_parse_url( $item->url, PHP_URL_PATH ) );
		return ! in_array( $path, $hidden_paths, true );
	} ) );
} );

/**
 * Pages whose forms carry nonces (login / registration) must never be served from the page cache.
 */
function caast_ag_login_shortcodes() {
	return apply_filters( 'caast_access_guard_login_shortcodes', array(
		'smgt_login',
		'academy_login_form',
		'academy_dashboard',
		'academy_instructor_registration_form',
		'academy_student_registration_form',
		'academy_password_reset_form',
		'custom_role_dashboard',
	) );
}

function caast_ag_is_login_page( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	if ( (int) get_option( 'smgt_login_page' ) === (int) $post->ID ) {
		return true;
	}
	$pattern = '/\[(' . implode( '|', array_map( 'preg_quote', caast_ag_login_shortcodes() ) ) . ')[\s\]\/]/';
	return (bool) preg_match( $pattern, (string) $post->post_content );
}

add_action( 'template_redirect', function () {
	if ( ! is_singular() || ! caast_ag_is_login_page( get_queried_object() ) ) {
		return;
	}
	do_action( 'litespeed_control_set_nocache', 'caast-access-guard: login form' );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
}, 0 );

function caast_ag_purge_login_pages() {
	$ids = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach ( $ids as $id ) {
		if ( caast_ag_is_login_page( get_post( $id ) ) ) {
			do_action( 'litespeed_purge_url', get_permalink( $id ) );
			do_action( 'litespeed_purge_post', $id );
		}
	}
}
register_activation_hook( __FILE__, 'caast_ag_purge_login_pages' );

add_action( 'init', function () {
	if ( get_option( 'caast_access_guard_purged' ) !== CAAST_ACCESS_GUARD_VERSION ) {
		caast_ag_purge_login_pages();
		update_option( 'caast_access_guard_purged', CAAST_ACCESS_GUARD_VERSION, false );
	}
}, 99 );
