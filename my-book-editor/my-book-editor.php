<?php
/*
Plugin Name: My Book Editor
Plugin URI:  https://example.com/
Description: A custom editor for book profiles, accessible via shortcode, with custom category and level dropdowns, and author selection.
Version:     1.4.1
Author:      Muhamad Fikri Haikal
Author URI:  https://caastedu.com/
License:     GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global flag to indicate if the shortcode is used on the current page
global $my_book_editor_shortcode_used;
$my_book_editor_shortcode_used = false;

// Define plugin constants
if ( ! defined( 'MY_BOOK_EDITOR_VERSION' ) ) {
    define( 'MY_BOOK_EDITOR_VERSION', '1.4.1' );
}
if ( ! defined( 'MY_BOOK_EDITOR_PLUGIN_URL' ) ) {
    define( 'MY_BOOK_EDITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MY_BOOK_EDITOR_PLUGIN_DIR' ) ) {
    define( 'MY_BOOK_EDITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Define Webhook URLs for individual agent testing (if still used)
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_R1', 'https://n8n.srv835792.hstgr.cloud/webhook/98c6e601-69c0-4768-bb56-bc011e802259' );
}
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_V3' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_DEEPSEEK_V3', 'https://n8n.srv835792.hstgr.cloud/webhook/98c6e601-69c0-4768-bb56-bc011e802259' );
}
if ( ! defined( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_QWEN_2_5' ) ) {
    define( 'MY_AUTHOR_EDITOR_WEBHOOK_URL_QWEN_2_5', 'https://n8n.srv835792.hstgr.cloud/webhook/100d1001-6584-4650-9756-79ee12199af3' );
}

// n8n main webhook — same default as my-author-editor v3 (override in wp-config if needed).
if ( ! defined( 'MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL' ) ) {
    define(
        'MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL',
        ( defined( 'MAE_V3_N8N_MAIN_WEBHOOK_URL' ) && is_string( MAE_V3_N8N_MAIN_WEBHOOK_URL ) && MAE_V3_N8N_MAIN_WEBHOOK_URL )
            ? MAE_V3_N8N_MAIN_WEBHOOK_URL
            : 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391'
    );
}

/**
 * Numeric id for n8n memory (aligned with author v3: logged-in user id or cookie).
 *
 * @return int
 */
if ( ! function_exists( 'my_book_editor_get_memory_id' ) ) {
function my_book_editor_get_memory_id() {
    if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
        $uid = (int) get_current_user_id();
        if ( $uid > 0 ) {
            return $uid;
        }
    }
    $cookie_name = 'my_book_editor_session_id';
    if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
        $existing = (int) preg_replace( '/\D+/', '', (string) wp_unslash( $_COOKIE[ $cookie_name ] ) );
        if ( $existing > 0 ) {
            return $existing;
        }
    }
    $new_id = random_int( 1000000000, 9999999999 );
    setcookie( $cookie_name, (string) $new_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
    $_COOKIE[ $cookie_name ] = (string) $new_id;
    return $new_id;
}
}

/**
 * Active n8n URL for book automation (book constant wins; else author v3 constant; else hardcoded default).
 *
 * @return string
 */
if ( ! function_exists( 'my_book_editor_get_n8n_main_webhook_url' ) ) {
function my_book_editor_get_n8n_main_webhook_url() {
    if ( defined( 'MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL' ) && MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL && 'YOUR_N8N_MAIN_WEBHOOK_URL_HERE' !== MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL ) {
        return MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL;
    }
    if ( defined( 'MAE_V3_N8N_MAIN_WEBHOOK_URL' ) && MAE_V3_N8N_MAIN_WEBHOOK_URL && 'YOUR_N8N_MAIN_WEBHOOK_URL_HERE' !== MAE_V3_N8N_MAIN_WEBHOOK_URL ) {
        return MAE_V3_N8N_MAIN_WEBHOOK_URL;
    }
    return 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391';
}
}

/**
 * Placeholder replacement for book intro master prompt (same tokens as author v3).
 *
 * @param string $template         Master prompt template.
 * @param string $book_name        Book title.
 * @param string $author_name      Author or N/A.
 * @param string $difficulty_level regular|preparatory.
 * @return string
 */
if ( ! function_exists( 'my_book_editor_replace_prompt_placeholders' ) ) {
function my_book_editor_replace_prompt_placeholders( $template, $book_name, $author_name, $difficulty_level ) {
    if ( ! is_string( $template ) ) {
        return '';
    }
    return strtr(
        $template,
        array(
            '{{book_name}}'        => $book_name,
            '{{author_name}}'      => $author_name,
            '{{difficulty_level}}' => $difficulty_level,
        )
    );
}
}

/**
 * Build selected_tasks for n8n: single book_introduction with resolved_prompt (aligned with mae_v3_resolve_tasks_for_n8n).
 *
 * @param string $book_name        Sanitized book name.
 * @param string $author_name      Sanitized author name.
 * @param string $difficulty_level regular|preparatory.
 * @return array<int, array<string, mixed>>
 */
if ( ! function_exists( 'my_book_editor_build_book_intro_tasks_for_n8n' ) ) {
function my_book_editor_build_book_intro_tasks_for_n8n( $book_name, $author_name, $difficulty_level ) {
    $tasks_raw = array( array( 'id' => 'book_introduction' ) );
    if ( function_exists( 'mae_v3_sanitize_task_list' ) && function_exists( 'mae_v3_resolve_tasks_for_n8n' ) ) {
        $sanitized = mae_v3_sanitize_task_list( $tasks_raw );
        if ( ! empty( $sanitized ) ) {
            return mae_v3_resolve_tasks_for_n8n( $sanitized, $book_name, $author_name, $difficulty_level );
        }
    }
    $master = get_option( 'mae_v3_book_intro_master_prompt', '' );
    $master = is_string( $master ) ? $master : '';
    $resolved_prompt = my_book_editor_replace_prompt_placeholders( $master, $book_name, $author_name, $difficulty_level );
    return array(
        array(
            'id'              => 'book_introduction',
            'resolved_prompt' => $resolved_prompt,
        ),
    );
}
}

/**
 * Register Custom Post Type for Book Content
 */
if ( ! function_exists( 'my_book_editor_register_cpt' ) ) {
function my_book_editor_register_cpt() {
    $labels = array(
        'name'          => _x( 'Books', 'Post Type General Name', 'my-book-editor' ),
        'singular_name' => _x( 'Book', 'Post Type Singular Name', 'my-book-editor' ),
        'menu_name'     => __( 'Books', 'my-book-editor' ),
        'all_items'     => __( 'All Books', 'my-book-editor' ),
        'add_new_item'  => __( 'Add New Book', 'my-book-editor' ),
        'add_new'       => __( 'Add New', 'my-book-editor' ),
        'new_item'      => __( 'New Book', 'my-book-editor' ),
        'edit_item'     => __( 'Edit Book', 'my-book-editor' ),
        'update_item'   => __( 'Update Book', 'my-book-editor' ),
        'view_item'     => __( 'View Book', 'my-book-editor' ),
        'search_items'  => __( 'Search Books', 'my-book-editor' ),
        'not_found'     => __( 'Not Found', 'my-book-editor' ),
        'not_found_in_trash' => __( 'Not found in Trash', 'my-book-editor' ),
    );
    $args = array(
        'label'               => __( 'Book', 'my-book-editor' ),
        'description'         => __( 'Content associated with books', 'my-book-editor' ),
        'labels'              => $labels,
        'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail' ),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-book',
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'rewrite'             => array( 'slug' => 'book-profile' ),
        'query_var'           => true,
    );
    register_post_type( 'book_profile', $args );
}
}
add_action( 'init', 'my_book_editor_register_cpt' );

/**
 * Register custom taxonomies for Books (Category and Level)
 */
if ( ! function_exists( 'my_book_editor_register_taxonomies' ) ) {
function my_book_editor_register_taxonomies() {
    // Category Taxonomy
    $category_labels = array(
        'name'              => _x( 'Book Categories', 'taxonomy general name', 'my-book-editor' ),
        'singular_name'     => _x( 'Book Category', 'taxonomy singular name', 'my-book-editor' ),
        'search_items'      => __( 'Search Book Categories', 'my-book-editor' ),
        'all_items'         => __( 'All Book Categories', 'my-book-editor' ),
        'parent_item'       => __( 'Parent Book Category', 'my-book-editor' ),
        'parent_item_colon' => __( 'Parent Book Category:', 'my-book-editor' ),
        'edit_item'         => __( 'Edit Book Category', 'my-book-editor' ),
        'update_item'       => __( 'Update Book Category', 'my-book-editor' ),
        'add_new_item'      => __( 'Add New Book Category', 'my-book-editor' ),
        'new_item_name'     => __( 'New Book Category Name', 'my-book-editor' ),
        'menu_name'         => __( 'Categories', 'my-book-editor' ),
    );
    $category_args = array(
        'hierarchical'      => true,
        'labels'            => $category_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'book-category' ),
    );
    register_taxonomy( 'book_category', array( 'book_profile' ), $category_args );

    // Level Taxonomy
    $level_labels = array(
        'name'              => _x( 'Book Levels', 'taxonomy general name', 'my-book-editor' ),
        'singular_name'     => _x( 'Book Level', 'taxonomy singular name', 'my-book-editor' ),
        'search_items'      => __( 'Search Book Levels', 'my-book-editor' ),
        'all_items'         => __( 'All Book Levels', 'my-book-editor' ),
        'edit_item'         => __( 'Edit Book Level', 'my-book-editor' ),
        'update_item'       => __( 'Update Book Level', 'my-book-editor' ),
        'add_new_item'      => __( 'Add New Book Level', 'my-book-editor' ),
        'new_item_name'     => __( 'New Book Level Name', 'my-book-editor' ),
        'menu_name'         => __( 'Levels', 'my-book-editor' ),
    );
    $level_args = array(
        'hierarchical'      => false,
        'labels'            => $level_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'book-level' ),
    );
    register_taxonomy( 'book_level', array( 'book_profile' ), $level_args );

    // Placement Category Taxonomy (New)
    $placement_labels = array(
        'name'                       => _x( 'Placement Categories', 'Taxonomy General Name', 'my-book-editor' ),
        'singular_name'              => _x( 'Placement Category', 'Taxonomy Singular Name', 'my-book-editor' ),
        'menu_name'                  => __( 'Placement', 'my-book-editor' ),
        'all_items'                  => __( 'All Placements', 'my-book-editor' ),
        'parent_item'                => __( 'Parent Placement', 'my-book-editor' ),
        'parent_item_colon'          => __( 'Parent Placement:', 'my-book-editor' ),
        'new_item_name'              => __( 'New Placement Name', 'my-book-editor' ),
        'add_new_item'               => __( 'Add New Placement', 'my-book-editor' ),
        'edit_item'                  => __( 'Edit Placement', 'my-book-editor' ),
        'update_item'                => __( 'Update Placement', 'my-book-editor' ),
        'view_item'                  => __( 'View Placement', 'my-book-editor' ),
        'separate_items_with_commas' => __( 'Separate placements with commas', 'my-book-editor' ),
        'add_or_remove_items'        => __( 'Add or remove placements', 'my-book-editor' ),
        'choose_from_most_used'      => __( 'Choose from the most used placements', 'my-book-editor' ),
        'popular_items'              => __( 'Popular Placements', 'my-book-editor' ),
        'search_items'               => __( 'Search Placements', 'my-book-editor' ),
        'not_found'                  => __( 'No Placements Found', 'my-book-editor' ),
        'no_terms'                   => __( 'No placements', 'my-book-editor' ),
        'items_list'                 => __( 'Placements list', 'my-book-editor' ),
        'items_list_navigation'      => __( 'Placements list navigation', 'my-book-editor' ),
    );
    $placement_args = array(
        'labels'                     => $placement_labels,
        'hierarchical'               => true, // Can be true or false depending on your need
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => false,
        'rewrite'                    => array( 'slug' => 'book-placement' ),
        'show_in_rest'               => true,
    );
    register_taxonomy( 'book_placement', array( 'book_profile' ), $placement_args );
}
}
add_action( 'init', 'my_book_editor_register_taxonomies' );

/**
 * Add default terms for Book Categories and Levels on plugin activation.
 */
if ( ! function_exists( 'my_book_editor_add_default_terms' ) ) {
function my_book_editor_add_default_terms() {
    $categories = array(
        'Fiction',
        'Non-fiction',
        'Language Arts',
        'STEM',
        'Tests & Assessments',
        'Arts & Design'
    );
    foreach ( $categories as $category_name ) {
        if ( ! term_exists( $category_name, 'book_category' ) ) {
            wp_insert_term( $category_name, 'book_category' );
        }
    }

    $levels = array(
        'Elementary',
        'Intermediate',
        'Advanced',
        'College'
    );
    foreach ( $levels as $level_name ) {
        if ( ! term_exists( $level_name, 'book_level' ) ) {
            wp_insert_term( $level_name, 'book_level' );
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
        'Book Right',
        'Book Footer',
        'Video Left',
        'Video Right',
        'Video Footer'
    );
    foreach ( $placements as $placement_name ) {
        if ( ! term_exists( $placement_name, 'book_placement' ) ) {
            wp_insert_term( $placement_name, 'book_placement' );
        }
    }
}
}
register_activation_hook( __FILE__, 'my_book_editor_add_default_terms' );


/**
 * Enqueue styles and scripts for the book editor.
 * This will only run if the shortcode is detected on the current page.
 */
if ( ! function_exists( 'my_book_editor_enqueue_assets' ) ) {
function my_book_editor_enqueue_assets() {
    global $my_book_editor_shortcode_used;

    if ( $my_book_editor_shortcode_used ) {
        wp_enqueue_style( 'my-book-editor-tailwind', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), null );

        if ( defined( 'MY_LESSON_EDITOR_VERSION' ) && defined( 'MY_LESSON_EDITOR_PLUGIN_URL' ) && ! wp_style_is( 'my-lesson-editor-style', 'enqueued' ) ) {
            wp_enqueue_style(
                'my-book-editor-lesson-mirror',
                MY_LESSON_EDITOR_PLUGIN_URL . 'css/my-lesson-editor.css',
                array(),
                MY_LESSON_EDITOR_VERSION
            );
        }

        wp_enqueue_media(); // For media uploader

        // Enqueue TinyMCE and other editor-related scripts (only if current user can use editor)
        if ( current_user_can( 'edit_posts' ) ) {
            wp_enqueue_script( 'wp-tinymce' );
            wp_enqueue_script( 'editor' );
            wp_enqueue_script( 'wplink' );
            wp_enqueue_script( 'wp-plupload' );
            wp_enqueue_script( 'thickbox' );
            wp_enqueue_style( 'thickbox' );
        }

        // jQuery as explicit dependency; script runs in no-conflict mode via passed `$`.
        wp_enqueue_script(
            'my-book-editor-js',
            MY_BOOK_EDITOR_PLUGIN_URL . 'js/my-book-editor.js',
            array( 'jquery' ),
            MY_BOOK_EDITOR_VERSION,
            true
        );
        wp_enqueue_script(
            'my-book-editor-ai-js',
            MY_BOOK_EDITOR_PLUGIN_URL . 'js/my-book-editor-ai.js',
            array( 'jquery', 'my-book-editor-js' ),
            MY_BOOK_EDITOR_VERSION,
            true
        );

        wp_enqueue_style(
            'my-book-editor-style',
            MY_BOOK_EDITOR_PLUGIN_URL . 'css/my-book-editor.css',
            array( 'my-book-editor-tailwind' ),
            MY_BOOK_EDITOR_VERSION
        );

        wp_add_inline_style(
            'my-book-editor-style',
            '#book-editor-form, form#book-editor-form { max-width: none !important; width: 100% !important; margin: 0 !important; padding: 0 !important; background: transparent !important; box-shadow: none !important; border-radius: 0 !important; }'
        );

        $book_nonce = wp_create_nonce( 'book_editor_nonce' );
        wp_localize_script(
            'my-book-editor-js',
            'myBookEditorAjax',
            array(
                'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
                'nonce'                   => $book_nonce,
                'alert_no_book_selected'  => __( 'Please select a book first.', 'my-book-editor' ),
                'alert_no_author_selected' => __( 'Please select an author first.', 'my-book-editor' ),
                'alert_confirm_delete'    => __( 'Are you sure you want to delete this book? This action cannot be undone.', 'my-book-editor' ),
                'alert_content_not_found' => __( 'No content found for the selected book.', 'my-book-editor' ),
                'n8n_webhook_url'         => defined( 'MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL' ) ? MY_BOOK_EDITOR_N8N_MAIN_WEBHOOK_URL : '',
            )
        );

        // Book endpoint automation: isolated localize object to reduce global collisions with Author/Lesson scripts.
        wp_localize_script(
            'my-book-editor-ai-js',
            'book_v3_data',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => $book_nonce,
            )
        );
    }
}
}
add_action( 'wp_enqueue_scripts', 'my_book_editor_enqueue_assets' );



/**
 * Handle form submission for Book Content Editor.
 */
if ( ! function_exists( 'my_book_editor_handle_submission' ) ) {
function my_book_editor_handle_submission() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'my_book_editor_submit' ) {
        return;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
        exit;
    }

    // Verify nonce
    if ( ! isset( $_POST['_wpnonce_book_content'] ) || ! wp_verify_nonce( $_POST['_wpnonce_book_content'], 'book_content_submission' ) ) {
        wp_die( 'Security check failed. Please refresh the page and try again.' );
    }

    // Sanitize and retrieve form data
    $selected_book_id   = isset( $_POST['selected_book_id'] ) ? intval( $_POST['selected_book_id'] ) : 0;
    $new_book_name      = sanitize_text_field( $_POST['new_book_name'] );
    $book_subtitle      = sanitize_text_field( $_POST['book_subtitle'] );
    $book_content       = wp_kses_post( $_POST['book_content'] );
    $book_cover_id      = isset( $_POST['book_cover_id'] ) ? intval( $_POST['book_cover_id'] ) : 0;
    $placement_category = isset( $_POST['placement_category'] ) ? sanitize_text_field( $_POST['placement_category'] ) : ''; // New: Placement Category
    $insert_link        = isset( $_POST['insert_link'] ) ? esc_url_raw( $_POST['insert_link'] ) : '';

    // Get selected taxonomy terms (slugs)
    $book_category_slug = isset( $_POST['book_category'] ) ? sanitize_text_field( $_POST['book_category'] ) : '';
    $book_level_slug    = isset( $_POST['book_level'] ) ? sanitize_text_field( $_POST['book_level'] ) : '';
    $selected_author_id = isset( $_POST['selected_author_id'] ) ? intval( $_POST['selected_author_id'] ) : 0;

    $submit_action      = sanitize_text_field( $_POST['submit_book_action'] ); // Existing action for Save/Publish/etc.
    $placement_action   = sanitize_text_field( $_POST['placement_action'] ); // New: Action for Placement Submit

    // Determine the book title to use for the post
    $post_title = '';
    if ( ! empty( $new_book_name ) ) {
        $post_title = $new_book_name;
    } elseif ( $selected_book_id > 0 ) {
        $existing_book = get_post( $selected_book_id );
        if ( $existing_book ) {
            $post_title = $existing_book->post_title;
        }
    }

    $existing_post_id = isset( $_POST['book_post_id'] ) ? intval( $_POST['book_post_id'] ) : 0;

    // Basic validation for new book or updating existing book if title changed
    if ( empty( $post_title ) && empty($existing_post_id) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_empty_title', wp_get_referer() ) );
        exit;
    }

    // Determine post status based on button clicked
    $post_status = 'pending';
    $redirect_status = 'error';

    if ( $submit_action === 'publish' ) {
        if ( current_user_can( 'publish_book_profiles' ) || current_user_can( 'publish_posts' ) ) {
            $post_status = 'publish';
            $redirect_status = 'book_published';
        } else {
            $post_status = 'pending';
            $redirect_status = 'book_pending_review';
        }
    } elseif ( $submit_action === 'save' ) {
        $post_status = 'draft';
        $redirect_status = 'book_saved_draft';
    } elseif ( $submit_action === 'unpublish' ) {
        $post_status = 'draft';
        $redirect_status = 'book_unpublished';
    } elseif ( $submit_action === 'archive' ) {
        if ( get_post_status_object('archive') ) {
             $post_status = 'archive';
        } else {
            $post_status = 'draft';
        }
        $redirect_status = 'book_archived';
    } elseif ( $submit_action === 'delete' ) {
        if ( $existing_post_id ) {
            if ( current_user_can( 'delete_book_profiles' ) || current_user_can( 'delete_posts' ) ) {
                if ( wp_delete_post( $existing_post_id, true ) ) {
                    wp_safe_redirect( add_query_arg( 'status', 'book_deleted', wp_get_referer() ) );
                    exit;
                } else {
                    error_log( 'Error deleting book post: ' . $existing_post_id );
                    wp_safe_redirect( add_query_arg( 'status', 'error_book_delete', wp_get_referer() ) );
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
    } elseif ( $placement_action === 'publish_placement' ) { // Handle new placement publish
        // Ensure the post exists and the user has permission to publish
        if ($existing_post_id > 0) {
            if ( current_user_can( 'publish_book_profiles' ) || current_user_can( 'publish_posts' ) ) {
                // Update post status to publish if not already
                if (get_post_status($existing_post_id) !== 'publish') {
                    wp_update_post(array(
                        'ID'          => $existing_post_id,
                        'post_status' => 'publish',
                    ));
                }
                // Set the placement taxonomy
                if (!empty($placement_category)) {
                    wp_set_post_terms( $existing_post_id, $placement_category, 'book_placement' );
                }
                $redirect_status = 'book_published_to_placement';
                $post_id = $existing_post_id;
            } else {
                wp_safe_redirect( add_query_arg( 'status', 'error_permissions', wp_get_referer() ) );
                exit;
            }
        } else {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_book_selected_for_placement', wp_get_referer() ) );
            exit;
        }
    }


    // Prepare post data for save/update if not already handled by delete/placement publish
    $post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $book_content,
        'post_status'   => $post_status,
        'post_type'     => 'book_profile',
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
        error_log( 'Error creating/updating book post: ' . $result->get_error_message() );
        wp_safe_redirect( add_query_arg( 'status', 'error_book_db', wp_get_referer() ) );
    } elseif ( $result === 0 ) {
        error_log( 'Failed to create/update book post, wp_insert_post/wp_update_post returned 0.' );
        wp_safe_redirect( add_query_arg( 'status', 'error_book_db', wp_get_referer() ) );
    } else {
        // Save book subtitle as post meta
        update_post_meta( $result, '_book_subtitle', $book_subtitle );

        // Set featured image (Book Cover)
        if ( ! empty( $book_cover_id ) ) {
            set_post_thumbnail( $result, $book_cover_id );
        } else {
            delete_post_thumbnail( $result ); // Remove if ID is 0 or empty
        }

        // Save new insert_link as post meta (footer_position is now placement_category)
        update_post_meta( $result, '_insert_link', $insert_link );
        // Set the placement taxonomy directly
        if ( ! empty( $placement_category ) ) {
            wp_set_post_terms( $result, $placement_category, 'book_placement' );
        } else {
            wp_set_post_terms( $result, null, 'book_placement' ); // Clear if no placement selected
        }


        // Save selected Author ID as post meta for the book
        update_post_meta( $result, '_book_author_id', $selected_author_id );

        // Set taxonomies
        if ( ! empty( $book_category_slug ) ) {
            wp_set_post_terms( $result, $book_category_slug, 'book_category' );
        } else {
            wp_set_post_terms( $result, null, 'book_category' ); // Clear if no category selected
        }

        if ( ! empty( $book_level_slug ) ) {
            wp_set_post_terms( $result, $book_level_slug, 'book_level' );
        } else {
            wp_set_post_terms( $result, null, 'book_level' ); // Clear if no level selected
        }

        wp_safe_redirect( add_query_arg( 'status', $redirect_status, wp_get_referer() ) );
    }
    exit;
}
}
add_action( 'admin_post_my_book_editor_submit', 'my_book_editor_handle_submission' );
add_action( 'admin_post_nopriv_my_book_editor_submit', 'my_book_editor_handle_submission' );

/**
 * AJAX handler to get book content based on book ID.
 */
add_action( 'wp_ajax_my_book_editor_get_book_content', 'my_book_editor_get_book_content_ajax' );
if ( ! function_exists( 'my_book_editor_get_book_content_ajax' ) ) {
function my_book_editor_get_book_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-book-editor' ) ) );
    }

    check_ajax_referer( 'book_editor_nonce', 'nonce' );

    $book_id = isset( $_POST['book_id'] ) ? intval( $_POST['book_id'] ) : 0;

    if ( $book_id ) {
        $args = array(
            'p'              => $book_id,
            'post_type'      => 'book_profile',
            'post_status'    => array( 'publish', 'pending', 'draft', 'archive' ),
            'posts_per_page' => 1,
        );
        $book_query = new WP_Query( $args );

        if ( $book_query->have_posts() ) {
            $book_post = $book_query->posts[0];

            $current_categories = wp_get_post_terms( $book_post->ID, 'book_category', array( 'fields' => 'slugs' ) );
            $current_levels     = wp_get_post_terms( $book_post->ID, 'book_level', array( 'fields' => 'slugs' ) );
            $current_placement  = wp_get_post_terms( $book_post->ID, 'book_placement', array( 'fields' => 'slugs' ) ); // New: Get placement

            $associated_author_id = get_post_meta( $book_post->ID, '_book_author_id', true );

            // Ensure book cover URL is fetched correctly
            $book_cover_id = get_post_thumbnail_id( $book_post->ID );
            $book_cover_url = $book_cover_id ? wp_get_attachment_url( $book_cover_id ) : '';


            wp_send_json_success( array(
                'book_post_id'    => $book_post->ID,
                'book_name'       => esc_html( $book_post->post_title ),
                'book_subtitle'   => esc_html( get_post_meta( $book_post->ID, '_book_subtitle', true ) ),
                'book_content'    => $book_post->post_content,
                'book_cover_id'   => $book_cover_id,
                'book_cover_url'  => $book_cover_url,
                'placement_category' => !empty($current_placement) ? $current_placement[0] : '', // New: Send placement
                'insert_link'     => esc_url( get_post_meta( $book_post->ID, '_insert_link', true ) ),
                'book_categories' => !empty($current_categories) ? $current_categories[0] : '',
                'book_levels'     => !empty($current_levels) ? $current_levels[0] : '',
                'associated_author_id' => intval($associated_author_id),
            ) );
        } else {
            wp_send_json_success( array(
                'book_post_id'    => 0,
                'book_name'       => '',
                'book_subtitle'   => '',
                'book_content'    => '',
                'book_cover_id'   => 0,
                'book_cover_url'  => '',
                'placement_category' => '', // New: Clear placement
                'insert_link'     => '',
                'book_categories' => '',
                'book_levels'     => '',
                'associated_author_id' => 0,
                'message'         => 'No existing content found for this book. You can create a new one.',
            ) );
        }
    } else {
        wp_send_json_success( array(
            'book_post_id'    => 0,
            'book_name'       => '',
            'book_subtitle'   => '',
            'book_content'    => '',
            'book_cover_id'   => 0,
            'book_cover_url'  => '',
            'placement_category' => '', // New: Clear placement
            'insert_link'     => '',
            'book_categories' => '',
            'book_levels'     => '',
            'associated_author_id' => 0,
            'message'         => __( 'Invalid book ID, or no book selected.', 'my-book-editor' )
        ) );
    }
}
}

/**
 * AJAX handler to get author content based on author ID.
 */
add_action( 'wp_ajax_my_book_editor_get_author_content', 'my_book_editor_get_author_content_ajax' );
if ( ! function_exists( 'my_book_editor_get_author_content_ajax' ) ) {
function my_book_editor_get_author_content_ajax() {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to view this content.', 'my-book-editor' ) ) );
    }

    check_ajax_referer( 'book_editor_nonce', 'nonce' );

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
            wp_send_json_error( array( 'message' => __( 'Author not found.', 'my-book-editor' ) ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'No author selected.', 'my-book-editor' ) ) );
    }
}
}

// AJAX handler for full automation process (for book editor) — JSON book name → n8n (prompts in n8n/backend).
add_action( 'wp_ajax_my_book_editor_full_automation', 'my_book_editor_full_automation_callback' );
if ( ! function_exists( 'my_book_editor_full_automation_callback' ) ) {
function my_book_editor_full_automation_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'book_editor_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    $raw_payload = isset( $_POST['automation_payload'] ) ? wp_unslash( $_POST['automation_payload'] ) : '';
    $parsed      = json_decode( $raw_payload, true );

    if ( is_array( $parsed ) ) {
        $book_name = isset( $parsed['book_name'] ) ? sanitize_text_field( $parsed['book_name'] ) : '';
        $author_in = isset( $parsed['author_name'] ) ? sanitize_text_field( $parsed['author_name'] ) : 'N/A';
    } else {
        $book_name = isset( $_POST['book_name'] ) ? sanitize_text_field( wp_unslash( $_POST['book_name'] ) ) : '';
        $author_in = 'N/A';
    }

    if ( '' === $book_name ) {
        wp_send_json_error( 'Please enter a book name.' );
    }

    $allowed_models = array( 'deepseek_r1', 'gpt_4o', 'claude_3_5_sonnet' );
    $selected_model  = 'deepseek_r1';
    if ( is_array( $parsed ) ) {
        if ( isset( $parsed['model'] ) ) {
            $selected_model = sanitize_text_field( $parsed['model'] );
        } elseif ( isset( $parsed['selected_model'] ) ) {
            $selected_model = sanitize_text_field( $parsed['selected_model'] );
        }
    } elseif ( isset( $_POST['selected_model'] ) ) {
        $selected_model = sanitize_text_field( wp_unslash( $_POST['selected_model'] ) );
    } elseif ( isset( $_POST['model'] ) ) {
        $selected_model = sanitize_text_field( wp_unslash( $_POST['model'] ) );
    }
    if ( ! in_array( $selected_model, $allowed_models, true ) ) {
        $selected_model = 'deepseek_r1';
    }

    // Book editor always sends this task type to n8n (not user-selectable).
    $task_type = 'book_introduction';

    $allowed_diff = array( 'regular', 'preparatory' );
    if ( is_array( $parsed ) && isset( $parsed['difficulty_level'] ) ) {
        $difficulty_level = sanitize_text_field( $parsed['difficulty_level'] );
    } else {
        $difficulty_level = 'regular';
    }
    if ( ! in_array( $difficulty_level, $allowed_diff, true ) ) {
        $difficulty_level = 'regular';
    }

    $author_name = '' !== $author_in ? $author_in : 'N/A';

    $selected_tasks_resolved = my_book_editor_build_book_intro_tasks_for_n8n( $book_name, $author_name, $difficulty_level );

    // Book endpoint: mirrors author v3 body plus explicit keys for n8n (model, task_type).
    $body_payload = array(
        'book_name'           => $book_name,
        'author_name'         => $author_name,
        'selected_model'      => $selected_model,
        'model'               => $selected_model,
        'task_type'           => $task_type,
        'difficulty_level'    => $difficulty_level,
        'selected_tasks'      => $selected_tasks_resolved,
        'book_introduction'   => true,
        'memory_id'           => my_book_editor_get_memory_id(),
        'executionMode'       => 'v3_structured',
    );

    $webhook_url = my_book_editor_get_n8n_main_webhook_url();
    if ( '' === $webhook_url ) {
        wp_send_json_error( 'Main n8n Webhook URL is not configured.' );
    }

    $response = wp_remote_post(
        $webhook_url,
        array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'body'    => wp_json_encode( $body_payload ),
            'timeout' => 360,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Failed to connect to automation service: ' . $response->get_error_message() );
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $body      = wp_remote_retrieve_body( $response );

    if ( $http_code < 200 || $http_code >= 300 ) {
        wp_send_json_error( 'n8n webhook returned HTTP ' . (int) $http_code . ' — ' . $body );
    }

    $n8n_response_data = json_decode( $body, true );
    if ( JSON_ERROR_NONE !== json_last_error() ) {
        wp_send_json_success(
            array(
                'message'      => 'Automation payload sent to n8n; response is not JSON.',
                'n8n_response' => $body,
                'http_code'    => $http_code,
            )
        );
    }

    if ( isset( $n8n_response_data['data'] ) && is_array( $n8n_response_data['data'] ) ) {
        $n8n_response_data = $n8n_response_data['data'];
    }

    wp_send_json_success(
        array(
            'message'      => __( 'Automation payload sent to n8n successfully.', 'my-book-editor' ),
            'n8n_response' => $n8n_response_data,
            'http_code'    => $http_code,
        )
    );
}
}

// AJAX handler for saving final generated content as a new draft (for book editor)
add_action( 'wp_ajax_my_book_editor_save_final_content_as_draft', 'my_book_editor_save_final_content_as_draft_callback' );
if ( ! function_exists( 'my_book_editor_save_final_content_as_draft_callback' ) ) {
function my_book_editor_save_final_content_as_draft_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'book_editor_nonce' ) ) { // Using book_editor_nonce now
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
        'post_type'     => 'book_profile',
    );

    $new_post_id = wp_insert_post( $new_post_data, true );

    if ( is_wp_error( $new_post_id ) ) {
        wp_send_json_error( $new_post_id->get_error_message() );
    } else {
        wp_send_json_success( array( 'message' => 'New draft created successfully!', 'post_id' => $new_post_id ) );
    }
}
}

// AJAX handler for loading content from a specific draft (for "Generated Drafts Content" section in book editor)
add_action( 'wp_ajax_my_book_editor_load_draft_content', 'my_book_editor_load_draft_content_callback' );
if ( ! function_exists( 'my_book_editor_load_draft_content_callback' ) ) {
function my_book_editor_load_draft_content_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'book_editor_nonce' ) ) { // Using book_editor_nonce now
        wp_send_json_error( 'Nonce verification failed. Please try reloading the page.' );
    }

    $post_id = intval( $_POST['post_id'] );
    $draft_type = sanitize_text_field( $_POST['draft_type'] );

    $post = get_post( $post_id );

    if ( !$post || $post->post_type !== 'book_profile' ) {
        wp_send_json_error( 'Invalid post ID or post type.' );
    }

    $content_to_return = '';
    $title_to_return = $post->post_title;

    // Example: If you stored draft content in custom fields like 'agent_1_draft_content'
    if ($draft_type === 'draft1') {
        $content_to_return = get_post_meta($post_id, 'agent_1_draft_content', true);
    } elseif ($draft_type === 'draft2') {
        $content_to_return = get_post_meta($post_id, 'agent_2_draft_content', true);
    } elseif ($draft_type === 'draft3') {
        $content_to_return = get_post_meta($post_id, 'agent_3_draft_content', true);
    } else {
        $content_to_return = $post->post_content; // Fallback to main content
    }

    wp_send_json_success( array(
        'title' => $title_to_return,
        'content' => apply_filters( 'the_content', $content_to_return ),
    ));
}
}

/**
 * Shortcode to display the Book Content Editor.
 */
if ( ! function_exists( 'my_book_editor_shortcode' ) ) {
function my_book_editor_shortcode() {
    global $my_book_editor_shortcode_used;
    $my_book_editor_shortcode_used = true;

    ob_start();

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        ?>
        <p class="text-center text-red-500">You must be logged in with sufficient permissions to access the Book Content Editor.</p>
        <?php
        return ob_get_clean();
    }

    // Initialize values for form fields - These will be populated by JS on load/selection
    $book_name_val      = '';
    $book_subtitle_val  = '';
    $book_content_val   = '';
    $book_cover_id_val  = 0;
    $book_cover_url_val = '';
    $placement_category_val = ''; // New: Initialize for placement
    $insert_link_val    = '';
    $book_post_id_val   = 0;
    $book_category_val  = '';
    $book_level_val     = '';
    $associated_author_id_val = 0;


    // Display status messages
    if ( isset( $_GET['status'] ) ) {
        $status_message = '';
        $status_class = 'bg-green-100 border border-green-400 text-green-700';

        switch ( $_GET['status'] ) {
            case 'book_published':
                $status_message = 'Book successfully published!'; break;
            case 'book_saved_draft':
                $status_message = 'Book saved as draft!'; break;
            case 'book_pending_review':
                $status_message = 'Book submitted for review!'; break;
            case 'book_unpublished':
                $status_message = 'Book successfully unpublished (set to draft)!'; break;
            case 'book_archived':
                $status_message = 'Book successfully archived!'; break;
            case 'book_deleted':
                $status_message = 'Book successfully deleted!'; break;
            case 'book_added':
                $status_message = 'New book added successfully!'; break;
            case 'book_published_to_placement': // New status for placement publish
                $status_message = 'Book successfully published to selected placement!'; break;
            case 'error_empty_title':
                $status_message = 'Error: Book name cannot be empty. Please fill the book name field.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_book_db':
                $status_message = 'An error occurred while submitting the book. Please try again.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_book_delete':
                $status_message = 'An error occurred while deleting the book. Please try again.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_no_content_to_delete':
                $status_message = 'No content was selected for deletion.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_permissions':
                $status_message = 'You do not have sufficient permissions to perform this action.';
                $status_class = 'bg-red-100 border border-red-400 text-red-700'; break;
            case 'error_no_book_selected_for_placement': // New error status
                $status_message = 'Please select a book before attempting to publish to a placement.';
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

    <main class="w-full mx-auto flex flex-col lg:flex-row gap-2 px-2 py-2 min-h-screen">
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
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/article-endpoint/">
                        <i class="fas fa-newspaper text-gray-500 w-4"></i> 
                        <span>Article Endpoint</span>
                    </a>
                    <!-- Book endpoint: make this the active button -->
                    <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-3 py-2 text-xs font-medium" href="https://caastedu.com/book-endpoint/">
                        <i class="fas fa-book-open text-blue-600 w-4"></i> 
                        <span>Book Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/llesson-plan-editor/">
                        <i class="fas fa-file-alt text-gray-500 w-4"></i> 
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



        

        <section class="flex-1 flex flex-col lg:flex-row gap-3 w-full max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]" style="min-height:100vh;">
            <div class="flex-1 bg-white border border-gray-300 rounded-none p-2 lg:p-6 min-h-[calc(100vh-40px)] flex flex-col w-full">
                <h2 class="text-xl font-semibold mb-4">Book Content Editor</h2>

                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="font-sans w-full flex flex-col flex-1 box-shadow:none" id="book-editor-form" style="max-width: none !important; box-shadow: none !important; border-radius: 0 !important; margin: 0 !important; padding: 0 !important; background: transparent !important;">
                    <input type="hidden" name="action" value="my_book_editor_submit">
                    <?php wp_nonce_field('book_content_submission', '_wpnonce_book_content'); ?>
                    <input type="hidden" name="book_post_id" id="book_post_id" value="<?php echo esc_attr( $book_post_id_val ); ?>">
                    <input type="hidden" name="selected_author_id" id="selected_author_id" value="0">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3">
                        <div class="md:col-span-2">
                            <label for="selected_book_id" class="block text-sm font-medium text-gray-700">Select a book</label>
                            <select name="selected_book_id" id="selected_book_id" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select a book --</option>
                                <?php
                                $books = get_posts( array(
                                    'post_type'      => 'book_profile',
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'post_status'    => array('publish', 'draft', 'pending', 'archive'),
                                ) );
                                foreach ( $books as $book ) {
                                    echo '<option value="' . esc_attr( $book->ID ) . '">' . esc_html( $book->post_title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="load-book-content-btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <div class="flex flex-col mb-3">
                        <label for="new_book_name" class="block text-sm font-medium text-gray-700">Add a book</label>
                        <input type="text" name="new_book_name" id="new_book_name" placeholder="Enter book name" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($book_name_val); ?>" />
                    </div>

                    <div class="flex flex-col mb-3">
                        <label for="book_subtitle" class="block text-sm font-medium text-gray-700">Book Subtitle:</label>
                        <input type="text" name="book_subtitle" id="book_subtitle" placeholder="Book subtitle text" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($book_subtitle_val); ?>" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mb-3">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Upload book cover image</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" id="book_cover_url" name="book_cover_url" class="flex-grow border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Browse book cover" readonly value="<?php echo esc_url($book_cover_url_val); ?>" />
                                <input type="hidden" id="book_cover_id" name="book_cover_id" value="<?php echo esc_attr($book_cover_id_val); ?>" />
                            </div>
                            <div id="book-cover-preview" class="mt-2 text-center" style="<?php echo empty($book_cover_url_val) ? 'display:none;' : ''; ?>">
                                <img src="<?php echo esc_url($book_cover_url_val); ?>" alt="Book Cover Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />
                                <button type="button" class="remove-book-cover text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="button button-secondary browse-book-cover bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Browse</button>
                        </div>
                    </div>


                    <div class="mb-3">
                        <label for="book_content" class="block text-sm font-medium text-gray-700">Body Content:</label>
                        <?php
                        if ( current_user_can( 'edit_posts' ) ) {
                            wp_editor( $book_content_val, 'book_content', array(
                                'textarea_name' => 'book_content',
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
                            echo '<textarea name="book_content" id="book_content" rows="12" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">' . esc_textarea($book_content_val) . '</textarea>';
                        }
                        ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            
                            <label for="book_category" class="block text-sm font-medium text-gray-700">Select a category</label>
                            <select name="book_category" id="book_category" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select a category --</option>
                                <?php
                                $categories = get_terms( array(
                                    'taxonomy'   => 'book_category',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $categories as $category ) {
                                    $selected = ( $book_category_val === $category->slug ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $category->slug ) . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
                                }
                                ?>
                            </select>
                            
                        </div>

                        <div>
                            <label for="book_level" class="block text-gray-700 text-sm font-bold mb-2">
    Book Level:
</label>
<select name="book_level" id="book_level" class="w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
    <option value="">-- Select a Level --</option>
    <option value="beginner">Beginner</option>
    <option value="intermediate">Intermediate</option>
    <option value="advanced">Advanced</option>
</select>
                            
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
                        <div class="md:col-span-2 flex flex-col">
                            <label for="submit_book_action_select" class="block text-sm font-medium text-gray-700">Action</label>
                            <select name="submit_book_action" id="submit_book_action_select" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="save">Save Draft</option>
                                <option value="publish">Publish Ready</option>
                                <option value="unpublish">Unpublish</option>
                                <option value="archive">Archive</option>
                                <option value="delete">Delete</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="submit_book_action_btn" value="submit" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
                        <div class="flex flex-col md:col-span-2">
                            <label for="placement_category" class="block text-sm font-medium text-gray-700">Placement Category</label>
                            <select name="placement_category" id="placement_category" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select Placement --</option>
                                <?php
                                $placements = get_terms( array(
                                    'taxonomy'   => 'book_placement',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $placements as $placement ) {
                                    $selected = ( $placement_category_val === $placement->slug ) ? 'selected' : '';
                                    echo '<option value="' . esc_attr( $placement->slug ) . '" ' . $selected . '>' . esc_html( $placement->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <!--<div class="flex flex-col">-->
                        <!--    <label for="insert_link" class="block text-sm font-medium text-gray-700">Insert Link</label>-->
                        <!--    <input type="url" name="insert_link" id="insert_link" placeholder="https://example.com" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_url($insert_link_val); ?>" />-->
                        <!--</div>-->
                        <div class="flex items-end">
                            <button type="submit" name="placement_action" value="publish_placement" class="button button-primary bg-blue-600 text-white px-4 py-2 rounded-none hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full">Publish</button>
                        </div>
                    </div>

                </form>
            </div>
        </section>
        <style>
            @media (max-width: 1024px) {
                section.flex-1.flex, section .flex-1 {
                    min-height: unset !important;
                }
            }
            section.flex-1.flex {
                min-height: 100vh;
                width: 100vw;
                max-width: 100vw;
            }
            section.flex-1.flex .flex-1 {
                min-width:0;
                flex-shrink:1;
                flex-grow:1;
                width: 100%;
                display: flex;
                flex-direction: column;
            }
            #book-editor-form {
                flex: 1 1 auto;
                min-height: 0;
                max-width: 100vw;
            }
            @media (max-width: 640px) {
                section.flex-1.flex .flex-1 {
                    padding: 0.5rem !important;
                }
                #book-editor-form {
                    padding: 0 !important;
                }
            }
        </style>
        <script>
        (function() {
            function fixVH() {
                document.querySelectorAll('section.flex-1.flex').forEach(function(section) {
                    section.style.minHeight = window.innerHeight + 'px';
                });
            }
            window.addEventListener('resize', fixVH);
            window.addEventListener('zoom', fixVH);
            fixVH();
        })();
        </script>

        <aside class="w-full lg:w-1/4 bg-white border border-gray-300 rounded-lg p-4 lg:p-6 shadow-md max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">AI Content Generator</h2>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Book introduction</h3>
                <p class="text-xs text-gray-600 mb-4">Enter the book title only. Prompts are resolved in n8n / backend.</p>
                <div class="mb-4">
                    <label for="book_name" class="block text-gray-700 text-sm font-bold mb-2">Book name</label>
                    <input type="text" id="book_name" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" placeholder="Title of the book" autocomplete="off">
                </div>
                <div id="llm-model-selector-block" class="mb-4">
                    <label for="llm_model_select" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'AI Model', 'my-book-editor' ); ?></label>
                    <select id="llm_model_select" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2">
                        <option value="deepseek_r1" selected><?php esc_html_e( 'DeepSeek-R1', 'my-book-editor' ); ?></option>
                        <option value="gpt_4o"><?php esc_html_e( 'Kimi K2', 'my-book-editor' ); ?></option>
                        <option value="claude_3_5_sonnet"><?php esc_html_e( 'Qwen 3', 'my-book-editor' ); ?></option>
                    </select>
                    <p class="text-gray-500 text-xs mt-1"><?php esc_html_e( 'Active model:', 'my-book-editor' ); ?> <span id="active-model-label" class="font-semibold text-gray-800"><?php esc_html_e( 'DeepSeek-R1', 'my-book-editor' ); ?></span></p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Difficulty level', 'my-book-editor' ); ?></label>
                    <div class="inline-flex w-full rounded-none border border-gray-300 bg-gray-100 p-1 shadow-inner" role="radiogroup" aria-label="Difficulty level">
                        <label class="flex-1 cursor-pointer text-center min-w-0">
                            <input type="radio" name="difficulty_level" value="regular" class="peer sr-only" checked>
                            <span class="block rounded-none px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 peer-checked:bg-white peer-checked:text-gray-900 peer-checked:shadow"><?php esc_html_e( 'Regular', 'my-book-editor' ); ?></span>
                        </label>
                        <label class="flex-1 cursor-pointer text-center min-w-0">
                            <input type="radio" name="difficulty_level" value="preparatory" class="peer sr-only">
                            <span class="block rounded-none px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 peer-checked:bg-white peer-checked:text-gray-900 peer-checked:shadow"><?php esc_html_e( 'Preparatory', 'my-book-editor' ); ?></span>
                        </label>
                    </div>
                </div>
                <button type="button" id="start_full_automation" class="generate-btn mbe-generate-btn button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full"><span class="mbe-btn-spinner" aria-hidden="true"></span><span class="mbe-generate-btn-label"><?php esc_html_e( 'Generate', 'my-book-editor' ); ?></span></button>
                <input type="checkbox" id="task_book_introduction" class="sr-only" checked="checked" aria-hidden="true" tabindex="-1" data-task-id="book_introduction">
                <div class="content-type-card bg-blue-50 border border-blue-200 rounded-lg p-3 mt-4">
                    <span class="text-sm font-medium text-gray-900">Book Introduction</span>
                    <p class="text-xs text-gray-600 mt-1"><?php esc_html_e( 'This page sends only the book introduction task to n8n.', 'my-book-editor' ); ?></p>
                </div>
            </div>
            <div id="full_automation_status" class="response-status-message mt-4 p-3 hidden text-center font-semibold rounded"></div>

            <div class="mt-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-800">LLM output</h3>
                    <span class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Document preview</span>
                </div>
                <label for="final_generated_title" class="block text-gray-700 text-sm font-bold mb-2"><?php esc_html_e( 'Title:', 'my-book-editor' ); ?></label>
                <input type="text" id="final_generated_title" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" readonly placeholder="<?php esc_attr_e( 'Generated title…', 'my-book-editor' ); ?>">

                <label for="final_generated_content" class="block text-gray-700 text-sm font-bold mb-2 mt-4"><?php esc_html_e( 'Content:', 'my-book-editor' ); ?></label>
                <div class="flex justify-end mb-2">
                    <button type="button" id="copy_final_generated_content_btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm"><?php esc_html_e( 'Copy to Clipboard', 'my-book-editor' ); ?></button>
                </div>
                <textarea id="final_generated_content" class="output-content-textarea shadow appearance-none border rounded-none w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="10" readonly placeholder="<?php esc_attr_e( 'Generated content…', 'my-book-editor' ); ?>"></textarea>
            </div>
            <button type="button" id="upload_final_content_to_editor" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full mt-4"><?php esc_html_e( 'Load to Main Editor', 'my-book-editor' ); ?></button>
            <button type="button" id="save_final_content_as_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full mt-2"><?php esc_html_e( 'Save as New Draft', 'my-book-editor' ); ?></button>
        </aside>

        <aside class="w-full lg:w-1/5 bg-white border border-gray-300 rounded-lg p-4 lg:p-6 shadow-md max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 mt-6"><?php esc_html_e( 'Generated Drafts Content', 'my-book-editor' ); ?></h2>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="select_title_ce" class="block text-gray-700 text-sm font-bold mb-2">
                        <?php esc_html_e( 'Select Title:', 'my-book-editor' ); ?>
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
                    <button type="button" id="load_content_to_editor_from_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full"><?php esc_html_e( 'Load Content to Editor', 'my-book-editor' ); ?></button>
                </div>
            </div>
        </aside>

    </main>
    <?php
    return ob_get_clean();
}
}
if ( ! shortcode_exists( 'my_book_editor' ) ) {
    add_shortcode( 'my_book_editor', 'my_book_editor_shortcode' );
}

// Add rewrite rules
if ( ! function_exists( 'my_book_editor_rewrite_rule' ) ) {
function my_book_editor_rewrite_rule() {
    add_rewrite_rule(
        '^book-profile/([0-9]+)/?$',
        'index.php?post_type=book_profile&p=$matches[1]',
        'top'
    );
}
}
add_action( 'init', 'my_book_editor_rewrite_rule' );

// Flush rewrite rules on plugin activation
function my_book_editor_activate() {
    my_book_editor_register_cpt();
    my_book_editor_register_taxonomies();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'my_book_editor_activate' );

// Flush rewrite rules on plugin deactivation
function my_book_editor_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'my_book_editor_deactivate' );