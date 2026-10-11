<?php
/*
Plugin Name: My Lesson Editor
Plugin URI:  https://example.com/
Description: A custom editor for lesson profiles, accessible via shortcode, with custom category and level dropdowns, and author selection.
Version:     2.1.6
Author:      Muhamad Fikri Haikal
Author URI:  https://caastedu.com/
License:     GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global flag to indicate if the shortcode is used on the current page
global $my_lesson_editor_shortcode_used;
$my_lesson_editor_shortcode_used = false;

// Define plugin constants
if ( ! defined( 'MY_LESSON_EDITOR_VERSION' ) ) {
    define( 'MY_LESSON_EDITOR_VERSION', '2.1.6' );
}
if ( ! defined( 'MY_LESSON_EDITOR_PLUGIN_URL' ) ) {
    define( 'MY_LESSON_EDITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MY_LESSON_EDITOR_PLUGIN_DIR' ) ) {
    define( 'MY_LESSON_EDITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Include required classes
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/lesson-list-helpers.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-chapter-parser.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-ai-generator.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-academy-lms-integration.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/class-academylm-client.php';
require_once MY_LESSON_EDITOR_PLUGIN_DIR . 'includes/delete-handler.php';

// Initialize classes
$my_lesson_editor_chapter_parser = new My_Lesson_Editor_Chapter_Parser();
$my_lesson_editor_ai_generator = new My_Lesson_Editor_AI_Generator();
$my_lesson_editor_academy_lms = new My_Lesson_Editor_Academy_LMS_Integration();

// Define Webhook URLs for individual agent testing (if still used)
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1', 'https://n8n.srv835792.hstgr.cloud/webhook/48d3ec34-bb02-448d-846a-788a3dcfe0ae' );
}
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_V3' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_V3', 'https://n8n.srv835792.hstgr.cloud/webhook/78897c47-fca8-44ab-88a7-933ce5ff772b' );
}
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_QWEN_2_5' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_QWEN_2_5', 'https://n8n.srv835792.hstgr.cloud/webhook/100d1001-6584-4650-9756-79ee12199af3' );
}

// Define Webhook URL for n8n main automation (override in wp-config.php if needed).
if ( ! defined( 'MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL' ) ) {
    define( 'MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL', MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1 );
}


/**
 * Register Custom Post Type for lesson Content
 */
function my_lesson_editor_register_cpt() {
    $labels = array(
        'name'          => _x( 'Lessons', 'Post Type General Name', 'my-lesson-editor' ),
        'singular_name' => _x( 'Lesson', 'Post Type Singular Name', 'my-lesson-editor' ),
        'menu_name'     => __( 'Lessons', 'my-lesson-editor' ),
        'all_items'     => __( 'All lessons', 'my-lesson-editor' ),
        'add_new_item'  => __( 'Add New lesson', 'my-lesson-editor' ),
        'add_new'       => __( 'Add New', 'my-lesson-editor' ),
        'new_item'      => __( 'New lesson', 'my-lesson-editor' ),
        'edit_item'     => __( 'Edit lesson', 'my-lesson-editor' ),
        'update_item'   => __( 'Update lesson', 'my-lesson-editor' ),
        'view_item'     => __( 'View lesson', 'my-lesson-editor' ),
        'search_items'  => __( 'Search lessons', 'my-lesson-editor' ),
        'not_found'     => __( 'Not Found', 'my-lesson-editor' ),
        'not_found_in_trash' => __( 'Not found in Trash', 'my-lesson-editor' ),
    );
    $args = array(
        'label'               => __( 'Lesson', 'my-lesson-editor' ),
        'description'         => __( 'Content associated with lessons', 'my-lesson-editor' ),
        'labels'              => $labels,
        'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail' ),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-lesson',
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'rewrite'             => array( 'slug' => 'lesson-profile' ),
        'query_var'           => true,
    );
    register_post_type( 'lesson_profile', $args );
}
add_action( 'init', 'my_lesson_editor_register_cpt' );

/**
 * Register custom taxonomies for lessons (Category and Level)
 */
function my_lesson_editor_register_taxonomies() {
    // Category Taxonomy
    $category_labels = array(
        'name'              => _x( 'Lesson Categories', 'taxonomy general name', 'my-lesson-editor' ),
        'singular_name'     => _x( 'lesson Category', 'taxonomy singular name', 'my-lesson-editor' ),
        'search_items'      => __( 'Search lesson Categories', 'my-lesson-editor' ),
        'all_items'         => __( 'All lesson Categories', 'my-lesson-editor' ),
        'parent_item'       => __( 'Parent lesson Category', 'my-lesson-editor' ),
        'parent_item_colon' => __( 'Parent lesson Category:', 'my-lesson-editor' ),
        'edit_item'         => __( 'Edit lesson Category', 'my-lesson-editor' ),
        'update_item'       => __( 'Update lesson Category', 'my-lesson-editor' ),
        'add_new_item'      => __( 'Add New lesson Category', 'my-lesson-editor' ),
        'new_item_name'     => __( 'New lesson Category Name', 'my-lesson-editor' ),
        'menu_name'         => __( 'Categories', 'my-lesson-editor' ),
    );
    $category_args = array(
        'hierarchical'      => true,
        'labels'            => $category_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'lesson-category' ),
    );
    register_taxonomy( 'lesson_category', array( 'lesson_profile' ), $category_args );

    // Level Taxonomy
    $level_labels = array(
        'name'              => _x( 'Lesson Levels', 'taxonomy general name', 'my-lesson-editor' ),
        'singular_name'     => _x( 'lesson Level', 'taxonomy singular name', 'my-lesson-editor' ),
        'search_items'      => __( 'Search lesson Levels', 'my-lesson-editor' ),
        'all_items'         => __( 'All lesson Levels', 'my-lesson-editor' ),
        'edit_item'         => __( 'Edit lesson Level', 'my-lesson-editor' ),
        'update_item'       => __( 'Update lesson Level', 'my-lesson-editor' ),
        'add_new_item'      => __( 'Add New lesson Level', 'my-lesson-editor' ),
        'new_item_name'     => __( 'New lesson Level Name', 'my-lesson-editor' ),
        'menu_name'         => __( 'Levels', 'my-lesson-editor' ),
    );
    $level_args = array(
        'hierarchical'      => false,
        'labels'            => $level_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'lesson-level' ),
    );
    register_taxonomy( 'lesson_level', array( 'lesson_profile' ), $level_args );

    // Placement Category Taxonomy (New)
    $placement_labels = array(
        'name'                       => _x( 'Placement Categories', 'Taxonomy General Name', 'my-lesson-editor' ),
        'singular_name'              => _x( 'Placement Category', 'Taxonomy Singular Name', 'my-lesson-editor' ),
        'menu_name'                  => __( 'Placement', 'my-lesson-editor' ),
        'all_items'                  => __( 'All Placements', 'my-lesson-editor' ),
        'parent_item'                => __( 'Parent Placement', 'my-lesson-editor' ),
        'parent_item_colon'          => __( 'Parent Placement:', 'my-lesson-editor' ),
        'new_item_name'              => __( 'New Placement Name', 'my-lesson-editor' ),
        'add_new_item'               => __( 'Add New Placement', 'my-lesson-editor' ),
        'edit_item'                  => __( 'Edit Placement', 'my-lesson-editor' ),
        'update_item'                => __( 'Update Placement', 'my-lesson-editor' ),
        'view_item'                  => __( 'View Placement', 'my-lesson-editor' ),
        'separate_items_with_commas' => __( 'Separate placements with commas', 'my-lesson-editor' ),
        'add_or_remove_items'        => __( 'Add or remove placements', 'my-lesson-editor' ),
        'choose_from_most_used'      => __( 'Choose from the most used placements', 'my-lesson-editor' ),
        'popular_items'              => __( 'Popular Placements', 'my-lesson-editor' ),
        'search_items'               => __( 'Search Placements', 'my-lesson-editor' ),
        'not_found'                  => __( 'No Placements Found', 'my-lesson-editor' ),
        'no_terms'                   => __( 'No placements', 'my-lesson-editor' ),
        'items_list'                 => __( 'Placements list', 'my-lesson-editor' ),
        'items_list_navigation'      => __( 'Placements list navigation', 'my-lesson-editor' ),
    );
    $placement_args = array(
        'labels'                     => $placement_labels,
        'hierarchical'               => true, // Can be true or false depending on your need
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => false,
        'rewrite'                    => array( 'slug' => 'lesson-placement' ),
        'show_in_rest'               => true,
    );
    register_taxonomy( 'lesson_placement', array( 'lesson_profile' ), $placement_args );
}
add_action( 'init', 'my_lesson_editor_register_taxonomies' );

/**
 * Add default terms for lesson Categories and Levels on plugin activation.
 */
function my_lesson_editor_add_default_terms() {
    $categories = array(
        'Fiction',
        'Non-fiction',
        'Language Arts',
        'STEM',
        'Tests & Assessments',
        'Arts & Design'
    );
    foreach ( $categories as $category_name ) {
        if ( ! term_exists( $category_name, 'lesson_category' ) ) {
            wp_insert_term( $category_name, 'lesson_category' );
        }
    }

    $levels = array(
        'Elementary',
        'Intermediate',
        'Advanced',
        'College'
    );
    foreach ( $levels as $level_name ) {
        if ( ! term_exists( $level_name, 'lesson_level' ) ) {
            wp_insert_term( $level_name, 'lesson_level' );
        }
    }

    // Default terms for Placement Category (New)
    $placements = array(
        'Homepage Featured',
        'Category Page',
        'Sidebar Ads',
        'Blog Post Insert',
        'Landing Page',
        'Home Left Section 1',
        'Home Left Section 2',
        'Home Right List 1',
        'Home Right List 2',
        'Author Footer',
        'Author Right',
        'Article Footer',
        'Article Right',
        'lesson Right',
        'lesson Footer',
        'Video Left',
        'Video Right',
        'Video Footer'
    );
    foreach ( $placements as $placement_name ) {
        if ( ! term_exists( $placement_name, 'lesson_placement' ) ) {
            wp_insert_term( $placement_name, 'lesson_placement' );
        }
    }
}
register_activation_hook( __FILE__, 'my_lesson_editor_add_default_terms' );


/**
 * Enqueue lesson editor scripts/styles.
 *
 * Must run when the shortcode renders: wp_enqueue_scripts runs before the_content,
 * so the old "global shortcode_used" check there never became true and assets never loaded.
 */
function my_lesson_editor_enqueue_frontend_assets() {
    static $mle_assets_enqueued = false;
    if ( $mle_assets_enqueued ) {
        return;
    }
    $mle_assets_enqueued = true;

    wp_enqueue_style( 'mle-tailwind', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), '2.2.19' );
    wp_enqueue_style( 'mle-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', array(), null );
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4' );

    wp_enqueue_media();

    if ( current_user_can( 'edit_posts' ) ) {
        wp_enqueue_script( 'wp-tinymce' );
        wp_enqueue_script( 'editor' );
        wp_enqueue_script( 'wplink' );
        wp_enqueue_script( 'wp-plupload' );
        wp_enqueue_script( 'thickbox' );
        wp_enqueue_style( 'thickbox' );
    }

    wp_enqueue_script(
        'mle-shared-uploader',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/mle-shared-uploader.js',
        array(),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_script(
        'my-lesson-editor-script',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/my-lesson-editor.js',
        array( 'jquery', 'mle-shared-uploader' ),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_script(
        'mle-layout-resizer',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/mle-layout-resizer.js',
        array(),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_script(
        'mle-lesson-tree',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/mle-lesson-tree.js',
        array( 'jquery', 'my-lesson-editor-script' ),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_script(
        'my-lesson-editor-enhanced-script',
        MY_LESSON_EDITOR_PLUGIN_URL . 'js/my-lesson-editor-enhanced.js',
        array( 'jquery', 'my-lesson-editor-script' ),
        MY_LESSON_EDITOR_VERSION,
        true
    );

    wp_enqueue_style(
        'my-lesson-editor-style',
        MY_LESSON_EDITOR_PLUGIN_URL . 'css/my-lesson-editor.css',
        array( 'mle-tailwind' ),
        MY_LESSON_EDITOR_VERSION
    );

    $mle_local = array(
        'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
        'nonce'                    => wp_create_nonce( 'lesson_editor_nonce' ),
        'alert_no_lesson_selected' => __( 'Please Select a lesson first.', 'my-lesson-editor' ),
        'alert_no_author_selected' => __( 'Please select an author first.', 'my-lesson-editor' ),
        'alert_confirm_delete'     => __( 'Are you sure you want to delete this lesson? This action cannot be undone.', 'my-lesson-editor' ),
        'alert_content_not_found'  => __( 'No content found for the selected lesson.', 'my-lesson-editor' ),
        'delete_confirm_message'   => __( 'Are you sure you want to delete this item?', 'my-lesson-editor' ),
        'delete_success_message'   => __( 'Deleted successfully', 'my-lesson-editor' ),
        'delete_error_message'     => __( 'Failed to delete this item. Please try again.', 'my-lesson-editor' ),
        'n8n_webhook_url'          => defined( 'MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL' ) ? MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL : '',
    );
    wp_localize_script( 'my-lesson-editor-script', 'mylessonEditorAjax', $mle_local );
    wp_localize_script( 'my-lesson-editor-script', 'myLessonPlanEditorAjax', $mle_local );
}

/**
 * Ticked placement slugs from the submitted form (checkbox array or legacy single value).
 *
 * @return string[]
 */
function my_lesson_editor_get_placement_slugs_from_post() {
	if ( ! isset( $_POST['placement_category'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return array();
	}

	$slugs = array();
	foreach ( (array) wp_unslash( $_POST['placement_category'] ) as $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$slug = sanitize_title( (string) $value );
		if ( '' !== $slug && term_exists( $slug, 'lesson_placement' ) ) {
			$slugs[] = $slug;
		}
	}

	return array_values( array_unique( $slugs ) );
}

/**
 * Handle form submission for lesson Content Editor.
 */
function my_lesson_editor_handle_submission() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'my_lesson_editor_submit' ) {
        return;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
        exit;
    }

    // Verify nonce
    if ( ! isset( $_POST['_wpnonce_lesson_content'] ) || ! wp_verify_nonce( $_POST['_wpnonce_lesson_content'], 'lesson_content_submission' ) ) {
        wp_die( 'Security check failed. Please refresh the page and try again.' );
    }

    // Sanitize and retrieve form data
    $selected_lesson_id   = isset( $_POST['selected_lesson_id'] ) ? intval( $_POST['selected_lesson_id'] ) : 0;
    $new_lesson_name      = sanitize_text_field( $_POST['new_lesson_name'] );
    $lesson_subtitle      = sanitize_text_field( $_POST['lesson_subtitle'] );
    $lesson_content       = wp_kses_post( $_POST['lesson_content'] );
    $lesson_cover_id      = isset( $_POST['lesson_cover_id'] ) ? intval( $_POST['lesson_cover_id'] ) : 0;
    $placement_category = my_lesson_editor_get_placement_slugs_from_post();
    $insert_link        = isset( $_POST['insert_link'] ) ? esc_url_raw( $_POST['insert_link'] ) : '';

    // Get selected taxonomy terms (slugs)
    $lesson_category_slug = isset( $_POST['lesson_category'] ) ? sanitize_text_field( $_POST['lesson_category'] ) : '';
    $lesson_level_slug    = isset( $_POST['lesson_level'] ) ? sanitize_text_field( $_POST['lesson_level'] ) : '';
    $selected_author_id = isset( $_POST['selected_author_id'] ) ? intval( $_POST['selected_author_id'] ) : 0;

    $submit_action      = sanitize_text_field( $_POST['submit_lesson_action'] ); // Existing action for Save/Publish/etc.
    $placement_action   = isset( $_POST['placement_action'] ) ? sanitize_text_field( $_POST['placement_action'] ) : '';

    // Determine the lesson title to use for the post
    $post_title = '';
    if ( ! empty( $new_lesson_name ) ) {
        $post_title = $new_lesson_name;
    } elseif ( $selected_lesson_id > 0 ) {
        $existing_lesson = get_post( $selected_lesson_id );
        if ( $existing_lesson ) {
            $post_title = $existing_lesson->post_title;
        }
    }

    $existing_post_id = isset( $_POST['lesson_post_id'] ) ? intval( $_POST['lesson_post_id'] ) : 0;

    // Basic validation for new lesson or updating existing lesson if title changed
    if ( empty( $post_title ) && empty($existing_post_id) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_empty_title', wp_get_referer() ) );
        exit;
    }

    // Determine post status based on button clicked
    $post_status = 'pending';
    $redirect_status = 'error';

    if ( 'publish_placement' === $placement_action ) {
        if ( $existing_post_id <= 0 ) {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_lesson_selected_for_placement', wp_get_referer() ) );
            exit;
        }
        if ( empty( $placement_category ) ) {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_placement_selected', wp_get_referer() ) );
            exit;
        }
        if ( ! current_user_can( 'publish_lesson_profiles' ) && ! current_user_can( 'publish_posts' ) ) {
            wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
            exit;
        }
        $post_status     = 'publish';
        $redirect_status = 'lesson_published_to_placement';
    } elseif ( $submit_action === 'publish' ) {
        if ( current_user_can( 'publish_lesson_profiles' ) || current_user_can( 'publish_posts' ) ) {
            $post_status = 'publish';
            $redirect_status = 'lesson_published';
        } else {
            $post_status = 'pending';
            $redirect_status = 'lesson_pending_review';
        }
    } elseif ( $submit_action === 'save' ) {
        $post_status = 'draft';
        $redirect_status = 'lesson_saved_draft';
    } elseif ( $submit_action === 'unpublish' ) {
        $post_status = 'draft';
        $redirect_status = 'lesson_unpublished';
    } elseif ( $submit_action === 'archive' ) {
        if ( get_post_status_object('archive') ) {
             $post_status = 'archive';
        } else {
            $post_status = 'draft';
        }
        $redirect_status = 'lesson_archived';
    } elseif ( $submit_action === 'delete' ) {
        if ( $existing_post_id ) {
            if ( current_user_can( 'delete_lesson_profiles' ) || current_user_can( 'delete_posts' ) ) {
                if ( wp_delete_post( $existing_post_id, true ) ) {
                    wp_safe_redirect( add_query_arg( 'status', 'lesson_deleted', wp_get_referer() ) );
                    exit;
                } else {
                    error_log( 'Error deleting lesson post: ' . $existing_post_id );
                    wp_safe_redirect( add_query_arg( 'status', 'error_lesson_delete', wp_get_referer() ) );
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
    }


    // Prepare post data for save/update if not already handled by delete/placement publish
    $post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $lesson_content,
        'post_status'   => $post_status,
        'post_type'     => 'lesson_profile',
        'post_author'   => get_current_user_id(),
    );

    $post_id = 0;
    if ( $existing_post_id > 0 ) {
        $post_data['ID'] = $existing_post_id;
        $result = wp_update_post( $post_data, true );
    } else {
        $result = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $result ) ) {
        error_log( 'Error creating/updating lesson post: ' . $result->get_error_message() );
        wp_safe_redirect( add_query_arg( 'status', 'error_lesson_db', wp_get_referer() ) );
    } elseif ( $result === 0 ) {
        error_log( 'Failed to create/update lesson post, wp_insert_post/wp_update_post returned 0.' );
        wp_safe_redirect( add_query_arg( 'status', 'error_lesson_db', wp_get_referer() ) );
    } else {
        // Save lesson subtitle as post meta
        update_post_meta( $result, '_lesson_subtitle', $lesson_subtitle );

        // Set featured image (lesson cover)
        if ( ! empty( $lesson_cover_id ) ) {
            set_post_thumbnail( $result, $lesson_cover_id );
        } else {
            delete_post_thumbnail( $result ); // Remove if ID is 0 or empty
        }

        // Save new insert_link as post meta (footer_position is now placement_category)
        update_post_meta( $result, '_insert_link', $insert_link );
        // Set the placement taxonomy directly
        if ( ! empty( $placement_category ) ) {
            wp_set_post_terms( $result, $placement_category, 'lesson_placement' );
        } else {
            wp_set_post_terms( $result, null, 'lesson_placement' ); // Clear if no placement selected
        }


        // Save selected Author ID as post meta for the lesson
        update_post_meta( $result, '_lesson_author_id', $selected_author_id );

        // Set taxonomies
        if ( ! empty( $lesson_category_slug ) ) {
            wp_set_post_terms( $result, $lesson_category_slug, 'lesson_category' );
        } else {
            wp_set_post_terms( $result, null, 'lesson_category' ); // Clear if no category selected
        }

        if ( ! empty( $lesson_level_slug ) ) {
            wp_set_post_terms( $result, $lesson_level_slug, 'lesson_level' );
        } else {
            wp_set_post_terms( $result, null, 'lesson_level' ); // Clear if no level selected
        }

        $saved_post = get_post( $result );
        if ( $saved_post ) {
            update_post_meta( $result, '_mle_book_title', mle_get_lesson_book_title( $saved_post ) );
        }

        wp_safe_redirect( add_query_arg( 'status', $redirect_status, wp_get_referer() ) );
    }
    exit;
}
add_action( 'admin_post_my_lesson_editor_submit', 'my_lesson_editor_handle_submission' );
add_action( 'admin_post_nopriv_my_lesson_editor_submit', 'my_lesson_editor_handle_submission' );

/**
 * AJAX handler to get lesson content based on lesson ID.
 */
add_action( 'wp_ajax_my_lesson_editor_get_lesson_content', 'my_lesson_editor_get_lesson_content_ajax' );
function my_lesson_editor_get_lesson_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-lesson-editor' ) ) );
    }

    check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

    $lesson_id = isset( $_POST['lesson_id'] ) ? intval( $_POST['lesson_id'] ) : 0;

    if ( $lesson_id ) {
        $args = array(
            'p'              => $lesson_id,
            'post_type'      => 'lesson_profile',
            'post_status'    => array( 'publish', 'pending', 'draft', 'archive' ),
            'posts_per_page' => 1,
        );
        $lesson_query = new WP_Query( $args );

        if ( $lesson_query->have_posts() ) {
            $lesson_post = $lesson_query->posts[0];

            $current_categories = wp_get_post_terms( $lesson_post->ID, 'lesson_category', array( 'fields' => 'slugs' ) );
            $current_levels     = wp_get_post_terms( $lesson_post->ID, 'lesson_level', array( 'fields' => 'slugs' ) );
            $current_placement  = wp_get_post_terms( $lesson_post->ID, 'lesson_placement', array( 'fields' => 'slugs' ) ); // New: Get placement

            $associated_author_id = get_post_meta( $lesson_post->ID, '_lesson_author_id', true );

            // Ensure lesson cover URL is fetched correctly
            $lesson_cover_id = get_post_thumbnail_id( $lesson_post->ID );
            $lesson_cover_url = $lesson_cover_id ? wp_get_attachment_url( $lesson_cover_id ) : '';


            wp_send_json_success( array(
                'lesson_post_id'    => $lesson_post->ID,
                'lesson_name'       => esc_html( $lesson_post->post_title ),
                'lesson_subtitle'   => esc_html( get_post_meta( $lesson_post->ID, '_lesson_subtitle', true ) ),
                'lesson_content'    => $lesson_post->post_content,
                'lesson_cover_id'   => $lesson_cover_id,
                'lesson_cover_url'  => $lesson_cover_url,
                'placement_category' => ( ! empty( $current_placement ) && ! is_wp_error( $current_placement ) ) ? array_values( $current_placement ) : array(),
                'insert_link'     => esc_url( get_post_meta( $lesson_post->ID, '_insert_link', true ) ),
                'lesson_categories' => !empty($current_categories) ? $current_categories[0] : '',
                'lesson_levels'     => !empty($current_levels) ? $current_levels[0] : '',
                'associated_author_id' => intval($associated_author_id),
            ) );
        } else {
            wp_send_json_success( array(
                'lesson_post_id'    => 0,
                'lesson_name'       => '',
                'lesson_subtitle'   => '',
                'lesson_content'    => '',
                'lesson_cover_id'   => 0,
                'lesson_cover_url'  => '',
                'placement_category' => array(),
                'insert_link'     => '',
                'lesson_categories' => '',
                'lesson_levels'     => '',
                'associated_author_id' => 0,
                'message'         => 'No existing content found for this lesson. You can create a new one.',
            ) );
        }
    } else {
        wp_send_json_success( array(
            'lesson_post_id'    => 0,
            'lesson_name'       => '',
            'lesson_subtitle'   => '',
            'lesson_content'    => '',
            'lesson_cover_id'   => 0,
            'lesson_cover_url'  => '',
            'placement_category' => array(),
            'insert_link'     => '',
            'lesson_categories' => '',
            'lesson_levels'     => '',
            'associated_author_id' => 0,
            'message'         => __( 'Invalid lesson ID, or no lesson selected.', 'my-lesson-editor' )
        ) );
    }
}


/**
 * AJAX handler to get author content based on author ID.
 */
add_action( 'wp_ajax_my_lesson_editor_get_author_content', 'my_lesson_editor_get_author_content_ajax' );
function my_lesson_editor_get_author_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-lesson-editor' ) ) );
    }

    check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

    $author_id = isset( $_POST['author_id'] ) ? intval( $_POST['author_id'] ) : 0;

    if ( $author_id ) {
        $author_post = get_post( $author_id );
        if ( $author_post && $author_post->post_type === 'author_profile' ) {
            wp_send_json_success( array(
                'author_id'   => $author_post->ID,
                'author_name' => $author_post->post_title,
                'message'     => 'Author selected: ' . $author_post->post_title,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Author not found.', 'my-lesson-editor' ) ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'No author selected.', 'my-lesson-editor' ) ) );
    }
}


/**
 * Build n8n webhook envelope: { source, body: { ... } } so expressions like $json.body.book_name work.
 * Also builds selected_tasks[] with id, resolved_prompt, mc_qty, short_answer_qty, discussion_questions_count.
 *
 * @param array $automation_in Decoded automation_payload from the browser.
 * @param array $tasks_out     Boolean map lesson_plan, vocabulary, …
 * @param array $file_extras   textbook_filename, textbook_base64, optional trigger_spreadsheet_content.
 * @return array Top-level keys: source (string), body (array) for n8n $json.body.*.
 */
function my_lesson_editor_build_n8n_webhook_envelope( array $automation_in, array $tasks_out, array $file_extras = array() ) {
    $book_name   = isset( $automation_in['book_name'] ) ? sanitize_text_field( $automation_in['book_name'] ) : '';
    $author_name = isset( $automation_in['author_name'] ) ? sanitize_text_field( $automation_in['author_name'] ) : '';
    $chapter     = isset( $automation_in['chapter'] ) ? sanitize_text_field( $automation_in['chapter'] ) : '';

    $difficulty = isset( $automation_in['difficulty_level'] ) ? sanitize_text_field( $automation_in['difficulty_level'] ) : '';
    $allowed    = array( 'beginner', 'intermediate', 'advanced', 'preparatory', 'regular', 'honors' );
    if ( $difficulty === '' || ! in_array( $difficulty, $allowed, true ) ) {
        $difficulty = 'regular';
    }

    $grade_level = isset( $automation_in['grade_level'] ) ? sanitize_text_field( $automation_in['grade_level'] ) : '';
    $allowed_grade = array( 'elementary_school', 'middle_school', 'high_school', 'college' );
    if ( $grade_level === '' || ! in_array( $grade_level, $allowed_grade, true ) ) {
        $grade_level = 'middle_school';
    }

    $task_prompts = array(
        'lesson_plan' => 'Generate a comprehensive lesson plan for the given book context and chapter. Include learning objectives, instructional sequence, formative checks, and materials notes.',
        'vocabulary'  => 'Generate a vocabulary list with clear definitions and example sentences, aligned to the book and chapter.',
        'flashcards'  => 'Generate flashcard items (term/definition or question/answer) derived from the vocabulary scope.',
        'quiz'        => 'Generate a chapter quiz with the specified numbers of multiple-choice and short-answer questions.',
        'midterm'     => 'Generate a midterm-style assessment appropriate to the difficulty level and book context.',
        'final_exam'  => 'Generate a comprehensive final-exam-style assessment for the book context.',
    );

    $default_qty = array(
        'lesson_plan' => array( 'mc_qty' => null, 'short_answer_qty' => null, 'discussion_questions_count' => null ),
        'vocabulary'  => array( 'mc_qty' => null, 'short_answer_qty' => null, 'discussion_questions_count' => null ),
        'flashcards'  => array( 'mc_qty' => null, 'short_answer_qty' => null, 'discussion_questions_count' => null ),
        'quiz'        => array( 'mc_qty' => 10, 'short_answer_qty' => 4, 'discussion_questions_count' => 0 ),
        'midterm'     => array( 'mc_qty' => 15, 'short_answer_qty' => 5, 'discussion_questions_count' => 3 ),
        'final_exam'  => array( 'mc_qty' => 20, 'short_answer_qty' => 8, 'discussion_questions_count' => 5 ),
    );

    $overrides = array();
    if ( ! empty( $automation_in['task_quantities'] ) && is_array( $automation_in['task_quantities'] ) ) {
        foreach ( $automation_in['task_quantities'] as $tid => $q ) {
            if ( is_array( $q ) ) {
                $overrides[ sanitize_key( $tid ) ] = $q;
            }
        }
    }

    $selected_tasks = array();
    foreach ( $tasks_out as $task_id => $enabled ) {
        if ( ! $enabled ) {
            continue;
        }
        $qty = isset( $default_qty[ $task_id ] ) ? $default_qty[ $task_id ] : array(
            'mc_qty'                       => null,
            'short_answer_qty'             => null,
            'discussion_questions_count' => null,
        );
        if ( isset( $overrides[ $task_id ] ) && is_array( $overrides[ $task_id ] ) ) {
            foreach ( array( 'mc_qty', 'short_answer_qty', 'discussion_questions_count' ) as $qk ) {
                if ( array_key_exists( $qk, $overrides[ $task_id ] ) && null !== $overrides[ $task_id ][ $qk ] && '' !== $overrides[ $task_id ][ $qk ] ) {
                    $qty[ $qk ] = (int) $overrides[ $task_id ][ $qk ];
                }
            }
        }
        $base = isset( $task_prompts[ $task_id ] ) ? $task_prompts[ $task_id ] : 'Generate educational content for this task type.';
        if ( $chapter !== '' && '—' !== $chapter ) {
            $base .= ' Scope: chapter ' . $chapter . '.';
        }
        $selected_tasks[] = array(
            'id'                           => $task_id,
            'resolved_prompt'              => $base,
            'mc_qty'                       => $qty['mc_qty'],
            'short_answer_qty'             => $qty['short_answer_qty'],
            'discussion_questions_count'   => $qty['discussion_questions_count'],
        );
    }

    $body = array(
        'book_name'         => $book_name,
        'author_name'       => $author_name,
        'difficulty_level'  => $difficulty,
        'grade_level'       => $grade_level,
        'chapter'           => $chapter,
        'model'             => isset( $automation_in['model'] ) ? sanitize_text_field( $automation_in['model'] ) : 'deepseek_r1',
        'lesson_id'         => isset( $automation_in['lesson_id'] ) ? absint( $automation_in['lesson_id'] ) : 0,
        'lesson_title'      => isset( $automation_in['lesson_title'] ) ? sanitize_text_field( $automation_in['lesson_title'] ) : '',
        'lesson_subtitle'   => isset( $automation_in['lesson_subtitle'] ) ? sanitize_text_field( $automation_in['lesson_subtitle'] ) : '',
        'lesson_category'   => isset( $automation_in['lesson_category'] ) ? sanitize_text_field( $automation_in['lesson_category'] ) : '',
        'tasks'             => $tasks_out,
        'selected_tasks'    => $selected_tasks,
    );

    if ( ! empty( $file_extras ) && is_array( $file_extras ) ) {
        $body = array_merge( $body, $file_extras );
    }

    return array(
        'source' => 'my_lesson_editor_full_automation',
        'body'   => $body,
    );
}

/**
 * Normalize LLM / n8n JSON (title+content or final_title+final_content, nested or string JSON in output).
 *
 * @param array $data Parsed JSON from webhook.
 * @return array Tuple [0 => title string, 1 => content string].
 */
function my_lesson_editor_extract_automation_output( $data ) {
    $title   = '';
    $content = '';
    if ( ! is_array( $data ) ) {
        return array( $title, $content );
    }

    $pick_title = function ( $row ) {
        if ( ! is_array( $row ) ) {
            return '';
        }
        if ( ! empty( $row['final_title'] ) ) {
            return (string) $row['final_title'];
        }
        if ( ! empty( $row['title'] ) ) {
            return (string) $row['title'];
        }
        return '';
    };

    $pick_content = function ( $row ) {
        if ( ! is_array( $row ) ) {
            return '';
        }
        if ( isset( $row['final_content'] ) && $row['final_content'] !== '' ) {
            return (string) $row['final_content'];
        }
        if ( isset( $row['content'] ) && $row['content'] !== '' ) {
            return (string) $row['content'];
        }
        return '';
    };

    $title   = $pick_title( $data );
    $content = $pick_content( $data );

    if ( ( $title === '' || $content === '' ) && ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
        if ( $title === '' ) {
            $title = $pick_title( $data['data'] );
        }
        if ( $content === '' ) {
            $content = $pick_content( $data['data'] );
        }
    }

    if ( ( $title === '' || $content === '' ) && ! empty( $data['output'] ) && is_string( $data['output'] ) ) {
        $inner = json_decode( $data['output'], true );
        if ( is_array( $inner ) ) {
            if ( $title === '' ) {
                $title = $pick_title( $inner );
            }
            if ( $content === '' ) {
                $content = $pick_content( $inner );
            }
        } elseif ( $content === '' ) {
            $content = (string) $data['output'];
        }
    }

    if ( $title === '' && ! empty( $data['lesson_title'] ) ) {
        $title = (string) $data['lesson_title'];
    }
    if ( $title === '' && ! empty( $data['book_title'] ) ) {
        $title = (string) $data['book_title'];
    }
    if ( $content === '' && ! empty( $data['lesson_content'] ) ) {
        $content = (string) $data['lesson_content'];
    }
    if ( $content === '' && ! empty( $data['text'] ) && is_string( $data['text'] ) ) {
        $content = (string) $data['text'];
    }
    if ( $content === '' && ! empty( $data['result'] ) && is_string( $data['result'] ) ) {
        $content = (string) $data['result'];
    }
    if ( $content === '' && ! empty( $data['body'] ) && is_string( $data['body'] ) ) {
        $content = (string) $data['body'];
    }

    if ( ( $title === '' || $content === '' ) && ! empty( $data['body'] ) && is_array( $data['body'] ) ) {
        if ( $title === '' ) {
            $title = $pick_title( $data['body'] );
        }
        if ( $content === '' ) {
            $content = $pick_content( $data['body'] );
        }
    }

    if ( ( $title === '' || $content === '' ) && isset( $data[0] ) && is_array( $data[0] ) ) {
        if ( $title === '' ) {
            $title = $pick_title( $data[0] );
        }
        if ( $content === '' ) {
            $content = $pick_content( $data[0] );
        }
    }

    return array( $title, $content );
}

/**
 * Parse n8n HTTP body: strict JSON, fenced markdown, or embedded object.
 *
 * @param string $body Raw response body.
 * @return array{0: ?array, 1: string} Parsed array or null, and text used for fallback content.
 */
function my_lesson_editor_normalize_n8n_body( $body ) {
    $body = trim( (string) $body );
    if ( $body === '' ) {
        return array( null, '' );
    }

    $candidates = array( $body );
    if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $body, $fence ) ) {
        $candidates[] = trim( $fence[1] );
    }
    if ( preg_match( '/(\{[\s\S]+\}|\[[\s\S]+\])/', $body, $embedded ) ) {
        $candidates[] = trim( $embedded[1] );
    }

    foreach ( $candidates as $candidate ) {
        if ( $candidate === '' ) {
            continue;
        }
        $data = json_decode( $candidate, true );
        if ( JSON_ERROR_NONE === json_last_error() && is_array( $data ) ) {
            if ( isset( $data['data'] ) && is_array( $data['data'] ) && ! isset( $data['final_content'] ) && ! isset( $data['content'] ) ) {
                $data = $data['data'];
            }
            return array( $data, $body );
        }
    }

    return array( null, $body );
}

// AJAX handler for full automation process (for lesson editor)
add_action( 'wp_ajax_my_lesson_editor_full_automation', 'my_lesson_editor_full_automation_callback' );
function my_lesson_editor_full_automation_callback() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to run automation.', 'my-lesson-editor' ) ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'lesson_editor_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Nonce verification failed. Please reload the page.', 'my-lesson-editor' ) ) );
    }

    $webhook = MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL;
    if ( empty( $webhook ) || ( is_string( $webhook ) && false !== strpos( $webhook, 'YOUR_N8N' ) ) ) {
        $webhook = MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1;
    }

    $automation_in = array();
    if ( ! empty( $_POST['automation_payload'] ) ) {
        $decoded = json_decode( wp_unslash( $_POST['automation_payload'] ), true );
        if ( is_array( $decoded ) ) {
            $automation_in = $decoded;
        }
    }

    $task_keys = array( 'lesson_plan', 'vocabulary', 'flashcards', 'quiz', 'midterm', 'final_exam' );
    $tasks_out = array();
    foreach ( $task_keys as $tk ) {
        $tasks_out[ $tk ] = false;
    }
    if ( ! empty( $automation_in['tasks'] ) && is_array( $automation_in['tasks'] ) ) {
        foreach ( $task_keys as $tk ) {
            if ( array_key_exists( $tk, $automation_in['tasks'] ) ) {
                $tasks_out[ $tk ] = filter_var( $automation_in['tasks'][ $tk ], FILTER_VALIDATE_BOOLEAN );
            }
        }
    }

    $has_task = false;
    foreach ( $tasks_out as $v ) {
        if ( $v ) {
            $has_task = true;
            break;
        }
    }
    if ( ! $has_task ) {
        wp_send_json_error( array( 'message' => __( 'Select at least one content type to generate.', 'my-lesson-editor' ) ) );
    }

    $file_extras = array();

    // Optional textbook / book file (same field names as the lesson editor UI).
    $file_fields = array( 'book_upload', 'textbook_file' );
    foreach ( $file_fields as $field ) {
        if ( empty( $_FILES[ $field ]['tmp_name'] ) || ! is_uploaded_file( $_FILES[ $field ]['tmp_name'] ) ) {
            continue;
        }
        $size = isset( $_FILES[ $field ]['size'] ) ? (int) $_FILES[ $field ]['size'] : 0;
        if ( $size <= 0 || $size > 4 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => __( 'Uploaded file must be under 4 MB.', 'my-lesson-editor' ) ) );
        }
        $raw = file_get_contents( $_FILES[ $field ]['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $raw ) {
            wp_send_json_error( array( 'message' => __( 'Could not read uploaded file.', 'my-lesson-editor' ) ) );
        }
        $file_extras['textbook_filename'] = isset( $_FILES[ $field ]['name'] ) ? sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) ) : 'upload.bin';
        $file_extras['textbook_base64']   = base64_encode( $raw );
        break;
    }

    // Optional legacy CSV trigger (not required).
    if ( ! empty( $_FILES['trigger_spreadsheet']['tmp_name'] ) && is_uploaded_file( $_FILES['trigger_spreadsheet']['tmp_name'] ) ) {
        $csv_raw = file_get_contents( $_FILES['trigger_spreadsheet']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false !== $csv_raw ) {
            $file_extras['trigger_spreadsheet_content'] = base64_encode( $csv_raw );
        }
    }

    $payload = my_lesson_editor_build_n8n_webhook_envelope( $automation_in, $tasks_out, $file_extras );

    $response = wp_remote_post(
        $webhook,
        array(
            'method'  => 'POST',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 300,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ) );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    list( $data, $raw_body ) = my_lesson_editor_normalize_n8n_body( $body );

    if ( $code < 200 || $code >= 300 ) {
        wp_send_json_error(
            array(
                'message' => sprintf(
                    /* translators: 1: HTTP status code, 2: response body excerpt */
                    __( 'n8n returned HTTP %1$d. Body: %2$s', 'my-lesson-editor' ),
                    $code,
                    function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 500 ) : substr( $body, 0, 500 )
                ),
            )
        );
    }

    $book_fallback = ! empty( $payload['body']['book_name'] ) ? $payload['body']['book_name'] : __( 'Generated output', 'my-lesson-editor' );

    if ( ! is_array( $data ) ) {
        $plain = $raw_body !== '' ? $raw_body : $body;
        wp_send_json_success(
            array(
                'message'       => __( 'Automation finished.', 'my-lesson-editor' ),
                'http_code'     => $code,
                'n8n_response'  => $plain,
                'final_title'   => $book_fallback,
                'final_content' => $plain,
            )
        );
    }

    list( $final_title, $final_content ) = my_lesson_editor_extract_automation_output( $data );

    if ( $final_title === '' ) {
        $final_title = $book_fallback;
    }
    if ( $final_content === '' ) {
        $final_content = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    wp_send_json_success(
        array(
            'message'       => isset( $data['message'] ) ? $data['message'] : __( 'Automation finished.', 'my-lesson-editor' ),
            'http_code'     => $code,
            'n8n_response'  => $data,
            'final_title'   => $final_title,
            'final_content' => $final_content,
        )
    );
}

// AJAX handler for saving final generated content as a new draft (for lesson editor)
add_action( 'wp_ajax_my_lesson_editor_save_final_content_as_draft', 'my_lesson_editor_save_final_content_as_draft_callback' );
function my_lesson_editor_save_final_content_as_draft_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) { // Using lesson_editor_nonce now
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    $title = sanitize_text_field( $_POST['title'] );
    $content = wp_kses_post( $_POST['content'] );

    if ( empty( $title ) || empty( $content ) ) {
        wp_send_json_error( 'Title and content cannot be empty.' );
    }

    $new_post_data = array(
        'post_title'    => $title,
        'post_content'  => $content,
        'post_status'   => 'draft',
        'post_type'     => 'lesson_profile',
    );

    $new_post_id = wp_insert_post( $new_post_data, true );

    if ( is_wp_error( $new_post_id ) ) {
        wp_send_json_error( $new_post_id->get_error_message() );
    }

    $saved_post = get_post( $new_post_id );
    if ( $saved_post ) {
        update_post_meta( $new_post_id, '_mle_book_title', mle_get_lesson_book_title( $saved_post ) );
    }

    wp_send_json_success(
        array(
            'message'     => 'New draft created successfully!',
            'post_id'     => $new_post_id,
            'post_title'  => $title,
            'post_status' => 'draft',
        )
    );
}

/**
 * Query lesson_profile posts for the Generated Drafts dropdown.
 *
 * @return WP_Post[]
 */
function my_lesson_editor_query_draft_list_posts() {
    $query_args = array(
        'post_type'              => 'lesson_profile',
        'posts_per_page'         => -1,
        'post_status'            => array( 'publish', 'draft', 'pending', 'archive' ),
        'orderby'                => 'modified',
        'order'                  => 'DESC',
        'suppress_filters'       => true,
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );

    if ( ! current_user_can( 'edit_others_posts' ) ) {
        $query_args['author'] = get_current_user_id();
    }

    return get_posts( $query_args );
}

// AJAX: list lesson_profile drafts for Generated Drafts dropdown.
add_action( 'wp_ajax_my_lesson_editor_get_draft_titles', 'my_lesson_editor_get_draft_titles_callback' );
function my_lesson_editor_get_draft_titles_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $posts  = my_lesson_editor_query_draft_list_posts();
    $titles = array();
    foreach ( $posts as $post ) {
        $titles[] = array(
            'id'     => $post->ID,
            'title'  => $post->post_title,
            'status' => $post->post_status,
        );
    }

    wp_send_json_success( array( 'drafts' => $titles ) );
}

// AJAX: grouped lesson tree for right column (by book title).
add_action( 'wp_ajax_my_lesson_editor_get_grouped_lessons', 'my_lesson_editor_get_grouped_lessons_callback' );
function my_lesson_editor_get_grouped_lessons_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'lesson_editor_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'my-lesson-editor' ) ) );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ) );
    }

    $posts = my_lesson_editor_query_draft_list_posts();
    wp_send_json_success( mle_build_grouped_lesson_tree( $posts ) );
}

// AJAX: bulk submit selected lesson_profile posts to Academy LMS.
add_action( 'wp_ajax_mle_bulk_submit_to_academylm', 'mle_ajax_bulk_submit_to_academylm_callback' );
function mle_ajax_bulk_submit_to_academylm_callback() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ), 403 );
    }
    check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

    $course_id = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
    if ( $course_id <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Select a course before submitting to LMS.', 'my-lesson-editor' ) ) );
    }

    $post_ids = isset( $_POST['post_ids'] ) ? wp_unslash( $_POST['post_ids'] ) : array();
    if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
        wp_send_json_error( array( 'message' => __( 'No lessons selected.', 'my-lesson-editor' ) ) );
    }

    $client  = new MLE_AcademyLM_Client();
    $results = array(
        'success' => array(),
        'failed'  => array(),
        'skipped' => array(),
    );

    foreach ( $post_ids as $raw_id ) {
        $post_id = (int) $raw_id;
        $post    = get_post( $post_id );
        if ( ! $post || 'lesson_profile' !== $post->post_type ) {
            $results['failed'][] = array(
                'id'      => $post_id,
                'message' => __( 'Invalid lesson.', 'my-lesson-editor' ),
            );
            continue;
        }

        // create_lesson() also updates content when _mle_academy_lesson_id already exists.
        $result = $client->create_lesson(
            array(
                'title'             => $post->post_title,
                'content'           => $post->post_content,
                'course_id'         => $course_id,
                'lesson_profile_id' => $post_id,
                'kind'              => mle_get_lesson_item_kind( $post->post_title ),
            )
        );

        if ( is_wp_error( $result ) ) {
            $results['failed'][] = array(
                'id'      => $post_id,
                'title'   => $post->post_title,
                'message' => $result->get_error_message(),
            );
            continue;
        }

        $results['success'][] = array(
            'id'        => $post_id,
            'title'     => $post->post_title,
            'lesson_id' => isset( $result['lesson_id'] ) ? (int) $result['lesson_id'] : 0,
        );
    }

    wp_send_json_success(
        array(
            'message' => sprintf(
                /* translators: 1: success count, 2: failed count */
                __( 'LMS submit finished. %1$d succeeded, %2$d failed.', 'my-lesson-editor' ),
                count( $results['success'] ),
                count( $results['failed'] )
            ),
            'results' => $results,
        )
    );
}

// AJAX handler for loading content from a specific draft (for "Generated Drafts Content" section in lesson editor)
add_action( 'wp_ajax_my_lesson_editor_load_draft_content', 'my_lesson_editor_load_draft_content_callback' );
function my_lesson_editor_load_draft_content_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) { // Using lesson_editor_nonce now
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    $post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $draft_type = isset( $_POST['draft_type'] ) ? sanitize_text_field( wp_unslash( $_POST['draft_type'] ) ) : '';

    $post = get_post( $post_id );

    if ( ! $post || $post->post_type !== 'lesson_profile' ) {
        wp_send_json_error( 'Invalid post ID or post type.' );
    }

    $content_to_return = '';
    $title_to_return   = $post->post_title;

    if ( '' === $draft_type || 'main' === $draft_type ) {
        $content_to_return = $post->post_content;
    } elseif ( 'draft1' === $draft_type ) {
        $content_to_return = get_post_meta( $post_id, 'agent_1_draft_content', true );
    } elseif ( 'draft2' === $draft_type ) {
        $content_to_return = get_post_meta( $post_id, 'agent_2_draft_content', true );
    } elseif ( 'draft3' === $draft_type ) {
        $content_to_return = get_post_meta( $post_id, 'agent_3_draft_content', true );
    } else {
        $content_to_return = $post->post_content;
    }

    wp_send_json_success( array(
        'title' => $title_to_return,
        'content' => apply_filters( 'the_content', $content_to_return ),
    ));
}

// AJAX handler for parsing uploaded book files
add_action( 'wp_ajax_my_lesson_editor_parse_book', 'my_lesson_editor_parse_book_callback' );
function my_lesson_editor_parse_book_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to upload files.' );
    }

    if ( ! isset( $_FILES['book_file'] ) || empty( $_FILES['book_file']['tmp_name'] ) ) {
        wp_send_json_error( 'No file uploaded.' );
    }

    $book_title = sanitize_text_field( $_POST['book_title'] );
    $file_path = $_FILES['book_file']['tmp_name'];
    
    global $my_lesson_editor_chapter_parser;
    $parsed_data = $my_lesson_editor_chapter_parser->parse_book_file( $file_path, $book_title );

    if ( is_wp_error( $parsed_data ) ) {
        wp_send_json_error( $parsed_data->get_error_message() );
    }

    // Create a book post
    $book_post_data = array(
        'post_title' => $book_title,
        'post_content' => 'Parsed book with ' . $parsed_data['total_chapters'] . ' chapters',
        'post_status' => 'draft',
        'post_type' => 'lesson_profile',
        'post_author' => get_current_user_id()
    );

    $book_id = wp_insert_post( $book_post_data );

    if ( is_wp_error( $book_id ) ) {
        wp_send_json_error( 'Failed to create book post.' );
    }

    // Save chapters to database
    $my_lesson_editor_chapter_parser->save_chapters_to_db( $book_id, $parsed_data['chapters'] );

    // Add book metadata
    update_post_meta( $book_id, '_book_title', $book_title );
    update_post_meta( $book_id, '_total_chapters', $parsed_data['total_chapters'] );
    update_post_meta( $book_id, '_book_type', 'parsed_book' );

    wp_send_json_success( array(
        'book_id' => $book_id,
        'book_title' => $book_title,
        'chapters' => $parsed_data['chapters'],
        'total_chapters' => $parsed_data['total_chapters']
    ));
}

// AJAX handler for AI content generation
add_action( 'wp_ajax_my_lesson_editor_generate_ai_content', 'my_lesson_editor_generate_ai_content_callback' );
function my_lesson_editor_generate_ai_content_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to generate content.' );
    }

    $chapter_id = intval( $_POST['chapter_id'] );
    $content_types = array_map( 'sanitize_text_field', $_POST['content_types'] );
    $step_data = array_map( 'sanitize_text_field', $_POST['step_data'] );

    global $my_lesson_editor_ai_generator;
    $generated_content = $my_lesson_editor_ai_generator->generate_content( $chapter_id, $content_types, $step_data );

    if ( is_wp_error( $generated_content ) ) {
        wp_send_json_error( $generated_content->get_error_message() );
    }

    // Save generated content to database
    foreach ( $content_types as $content_type ) {
        if ( isset( $generated_content[ $content_type ] ) ) {
            $my_lesson_editor_ai_generator->save_generated_content( 
                $chapter_id, 
                $content_type, 
                $generated_content[ $content_type ] 
            );
        }
    }

    wp_send_json_success( $generated_content );
}

// AJAX handler for publishing to Academy LMS
add_action( 'wp_ajax_my_lesson_editor_publish_to_academy', 'my_lesson_editor_publish_to_academy_callback' );
function my_lesson_editor_publish_to_academy_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to publish content.' );
    }

    $content_type = sanitize_text_field( $_POST['content_type'] );
    $publish_type = sanitize_text_field( $_POST['publish_type'] );
    $chapter_id = intval( $_POST['chapter_id'] );
    $include_video = isset( $_POST['include_video'] ) && $_POST['include_video'] === 'true';
    $include_slides = isset( $_POST['include_slides'] ) && $_POST['include_slides'] === 'true';

    global $my_lesson_editor_academy_lms;
    global $my_lesson_editor_ai_generator;

    // Get generated content
    $generated_content = $my_lesson_editor_ai_generator->get_generated_content( $chapter_id, $content_type );

    if ( empty( $generated_content ) ) {
        wp_send_json_error( 'No generated content found for this chapter.' );
    }

    $content_data = $generated_content[0]->content_data;
    $published_id = null;

    // Publish based on content type
    switch ( $content_type ) {
        case 'lesson_plan':
            $published_id = $my_lesson_editor_academy_lms->publish_lesson_plan( $content_data, $chapter_id );
            break;
        case 'vocabulary':
            $published_id = $my_lesson_editor_academy_lms->publish_vocabulary( $content_data, $chapter_id );
            break;
        case 'flashcards':
            $published_id = $my_lesson_editor_academy_lms->publish_flashcards( $content_data, $chapter_id );
            break;
        case 'quiz':
            $published_id = $my_lesson_editor_academy_lms->publish_quiz( $content_data, $chapter_id );
            break;
        case 'midterm':
            $published_id = $my_lesson_editor_academy_lms->publish_exam( $content_data, 'midterm', $chapter_id );
            break;
        case 'final':
            $published_id = $my_lesson_editor_academy_lms->publish_exam( $content_data, 'final', $chapter_id );
            break;
        default:
            wp_send_json_error( 'Unsupported content type.' );
    }

    if ( is_wp_error( $published_id ) ) {
        wp_send_json_error( $published_id->get_error_message() );
    }

    // Update generated content status to published
    $my_lesson_editor_ai_generator->update_content_status( $generated_content[0]->id, 'published' );

    wp_send_json_success( array(
        'published_id' => $published_id,
        'content_type' => $content_type,
        'publish_type' => $publish_type,
        'message' => 'Content published successfully to Academy LMS.'
    ));
}

// AJAX handler for getting published content
add_action( 'wp_ajax_my_lesson_editor_get_published_content', 'my_lesson_editor_get_published_content_callback' );
function my_lesson_editor_get_published_content_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'lesson_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'You do not have permission to view published content.' );
    }

    $chapter_id = intval( $_POST['chapter_id'] );

    global $my_lesson_editor_academy_lms;
    $published_content = $my_lesson_editor_academy_lms->get_published_content( $chapter_id );

    wp_send_json_success( $published_content );
}


/**
 * Shortcode to display the lesson Content Editor.
 */
/**
 * AJAX: list AcademyLM courses for the lesson editor dropdown.
 */
add_action( 'wp_ajax_mle_get_courses', 'mle_ajax_get_courses_callback' );
function mle_ajax_get_courses_callback() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ), 403 );
	}
	check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

	$client  = new MLE_AcademyLM_Client();
	$courses = $client->get_courses();
	wp_send_json_success(
		array(
			'remote'  => $client->is_remote(),
			'courses' => $courses,
		)
	);
}

/**
 * AJAX: extract text from an uploaded desktop file.
 *
 * Field name: "upload_file" (multipart/form-data).
 */
add_action( 'wp_ajax_mle_extract_text_from_upload', 'mle_ajax_extract_text_from_upload_callback' );
function mle_ajax_extract_text_from_upload_callback() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ), 403 );
	}
	check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

	if ( empty( $_FILES['upload_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['upload_file']['tmp_name'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'my-lesson-editor' ) ) );
	}

	$size = isset( $_FILES['upload_file']['size'] ) ? (int) $_FILES['upload_file']['size'] : 0;
	if ( $size <= 0 || $size > 8 * 1024 * 1024 ) {
		wp_send_json_error( array( 'message' => __( 'File must be greater than 0 and less than 8 MB.', 'my-lesson-editor' ) ) );
	}

	$name   = sanitize_file_name( wp_unslash( $_FILES['upload_file']['name'] ) );
	$client = new MLE_AcademyLM_Client();
	$text   = $client->extract_text( $_FILES['upload_file']['tmp_name'], $name );

	if ( is_wp_error( $text ) ) {
		wp_send_json_error( array( 'message' => $text->get_error_message() ) );
	}

	$preview_html = wpautop( esc_html( wp_check_invalid_utf8( (string) $text, true ) ) );
	wp_send_json_success(
		array(
			'filename' => $name,
			'size'     => $size,
			'text'     => (string) $text,
			'html'     => $preview_html,
		)
	);
}

/**
 * AJAX: submit current main editor content to AcademyLM.
 */
add_action( 'wp_ajax_mle_submit_to_academylm', 'mle_ajax_submit_to_academylm_callback' );
function mle_ajax_submit_to_academylm_callback() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'my-lesson-editor' ) ), 403 );
	}
	check_ajax_referer( 'lesson_editor_nonce', 'nonce' );

	$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$content   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
	$course_id = isset( $_POST['course_id'] ) ? sanitize_text_field( wp_unslash( $_POST['course_id'] ) ) : '';

	if ( $title === '' || $content === '' ) {
		wp_send_json_error( array( 'message' => __( 'Title and content are required.', 'my-lesson-editor' ) ) );
	}

	$client = new MLE_AcademyLM_Client();
	$result = $client->create_lesson(
		array(
			'title'             => $title,
			'content'           => $content,
			'course_id'         => $course_id,
			'lesson_profile_id' => isset( $_POST['lesson_profile_id'] ) ? (int) $_POST['lesson_profile_id'] : 0,
			'kind'              => isset( $_POST['kind'] ) ? sanitize_text_field( wp_unslash( $_POST['kind'] ) ) : mle_get_lesson_item_kind( $title ),
		)
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message'   => __( 'Lesson submitted to AcademyLM.', 'my-lesson-editor' ),
			'lesson_id' => isset( $result['lesson_id'] ) ? $result['lesson_id'] : 0,
			'remote'    => ! empty( $result['remote'] ),
		)
	);
}

function my_lesson_editor_shortcode() {
    global $my_lesson_editor_shortcode_used;
    $my_lesson_editor_shortcode_used = true;

    ob_start();

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        ?>
        <p class="text-center text-red-500">You must be logged in with sufficient permissions to access the lesson Content Editor.</p>
        <?php
        return ob_get_clean();
    }

    // Enqueue here: wp_enqueue_scripts already ran before shortcode output.
    my_lesson_editor_enqueue_frontend_assets();

    // Initialize values for form fields - These will be populated by JS on load/selection
    $lesson_name_val      = '';
    $lesson_subtitle_val  = '';
    $lesson_content_val   = '';
    $lesson_cover_id_val  = 0;
    $lesson_cover_url_val = '';
    $placement_category_val = ''; // New: Initialize for placement
    $insert_link_val    = '';
    $lesson_post_id_val   = 0;
    $lesson_category_val  = '';
    $lesson_level_val     = '';
    $associated_author_id_val = 0;
    $caast_delete_nonce = wp_create_nonce( 'caast_delete_nonce' );


    // Display status messages
    if ( isset( $_GET['status'] ) ) {
        $status_message = '';
        $status_class = 'bg-green-100 border border-green-400 text-green-700';

        switch ( $_GET['status'] ) {
            case 'lesson_published':
                $status_message = 'lesson successfully published!'; break;
            case 'lesson_saved_draft':
                $status_message = 'lesson saved as draft!'; break;
            case 'lesson_pending_review':
                $status_message = 'lesson submitted for review!'; break;
            case 'lesson_unpublished':
                $status_message = 'lesson successfully unpublished (set to draft)!'; break;
            case 'lesson_archived':
                $status_message = 'lesson successfully archived!'; break;
            case 'lesson_deleted':
                $status_message = 'lesson successfully deleted!'; break;
            case 'lesson_added':
                $status_message = 'New lesson added successfully!'; break;
            case 'lesson_published_to_placement': // New status for placement publish
                $status_message = 'lesson successfully published to selected placement!'; break;
            case 'error_empty_title':
                $status_message = 'Error: lesson name cannot be empty. Please fill the lesson name field.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_lesson_db':
                $status_message = 'An error occurred while submitting the lesson. Please try again.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_lesson_delete':
                $status_message = 'An error occurred while deleting the lesson. Please try again.';
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
            case 'error_no_lesson_selected_for_placement': // New error status
                $status_message = 'Please Select a lesson before attempting to publish to a placement.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            default:
                $status_message = 'An unknown status occurred.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700';
        }
        $notice_class = ( strpos( $status_class, 'red' ) !== false ) ? 'caast-status--error' : 'caast-status--success';
        echo '<div class="caast-status ' . esc_attr( $notice_class ) . ' relative mb-4 mx-4 lg:mx-6 mt-4 is-dismissible"><p>' . esc_html($status_message) . '</p></div>';
    }

    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $user_avatar = get_avatar_url( $current_user->ID, array( 'size' => 32 ) );

    ?>
    <div class="caast-dashboard min-h-screen">
    <header class="caast-topbar">
        <span class="caast-topbar__brand">CAASTEdu · Lesson Content Editor</span>
        <div class="caast-topbar__actions">
            <a href="https://caastedu.com/dashboard/" class="caast-topbar__link">Dashboard</a>
            <button type="button" id="mle_reset_layout" class="caast-topbar__link caast-topbar__btn" title="<?php esc_attr_e( 'Reset column widths to default', 'my-lesson-editor' ); ?>"><?php esc_html_e( 'Reset layout', 'my-lesson-editor' ); ?></button>
            <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="caast-topbar__link">Logout</a>
        </div>
    </header>

    <div class="caast-layout mle-resizable-layout" id="mle-resizable-layout">
        <input type="hidden" id="caast_delete_nonce" value="<?php echo esc_attr( $caast_delete_nonce ); ?>">
        <aside aria-label="Left admin navigation panel" class="caast-col caast-col--nav caast-sidebar hide-scrollbar">
            <div class="caast-sidebar__profile">
                <img alt="User avatar" class="caast-sidebar__avatar" src="<?php echo esc_url($user_avatar); ?>" />
                <span class="caast-sidebar__profile-name"><?php echo esc_html($user_email); ?></span>
                <span class="caast-sidebar__profile-role">Content Editor</span>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="space-y-1">
                <h2 class="caast-nav-heading">Website Content Editor</h2>
                
                <!-- Content Editor Links -->
                <div class="space-y-1 mb-4">
                    <a class="caast-nav-link" href="https://caastedu.com/author-endpoint-v2/">
                        <i class="fas fa-user"></i> 
                        <span>Author Endpoint</span>
                    </a>
                    <a class="caast-nav-link" href="https://caastedu.com/article-endpoint/">
                        <i class="fas fa-newspaper"></i> 
                        <span>Article Endpoint</span>
                    </a>
                    <a class="caast-nav-link" href="https://caastedu.com/book-endpoint/">
                        <i class="fas fa-book-open"></i> 
                        <span>Book Endpoint</span>
                    </a>
                    <a class="caast-nav-link is-active" href="https://caastedu.com/llesson-plan-editor/">
                        <i class="fas fa-file-alt"></i> 
                        <span>Lesson Endpoint</span>
                    </a>
                    <a class="caast-nav-link" href="https://caastedu.com/video-endpoint/">
                        <i class="fab fa-youtube"></i> 
                        <span>Video Endpoint</span>
                    </a>
                    <a class="caast-nav-link" href="https://caastedu.com/content-automation/">
                        <i class="fas fa-robot"></i> 
                        <span>Content Automation</span>
                    </a>
                </div>
                
                <!-- Dashboard Links -->
                <div class="border-t caast-nav-divider pt-4">
                    <h3 class="caast-nav-heading">Dashboard</h3>
                    <div class="space-y-1">
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/">
                            <i class="fas fa-th-large"></i> 
                            <span>Dashboard</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/profile/">
                            <i class="fas fa-user"></i> 
                            <span>My Profile</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/enrolled-courses/">
                            <i class="fas fa-graduation-cap"></i> 
                            <span>Enrolled Courses</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/my-lessonings/">
                            <i class="fas fa-calendar-check"></i> 
                            <span>My Tutor Sessions</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/download-certificate/">
                            <i class="fas fa-download"></i> 
                            <span>Download Certificates</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/wishlist/">
                            <i class="far fa-heart"></i> 
                            <span>Wishlist</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/reviews/">
                            <i class="far fa-star"></i> 
                            <span>Reviews</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/purchase-history/">
                            <i class="fas fa-history"></i> 
                            <span>Purchase History</span>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/my-account/">
                            <i class="fas fa-store"></i> 
                            <span>Store Dashboard</span>
                        </a>
                    </div>
                </div>
                
                <!-- Course Management Links -->
                <div class="border-t caast-nav-divider pt-4">
                    <h3 class="caast-nav-heading">Course Management</h3>
                    <div class="space-y-1">
                        <a class="caast-nav-link justify-between" href="https://caastedu.com/dashboard/courses/">
                            <span class="flex items-center gap-3"><i class="fas fa-graduation-cap"></i><span>Courses</span></span>
                            <i class="fas fa-chevron-right text-xs opacity-50"></i>
                        </a>
                        <a class="caast-nav-link justify-between" href="https://caastedu.com/dashboard/lessons/">
                            <span class="flex items-center gap-3"><i class="fas fa-file-alt"></i><span>All Lessons</span></span>
                            <i class="fas fa-chevron-right text-xs opacity-50"></i>
                        </a>
                        <a class="caast-nav-link justify-between" href="https://caastedu.com/dashboard/quizzes/">
                            <span class="flex items-center gap-3"><i class="fas fa-question-circle"></i><span>Quizzes</span></span>
                            <i class="fas fa-chevron-right text-xs opacity-50"></i>
                        </a>
                        <a class="caast-nav-link justify-between" href="https://caastedu.com/dashboard/meeting/">
                            <span class="flex items-center gap-3"><i class="fab fa-youtube"></i><span>Meetings</span></span>
                            <i class="fas fa-chevron-right text-xs opacity-50"></i>
                        </a>
                        <a class="caast-nav-link justify-between" href="https://caastedu.com/dashboard/tutor-lessoning/">
                            <span class="flex items-center gap-3"><i class="fas fa-calendar-check"></i><span>Tutor Sessions</span></span>
                            <i class="fas fa-chevron-right text-xs opacity-50"></i>
                        </a>
                        <a class="caast-nav-link justify-between" href="https://caastedu.com/dashboard/assignments/">
                            <span class="flex items-center gap-3"><i class="fas fa-tasks"></i><span>Assignments</span></span>
                            <i class="fas fa-chevron-right text-xs opacity-50"></i>
                        </a>
                        <a class="caast-nav-link justify-between" href="https://caastedu.com/dashboard/question-answer/">
                            <span class="flex items-center gap-3"><i class="fas fa-question-circle"></i><span>Q&A</span></span>
                            <i class="fas fa-chevron-right text-xs opacity-50"></i>
                        </a>
                        <a class="caast-nav-link" href="https://caastedu.com/dashboard/announcements/">
                            <i class="fas fa-bullhorn"></i> 
                            <span>Announcements</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Settings Section -->
            <div class="border-t caast-nav-divider pt-4 mt-4">
                <a href="https://caastedu.com/dashboard/settings/" class="caast-nav-link">
                    <i class="fas fa-cog"></i> 
                    <span>Settings</span>
                </a>
            </div>
        </aside>

        <div class="mle-layout-resizer" data-resize="nav" role="separator" aria-orientation="vertical" aria-label="<?php esc_attr_e( 'Resize navigation column', 'my-lesson-editor' ); ?>" tabindex="0"></div>

            <div class="caast-col caast-col--editor caast-col-pane">
                <header class="caast-pane-header">
                    <h1 class="caast-page-title">Lesson Content Editor</h1>
                    <p class="caast-page-subtitle">Select an author or lesson, manage content, and run AI automation.</p>
                </header>

                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="w-full flex flex-col flex-1" id="lesson-editor-form">
                    <input type="hidden" name="action" value="my_lesson_editor_submit">
                    <?php wp_nonce_field('lesson_content_submission', '_wpnonce_lesson_content'); ?>
                    <input type="hidden" name="lesson_post_id" id="lesson_post_id" value="<?php echo esc_attr( $lesson_post_id_val ); ?>">

                    <div class="caast-form-section space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end w-full">
                        <div class="md:col-span-2">
                            <label for="selected_author_id" class="caast-label">Select an author</label>
                            <select name="selected_author_id" id="selected_author_id" class="caast-select">
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
                            <button type="button" id="load-author-content-btn" class="caast-btn caast-btn--secondary caast-btn--block">Load author</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end w-full">
                        <div class="md:col-span-2">
                            <label for="selected_lesson_id" class="caast-label">Select a lesson</label>
                            <select name="selected_lesson_id" id="selected_lesson_id" class="caast-select">
                                <option value="">-- Select a lesson --</option>
                                <?php
                                $lessons = get_posts( array(
                                    'post_type'      => 'lesson_profile',
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'post_status'    => array('publish', 'draft', 'pending', 'archive'),
                                ) );
                                foreach ( $lessons as $lesson ) {
                                    echo '<option value="' . esc_attr( $lesson->ID ) . '">' . esc_html( $lesson->post_title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="load-lesson-content-btn" class="caast-btn caast-btn--secondary caast-btn--block">Load lesson</button>
                        </div>
                    </div>
                    </div>

                    <!-- AcademyLM Course + Source/Action Block -->
                    <div class="caast-form-section caast-form-section--muted" data-mle-uploader="lesson">
                        <h3 class="caast-form-section__title"><?php esc_html_e( 'Course & file upload', 'my-lesson-editor' ); ?></h3>
                        <p class="caast-form-section__desc"><?php esc_html_e( 'Connect to AcademyLM, upload source files, and submit to your course folder.', 'my-lesson-editor' ); ?></p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-4 w-full">
                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between mb-1">
                                    <label for="select_academylm_course" class="caast-label mb-0"><?php esc_html_e( 'Select a Course (Learning Management System)', 'my-lesson-editor' ); ?></label>
                                    <a href="https://caastedu.com/wp-admin/admin.php?page=academy-courses" target="_blank" rel="noopener noreferrer" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium ml-2 whitespace-nowrap"><?php esc_html_e( 'Course Setting', 'my-lesson-editor' ); ?> &#x2197;</a>
                                </div>
                                <select id="select_academylm_course" name="select_academylm_course" class="caast-select">
                                    <option value=""><?php esc_html_e( 'Loading courses…', 'my-lesson-editor' ); ?></option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="button" id="refresh-academylm-courses" class="caast-btn caast-btn--secondary caast-btn--block"><?php esc_html_e( 'Refresh', 'my-lesson-editor' ); ?></button>
                            </div>
                        </div>

                        <div class="flex flex-col mb-4 w-full">
                            <label for="mle_source_book_name" class="caast-label"><?php esc_html_e( 'Book Name', 'my-lesson-editor' ); ?></label>
                            <input type="text" id="mle_source_book_name" name="mle_source_book_name" placeholder="<?php esc_attr_e( 'Mirrors the AI Book Name or upload filename', 'my-lesson-editor' ); ?>" class="caast-input" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-2 w-full">
                            <button type="button" data-mle-action="upload" class="caast-btn caast-btn--secondary caast-btn--block">
                                <span class="mle-btn-label"><?php esc_html_e( 'Upload', 'my-lesson-editor' ); ?></span>
                                <span class="mle-btn-spinner hidden inline-block w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                            </button>
                            <button type="button" data-mle-action="edit" class="caast-btn caast-btn--secondary caast-btn--block">
                                <span class="mle-btn-label"><?php esc_html_e( 'Edit', 'my-lesson-editor' ); ?></span>
                                <span class="mle-btn-spinner hidden inline-block w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                            </button>
                            <button type="button" data-mle-action="submit" class="caast-btn caast-btn--primary caast-btn--block">
                                <span class="mle-btn-label"><?php esc_html_e( 'Submit', 'my-lesson-editor' ); ?></span>
                                <span class="mle-btn-spinner hidden inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                            </button>
                        </div>

                        <input type="file" data-mle-file="hidden" class="hidden" accept=".pdf,.txt,.md,.csv,.doc,.docx" />
                        <p data-mle-status class="caast-meta mt-2 min-h-[1rem]" role="status" aria-live="polite"></p>
                    </div>

                    <div class="caast-form-section space-y-4">
                    <div class="flex flex-col w-full">
                        <label for="new_lesson_name" class="caast-label">Add a lesson</label>
                        <input type="text" name="new_lesson_name" id="new_lesson_name" placeholder="Enter lesson name" class="caast-input" value="<?php echo esc_attr($lesson_name_val); ?>" />
                    </div>

                    <div class="flex flex-col w-full">
                        <label for="lesson_subtitle" class="caast-label">Lesson subtitle</label>
                        <input type="text" name="lesson_subtitle" id="lesson_subtitle" placeholder="Lesson subtitle text" class="caast-input" value="<?php echo esc_attr($lesson_subtitle_val); ?>" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end w-full">
                        <div class="md:col-span-2">
                            <label class="caast-label">Upload lesson cover image</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" id="lesson_cover_url" name="lesson_cover_url" class="caast-input flex-grow" placeholder="Browse lesson cover" readonly value="<?php echo esc_url($lesson_cover_url_val); ?>" />
                                <input type="hidden" id="lesson_cover_id" name="lesson_cover_id" value="<?php echo esc_attr($lesson_cover_id_val); ?>" />
                            </div>
                            <div id="lesson-cover-preview" class="mt-2 text-center" style="<?php echo empty($lesson_cover_url_val) ? 'display:none;' : ''; ?>">
                                <img src="<?php echo esc_url($lesson_cover_url_val); ?>" alt="lesson cover Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />
                                <button type="button" class="remove-lesson-cover text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>
                            </div>
                        </div>
                        <div class="flex items-end">
                                <button type="button" class="caast-btn caast-btn--secondary caast-btn--block browse-lesson-cover">Browse</button>
                        </div>
                    </div>

                    <div class="w-full">
                        <label for="lesson_content" class="caast-label">Body content</label>
                        <?php
                        if ( current_user_can( 'edit_posts' ) ) {
                            wp_editor( $lesson_content_val, 'lesson_content', array(
                                'textarea_name' => 'lesson_content',
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
                            echo '<textarea name="lesson_content" id="lesson_content" rows="12" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">' . esc_textarea($lesson_content_val) . '</textarea>';
                        }
                        ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                        <div>
                            <label for="lesson_category" class="caast-label">Category</label>
                            <select name="lesson_category" id="lesson_category" class="caast-select">
                                <option value="">-- Select a category --</option>
                                <?php
                                $categories = get_terms( array(
                                    'taxonomy'   => 'lesson_category',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $categories as $category ) {
                                    $selected = ( $lesson_category_val === $category->slug ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $category->slug ) . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label for="lesson_level" class="caast-label">Lesson level</label>
                            <select name="lesson_level" id="lesson_level" class="caast-select">
                                <option value="">-- Select a Level --</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 w-full pt-2 border-t border-slate-100">
                        <div class="md:col-span-2 flex flex-col">
                            <label for="submit_lesson_action_select" class="caast-label">Action</label>
                            <select name="submit_lesson_action" id="submit_lesson_action_select" class="caast-select">
                                <option value="save">Save Draft</option>
                                <option value="publish">Publish Ready</option>
                                <option value="unpublish">Unpublish</option>
                                <option value="archive">Archive</option>
                                <option value="delete">Delete</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="submit_lesson_action_btn" value="submit" class="caast-btn caast-btn--secondary caast-btn--block">Submit</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 w-full">
                        <div class="flex flex-col md:col-span-2">
                            <label class="caast-label">Placement category</label>
                            <div id="placement_category" class="caast-placement-checkboxes space-y-1">
                                <?php
                                $placements = get_terms( array(
                                    'taxonomy'   => 'lesson_placement',
                                    'hide_empty' => false,
                                ) );
                                if ( ! is_wp_error( $placements ) ) {
                                    foreach ( $placements as $placement ) {
                                        echo '<label class="caast-checkbox-row flex items-center gap-2"><input type="checkbox" class="caast-placement-checkbox" name="placement_category[]" value="' . esc_attr( $placement->slug ) . '"> <span>' . esc_html( $placement->name ) . '</span></label>';
                                    }
                                }
                                ?>
                            </div>
                            <p class="caast-meta mt-1">Tick one or more placements to publish this lesson to multiple sections at the same time.</p>
                        </div>
                        <!--<div class="flex flex-col">-->
                        <!--    <label for="insert_link" class="block text-sm font-medium text-gray-700">Insert Link</label>-->
                        <!--    <input type="url" name="insert_link" id="insert_link" placeholder="https://example.com" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_url($insert_link_val); ?>" />-->
                        <!--</div>-->
                        <div class="flex items-end">
                            <button type="submit" name="placement_action" value="publish_placement" class="caast-btn caast-btn--success caast-btn--block">Publish</button>
                        </div>
                    </div>
                    </div>
                </form>
            </div>

            <div class="mle-layout-resizer" data-resize="ai" role="separator" aria-orientation="vertical" aria-label="<?php esc_attr_e( 'Resize AI panel', 'my-lesson-editor' ); ?>" tabindex="0"></div>

            <aside class="caast-col caast-col--ai caast-col-pane hide-scrollbar space-y-4">
                <header>
                    <h2 class="caast-section-title"><?php esc_html_e( 'AI Content Generator', 'my-lesson-editor' ); ?></h2>
                    <p class="caast-meta mt-1"><?php esc_html_e( 'Choose a model, select content types, and start automation.', 'my-lesson-editor' ); ?></p>
                </header>

                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wide"><?php esc_html_e( 'Reference & context', 'my-lesson-editor' ); ?></h3>
                    <div>
                        <label for="book_title" class="caast-label"><?php esc_html_e( 'Book / textbook title', 'my-lesson-editor' ); ?></label>
                        <input type="text" id="book_title" class="caast-input form-input" placeholder="<?php esc_attr_e( 'Optional — uses lesson title if empty', 'my-lesson-editor' ); ?>">
                    </div>
                    <div>
                        <label for="ai_chapter" class="caast-label"><?php esc_html_e( 'Chapter number or name', 'my-lesson-editor' ); ?></label>
                        <input type="text" id="ai_chapter" class="caast-input form-input" placeholder="<?php esc_attr_e( 'e.g. Chapter 1 (optional)', 'my-lesson-editor' ); ?>">
                    </div>
                    <div>
                        <label for="llm_model_select" class="caast-label"><?php esc_html_e( 'AI model', 'my-lesson-editor' ); ?></label>
                        <select id="llm_model_select" class="caast-select form-input">
                            <option value="deepseek_r1" selected><?php esc_html_e( 'DeepSeek R1', 'my-lesson-editor' ); ?></option>
                            <option value="gpt_4o"><?php esc_html_e( 'Kimi K2', 'my-lesson-editor' ); ?></option>
                            <option value="claude_3_5_sonnet"><?php esc_html_e( 'Qwen 3', 'my-lesson-editor' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="grade_level" class="caast-label"><?php esc_html_e( 'Grade level', 'my-lesson-editor' ); ?></label>
                        <select id="grade_level" name="grade_level" class="caast-select form-input">
                            <option value="elementary_school"><?php esc_html_e( 'Elementary School', 'my-lesson-editor' ); ?></option>
                            <option value="middle_school" selected><?php esc_html_e( 'Middle School', 'my-lesson-editor' ); ?></option>
                            <option value="high_school"><?php esc_html_e( 'High School', 'my-lesson-editor' ); ?></option>
                            <option value="college"><?php esc_html_e( 'College', 'my-lesson-editor' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="ai_difficulty_level" class="caast-label"><?php esc_html_e( 'Difficulty level', 'my-lesson-editor' ); ?></label>
                        <select id="ai_difficulty_level" name="ai_difficulty_level" class="caast-select form-input">
                            <option value="regular" selected><?php esc_html_e( 'Regular', 'my-lesson-editor' ); ?></option>
                            <option value="honors"><?php esc_html_e( 'Honors', 'my-lesson-editor' ); ?></option>
                            <option value="preparatory"><?php esc_html_e( 'Preparatory', 'my-lesson-editor' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="book_upload" class="caast-label"><?php esc_html_e( 'Upload book file (optional)', 'my-lesson-editor' ); ?></label>
                        <input type="file" id="book_upload" name="book_upload" class="caast-input form-input file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:text-sm file:font-medium" accept=".pdf,.txt,.doc,.docx">
                        <p class="caast-meta mt-1"><?php esc_html_e( 'PDF, TXT, DOC, DOCX — max 4 MB. Not required.', 'my-lesson-editor' ); ?></p>
                    </div>
                    <div id="book_upload_status" class="caast-meta"></div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wide mb-4"><?php esc_html_e( 'Content types to generate', 'my-lesson-editor' ); ?></h3>
                    <div class="space-y-3">
                        <div class="caast-content-type-card content-type-card">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_lesson_plan" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500" value="lesson_plan" data-task-key="lesson_plan">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-slate-900 group-hover:text-blue-700"><?php esc_html_e( 'Lesson plan by chapter', 'my-lesson-editor' ); ?></span>
                                    <p class="caast-meta mt-1"><?php esc_html_e( 'Lesson plans aligned to chapters', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="caast-content-type-card content-type-card">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_vocabulary" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500" value="vocabulary" data-task-key="vocabulary">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-slate-900 group-hover:text-blue-700"><?php esc_html_e( 'Vocabulary list', 'my-lesson-editor' ); ?></span>
                                    <p class="caast-meta mt-1"><?php esc_html_e( 'Key terms per chapter', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="caast-content-type-card content-type-card">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_flashcards" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500" value="flashcards" data-task-key="flashcards">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-slate-900 group-hover:text-blue-700"><?php esc_html_e( 'Flashcards', 'my-lesson-editor' ); ?></span>
                                    <p class="caast-meta mt-1"><?php esc_html_e( 'From vocabulary', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="caast-content-type-card content-type-card">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_quiz" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500" value="quiz" data-task-key="quiz">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-slate-900 group-hover:text-blue-700"><?php esc_html_e( 'Quiz by chapter', 'my-lesson-editor' ); ?></span>
                                    <p class="caast-meta mt-1"><?php esc_html_e( 'Chapter quizzes', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="caast-content-type-card content-type-card">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_midterm" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500" value="midterm" data-task-key="midterm">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-slate-900 group-hover:text-blue-700"><?php esc_html_e( 'Midterm exam', 'my-lesson-editor' ); ?></span>
                                    <p class="caast-meta mt-1"><?php esc_html_e( 'Midterm-style assessment', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                        <div class="caast-content-type-card content-type-card">
                            <label class="flex items-start space-x-3 cursor-pointer group">
                                <input type="checkbox" id="task_final_exam" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500" value="final" data-task-key="final_exam">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-slate-900 group-hover:text-blue-700"><?php esc_html_e( 'Final exam', 'my-lesson-editor' ); ?></span>
                                    <p class="caast-meta mt-1"><?php esc_html_e( 'Final assessment', 'my-lesson-editor' ); ?></p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="caast-selection-summary mt-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-700"><?php esc_html_e( 'Selected content types', 'my-lesson-editor' ); ?></span>
                            <span id="selected-count" class="caast-selection-count">0</span>
                        </div>
                        <div id="selected-types" class="mt-2 caast-meta"></div>
                    </div>
                </div>

                <div id="chapters_list" style="display:none;"></div>
                <div id="generated_content_container" style="display:none;"></div>

                <div class="pt-4 border-t border-slate-100 space-y-3">
                    <h3 class="text-sm font-semibold text-slate-800"><?php esc_html_e( 'n8n automation', 'my-lesson-editor' ); ?></h3>
                    <p class="caast-meta"><?php esc_html_e( 'Pick content types above, then start automation — no manual prompt required.', 'my-lesson-editor' ); ?></p>
                    <button type="button" id="start_full_automation" class="caast-btn caast-btn--primary caast-btn--block">
                        <span class="mle-generate-label"><?php esc_html_e( 'Start Automation Process', 'my-lesson-editor' ); ?></span>
                    </button>
                    <div id="full_automation_status" class="caast-status caast-status--neutral text-left"></div>
                </div>

                <div class="pt-4 border-t border-slate-100 space-y-3">
                    <h3 class="text-sm font-semibold text-slate-800"><?php esc_html_e( 'Output preview', 'my-lesson-editor' ); ?></h3>
                    <label for="final_generated_title" class="caast-label"><?php esc_html_e( 'Title', 'my-lesson-editor' ); ?></label>
                    <input type="text" id="final_generated_title" class="caast-input form-input" placeholder="<?php esc_attr_e( 'Filled after automation', 'my-lesson-editor' ); ?>">
                    <label for="final_generated_content" class="caast-label"><?php esc_html_e( 'Content', 'my-lesson-editor' ); ?></label>
                    <textarea id="final_generated_content" rows="10" class="output-content-textarea caast-input w-full" placeholder="<?php esc_attr_e( 'n8n response / generated body', 'my-lesson-editor' ); ?>"></textarea>
                    <button type="button" id="upload_final_content_to_editor" class="caast-btn caast-btn--secondary caast-btn--block"><?php esc_html_e( 'Load into main editor', 'my-lesson-editor' ); ?></button>
                    <button type="button" id="save_final_content_as_draft" class="caast-btn caast-btn--secondary caast-btn--block"><?php esc_html_e( 'Save as new lesson draft', 'my-lesson-editor' ); ?></button>
                </div>

                <details class="pt-4 border-t border-slate-100">
                    <summary class="text-sm font-semibold text-slate-800 cursor-pointer"><?php esc_html_e( 'Optional: manual LLM prompt', 'my-lesson-editor' ); ?></summary>
                    <div class="mt-3 space-y-3">
                        <label for="llm_prompt_title" class="caast-label"><?php esc_html_e( 'Prompt title', 'my-lesson-editor' ); ?></label>
                        <input type="text" id="llm_prompt_title" class="caast-input form-input">
                        <label for="llm_prompt_content" class="caast-label"><?php esc_html_e( 'Prompt body', 'my-lesson-editor' ); ?></label>
                        <textarea id="llm_prompt_content" class="output-content-textarea caast-input w-full" rows="6"></textarea>
                        <button type="button" id="send_prompt_to_llm" class="caast-btn caast-btn--secondary caast-btn--block"><?php esc_html_e( 'Send prompt to LLM (separate)', 'my-lesson-editor' ); ?></button>
                        <div id="llm_response" class="response-status-message caast-status hidden text-center font-semibold"></div>
                    </div>
                </details>
            </aside>

            <div class="mle-layout-resizer" data-resize="drafts" role="separator" aria-orientation="vertical" aria-label="<?php esc_attr_e( 'Resize lesson plans column', 'my-lesson-editor' ); ?>" tabindex="0"></div>

            <aside class="caast-col caast-col--drafts caast-col-pane hide-scrollbar space-y-3">
                <h2 class="caast-section-title"><?php esc_html_e( 'Lesson Plans', 'my-lesson-editor' ); ?></h2>
                <p class="caast-meta"><?php esc_html_e( 'Browse by book title, select lessons under that book, then load or submit to LMS.', 'my-lesson-editor' ); ?></p>

                <div id="mle-lesson-tree" class="mle-lesson-tree" aria-live="polite">
                    <p class="caast-meta mle-lesson-tree-loading"><?php esc_html_e( 'Loading lesson plans…', 'my-lesson-editor' ); ?></p>
                </div>

                <div class="space-y-2 mle-lesson-tree-actions">
                    <button type="button" id="mle_load_selected_lessons" class="caast-btn caast-btn--primary caast-btn--block">
                        <?php esc_html_e( 'Load selected into editor', 'my-lesson-editor' ); ?>
                    </button>
                    <button type="button" id="mle_submit_selected_lms" class="caast-btn caast-btn--secondary caast-btn--block">
                        <?php esc_html_e( 'Submit selected to LMS', 'my-lesson-editor' ); ?>
                    </button>
                    <p id="mle_lesson_tree_status" class="caast-meta hidden" aria-live="polite"></p>
                </div>

                <select id="mle_select_title_ce" class="hidden" aria-hidden="true" tabindex="-1">
                    <option value="">-- Select Title --</option>
                    <?php
                    foreach ( my_lesson_editor_query_draft_list_posts() as $lesson_post ) {
                        printf(
                            '<option value="%1$d">%2$s (%3$s)</option>',
                            (int) $lesson_post->ID,
                            esc_html( $lesson_post->post_title ),
                            esc_html( $lesson_post->post_status )
                        );
                    }
                    ?>
                </select>
                <div class="hidden" id="mle_draft_selection_container">
                    <label for="mle_select_draft_ce" class="caast-label">Select draft</label>
                    <select id="mle_select_draft_ce" class="caast-select">
                        <option value="">-- Select Draft --</option>
                        <option value="main"><?php esc_html_e( 'Main lesson body', 'my-lesson-editor' ); ?></option>
                        <option value="draft1">Draft 1 (Agent 1)</option>
                        <option value="draft2">Draft 2 (Agent 2)</option>
                        <option value="draft3">Draft 3 (Agent 3)</option>
                    </select>
                </div>
                    <button id="load_content_to_editor_from_draft" type="button" class="caast-btn caast-btn--primary caast-btn--block mle-visually-hidden" aria-hidden="true" tabindex="-1"><?php esc_html_e( 'Load content to editor', 'my-lesson-editor' ); ?></button>
            </aside>
    </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'my_lesson_editor', 'my_lesson_editor_shortcode' );

// Add rewrite rules
function my_lesson_editor_rewrite_rule() {
    add_rewrite_rule(
        '^lesson-profile/([0-9]+)/?$',
        'index.php?post_type=lesson_profile&p=$matches[1]',
        'top'
    );
}
add_action( 'init', 'my_lesson_editor_rewrite_rule' );

// Flush rewrite rules on plugin activation
function my_lesson_editor_activate() {
    my_lesson_editor_register_cpt();
    my_lesson_editor_register_taxonomies();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'my_lesson_editor_activate' );

// Flush rewrite rules on plugin deactivation
function my_lesson_editor_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'my_lesson_editor_deactivate' );

// ================= DOWNLOAD HANDLER FOR LESSON PLAN/VOCABULARY ===============
// (letakkan setelah semua WP AJAX handler)
add_action('wp_ajax_mle_download_item', 'mle_handle_download_item'); // Hanya untuk user login, bukan nopriv!

function mle_handle_download_item() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 'Unauthorized', ['response' => 403]);
    }
    $item_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $type    = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $nonce   = isset($_POST['nonce']) ? $_POST['nonce'] : '';

    if (!$item_id || !$nonce) {
        wp_die('Invalid request', 'Bad Request', ['response' => 400]);
    }
    if (!wp_verify_nonce($nonce, 'mle_download_' . $item_id)) {
        wp_die('Nonce check failed', 'Forbidden', ['response' => 403]);
    }

    $post = get_post($item_id);
    if (!$post || !in_array($post->post_type, ['lesson_profile', 'vocabulary'])) {
        wp_die('Item not found', 'Not Found', ['response' => 404]);
    }

    $title   = $post->post_title;
    $content = apply_filters('the_content', $post->post_content);
    $author  = get_the_author_meta('display_name', $post->post_author);
    $date    = get_the_date('Y-m-d', $post);
    $label   = $post->post_type === 'vocabulary' ? 'vocabulary' : 'lesson-plan';

    $filename = $label . '-' . sanitize_title($title) . '.pdf';
    $use_pdf = class_exists('Dompdf\\Dompdf');
    if ($use_pdf) {
        // --- PDF GENERATION: Dompdf ---
        if (!class_exists('Dompdf\\Dompdf')) {
            if (file_exists(MY_LESSON_EDITOR_PLUGIN_DIR . 'vendor/autoload.php')) {
                require_once(MY_LESSON_EDITOR_PLUGIN_DIR . 'vendor/autoload.php');
            }
        }
        $dompdf = new Dompdf\Dompdf();
        $html = '<h2>' . esc_html($title) . '</h2>'
              . '<p><b>Type:</b> ' . esc_html($label) . '</p>'
              . '<p><b>Author:</b> ' . esc_html($author) . '</p>'
              . '<p><b>Date:</b> ' . esc_html($date) . '</p><hr>'
              . $content;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    } else {
        // --- TXT fallback ---
        $filename = $label . '-' . sanitize_title($title) . '.txt';
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $title . "\n" .
             "Type: $label\n" .
             "Author: $author\n" .
             "Date: $date\n\n" .
             strip_tags($content);
        exit;
    }
}