<?php
/*
Plugin Name: My Article Editor
Plugin URI:  https://example.com/
Description: A custom editor for article profiles, accessible via shortcode, with custom category and level dropdowns, and author selection.
Version:     1.4.3
Author:      Muhamad Fikri Haikal
Author URI:  https://caastedu.com/
License:     GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure WordPress functions are available
if ( ! function_exists( 'add_action' ) ) {
    exit;
}

// Global flag to indicate if the shortcode is used on the current page
global $my_article_editor_shortcode_used;
$my_article_editor_shortcode_used = false;

// Define plugin constants
if ( ! defined( 'MY_ARTICLE_EDITOR_VERSION' ) ) {
    define( 'MY_ARTICLE_EDITOR_VERSION', '1.4.3' );
}
if ( ! defined( 'MY_ARTICLE_EDITOR_PLUGIN_URL' ) ) {
    define( 'MY_ARTICLE_EDITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MY_ARTICLE_EDITOR_PLUGIN_DIR' ) ) {
    define( 'MY_ARTICLE_EDITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Register Custom Post Type for Article Content
 */
function my_article_editor_register_cpt() {
    $labels = array(
        'name'          => _x( 'Articles', 'Post Type General Name', 'my-article-editor' ),
        'singular_name' => _x( 'Article', 'Post Type Singular Name', 'my-article-editor' ),
        'menu_name'     => __( 'Articles', 'my-article-editor' ),
        'all_items'     => __( 'All Articles', 'my-article-editor' ),
        'add_new_item'  => __( 'Add New Article', 'my-article-editor' ),
        'add_new'       => __( 'Add New', 'my-article-editor' ),
        'new_item'      => __( 'New Article', 'my-article-editor' ),
        'edit_item'     => __( 'Edit Article', 'my-article-editor' ),
        'update_item'   => __( 'Update Article', 'my-article-editor' ),
        'view_item'     => __( 'View Article', 'my-article-editor' ),
        'search_items'  => __( 'Search Articles', 'my-article-editor' ),
        'not_found'     => __( 'Not Found', 'my-article-editor' ),
        'not_found_in_trash' => __( 'Not found in Trash', 'my-article-editor' ),
    );
    $args = array(
        'label'               => __( 'Article', 'my-article-editor' ),
        'description'         => __( 'Content associated with articles', 'my-article-editor' ),
        'labels'              => $labels,
        'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail' ),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-media-text', // Changed icon to better fit 'article'
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'rewrite'             => array( 'slug' => 'article-profile' ),
        'query_var'           => true,
    );
    register_post_type( 'article_profile', $args );
}
add_action( 'init', 'my_article_editor_register_cpt' );

/**
 * Register custom taxonomies for Articles (Category and Level, and Placement Category)
 */
function my_article_editor_register_taxonomies() {
    // Category Taxonomy
    $category_labels = array(
        'name'              => _x( 'Article Categories', 'taxonomy general name', 'my-article-editor' ),
        'singular_name'     => _x( 'Article Category', 'taxonomy singular name', 'my-article-editor' ),
        'search_items'      => __( 'Search Article Categories', 'my-article-editor' ),
        'all_items'         => __( 'All Article Categories', 'my-article-editor' ),
        'parent_item'       => __( 'Parent Article Category', 'my-article-editor' ),
        'parent_item_colon' => __( 'Parent Article Category:', 'my-article-editor' ),
        'edit_item'         => __( 'Edit Article Category', 'my-article-editor' ),
        'update_item'       => __( 'Update Article Category', 'my-article-editor' ),
        'add_new_item'      => __( 'Add New Article Category', 'my-article-editor' ),
        'new_item_name'     => __( 'New Article Category Name', 'my-article-editor' ),
        'menu_name'         => __( 'Categories', 'my-article-editor' ),
    );
    $category_args = array(
        'hierarchical'      => true,
        'labels'            => $category_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'article-category' ),
        'show_in_rest'      => true, // Make taxonomy available in REST API
    );
    register_taxonomy( 'article_category', array( 'article_profile' ), $category_args );

    // Level Taxonomy
    $level_labels = array(
        'name'              => _x( 'Article Levels', 'taxonomy general name', 'my-article-editor' ),
        'singular_name'     => _x( 'Article Level', 'taxonomy singular name', 'my-article-editor' ),
        'search_items'      => __( 'Search Article Levels', 'my-article-editor' ),
        'all_items'         => __( 'All Article Levels', 'my-article-editor' ),
        'edit_item'         => __( 'Edit Article Level', 'my-article-editor' ),
        'update_item'       => __( 'Update Article Level', 'my-article-editor' ),
        'add_new_item'      => __( 'Add New Article Level', 'my-article-editor' ),
        'new_item_name'     => __( 'New Article Level Name', 'my-article-editor' ),
        'menu_name'         => __( 'Levels', 'my-article-editor' ),
    );
    $level_args = array(
        'hierarchical'      => false,
        'labels'            => $level_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'article-level' ),
        'show_in_rest'      => true, // Make taxonomy available in REST API
    );
    register_taxonomy( 'article_level', array( 'article_profile' ), $level_args );

    // Placement Category Taxonomy
    $placement_labels = array(
        'name'                       => _x( 'Placement Categories', 'Taxonomy General Name', 'my-article-editor' ),
        'singular_name'              => _x( 'Placement Category', 'Taxonomy Singular Name', 'my-article-editor' ),
        'menu_name'                  => __( 'Placement', 'my-article-editor' ),
        'all_items'                  => __( 'All Placements', 'my-article-editor' ),
        'parent_item'                => __( 'Parent Placement', 'my-article-editor' ),
        'parent_item_colon'          => __( 'Parent Placement:', 'my-article-editor' ),
        'new_item_name'              => __( 'New Placement Name', 'my-article-editor' ),
        'add_new_item'               => __( 'Add New Placement', 'my-article-editor' ),
        'edit_item'                  => __( 'Edit Placement', 'my-article-editor' ),
        'update_item'                => __( 'Update Placement', 'my-article-editor' ),
        'view_item'                  => __( 'View Placement', 'my-article-editor' ),
        'separate_items_with_commas' => __( 'Separate placements with commas', 'my-article-editor' ),
        'add_or_remove_items'        => __( 'Add or remove placements', 'my-article-editor' ),
        'choose_from_most_used'      => __( 'Choose from the most used placements', 'my-article-editor' ),
        'popular_items'              => __( 'Popular Placements', 'my-article-editor' ),
        'search_items'               => __( 'Search Placements', 'my-article-editor' ),
        'not_found'                  => __( 'No Placements Found', 'my-article-editor' ),
        'no_terms'                   => __( 'No placements', 'my-article-editor' ),
        'items_list'                 => __( 'Placements list', 'my-article-editor' ),
        'items_list_navigation'      => __( 'Placements list navigation', 'my-article-editor' ),
    );
    $placement_args = array(
        'labels'                     => $placement_labels,
        'hierarchical'               => true, // Can be true or false depending on your need
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => false,
        'rewrite'                    => array( 'slug' => 'article-placement' ), // Unique slug for article placements
        'show_in_rest'               => true,
    );
    register_taxonomy( 'article_placement', array( 'article_profile' ), $placement_args );
}
add_action( 'init', 'my_article_editor_register_taxonomies' );

/**
 * Add default terms for Article Categories and Levels on plugin activation.
 */
function my_article_editor_add_default_terms() {
    $categories = array(
        'News',
        'Tutorials',
        'Reviews',
        'Opinion',
        'Guides',
        'Research'
    );
    foreach ( $categories as $category_name ) {
        if ( ! term_exists( $category_name, 'article_category' ) ) {
            wp_insert_term( $category_name, 'article_category' );
        }
    }

    $levels = array(
        'Beginner',
        'Intermediate',
        'Advanced',
        'Expert'
    );
    foreach ( $levels as $level_name ) {
        if ( ! term_exists( $level_name, 'article_level' ) ) {
            wp_insert_term( $level_name, 'article_level' );
        }
    }

    // Default terms for Article Placement Categories
    $placements = array(
        'Home Left Section 1',
        'Home Left Section 2',
        'Home Right List 1',
        'Home Right List 2',
        'Author Footer',
        'Author Right',
        'Book Left',
        'Book Right',
        'Book Footer',
        'Video Left',
        'Video Right',
        'Video Footer'
    );
    foreach ( $placements as $placement_name ) {
        if ( ! term_exists( $placement_name, 'article_placement' ) ) {
            wp_insert_term( $placement_name, 'article_placement' );
        }
    }
}
register_activation_hook( __FILE__, 'my_article_editor_add_default_terms' );


/**
 * Enqueue styles and scripts for the article editor.
 * This will only run if the shortcode is detected on the current page.
 */
function my_article_editor_enqueue_assets() {
    global $my_article_editor_shortcode_used;

    if ( $my_article_editor_shortcode_used ) {
        wp_enqueue_media(); // For media uploader

        // Enqueue TinyMCE and other editor-related scripts (only if current user can use editor)
        if ( current_user_can( 'edit_posts' ) ) {
            wp_enqueue_script('wp-tinymce');
            wp_enqueue_script('editor');
            wp_enqueue_script('wplink');
            wp_enqueue_script('wp-plupload');
            wp_enqueue_script('thickbox');
            wp_enqueue_style( 'thickbox' );
        }

        // Enqueue the plugin's specific JavaScript
        wp_enqueue_script(
            'my-article-editor-script',
            MY_ARTICLE_EDITOR_PLUGIN_URL . 'js/my-article-editor.js',
            array( 'jquery' ),
            MY_ARTICLE_EDITOR_VERSION,
            true
        );

        // Enqueue plugin's custom CSS for fully responsive form
        wp_enqueue_style(
            'my-article-editor-style',
            MY_ARTICLE_EDITOR_PLUGIN_URL . 'css/my-article-editor.css',
            array(),
            MY_ARTICLE_EDITOR_VERSION,
            'all'
        );

        // Localize AJAX URL and nonce for JavaScript
        wp_localize_script( 'my-article-editor-script', 'myArticleEditorAjax', array(
            'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
            'nonce'                   => wp_create_nonce( 'article_editor_nonce' ),
            'alert_no_article_selected'  => __( 'Please select an article first.', 'my-article-editor' ),
            'alert_no_author_selected' => __( 'Please select an author first.', 'my-article-editor' ),
            'alert_confirm_delete'    => __( 'Are you sure you want to delete this article? This action cannot be undone.', 'my-article-editor' ),
            'alert_content_not_found' => __( 'No content found for the selected article.', 'my-article-editor' ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'my_article_editor_enqueue_assets', 99 );

/**
 * Ticked placement slugs from the submitted form (checkbox array or legacy single value).
 *
 * @return string[]
 */
function my_article_editor_get_placement_slugs_from_post() {
	if ( ! isset( $_POST['placement_category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return array();
	}

	$slugs = array();
	foreach ( (array) wp_unslash( $_POST['placement_category'] ) as $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$slug = sanitize_title( (string) $value );
		if ( '' !== $slug ) {
			$slugs[] = $slug;
		}
	}

	return array_values( array_unique( $slugs ) );
}

/**
 * Handle form submission for Article Content Editor.
 */
function my_article_editor_handle_submission() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'my_article_editor_submit' ) {
        return;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
        exit;
    }

    // Verify nonce
    if ( ! isset( $_POST['_wpnonce_article_content'] ) || ! wp_verify_nonce( $_POST['_wpnonce_article_content'], 'article_content_submission' ) ) {
        wp_die( 'Security check failed. Please refresh the page and try again.' );
    }

    // Sanitize and retrieve form data
    $selected_article_id= isset( $_POST['selected_article_id'] ) ? intval( $_POST['selected_article_id'] ) : 0;
    $new_article_name   = sanitize_text_field( $_POST['new_article_name'] );
    $article_subtitle   = sanitize_text_field( $_POST['article_subtitle'] );
    $article_content    = wp_kses_post( $_POST['article_content'] );
    $article_cover_id   = isset( $_POST['article_cover_id'] ) ? intval( $_POST['article_cover_id'] ) : 0;
    $placement_category = my_article_editor_get_placement_slugs_from_post();
    $insert_link        = isset( $_POST['insert_link'] ) ? esc_url_raw( $_POST['insert_link'] ) : ''; // Still keeping insert_link if needed

    // Get selected taxonomy terms (slugs)
    $article_category_slug = isset( $_POST['article_category'] ) ? sanitize_text_field( $_POST['article_category'] ) : '';
    $article_level_slug    = isset( $_POST['article_level'] ) ? sanitize_text_field( $_POST['article_level'] ) : '';
    $selected_author_id = isset( $_POST['selected_author_id'] ) ? intval( $_POST['selected_author_id'] ) : 0;

    $submit_action      = sanitize_text_field( $_POST['submit_article_action'] );
    $placement_action   = isset( $_POST['placement_action'] ) ? sanitize_text_field( $_POST['placement_action'] ) : '';

    // Determine the article title to use for the post
    $post_title = '';
    if ( ! empty( $new_article_name ) ) {
        $post_title = $new_article_name;
    } elseif ( $selected_article_id > 0 ) {
        $existing_article = get_post( $selected_article_id );
        if ( $existing_article ) {
            $post_title = $existing_article->post_title;
        }
    }

    $existing_post_id = isset( $_POST['article_post_id'] ) ? intval( $_POST['article_post_id'] ) : 0;

    // Basic validation for new article or updating existing article if title changed
    if ( empty( $post_title ) && empty($existing_post_id) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_empty_title', wp_get_referer() ) );
        exit;
    }

    // Determine post status based on button clicked
    $post_status = 'draft';
    $redirect_status = 'error';

    if ( 'publish_placement' === $placement_action ) {
        if ( $existing_post_id <= 0 ) {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_article_selected_for_placement', wp_get_referer() ) );
            exit;
        }
        if ( empty( $placement_category ) ) {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_placement_selected', wp_get_referer() ) );
            exit;
        }
        if ( ! current_user_can( 'publish_article_profiles' ) && ! current_user_can( 'publish_posts' ) ) {
            wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
            exit;
        }
        $post_status     = 'publish';
        $redirect_status = 'article_published_to_placement';
    } elseif ( 'delete' === $submit_action ) {
        if ( $existing_post_id ) {
            if ( current_user_can( 'delete_article_profiles' ) || current_user_can( 'delete_posts' ) ) {
                if ( wp_delete_post( $existing_post_id, true ) ) {
                    wp_safe_redirect( add_query_arg( 'status', 'article_deleted', wp_get_referer() ) );
                    exit;
                } else {
                    error_log( 'Error deleting article post: ' . $existing_post_id );
                    wp_safe_redirect( add_query_arg( 'status', 'error_article_delete', wp_get_referer() ) );
                    exit;
                }
            } else {
                wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
                exit;
            }
        } else {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_content_to_delete', wp_get_referer() ) );
            exit;
        }
    } elseif ( function_exists( 'caast_workflow_resolve_submission' ) ) {
        $resolved = caast_workflow_resolve_submission(
            $submit_action,
            'article',
            array(
                'can_publish' => current_user_can( 'publish_article_profiles' ) || current_user_can( 'publish_posts' ),
            )
        );
        if ( $resolved && 'save' === $resolved['type'] ) {
            $post_status     = $resolved['post_status'];
            $redirect_status = $resolved['redirect_status'];
        }
    } elseif ( $submit_action === 'publish' ) {
        if ( current_user_can( 'publish_article_profiles' ) || current_user_can( 'publish_posts' ) ) {
            $post_status = 'publish';
            $redirect_status = 'article_published';
        } else {
            $post_status = 'pending';
            $redirect_status = 'article_pending_review';
        }
    } elseif ( $submit_action === 'save' ) {
        $post_status = 'draft';
        $redirect_status = 'article_saved_draft';
    } elseif ( $submit_action === 'unpublish' ) {
        $post_status = 'draft';
        $redirect_status = 'article_unpublished';
    } elseif ( $submit_action === 'archive' ) {
        // Check if 'archive' post status is registered
        if ( get_post_status_object('archive') ) {
             $post_status = 'archive';
        } else {
            // Fallback to draft if 'archive' status is not registered
            $post_status = 'draft';
        }
        $redirect_status = 'article_archived';
    }

    // Prepare post data
    $post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $article_content,
        'post_status'   => $post_status,
        'post_type'     => 'article_profile',
        'post_author'   => get_current_user_id(),
    );

    $post_id = 0;
    if ( $existing_post_id > 0 ) {
        $post_data['ID'] = $existing_post_id;
        $result = wp_update_post( $post_data, true );
        $post_id = $existing_post_id; // Assign post_id for further operations
    } else {
        $result = wp_insert_post( $post_data, true );
        $post_id = $result; // Assign post_id for further operations
    }

    if ( is_wp_error( $result ) ) {
        error_log( 'Error creating/updating article post: ' . $result->get_error_message() );
        wp_safe_redirect( add_query_arg( 'status', 'error_article_db', wp_get_referer() ) );
    } elseif ( $result === 0 ) {
        error_log( 'Failed to create/update article post, wp_insert_post/wp_update_post returned 0.' );
        wp_safe_redirect( add_query_arg( 'status', 'error_article_db', wp_get_referer() ) );
    } else {
        // Save article subtitle as post meta
        update_post_meta( $post_id, '_article_subtitle', $article_subtitle );

        // Set featured image (Article Cover)
        if ( ! empty( $article_cover_id ) ) {
            set_post_thumbnail( $post_id, $article_cover_id );
        } else {
            delete_post_thumbnail( $post_id ); // Remove if ID is 0 or empty
        }

        // Save new footer fields as post meta
        // update_post_meta( $post_id, '_footer_position', $footer_position ); // No longer using this meta
        update_post_meta( $post_id, '_insert_link', $insert_link ); // Still keeping insert_link meta

        // Save selected Author ID as post meta for the article
        update_post_meta( $post_id, '_article_author_id', $selected_author_id );

        // Set taxonomies
        if ( ! empty( $article_category_slug ) ) {
            wp_set_object_terms( $post_id, $article_category_slug, 'article_category' );
        } else {
            wp_set_object_terms( $post_id, null, 'article_category' ); // Clear if no category selected
        }

        if ( ! empty( $article_level_slug ) ) {
            wp_set_object_terms( $post_id, $article_level_slug, 'article_level' );
        } else {
            wp_set_object_terms( $post_id, null, 'article_level' ); // Clear if no level selected
        }

        // Set Article Placement Taxonomy
        if ( ! empty( $placement_category ) ) {
            wp_set_object_terms( $post_id, $placement_category, 'article_placement' );
        } else {
            wp_set_object_terms( $post_id, null, 'article_placement' ); // Clear if no placement selected
        }

        wp_safe_redirect( add_query_arg( 'status', $redirect_status, wp_get_referer() ) );
    }
    exit;
}
add_action( 'admin_post_my_article_editor_submit', 'my_article_editor_handle_submission' );
add_action( 'admin_post_nopriv_my_article_editor_submit', 'my_article_editor_handle_submission' );

/**
 * AJAX handler to get article content based on article ID.
 */
function my_article_editor_get_article_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-article-editor' ) ) );
    }

    check_ajax_referer( 'article_editor_nonce', 'nonce' );

    $article_id = isset( $_POST['article_id'] ) ? intval( $_POST['article_id'] ) : 0;

    if ( $article_id ) {
        $args = array(
            'p'              => $article_id,
            'post_type'      => 'article_profile',
            'post_status'    => function_exists( 'caast_workflow_get_editor_query_statuses' )
                ? caast_workflow_get_editor_query_statuses()
                : array( 'publish', 'pending', 'draft', 'archive' ),
            'posts_per_page' => 1,
        );
        $article_query = new WP_Query( $args );

        if ( $article_query->have_posts() ) {
            $article_post = $article_query->posts[0];

            $current_categories = wp_get_post_terms( $article_post->ID, 'article_category', array( 'fields' => 'slugs' ) );
            $current_levels     = wp_get_post_terms( $article_post->ID, 'article_level', array( 'fields' => 'slugs' ) );
            $current_placement  = wp_get_post_terms( $article_post->ID, 'article_placement', array( 'fields' => 'slugs' ) ); // Get current placement

            $associated_author_id = get_post_meta( $article_post->ID, '_article_author_id', true );

            // Ensure article cover URL is fetched correctly
            $article_cover_id = get_post_thumbnail_id( $article_post->ID );
            $article_cover_url = $article_cover_id ? wp_get_attachment_url( $article_cover_id ) : '';


            wp_send_json_success( array(
                'article_post_id'    => $article_post->ID,
                'article_name'       => esc_html( $article_post->post_title ),
                'article_subtitle'   => esc_html( get_post_meta( $article_post->ID, '_article_subtitle', true ) ),
                'article_content'    => $article_post->post_content,
                'article_cover_id'   => $article_cover_id,
                'article_cover_url'  => $article_cover_url,
                'placement_category' => ( ! empty( $current_placement ) && ! is_wp_error( $current_placement ) ) ? array_values( $current_placement ) : array(),
                'insert_link'     => esc_url( get_post_meta( $article_post->ID, '_insert_link', true ) ),
                'article_categories' => !empty($current_categories) ? $current_categories[0] : '',
                'article_levels'     => !empty($current_levels) ? $current_levels[0] : '',
                'associated_author_id' => intval($associated_author_id),
            ) );
        } else {
            // If article ID is provided but no content found for it, clear form and allow new entry
            wp_send_json_success( array(
                'article_post_id'    => 0, // Reset to 0 for new content
                'article_name'       => '',
                'article_subtitle'   => '',
                'article_content'    => '',
                'article_cover_id'   => 0,
                'article_cover_url'  => '',
                'placement_category' => array(),
                'insert_link'     => '',
                'article_categories' => '',
                'article_levels'     => '',
                'associated_author_id' => 0,
                'message'         => 'No existing content found for this article. You can create a new one.',
            ) );
        }
    } else {
        // If no article ID, implies a fresh start or "Add New" scenario
        wp_send_json_success( array(
            'article_post_id'    => 0,
            'article_name'       => '',
            'article_subtitle'   => '',
            'article_content'    => '',
            'article_cover_id'   => 0,
            'article_cover_url'  => '',
            'placement_category' => array(),
            'insert_link'     => '',
            'article_categories' => '',
            'article_levels'     => '',
            'associated_author_id' => 0,
            'message'         => __( 'Invalid article ID, or no article selected.', 'my-article-editor' )
        ) );
    }
}
add_action( 'wp_ajax_my_article_editor_get_article_content', 'my_article_editor_get_article_content_ajax' );

/**
 * AJAX handler to get author content based on author ID.
 */
function my_article_editor_get_author_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-article-editor' ) ) );
    }

    check_ajax_referer( 'article_editor_nonce', 'nonce' ); // Changed nonce to article_editor_nonce

    $author_id = isset( $_POST['author_id'] ) ? intval( $_POST['author_id'] ) : 0;

    if ( $author_id ) {
        $author_post = get_post( $author_id ); // Assuming 'author_profile' is a CPT
        if ( $author_post && $author_post->post_type === 'author_profile' ) {
            wp_send_json_success( array(
                'author_id'   => $author_post->ID,
                'author_name' => $author_post->post_title,
                'message'     => 'Author selected: ' . $author_post->post_title,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Author not found.', 'my-article-editor' ) ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'No author selected.', 'my-article-editor' ) ) );
    }
}
add_action( 'wp_ajax_my_article_editor_get_author_content', 'my_article_editor_get_author_content_ajax' );


/**
 * Shortcode to display the Article Content Editor.
 */
function my_article_editor_shortcode() {
    global $my_article_editor_shortcode_used;
    $my_article_editor_shortcode_used = true;

    // wp_enqueue_scripts fires before shortcodes render, so assets must be enqueued here.
    if ( ! wp_script_is( 'my-article-editor-script', 'enqueued' ) ) {
        my_article_editor_enqueue_assets();
    }

    ob_start();

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        ?>
        <p class="text-center text-red-500">You must be logged in with sufficient permissions to access the Article Content Editor.</p>
        <?php
        return ob_get_clean();
    }

    // Initialize values for form fields - These will be populated by JS on load/selection
    $article_name_val      = '';
    $article_subtitle_val  = '';
    $article_content_val   = '';
    $article_cover_id_val  = 0;
    $article_cover_url_val = '';
    $placement_category_val= ''; // Initialize for placement category
    $insert_link_val    = '';
    $article_post_id_val   = 0;
    $article_category_val  = '';
    $article_level_val     = '';
    $associated_author_id_val = 0;


    // Display status messages
    if ( isset( $_GET['status'] ) ) {
        $status_message = '';
        $status_class = 'bg-green-100 border border-green-400 text-green-700';

        switch ( $_GET['status'] ) {
            case 'article_published':
                $status_message = 'Article successfully published!'; break;
            case 'article_saved_draft':
                $status_message = 'Article saved as draft!'; break;
            case 'article_pending_review':
            case 'article_ready_for_approval':
                $status_message = 'Article marked as Ready for Approval.'; break;
            case 'article_approved':
                $status_message = 'Article marked as Approved.'; break;
            case 'article_unpublished':
                $status_message = 'Article unpublished.'; break;
            case 'article_archived':
                $status_message = 'Article successfully archived!'; break;
            case 'article_deleted':
                $status_message = 'Article successfully deleted!'; break;
            case 'article_added':
                $status_message = 'New article added successfully!'; break;
            case 'article_published_to_placement': // New status for placement publish
                $status_message = 'Article successfully published to selected placement!'; break;
            case 'error_empty_title':
                $status_message = 'Error: Article name cannot be empty. Please fill the article name field.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_article_db':
                $status_message = 'An error occurred while submitting the article. Please try again.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_article_delete':
                $status_message = 'An error occurred while deleting the article. Please try again.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_no_content_to_delete':
                $status_message = 'No content was selected for deletion.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_permissions':
                $status_message = 'You do not have sufficient permissions to perform this action.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_no_placement_selected':
                $status_message = 'Please tick at least one placement before publishing to placement.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_no_article_selected_for_placement': // New error status
                $status_message = 'Please select an article before attempting to publish to a placement.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            default:
                $status_message = 'An unknown status occurred.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700';
        }
        echo '<div class="notice ' . esc_attr($status_class) . ' px-4 py-3 rounded-none relative mb-4 is-dismissible"><p>' . esc_html($status_message) . '</p></div>';
    }

    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $user_avatar = get_avatar_url( $current_user->ID, array( 'size' => 24 ) );

    ?>
    <div class="bg-gray-100 text-[12px] text-gray-600 font-mono flex justify-end gap-9 px-20 py-1 max-w-12xl mx-auto">
        <span>myEMAIL</span>
        <span>myCALENDAR</span>
        <span>myPROJECTS</span>
        <span>myLEARNING</span>
        <span>myWORK</span>
        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="text-gray-600 hover:text-black">Logout</a>
    </div>

    <main class="w-full mx-auto flex flex-col md:flex-row gap-2 px-2 py-2 min-h-[118vh]">
         <aside aria-label="Left admin navigation panel" class="w-full lg:w-64 xl:w-72 border border-gray-300 rounded-none p-3 text-xs text-gray-700 font-sans bg-white flex-shrink-0 max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <!-- User Profile Section -->
            <div class="flex items-center gap-2 mb-4 p-2 bg-gray-50 rounded-lg">
                <img alt="User avatar placeholder" class="rounded-full w-8 h-8 flex-shrink-0" src="<?php echo esc_url($user_avatar); ?>" />
                <div class="flex-1 min-w-0">
                    <span class="block text-xs font-medium text-gray-900 truncate"><?php echo esc_html($user_email); ?></span>
                    <span class="block text-xs text-gray-500">Content Editor</span>
            </div>
                <i class="fas fa-sync-alt cursor-pointer text-gray-400 text-sm hover:text-gray-600 transition-colors"></i>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="space-y-1">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 px-2">Website Content Editor</h2>
                
                <!-- Content Editor Links -->
                <div class="space-y-1 mb-4">
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/author-endpoint-v2/">
                        <i class="fas fa-user text-gray-500 w-4"></i> 
                        <span>Author Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-3 py-2 text-xs font-medium" href="https://caastedu.com/article-endpoint/">
                        <i class="fas fa-file-alt text-blue-600 w-4"></i> 
                        <span>Article Endpoint</span>
                    
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/book-endpoint/">
                        <i class="fas fa-book-open text-gray-500 w-4"></i> 
                        <span>Book Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/llesson-plan-editor/">
                        <i class="fas fa-newspaper text-gray-500 w-4"></i> 
                        <span>Lesson Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/video-endpoint/">
                        <i class="fab fa-youtube text-gray-500 w-4"></i> 
                        <span>Video Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/content-automation/">
                        <i class="fas fa-robot text-gray-500 w-4"></i> 
                        <span>Content Automation</span>
                    </a>
                </div>
                
                <!-- Dashboard Links -->
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 px-2">Dashboard</h3>
                    <div class="space-y-1">
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/">
                            <i class="fas fa-th-large text-gray-500 w-4"></i> 
                            <span>Dashboard</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/profile/">
                            <i class="fas fa-user text-gray-500 w-4"></i> 
                            <span>My Profile</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/enrolled-courses/">
                            <i class="fas fa-graduation-cap text-gray-500 w-4"></i> 
                            <span>Enrolled Courses</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/my-lessonings/">
                            <i class="fas fa-calendar-check text-gray-500 w-4"></i> 
                            <span>My Tutor Sessions</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/download-certificate/">
                            <i class="fas fa-download text-gray-500 w-4"></i> 
                            <span>Download Certificates</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/wishlist/">
                            <i class="far fa-heart text-gray-500 w-4"></i> 
                            <span>Wishlist</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/reviews/">
                            <i class="far fa-star text-gray-500 w-4"></i> 
                            <span>Reviews</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/purchase-history/">
                            <i class="fas fa-history text-gray-500 w-4"></i> 
                            <span>Purchase History</span>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/my-account/">
                            <i class="fas fa-store text-gray-500 w-4"></i> 
                            <span>Store Dashboard</span>
                        </a>
                    </div>
                </div>
                
                <!-- Course Management Links -->
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 px-2">Course Management</h3>
                    <div class="space-y-1">
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/courses/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-graduation-cap text-gray-500 w-4"></i> 
                                <span>Courses</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/lessons/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-file-alt text-gray-500 w-4"></i> 
                                <span>All Lessons</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/quizzes/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-question-circle text-gray-500 w-4"></i> 
                                <span>Quizzes</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/meeting/">
                            <div class="flex items-center gap-3">
                                <i class="fab fa-youtube text-gray-500 w-4"></i> 
                                <span>Meetings</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/tutor-lessoning/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-calendar-check text-gray-500 w-4"></i> 
                                <span>Tutor Sessions</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/assignments/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-tasks text-gray-500 w-4"></i> 
                                <span>Assignments</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center justify-between hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/question-answer/">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-question-circle text-gray-500 w-4"></i> 
                                <span>Q&A</span>
                            </div>
                            <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                        </a>
                        <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/dashboard/announcements/">
                            <i class="fas fa-bullhorn text-gray-500 w-4"></i> 
                            <span>Announcements</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Settings Section -->
            <div class="border-t border-gray-200 pt-4 mt-4">
                <a href="https://caastedu.com/dashboard/settings/" class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors">
                    <i class="fas fa-cog text-gray-500 w-4"></i> 
                    <span>Settings</span>
                </a>
            </div>
        </aside>
    

        <section class="flex-1 flex flex-col gap-3 max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <div class="bg-white border border-gray-300 rounded-none p-2 min-h-[calc(118vh - 40px)]">
                <h2 class="text-xl font-semibold mb-4">Article Content Editor</h2>

                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="font-sans" id="article-editor-form" style="max-width: none !important; width: 100% !important; margin: 0 !important; padding: 0 !important; background: transparent !important; box-shadow: none !important; border-radius: 0 !important;">
                    <input type="hidden" name="action" value="my_article_editor_submit">
                    <?php wp_nonce_field('article_content_submission', '_wpnonce_article_content'); ?>
                    <input type="hidden" name="article_post_id" id="article_post_id" value="<?php echo esc_attr( $article_post_id_val ); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3">
                        <div class="md:col-span-2">
                            <label for="selected_author_id" class="block text-sm font-medium text-gray-700">Select an author</label>
                            <select name="selected_author_id" id="selected_author_id" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select an author --</option>
                                <?php
                                // Fetch authors from 'author_profile' CPT
                                $authors = get_posts( array(
                                    'post_type'      => 'author_profile', // Assuming 'author_profile' CPT
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'post_status'    => 'publish', // Only published authors
                                ) );
                                foreach ( $authors as $author ) {
                                    $selected = selected( $associated_author_id_val, $author->ID, false );
                                    echo '<option value="' . esc_attr( $author->ID ) . '" ' . $selected . '>' . esc_html( $author->post_title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="load-author-content-btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3">
                        <div class="md:col-span-2">
                            <label for="selected_article_id" class="block text-sm font-medium text-gray-700">Select an article</label>
                            <select name="selected_article_id" id="selected_article_id" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select an article --</option>
                                <?php
                                $articles = get_posts( array(
                                    'post_type'      => 'article_profile',
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'post_status'    => function_exists( 'caast_workflow_get_editor_query_statuses' )
                                        ? caast_workflow_get_editor_query_statuses()
                                        : array( 'publish', 'draft', 'pending', 'archive' ),
                                ) );
                                foreach ( $articles as $article ) {
                                    echo '<option value="' . esc_attr( $article->ID ) . '">' . esc_html( $article->post_title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="load-article-content-btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <div class="flex flex-col mb-3">
                        <label for="new_article_name" class="block text-sm font-medium text-gray-700">Add an article</label>
                        <input type="text" name="new_article_name" id="new_article_name" placeholder="Enter article name" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($article_name_val); ?>" />
                    </div>

                    <div class="flex flex-col mb-3">
                        <label for="article_subtitle" class="block text-sm font-medium text-gray-700">Article Subtitle:</label>
                        <input type="text" name="article_subtitle" id="article_subtitle" placeholder="Article subtitle text" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($article_subtitle_val); ?>" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Upload article cover image</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" id="article_cover_url" name="article_cover_url" class="flex-grow border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Browse article cover" readonly value="<?php echo esc_url($article_cover_url_val); ?>" />
                                <input type="hidden" id="article_cover_id" name="article_cover_id" value="<?php echo esc_attr($article_cover_id_val); ?>" />
                            </div>
                            <div id="article-cover-preview" class="mt-2 text-center" style="<?php echo empty($article_cover_url_val) ? 'display:none;' : ''; ?>">
                                <img src="<?php echo esc_url($article_cover_url_val); ?>" alt="Article Cover Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />
                                <button type="button" class="remove-article-cover text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="button button-secondary browse-article-cover bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Browse</button>
                        </div>
                    </div>


                    <div class="mb-3">
                        <label for="article_content" class="block text-sm font-medium text-gray-700">Body Content:</label>
                        <?php
                        if ( current_user_can( 'edit_posts' ) ) {
                            wp_editor( $article_content_val, 'article_content', array(
                                'textarea_name' => 'article_content',
                                'textarea_rows' => 12,
                                'teeny'         => false,
                                'media_buttons' => false,
                                'tinymce'       => array(
                                    'height' => 300,
                                    'toolbar1' => 'undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link | forecolor backcolor',
                                    'toolbar2' => 'print preview | table | charmap emoticons | code fullscreen',
                                ),
                                'editor_class' => 'mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                            ) );
                        } else {
                            echo '<textarea name="article_content" id="article_content" rows="12" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">' . esc_textarea($article_content_val) . '</textarea>';
                        }
                        ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="article_category" class="block text-sm font-medium text-gray-700">Select a category</label>
                            <select name="article_category" id="article_category" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select a category --</option>
                                <?php
                                $categories = get_terms( array(
                                    'taxonomy'   => 'article_category',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $categories as $category ) {
                                    $selected = ( $article_category_val === $category->slug ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $category->slug ) . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label for="article_level" class="block text-sm font-medium text-gray-700">Select a level</label>
                            <select name="article_level" id="article_level" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select a level --</option>
                                <?php
                                $levels = get_terms( array(
                                    'taxonomy'   => 'article_level',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $levels as $level ) {
                                    $selected = ( $article_level_val === $level->slug ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $level->slug ) . '" ' . $selected . '>' . esc_html( $level->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
                        <div class="md:col-span-2 flex flex-col">
                            <label for="submit_article_action_select" class="block text-sm font-medium text-gray-700">Action</label>
                            <select name="submit_article_action" id="submit_article_action_select" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <?php
                                $article_current_status = ! empty( $article_post_id_val ) ? get_post_status( $article_post_id_val ) : 'draft';
                                if ( function_exists( 'caast_workflow_render_action_options' ) ) {
                                    caast_workflow_render_action_options( $article_current_status );
                                } else {
                                    ?>
                                    <option value="save">Save Draft</option>
                                    <option value="publish">Publish</option>
                                    <option value="unpublish">Unpublish</option>
                                    <option value="archive">Archive</option>
                                    <option value="delete">Delete</option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="submit_article_action_btn" value="submit" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <!-- Updated Footer Section -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
                        <div class="flex flex-col md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Placement Category</label>
                            <div id="placement_category" class="caast-placement-checkboxes mt-1 space-y-1">
                                <?php
                                // Define the specific placement categories
                                $specific_placements = array(
                                    'Home Left Section 1',
                                    'Home Left Section 2',
                                    'Home Right List 1',
                                    'Home Right List 2',
                                    'Author Footer',
                                    'Author Right',
                                    'Book Left',
                                    'Book Right',
                                    'Book Footer',
                                    'Video Left',
                                    'Video Right',
                                    'Video Footer'
                                );

                                // Get terms for 'article_placement' taxonomy
                                $terms = get_terms( array(
                                    'taxonomy'   => 'article_placement',
                                    'hide_empty' => false,
                                ) );

                                if (!is_wp_error($terms) && !empty($terms)) {
                                    $current_placement_terms = wp_get_post_terms($article_post_id_val, 'article_placement', array('fields' => 'slugs'));
                                    $current_placement_slugs = ( ! is_wp_error( $current_placement_terms ) && ! empty( $current_placement_terms ) ) ? $current_placement_terms : array();

                                    foreach ($specific_placements as $placement_name) {
                                        // Find the corresponding term object by name to get its slug
                                        $term_obj = array_filter($terms, function($term) use ($placement_name) {
                                            return $term->name === $placement_name;
                                        });

                                        if (!empty($term_obj)) {
                                            $term_obj   = reset($term_obj); // Get the first matching term object
                                            $value      = $term_obj->slug;
                                            $label_text = $term_obj->name;
                                        } else {
                                            // Fallback if term not found in taxonomy, but still show the option
                                            $value      = sanitize_title($placement_name);
                                            $label_text = $placement_name;
                                        }
                                        $checked = in_array( $value, $current_placement_slugs, true ) ? 'checked' : '';
                                        echo '<label class="caast-checkbox-row flex items-center gap-2 text-sm"><input type="checkbox" class="caast-placement-checkbox" name="placement_category[]" value="' . esc_attr( $value ) . '" ' . $checked . '> <span>' . esc_html( $label_text ) . '</span></label>';
                                    }
                                }
                                ?>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Tick one or more placements to publish this article to multiple sections at the same time.</p>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="placement_action" value="publish_placement" class="button button-primary bg-blue-600 text-white px-4 py-2 rounded-none hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full">Publish</button>
                        </div>
                    </div>

                </form>
            </div>
        </section>
        
        
                <!-- AI Content Generator Section (Main Automation) -->
        <section class="flex-[1] bg-white p-3 sm:p-6 rounded-none shadow-md mb-6 border border-gray-200 max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-3 sm:mb-4">Content Generation and Automation</h2>
            <div class="mb-4">
                <label for="trigger_spreadsheet" class="block text-gray-700 text-xs sm:text-sm font-bold mb-2">
                    Upload Trigger CSV File:
                </label>
                <input type="file" id="trigger_spreadsheet" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" accept=".csv">
                <p class="file-name-display-trigger text-gray-500 text-xs mt-1">No file chosen</p>
            </div>

            <div class="mb-4">
                <label for="resource_files" class="block text-gray-700 text-xs sm:text-sm font-bold mb-2">
                    Upload Resource Files (Optional, for Agents):
                </label>
                <input type="file" id="resource_files" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" multiple>
                <p class="file-name-display-resources text-gray-500 text-xs mt-1">No file chosen</p>
            </div>
            
            <!-- === LLM Model Dropdown UI (Kustom AI Model Switch) === -->
                    <div id="llm-model-selector-block" class="mb-4">
                        <label for="llm_model_select" class="block text-xs sm:text-sm font-medium text-gray-700">LLM Model:</label>
                        <select id="llm_model_select" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="deepseek_r1">DeepSeek R1</option>
                            <option value="ollama_mistral">Ollama Mistral</option>
                            <option value="qwen_2_5">Qwen 2.5</option>
                        </select>
                    </div>
                    <!-- === End Model Dropdown UI === -->

            <!-- PROMPT INPUT FIELD sebelum Final Generated Output section -->
            <div class="mb-4">
                <label for="generation_prompt" class="block text-gray-700 text-xs sm:text-sm font-bold mb-2">Prompt:</label>
                <textarea id="generation_prompt" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" rows="3" placeholder="Enter prompt to generate content..."></textarea>
            </div>

            <button id="start_full_automation" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Start Automation Process</button>
            <div id="full_automation_status" class="response-status-message mt-4 p-3 hidden text-center font-semibold rounded"></div>

            

            <div class="mt-6">
                <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-2">Final Generated Output:</h3>
                <label for="final_generated_title" class="block text-gray-700 text-xs sm:text-sm font-bold mb-2">Title:</label>
                <input type="text" id="final_generated_title" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" readonly placeholder="Generated Title...">

                <label for="final_generated_content" class="block text-gray-700 text-xs sm:text-sm font-bold mb-2 mt-4">Content:</label>
                <textarea id="final_generated_content" class="output-content-textarea shadow appearance-none border rounded-none w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="10" readonly placeholder="Generated Content..."></textarea>
            </div>
            <button id="upload_final_content_to_editor" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full mb-2">Load to Main Editor</button>
            <button id="save_final_content_as_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Save as New Draft</button>



        </section>
        
        
                <section class="flex-[0.8] bg-white p-3 sm:p-6 rounded-none shadow-md mb-6 border border-gray-200 max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-3 sm:mb-4 mt-2 sm:mt-6">Generated Drafts Content</h2>
            <div class="flex flex-col gap-4">
                <div class="flex-1">
                    <label for="select_title_ce" class="block text-gray-700 text-xs sm:text-sm font-bold mb-2">
                        Select Title:
                    </label>
                    <select id="select_title_ce" class="w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Select Title --</option>
                        <?php
                        // Populate with existing book_profile posts (drafts and published)
                        $args = array(
                            'post_type'      => 'book_profile',
                            'posts_per_page' => -1,
                            'post_status'    => array( 'publish', 'draft' ),
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        );
                        $book_posts = new WP_Query( $args );
                        if ( $book_posts->have_posts() ) {
                            while ( $book_posts->have_posts() ) {
                                $book_posts->the_post();
                                echo '<option value="' . esc_attr( get_the_ID() ) . '">' . esc_html( get_the_title() ) . ' (' . esc_html(get_post_status()) . ')</option>';
                            }
                            wp_reset_postdata();
                        }
                        ?>
                    </select>
                </div>
                <div class="flex-1 hidden" id="draft_selection_container">
                    <label for="select_draft_ce" class="block text-gray-700 text-xs sm:text-sm font-bold mb-2">
                        Select Draft:
                    </label>
                    <select id="select_draft_ce" class="w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Select Draft --</option>
                        <option value="draft1">Draft 1 (Agent 1)</option>
                        <option value="draft2">Draft 2 (Agent 2)</option>
                        <option value="draft3">Draft 3 (Agent 3)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button id="load_content_to_editor_from_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Load Content to Editor</button>
                </div>
            </div>
            </section>

        
        

    </main>
    <?php
    return ob_get_clean();
}
add_shortcode( 'my_article_editor', 'my_article_editor_shortcode' );

/**
 * Add rewrite rules
 */
function my_article_editor_rewrite_rule() {
    add_rewrite_rule(
        '^article-profile/([0-9]+)/?$',
        'index.php?post_type=article_profile&p=$matches[1]',
        'top'
    );
}
add_action( 'init', 'my_article_editor_rewrite_rule' );

// Flush rewrite rules on plugin activation
function my_article_editor_activate() {
    my_article_editor_register_cpt();
    my_article_editor_register_taxonomies();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'my_article_editor_activate' );

// Flush rewrite rules on plugin deactivation
function my_article_editor_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'my_article_editor_deactivate' );