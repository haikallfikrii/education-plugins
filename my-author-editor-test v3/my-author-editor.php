<?php
/*
Plugin Name: My Author Editor (Test Version)
Plugin URI:  https://example.com/
Description: Author Content Editor v3. Shortcodes: [mae_v3_author_dashboard], [author_dashboard_v3], or legacy [my_author_editor_test].
Version:     1.3.3
Author:      Muhamad Fikri Haikal
Author URI:  https://caastedu.com/
License:     GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns a numeric memory/session id for n8n.
 * - Logged-in users: use WordPress user ID (persistent).
 * - Guests: use a cookie-stored random integer (stable per browser).
 */
if ( ! function_exists( 'mae_v3_get_memory_id' ) ) {
function mae_v3_get_memory_id() {
    if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
        $uid = (int) get_current_user_id();
        if ( $uid > 0 ) {
            return $uid;
        }
    }

    $cookie_name = 'mae_v3_session_id';
    if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
        $existing = (int) preg_replace( '/\D+/', '', (string) wp_unslash( $_COOKIE[ $cookie_name ] ) );
        if ( $existing > 0 ) {
            return $existing;
        }
    }

    // Create a new random numeric id (10 digits) and persist it in a cookie.
    $new_id = random_int( 1000000000, 9999999999 );
    // 30 days, HttpOnly.
    setcookie( $cookie_name, (string) $new_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
    $_COOKIE[ $cookie_name ] = (string) $new_id;
    return $new_id;
}
}

// Global flag to indicate if the shortcode is used on the current page
global $mae_v3_shortcode_used;
$mae_v3_shortcode_used = false;

// Define plugin constants (MAE_V3_ prefix avoids clashes with other plugin versions loaded alongside).
if ( ! defined( 'MAE_V3_VERSION' ) ) {
    define( 'MAE_V3_VERSION', '1.3.3' );
}
if ( ! defined( 'MAE_V3_PLUGIN_URL' ) ) {
    define( 'MAE_V3_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MAE_V3_PLUGIN_DIR' ) ) {
    define( 'MAE_V3_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MAE_V3_N8N_MAIN_WEBHOOK_URL' ) ) {
    define( 'MAE_V3_N8N_MAIN_WEBHOOK_URL', 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391' );
}

if ( ! defined( 'MAE_V3_WEBHOOK_URL_DEEPSEEK_R1' ) ) {
    define( 'MAE_V3_WEBHOOK_URL_DEEPSEEK_R1', 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391' );
}
if ( ! defined( 'MAE_V3_WEBHOOK_URL_DEEPSEEK_V3' ) ) {
    define( 'MAE_V3_WEBHOOK_URL_DEEPSEEK_V3', 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391' );
}
if ( ! defined( 'MAE_V3_WEBHOOK_URL_QWEN_2_5' ) ) {
    define( 'MAE_V3_WEBHOOK_URL_QWEN_2_5', 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391' );
}

/**
 * Register Custom Post Type for Author Content
 * This is highly recommended instead of using default 'post' type.
 */
if ( ! function_exists( 'mae_v3_register_cpt' ) ) {
function mae_v3_register_cpt() {
    $labels = array(
        'name'                  => _x( 'Author Profiles', 'Post Type General Name', 'my-author-editor' ),
        'singular_name'         => _x( 'Author Profile', 'Post Type Singular Name', 'my-author-editor' ),
        'menu_name'             => __( 'Author Editor (Test)', 'my-author-editor' ),
        'name_admin_bar'        => __( 'Author Profile', 'my-author-editor' ),
        'archives'              => __( 'Author Archives', 'my-author-editor' ),
        'attributes'            => __( 'Author Attributes', 'my-author-editor' ),
        'parent_item_colon'     => __( 'Parent Author:', 'my-author-editor' ),
        'all_items'             => __( 'All Author Profiles', 'my-author-editor' ),
        'add_new_item'          => __( 'Add New Author Profile', 'my-author-editor' ),
        'add_new'               => __( 'Add New', 'my-author-editor' ),
        'new_item'              => __( 'New Author Profile', 'my-author-editor' ),
        'edit_item'             => __( 'Edit Author Profile', 'my-author-editor' ),
        'update_item'           => __( 'Update Author Profile', 'my-author-editor' ),
        'view_item'             => __( 'View Author Profile', 'my-author-editor' ),
        'view_items'            => __( 'View Author Profiles', 'my-author-editor' ),
        'search_items'          => __( 'Search Author Profiles', 'my-author-editor' ),
        'not_found'             => __( 'Not found', 'my-author-editor' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'my-author-editor' ),
        'featured_image'        => __( 'Featured Image', 'my-author-editor' ),
        'set_featured_image'    => __( 'Set featured image', 'my-author-editor' ),
        'remove_featured_image' => __( 'Remove featured image', 'my-author-editor' ),
        'use_featured_image'    => __( 'Use as featured image', 'my-author-editor' ),
        'insert_into_item'      => __( 'Insert into Author Profile', 'my-author-editor' ),
        'uploaded_to_this_item' => __( 'Uploaded to this Author Profile', 'my-author-editor' ),
        'items_list'            => __( 'Author Profiles list', 'my-author-editor' ),
        'items_list_navigation' => __( 'Author Profiles list navigation', 'my-author-editor' ),
        'filter_items_list'     => __( 'Filter Author Profiles list', 'my-author-editor' ),
    );
    $args = array(
        'label'                 => __( 'Author Profile', 'my-author-editor' ),
        'description'           => __( 'Custom post type for managing author content.', 'my-author-editor' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-admin-users',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true, // Enable for Gutenberg or REST API usage
    );
    register_post_type( 'author_profile', $args );
}
}
add_action( 'init', 'mae_v3_register_cpt' );

/**
 * Register Custom Taxonomies for Author Profiles
 */
if ( ! function_exists( 'mae_v3_register_taxonomies' ) ) {
function mae_v3_register_taxonomies() {
    // Category Taxonomy
    $labels_category = array(
        'name'                       => _x( 'Categories', 'Taxonomy General Name', 'my-author-editor' ),
        'singular_name'              => _x( 'Category', 'Taxonomy Singular Name', 'my-author-editor' ),
        'menu_name'                  => __( 'Categories', 'my-author-editor' ),
        'all_items'                  => __( 'All Categories', 'my-author-editor' ),
        'parent_item'                => __( 'Parent Category', 'my-author-editor' ),
        'parent_item_colon'          => __( 'Parent Category:', 'my-author-editor' ),
        'new_item_name'              => __( 'New Category Name', 'my-author-editor' ),
        'add_new_item'               => __( 'Add New Category', 'my-author-editor' ),
        'edit_item'                  => __( 'Edit Category', 'my-author-editor' ),
        'update_item'                => __( 'Update Category', 'my-author-editor' ),
        'view_item'                  => __( 'View Category', 'my-author-editor' ),
        'separate_items_with_commas' => __( 'Separate categories with commas', 'my-author-editor' ),
        'add_or_remove_items'        => __( 'Add or remove categories', 'my-author-editor' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'my-author-editor' ),
        'popular_items'              => __( 'Popular Categories', 'my-author-editor' ),
        'search_items'               => __( 'Search Categories', 'my-author-editor' ),
        'not_found'                  => __( 'Not Found', 'my-author-editor' ),
        'no_terms'                   => __( 'No categories', 'my-author-editor' ),
        'items_list'                 => __( 'Categories list', 'my-author-editor' ),
        'items_list_navigation'      => __( 'Categories list navigation', 'my-author-editor' ),
    );
    $args_category = array(
        'labels'                     => $labels_category,
        'hierarchical'               => true,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_in_rest'               => true,
        'show_tagcloud'              => false,
    );
    register_taxonomy( 'author_category', array( 'author_profile' ), $args_category );

    // Level Taxonomy
    $labels_level = array(
        'name'                       => _x( 'Levels', 'Taxonomy General Name', 'my-author-editor' ),
        'singular_name'              => _x( 'Level', 'Taxonomy Singular Name', 'my-author-editor' ),
        'menu_name'                  => __( 'Levels', 'my-author-editor' ),
        'all_items'                  => __( 'All Levels', 'my-author-editor' ),
        'parent_item'                => __( 'Parent Level', 'my-author-editor' ),
        'parent_item_colon'          => __( 'Parent Level:', 'my-author-editor' ),
        'new_item_name'              => __( 'New Level Name', 'my-author-editor' ),
        'add_new_item'               => __( 'Add New Level', 'my-author-editor' ),
        'edit_item'                  => __( 'Edit Level', 'my-author-editor' ),
        'update_item'                => __( 'Update Level', 'my-author-editor' ),
        'view_item'                  => __( 'View Level', 'my-author-editor' ),
        'separate_items_with_commas' => __( 'Separate levels with commas', 'my-author-editor' ),
        'add_or_remove_items'        => __( 'Add or remove levels', 'my-author-editor' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'my-author-editor' ),
        'popular_items'              => __( 'Popular Levels', 'my-author-editor' ),
        'search_items'               => __( 'Search Levels', 'my-author-editor' ),
        'not_found'                  => __( 'Not Found', 'my-author-editor' ),
        'no_terms'                   => __( 'No levels', 'my-author-editor' ),
        'items_list'                 => __( 'Levels list', 'my-author-editor' ),
        'items_list_navigation'      => __( 'Levels list navigation', 'my-author-editor' ),
    );
    $args_level = array(
        'labels'                     => $labels_level,
        'hierarchical'               => true,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_in_rest'               => true,
        'show_tagcloud'              => false,
    );
    register_taxonomy( 'author_level', array( 'author_profile' ), $args_level );

    // New: Placement Category Taxonomy
    $labels_placement = array(
        'name'                       => _x( 'Placement Categories', 'Taxonomy General Name', 'my-author-editor' ),
        'singular_name'              => _x( 'Placement Category', 'Taxonomy Singular Name', 'my-author-editor' ),
        'menu_name'                  => __( 'Placement Categories', 'my-author-editor' ),
        'all_items'                  => __( 'All Placements', 'my-author-editor' ),
        'parent_item'                => __( 'Parent Placement', 'my-author-editor' ),
        'parent_item_colon'          => __( 'Parent Placement:', 'my-author-editor' ),
        'new_item_name'              => __( 'New Placement Name', 'my-author-editor' ),
        'add_new_item'               => __( 'Add New Placement', 'my-author-editor' ),
        'edit_item'                  => __( 'Edit Placement', 'my-author-editor' ),
        'update_item'                => __( 'Update Placement', 'my-author-editor' ),
        'view_item'                  => __( 'View Placement', 'my-author-editor' ),
        'separate_items_with_commas' => __( 'Separate placements with commas', 'my-author-editor' ),
        'add_or_remove_items'        => __( 'Add or remove placements', 'my-author-editor' ),
        'choose_from_most_used'      => __( 'Choose from the most used placements', 'my-author-editor' ),
        'popular_items'              => __( 'Popular Placements', 'my-author-editor' ),
        'search_items'               => __( 'Search Placements', 'my-author-editor' ),
        'not_found'                  => __( 'No Placements Found', 'my-author-editor' ),
        'no_terms'                   => __( 'No placements', 'my-author-editor' ),
        'items_list'                 => __( 'Placements list', 'my-author-editor' ),
        'items_list_navigation'      => __( 'Placements list navigation', 'my-author-editor' ),
    );
    $args_placement = array(
        'labels'                     => $labels_placement,
        'hierarchical'               => true,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_in_rest'               => true,
        'show_tagcloud'              => false,
        'rewrite'                    => array( 'slug' => 'placement-category' ), // Custom rewrite slug for clarity
    );
    register_taxonomy( 'placement_category', array( 'author_profile' ), $args_placement ); // Associated with author_profile CPT
}
}
add_action( 'init', 'mae_v3_register_taxonomies' );

/**
 * Add default terms for Placement Category on plugin activation.
 */
function mae_v3_add_default_placement_terms() {
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
        'Article Left',
        'Article Right',
        'Article Footer',
        'Book Left',
        'Book Right',
        'Book Footer',
        'Video Left',
        'Video Right',
        'Video Footer'
    );
    foreach ( $placements as $placement_name ) {
        if ( ! term_exists( $placement_name, 'placement_category' ) ) {
            wp_insert_term( $placement_name, 'placement_category' );
        }
    }
}

/**
 * Enqueue plugin assets (CSS and JS)
 */
if ( ! function_exists( 'mae_v3_load_assets' ) ) {
function mae_v3_load_assets() {
    global $mae_v3_shortcode_used;

    // Enqueue Tailwind CSS for basic styling
    wp_enqueue_style( 'mae_v3-tailwind', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), null );

    // Enqueue Select2 CSS (if not already enqueued by another plugin)
    wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );

    // Optional: reuse lesson editor utility CSS when that plugin is active (visual parity, no duplicate handle).
    if ( defined( 'MY_LESSON_EDITOR_VERSION' ) && defined( 'MY_LESSON_EDITOR_PLUGIN_URL' ) && ! wp_style_is( 'my-lesson-editor-style', 'enqueued' ) ) {
        wp_enqueue_style(
            'mae-v3-lesson-editor-mirror',
            MY_LESSON_EDITOR_PLUGIN_URL . 'css/my-lesson-editor.css',
            array(),
            MY_LESSON_EDITOR_VERSION
        );
    }

    // Enqueue custom CSS
    wp_enqueue_style( 'mae_v3-custom-css', MAE_V3_PLUGIN_URL . 'css/my-author.css', array(), MAE_V3_VERSION );

    // Enqueue custom script for AI generation logic
    wp_enqueue_script( 'mae_v3-ai-script', MAE_V3_PLUGIN_URL . 'js/my-author-editor-ai.js', array( 'jquery', 'select2' ), MAE_V3_VERSION, true );

    // Localize script for AI generation
    wp_localize_script( 'mae_v3-ai-script', 'maeV3EditorAIAjax', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mae_v3_ai' ),
        'webhook_url_deepseek_r1' => MAE_V3_WEBHOOK_URL_DEEPSEEK_R1,
        'webhook_url_deepseek_v3' => MAE_V3_WEBHOOK_URL_DEEPSEEK_V3,
        'webhook_url_qwen_2_5'    => MAE_V3_WEBHOOK_URL_QWEN_2_5,
        'n8n_main_webhook_url'    => MAE_V3_N8N_MAIN_WEBHOOK_URL,
    ));

    // Enqueue custom script only if the shortcode is used on the page
    if ( $mae_v3_shortcode_used ) {
        wp_enqueue_script( 'mae_v3-main-script', MAE_V3_PLUGIN_URL . 'js/my-author-editor.js', array( 'jquery', 'tinymce-api', 'select2' ), MAE_V3_VERSION, true );
        wp_localize_script( 'mae_v3-main-script', 'maeV3EditorAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mae_v3_editor' ),
            'cep_nonce' => wp_create_nonce( 'mae_v3_cep' ),
        ));
    }
}
}
add_action( 'wp_enqueue_scripts', 'mae_v3_load_assets' );


/**
 * Default master prompt bodies stored in wp_options. Keys match mae_v3_master_prompt_option_map()
 * so prompts stay modular for a future Admin Settings page. Placeholders: {{book_name}}, {{author_name}}, {{difficulty_level}}.
 *
 * @return array<string, string>
 */
function mae_v3_get_default_master_prompts() {
    return array(
        'mae_v3_author_intro_master_prompt'     => 'Write a concise author introduction for {{author_name}}, author of "{{book_name}}". Audience difficulty: {{difficulty_level}}.',
        'mae_v3_book_intro_master_prompt'     => 'Write a book introduction for "{{book_name}}" by {{author_name}}. Difficulty: {{difficulty_level}}.',
        'mae_v3_chapter_vocab_master_prompt'    => 'Produce a chapter vocabulary list for "{{book_name}}" ({{author_name}}). Difficulty: {{difficulty_level}}. Use the quantity settings from the task payload.',
        'mae_v3_chapter_quiz_master_prompt'     => 'Create chapter quiz content for "{{book_name}}" by {{author_name}}. Difficulty: {{difficulty_level}}. Respect multiple-choice and short-answer counts from the task payload.',
        'mae_v3_chapter_synopsis_master_prompt' => 'Write a chapter synopsis with discussion prompts for "{{book_name}}" ({{author_name}}). Difficulty: {{difficulty_level}}. Use the discussion question count from the task payload.',
        'mae_v3_midterm_exam_master_prompt'     => 'Design a mid-term exam aligned with "{{book_name}}" by {{author_name}}. Difficulty: {{difficulty_level}}.',
        'mae_v3_final_exam_master_prompt'      => 'Design a final exam aligned with "{{book_name}}" by {{author_name}}. Difficulty: {{difficulty_level}}.',
        'mae_v3_class_activities_master_prompt' => 'Outline class activities for "{{book_name}}" by {{author_name}}. Difficulty: {{difficulty_level}}.',
        'mae_v3_projects_master_prompt'       => 'Outline projects for "{{book_name}}" by {{author_name}}. Difficulty: {{difficulty_level}}.',
    );
}

/**
 * Seed default master prompts on activation (does not overwrite existing options).
 */
function mae_v3_seed_default_master_prompts() {
    foreach ( mae_v3_get_default_master_prompts() as $option_key => $template ) {
        if ( false === get_option( $option_key, false ) ) {
            add_option( $option_key, $template, '', 'no' );
        }
    }
}

/**
 * One-time seed for sites that upgraded without re-running activation.
 *
 * Safe from runaway loops: after the first successful pass, `mae_v3_master_prompts_seeded` is stored and
 * this callback returns immediately on subsequent admin_init runs. Seeding only uses add_option/get_option.
 */
function mae_v3_maybe_seed_master_prompts_upgrade() {
    if ( get_option( 'mae_v3_master_prompts_seeded', '' ) === '1' ) {
        return;
    }
    mae_v3_seed_default_master_prompts();
    update_option( 'mae_v3_master_prompts_seeded', '1', true );
}
add_action( 'admin_init', 'mae_v3_maybe_seed_master_prompts_upgrade', 5 );

/**
 * Maps task ids to wp_options keys for master prompts (editable later via Admin Settings).
 *
 * @return array<string, string>
 */
function mae_v3_master_prompt_option_map() {
    return array(
        'author_introduction'     => 'mae_v3_author_intro_master_prompt',
        'book_introduction'       => 'mae_v3_book_intro_master_prompt',
        'chapter_vocabulary_list'   => 'mae_v3_chapter_vocab_master_prompt',
        'chapter_quiz'            => 'mae_v3_chapter_quiz_master_prompt',
        'chapter_synopsis'        => 'mae_v3_chapter_synopsis_master_prompt',
        'mid_term_exam'           => 'mae_v3_midterm_exam_master_prompt',
        'final_exam'              => 'mae_v3_final_exam_master_prompt',
        'class_activities'        => 'mae_v3_class_activities_master_prompt',
        'projects'                => 'mae_v3_projects_master_prompt',
    );
}

/**
 * Replace placeholders in master prompt templates.
 *
 * @param string $template        Raw prompt from options.
 * @param string $book_name       Sanitized book name.
 * @param string $author_name     Sanitized author name.
 * @param string $difficulty_level regular|preparatory.
 * @return string
 */
function mae_v3_replace_prompt_placeholders( $template, $book_name, $author_name, $difficulty_level ) {
    if ( ! is_string( $template ) ) {
        return '';
    }
    $map = array(
        '{{book_name}}'        => $book_name,
        '{{author_name}}'      => $author_name,
        '{{difficulty_level}}' => $difficulty_level,
    );
    return strtr( $template, $map );
}

/**
 * Sanitize and whitelist selected_tasks from the client JSON.
 *
 * @param mixed $raw_tasks Decoded selected_tasks array.
 * @return array<int, array<string, mixed>>
 */
function mae_v3_sanitize_task_list( $raw_tasks ) {
    $out     = array();
    $allowed = array_keys( mae_v3_master_prompt_option_map() );
    if ( ! is_array( $raw_tasks ) ) {
        return $out;
    }
    foreach ( $raw_tasks as $item ) {
        if ( ! is_array( $item ) || empty( $item['id'] ) ) {
            continue;
        }
        $id = sanitize_text_field( $item['id'] );
        if ( ! in_array( $id, $allowed, true ) ) {
            continue;
        }
        $row = array( 'id' => $id );
        if ( 'chapter_vocabulary_list' === $id && isset( $item['qty_words'] ) ) {
            $row['qty_words'] = max( 0, (int) $item['qty_words'] );
        }
        if ( 'chapter_quiz' === $id ) {
            if ( isset( $item['mc_qty'] ) ) {
                $row['mc_qty'] = max( 0, (int) $item['mc_qty'] );
            }
            if ( isset( $item['short_answer_qty'] ) ) {
                $row['short_answer_qty'] = max( 0, (int) $item['short_answer_qty'] );
            }
        }
        if ( 'chapter_synopsis' === $id && isset( $item['discussion_questions_count'] ) ) {
            $row['discussion_questions_count'] = max( 0, (int) $item['discussion_questions_count'] );
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Attach resolved master prompts and params for each selected task.
 *
 * @param array<int, array<string, mixed>> $tasks Sanitized tasks.
 * @param string                           $book_name.
 * @param string                           $author_name.
 * @param string                           $difficulty_level.
 * @return array<int, array<string, mixed>>
 */
function mae_v3_resolve_tasks_for_n8n( $tasks, $book_name, $author_name, $difficulty_level ) {
    $map = mae_v3_master_prompt_option_map();
    $resolved = array();
    foreach ( $tasks as $task ) {
        $id      = $task['id'];
        $opt_key = isset( $map[ $id ] ) ? $map[ $id ] : '';
        $master  = $opt_key ? get_option( $opt_key, '' ) : '';
        $master  = is_string( $master ) ? $master : '';
        $resolved_prompt = mae_v3_replace_prompt_placeholders( $master, $book_name, $author_name, $difficulty_level );

        $entry = array(
            'id'              => $id,
            'resolved_prompt' => $resolved_prompt,
        );
        if ( 'chapter_vocabulary_list' === $id && isset( $task['qty_words'] ) ) {
            $entry['qty_words'] = (int) $task['qty_words'];
        }
        if ( 'chapter_quiz' === $id ) {
            if ( isset( $task['mc_qty'] ) ) {
                $entry['mc_qty'] = (int) $task['mc_qty'];
            }
            if ( isset( $task['short_answer_qty'] ) ) {
                $entry['short_answer_qty'] = (int) $task['short_answer_qty'];
            }
        }
        if ( 'chapter_synopsis' === $id && isset( $task['discussion_questions_count'] ) ) {
            $entry['discussion_questions_count'] = (int) $task['discussion_questions_count'];
        }
        $resolved[] = $entry;
    }
    return $resolved;
}

/**
 * Append optional resource files to a multipart body (same part naming as legacy for n8n).
 *
 * @param string $multipart_body Initial body (with fields already added).
 * @param string $boundary       Multipart boundary (no -- prefix).
 * @param string $eol            Line ending.
 * @return array{ body: string, resource_sent: int }
 */
function mae_v3_append_resource_files_multipart( $multipart_body, $boundary, $eol ) {
    $resource_sent = 0;
    if ( empty( $_FILES['resource_files'] ) || ! is_array( $_FILES['resource_files'] ) || ! isset( $_FILES['resource_files']['name'] ) ) {
        return array(
            'body'           => $multipart_body,
            'resource_sent'  => 0,
        );
    }
    $names = (array) $_FILES['resource_files']['name'];
    $tmps  = (array) $_FILES['resource_files']['tmp_name'];
    $types = (array) $_FILES['resource_files']['type'];
    $errs  = (array) $_FILES['resource_files']['error'];

    foreach ( $names as $i => $name ) {
        $err = isset( $errs[ $i ] ) ? (int) $errs[ $i ] : UPLOAD_ERR_NO_FILE;
        if ( UPLOAD_ERR_OK !== $err ) {
            continue;
        }
        $tmp  = isset( $tmps[ $i ] ) ? $tmps[ $i ] : '';
        $type = isset( $types[ $i ] ) && $types[ $i ] ? sanitize_text_field( $types[ $i ] ) : 'application/octet-stream';
        $safe_name = sanitize_file_name( (string) $name );
        if ( ! $tmp || ! file_exists( $tmp ) ) {
            continue;
        }
        $rfh = fopen( $tmp, 'r' );
        if ( ! $rfh ) {
            continue;
        }
        $resource_contents = stream_get_contents( $rfh );
        fclose( $rfh );
        if ( false === $resource_contents ) {
            continue;
        }
        error_log( '[mae_v3] v3 resource file: ' . $safe_name . ' bytes=' . strlen( $resource_contents ) );

        $multipart_body .= '--' . $boundary . $eol;
        $multipart_body .= 'Content-Disposition: form-data; name="resource_file_' . $resource_sent . '"; filename="' . $safe_name . '"' . $eol;
        $multipart_body .= 'Content-Type: ' . $type . $eol . $eol;
        $multipart_body .= $resource_contents . $eol;
        $resource_sent++;
    }
    if ( $resource_sent > 0 ) {
        $multipart_body .= '--' . $boundary . $eol;
        $multipart_body .= 'Content-Disposition: form-data; name="resource_files_count"' . $eol . $eol;
        $multipart_body .= (string) $resource_sent . $eol;
    }
    return array(
        'body'          => $multipart_body,
        'resource_sent' => $resource_sent,
    );
}

/**
 * AJAX handler: structured v3 JSON payload to n8n (optional resource files as multipart).
 */
function mae_v3_process_automation_trigger_callback() {
    if ( ! check_ajax_referer( 'mae_v3_ai', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $n8n_webhook_url = MAE_V3_N8N_MAIN_WEBHOOK_URL;
    if ( empty( $n8n_webhook_url ) || 'YOUR_N8N_MAIN_WEBHOOK_URL_HERE' === $n8n_webhook_url ) {
        wp_send_json_error( 'n8n Main Webhook URL is not configured (MAE_V3_N8N_MAIN_WEBHOOK_URL).' );
    }

    $raw_payload = isset( $_POST['automation_payload'] ) ? wp_unslash( $_POST['automation_payload'] ) : '';
    $data        = json_decode( $raw_payload, true );
    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'Invalid automation payload.' );
    }

    $book_name   = isset( $data['book_name'] ) ? sanitize_text_field( $data['book_name'] ) : '';
    $author_name = isset( $data['author_name'] ) ? sanitize_text_field( $data['author_name'] ) : '';
    if ( '' === $book_name || '' === $author_name ) {
        wp_send_json_error( 'Book name and author name are required.' );
    }

    $allowed_models   = array( 'deepseek_r1', 'gpt_4o', 'claude_3_5_sonnet' );
    $selected_model   = isset( $data['selected_model'] ) ? sanitize_text_field( $data['selected_model'] ) : 'deepseek_r1';
    if ( ! in_array( $selected_model, $allowed_models, true ) ) {
        $selected_model = 'deepseek_r1';
    }

    $allowed_diff     = array( 'regular', 'preparatory' );
    $difficulty_level = isset( $data['difficulty_level'] ) ? sanitize_text_field( $data['difficulty_level'] ) : 'regular';
    if ( ! in_array( $difficulty_level, $allowed_diff, true ) ) {
        $difficulty_level = 'regular';
    }

    $tasks_raw = isset( $data['selected_tasks'] ) ? $data['selected_tasks'] : array();
    $tasks     = mae_v3_sanitize_task_list( $tasks_raw );
    if ( empty( $tasks ) ) {
        wp_send_json_error( 'Please select at least one content option.' );
    }

    $selected_tasks_resolved = mae_v3_resolve_tasks_for_n8n( $tasks, $book_name, $author_name, $difficulty_level );

    $body_payload = array(
        'book_name'        => $book_name,
        'author_name'      => $author_name,
        'selected_model'   => $selected_model,
        'difficulty_level' => $difficulty_level,
        'selected_tasks'   => $selected_tasks_resolved,
        'memory_id'        => mae_v3_get_memory_id(),
        'executionMode'    => 'v3_structured',
    );

    $has_resource_files = false;
    if ( ! empty( $_FILES['resource_files'] ) && is_array( $_FILES['resource_files'] ) && isset( $_FILES['resource_files']['name'] ) ) {
        $errs = (array) $_FILES['resource_files']['error'];
        foreach ( $errs as $err ) {
            if ( (int) $err === UPLOAD_ERR_OK ) {
                $has_resource_files = true;
                break;
            }
        }
    }

    $debug_info = array(
        'memory_id'          => $body_payload['memory_id'],
        'selected_model'     => $selected_model,
        'difficulty_level'   => $difficulty_level,
        'task_count'         => count( $selected_tasks_resolved ),
        'has_resource_files' => $has_resource_files,
    );

    if ( $has_resource_files ) {
        $boundary       = 'mae_v3_' . wp_generate_password( 24, false, false );
        $eol            = "\r\n";
        $multipart_body = '';
        $json_part      = wp_json_encode( $body_payload );
        $multipart_body .= '--' . $boundary . $eol;
        $multipart_body .= 'Content-Disposition: form-data; name="payload"' . $eol;
        $multipart_body .= 'Content-Type: application/json; charset=UTF-8' . $eol . $eol;
        $multipart_body .= $json_part . $eol;

        $appended = mae_v3_append_resource_files_multipart( $multipart_body, $boundary, $eol );
        $multipart_body = $appended['body'];
        $debug_info['resource_files_sent'] = $appended['resource_sent'];

        $multipart_body .= '--' . $boundary . '--' . $eol;

        $response = wp_remote_post(
            $n8n_webhook_url,
            array(
                'headers' => array(
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ),
                'body'    => $multipart_body,
                'method'  => 'POST',
                'timeout' => 360,
            )
        );
    } else {
        $response = wp_remote_post(
            $n8n_webhook_url,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                ),
                'body'    => wp_json_encode( $body_payload ),
                'method'  => 'POST',
                'timeout' => 360,
            )
        );
    }

    if ( is_wp_error( $response ) ) {
        wp_send_json_error(
            array(
                'message'    => 'Error sending data to n8n webhook: ' . $response->get_error_message(),
                'debug_info' => $debug_info,
            )
        );
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $body      = wp_remote_retrieve_body( $response );
    error_log( '[mae_v3] n8n v3 response code: ' . $http_code );

    if ( $http_code >= 200 && $http_code < 300 ) {
        $n8n_response_data = json_decode( $body, true );
        if ( JSON_ERROR_NONE === json_last_error() ) {
            wp_send_json_success(
                array(
                    'message'      => 'Automation payload sent to n8n successfully.',
                    'n8n_response' => $n8n_response_data,
                    'http_code'    => $http_code,
                    'debug_info'   => $debug_info,
                )
            );
        }
        wp_send_json_success(
            array(
                'message'      => 'Automation payload sent to n8n; response is not JSON.',
                'n8n_response' => $body,
                'http_code'    => $http_code,
                'debug_info'   => $debug_info,
            )
        );
    }
    wp_send_json_error(
        array(
            'message'    => 'n8n webhook returned an error: HTTP ' . $http_code . ' - ' . $body,
            'http_code'  => $http_code,
            'debug_info' => $debug_info,
        )
    );
}
add_action( 'wp_ajax_mae_v3_process_automation_trigger', 'mae_v3_process_automation_trigger_callback' );
add_action( 'wp_ajax_nopriv_mae_v3_process_automation_trigger', 'mae_v3_process_automation_trigger_callback' );


/**
 * The shortcode function for displaying the author editor.
 */
function mae_v3_render_shortcode() {
    global $mae_v3_shortcode_used;
    $mae_v3_shortcode_used = true;

    ob_start();

    $user_email = '';
    $user_avatar = '';
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_avatar = get_avatar_url( $current_user->ID, array( 'size' => 24 ) );
    }

    // Initialize values for form fields - These will be populated by JS on load/selection
    $author_post_id_val   = 0;
    $author_name_val      = '';
    $author_subtitle_val  = '';
    $author_photo_id_val  = 0;
    $author_photo_url_val = '';
    $author_content_val   = '';
    $author_category_val  = ''; // Slug or ID, depends on your usage
    $author_level_val     = ''; // Slug or ID, depends on your usage
    $placement_category_val = ''; // New: For placement category
    $insert_link_val      = '';

    // If an author ID is passed in the URL (e.g., for editing an existing author)
    if (isset($_GET['author_id'])) {
        $edit_author_id = intval($_GET['author_id']);
        $author_post = get_post($edit_author_id);
        if ($author_post && $author_post->post_type === 'author_profile') {
            $author_post_id_val   = $author_post->ID;
            $author_name_val      = $author_post->post_title;
            $author_content_val   = $author_post->post_content;
            $author_subtitle_val  = get_post_meta($author_post->ID, 'author_subtitle', true);
            $author_photo_id_val  = get_post_meta($author_post->ID, 'author_photo_id', true); // Assuming you save ID
            $author_photo_url_val = get_post_meta($author_post->ID, 'author_photo_url', true); // Assuming you save URL
            $insert_link_val      = get_post_meta($author_post->ID, 'insert_link', true);


            // Get saved categories and levels (slugs for display in select)
            $categories = wp_get_post_terms($author_post->ID, 'author_category', array('fields' => 'slugs'));
            if (!is_wp_error($categories) && !empty($categories)) {
                $author_category_val = $categories[0];
            }

            $levels = wp_get_post_terms($author_post->ID, 'author_level', array('fields' => 'slugs'));
            if (!is_wp_error($levels) && !empty($levels)) {
                $author_level_val = $levels[0];
            }

            // Get saved placement category
            $placements = wp_get_post_terms($author_post->ID, 'placement_category', array('fields' => 'slugs'));
            if (!is_wp_error($placements) && !empty($placements)) {
                $placement_category_val = $placements[0];
            }
        }
    }


    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        ?>
        <p class="text-center text-red-500">You must be logged in with sufficient permissions to access the Author Content Editor.</p>
        <?php
        return ob_get_clean();
    }
    
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
                    <a class="flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-3 py-2 text-xs font-medium" href="https://caastedu.com/author-endpoint-v2/">
                        <i class="fas fa-user text-blue-600 w-4"></i>
                        <span>Author Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/article-endpoint/">
                        <i class="fas fa-newspaper text-gray-500 w-4"></i> 
                        <span>Article Endpoint</span>
                    </a>
                    <a class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 text-gray-700 text-xs transition-colors" href="https://caastedu.com/book-endpoint/">
                        <i class="fas fa-book-open text-gray-500 w-4"></i> 
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
            <!-- Main Content Area (matches my-lesson-editor Content Endpoint shell) -->
            <div class="flex-1 bg-white border border-gray-300 rounded-none p-2 lg:p-6 min-h-[calc(100vh-40px)] flex flex-col w-full">
                <h2 class="text-xl font-semibold mb-4">Author Content Editor</h2>

                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="font-sans w-full flex flex-col flex-1 box-shadow:none" id="author-editor-form" style="max-width: none !important; box-shadow: none !important; border-radius: 0 !important; margin: 0 !important; padding: 0 !important; background: transparent !important;">
                    <input type="hidden" name="action" value="mae_v3_submit">
                    <?php wp_nonce_field('author_content_submission', '_wpnonce_author_content'); ?>
                    <input type="hidden" name="author_post_id" id="author_post_id" value="<?php echo esc_attr( $author_post_id_val ); ?>">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end mb-3 w-full">
                        <div class="sm:col-span-2">
                            <label for="selected_author_id" class="block text-xs sm:text-sm font-medium text-gray-700">Select an existing author</label>
                            <select name="selected_author_id" id="selected_author_id" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select an author --</option>
                                <?php
                                $authors = get_posts( array(
                                    'post_type'      => 'author_profile',
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'post_status'    => array('publish', 'draft', 'pending', 'archive'),
                                ) );
                                foreach ( $authors as $author ) {
                                    $selected = selected( $author_post_id_val, $author->ID, false );
                                    echo '<option value="' . esc_attr( $author->ID ) . '" ' . $selected . '>' . esc_html( $author->post_title ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" id="load-author-content-btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <div class="flex flex-col mb-3 w-full">
                        <label for="new_author_name" class="block text-xs sm:text-sm font-medium text-gray-700">Add a new author</label>
                        <input type="text" name="new_author_name" id="new_author_name" placeholder="Enter new author name" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($author_name_val); ?>" />
                    </div>

                    <div class="flex flex-col mb-3 w-full">
                        <label for="author_subtitle" class="block text-xs sm:text-sm font-medium text-gray-700">Author Subtitle:</label>
                        <input type="text" name="author_subtitle" id="author_subtitle" placeholder="Author subtitle text" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($author_subtitle_val); ?>" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end mb-3 w-full">
                        <div class="sm:col-span-2">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700">Upload Author Photo</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" id="author_photo_url" name="author_photo_url" class="flex-grow border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Browse author photo" readonly value="<?php echo esc_url($author_photo_url_val); ?>" />
                                <input type="hidden" id="author_photo_id" name="author_photo_id" value="<?php echo esc_attr($author_photo_id_val); ?>" />
                            </div>
                            <div id="author-photo-preview" class="mt-2 text-center" style="<?php echo empty($author_photo_url_val) ? 'display:none;' : ''; ?>">
                                <img src="<?php echo esc_url($author_photo_url_val); ?>" alt="Author Photo Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />
                                <button type="button" class="remove-author-photo text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="button button-secondary browse-author-photo bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Browse</button>
                        </div>
                    </div>

                    <div class="mb-3 w-full">
                        <label for="author_content" class="block text-xs sm:text-sm font-medium text-gray-700">Biography/Description:</label>
                        <?php
                        if ( current_user_can( 'edit_posts' ) ) {
                            wp_editor( $author_content_val, 'author_content', array(
                                'textarea_name' => 'author_content',
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
                            echo '<textarea name="author_content" id="author_content" rows="12" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">' . esc_textarea($author_content_val) . '</textarea>';
                        }
                        ?>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 mb-4 w-full">
                        <div>
                            <label for="author_category" class="block text-xs sm:text-sm font-medium text-gray-700">Category</label>
                            <select name="author_category" id="author_category" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm select2-category">
                                <option value="">-- Select Category --</option>
                                <?php
                                $categories = get_terms( array(
                                    'taxonomy'   => 'author_category',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $categories as $category ) {
                                    $selected = selected( $author_category_val, $category->slug, false );
                                    echo '<option value="' . esc_attr( $category->slug ) . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label for="author_level" class="block text-xs sm:text-sm font-medium text-gray-700">Level</label>
                            <select name="author_level" id="author_level" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm select2-level">
                                <option value="">-- Select Level --</option>
                                <?php
                                $levels = get_terms( array(
                                    'taxonomy'   => 'author_level',
                                    'hide_empty' => false,
                                ) );
                                foreach ( $levels as $level ) {
                                    $selected = selected( $author_level_val, $level->slug, false );
                                    echo '<option value="' . esc_attr( $level->slug ) . '" ' . $selected . '>' . esc_html( $level->name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4 w-full">
                        <div class="sm:col-span-2 flex flex-col">
                            <label for="submit_author_action_select" class="block text-xs sm:text-sm font-medium text-gray-700">Action</label>
                            <select name="submit_author_action" id="submit_author_action_select" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="save" <?php selected('save', get_post_status($author_post_id_val)); ?>>Save Draft</option>
                                <option value="publish" <?php selected('publish', get_post_status($author_post_id_val)); ?>>Publish Ready</option>
                                <option value="unpublish" <?php selected('draft', get_post_status($author_post_id_val)); ?>>Unpublish</option>
                                <option value="archive" <?php selected('archive', get_post_status($author_post_id_val)); ?>>Archive</option>
                                <option value="delete">Delete</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="submit_author_action_btn" value="submit" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>

                    <!-- Updated Placement Category Section -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4 w-full">
                        <div class="flex flex-col sm:col-span-2">
                            <label for="placement_category" class="block text-xs sm:text-sm font-medium text-gray-700">Placement Category</label>
                            <select name="placement_category" id="placement_category" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select Placement --</option>
                                <?php
                                $placements = array(
                                    'Home Left Section 1',
                                    'Home Left Section 2',
                                    'Home Right List 1',
                                    'Home Right List 2',
                                    'Article Left',
                                    'Article Right',
                                    'Article Footer',
                                    'Book Left',
                                    'Book Right',
                                    'Book Footer',
                                    'Video Left',
                                    'Video Right',
                                    'Video Footer'
                                );
                                $current_placement = wp_get_post_terms($author_post_id_val, 'placement_category', array('fields' => 'slugs'));
                                $current_placement_slug = !is_wp_error($current_placement) && !empty($current_placement) ? $current_placement[0] : '';

                                foreach ( $placements as $placement_name ) {
                                    $slug = sanitize_title($placement_name); // Convert name to slug
                                    $selected = selected( $current_placement_slug, $slug, false );
                                    echo '<option value="' . esc_attr( $slug ) . '" ' . $selected . '>' . esc_html( $placement_name ) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
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
            #author-editor-form {
                flex: 1 1 auto;
                min-height: 0;
                max-width: 100vw;
            }
            @media (max-width: 640px) {
                section.flex-1.flex .flex-1 {
                    padding: 0.5rem !important;
                }
                #author-editor-form {
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

        <!-- AI Content Generator sidebar (visual shell aligned with my-lesson-editor Content Endpoint) -->
        <aside class="w-full lg:w-1/4 bg-white border border-gray-300 rounded-lg p-4 lg:p-6 shadow-md max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">AI Content Generator</h2>

            <input type="hidden" id="automation_book_name" name="automation_book_name" value="N/A" autocomplete="off">

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Automation input</h3>
                <div class="mb-4">
                    <label for="author_name" class="block text-gray-700 text-sm font-bold mb-2">Author name</label>
                    <input type="text" id="author_name" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" placeholder="Enter author full name…" autocomplete="name">
                </div>
            </div>

            <div id="llm-model-selector-block" class="mb-6">
                <label for="llm_model_select" class="block text-gray-700 text-sm font-bold mb-2">AI Model</label>
                <select id="llm_model_select" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2">
                    <option value="deepseek_r1" selected>DeepSeek-R1</option>
                    <option value="gpt_4o">Kimi K2</option>
                    <option value="claude_3_5_sonnet">Qwen 3</option>
                </select>
                <p class="text-gray-500 text-xs mt-1">Active model: <span id="active-model-label" class="font-semibold text-gray-800">DeepSeek-R1</span></p>
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Difficulty level</label>
                <div class="inline-flex w-full rounded-none border border-gray-300 bg-gray-100 p-1 shadow-inner" role="radiogroup" aria-label="Difficulty level">
                    <label class="flex-1 cursor-pointer text-center min-w-0">
                        <input type="radio" name="difficulty_level" value="regular" class="peer sr-only" checked>
                        <span class="block rounded-none px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 peer-checked:bg-white peer-checked:text-gray-900 peer-checked:shadow peer-focus:ring-2 peer-focus:ring-indigo-500 peer-focus:ring-offset-2">Regular</span>
                    </label>
                    <label class="flex-1 cursor-pointer text-center min-w-0">
                        <input type="radio" name="difficulty_level" value="preparatory" class="peer sr-only">
                        <span class="block rounded-none px-3 py-2 text-sm font-medium text-gray-600 transition-all duration-200 peer-checked:bg-white peer-checked:text-gray-900 peer-checked:shadow peer-focus:ring-2 peer-focus:ring-indigo-500 peer-focus:ring-offset-2">Preparatory</span>
                    </label>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Content Types to Generate</h3>
                <div class="space-y-3">
                    <div class="content-type-card bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <label class="flex items-start space-x-3 cursor-pointer group">
                            <input type="checkbox" id="task_author_intro" class="content-type-checkbox mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" data-task-id="author_introduction" checked>
                            <div class="flex-1">
                                <span class="text-sm font-medium text-gray-900 group-hover:text-blue-700">Author Introduction</span>
                                <p class="text-xs text-gray-600 mt-1">Sends the author introduction task to n8n (same payload shape as v3).</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <button type="button" id="start_full_automation" class="generate-btn mae-generate-btn button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Start Automation Process</button>
            <div id="full_automation_status" class="response-status-message mt-4 p-3 hidden text-center font-semibold rounded"></div>

            <div class="mt-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-800">LLM output</h3>
                    <span class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Document preview</span>
                </div>
                <label for="final_generated_title" class="block text-gray-700 text-sm font-bold mb-2">Prompt Title:</label>
                <input type="text" id="final_generated_title" class="form-input mt-1 block w-full border border-gray-300 rounded-none shadow-sm p-2" readonly placeholder="Generated title…">

                <label for="final_generated_content" class="block text-gray-700 text-sm font-bold mb-2 mt-4">Prompt Content:</label>
                <div class="flex justify-end mb-2">
                    <button type="button" id="copy_final_generated_content_btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm">Copy to Clipboard</button>
                </div>
                <textarea id="final_generated_content" class="output-content-textarea shadow appearance-none border rounded-none w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="10" readonly placeholder="Generated content will appear here…"></textarea>
            </div>

            <button type="button" id="upload_final_content_to_editor" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full mt-4">Load to Main Editor</button>
            <button type="button" id="save_final_content_as_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full mt-2">Save as New Draft</button>
        </aside>
        
        <aside class="w-full lg:w-1/5 bg-white border border-gray-300 rounded-lg p-4 lg:p-6 shadow-md max-h-screen overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 mt-6">Generated Drafts Content</h2>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="select_title_ce" class="block text-gray-700 text-sm font-bold mb-2">
                        Select Title:
                    </label>
                    <select id="select_title_ce" class="w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Select Title --</option>
                        <?php
                        // Populate with existing author_profile posts (drafts and published)
                        $args = array(
                            'post_type'      => 'author_profile',
                            'posts_per_page' => -1,
                            'post_status'    => array( 'publish', 'draft', 'pending', 'archive' ),
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        );
                        $author_posts = get_posts( $args );
                        if ( ! empty( $author_posts ) ) {
                            foreach ( $author_posts as $author_post ) {
                                echo '<option value="' . esc_attr( $author_post->ID ) . '">' . esc_html( $author_post->post_title ) . ' (' . esc_html( $author_post->post_status ) . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="flex-1 hidden" id="draft_selection_container">
                    <label for="select_draft_ce" class="block text-gray-700 text-sm font-bold mb-2">
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
                    <button type="button" id="load_content_to_editor_from_draft" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Load Content to Editor</button>
                </div>
            </div>

            <div class="mt-6 border-t border-gray-300 pt-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Draft List</h3>
                <div id="draft_list_container" class="space-y-2">
                    <p class="text-gray-500 text-sm">Select a title above to view drafts.</p>
                </div>
            </div>
        </aside>

            <script>
            jQuery(document).ready(function($) {
                // ========================================================================
                // Draft List Management JavaScript
                // ========================================================================
                
                var cepNonce = typeof maeV3EditorAjax !== 'undefined' ? maeV3EditorAjax.cep_nonce : '';
                var ajaxUrl = typeof maeV3EditorAjax !== 'undefined' ? maeV3EditorAjax.ajaxurl : '';

                // Load draft list when title is selected
                $('#select_title_ce').on('change', function() {
                    var authorProfileId = $(this).val();
                    if (authorProfileId) {
                        loadDraftList(authorProfileId);
                    } else {
                        $('#draft_list_container').html('<p class="text-gray-500 text-xs sm:text-sm">Select a title above to view drafts.</p>');
                    }
                });

                /**
                 * Load draft list for selected author profile
                 */
                function loadDraftList(authorProfileId) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mae_v3_load_generated_draft_list',
                            nonce: cepNonce,
                            author_profile_id: authorProfileId
                        },
                        success: function(response) {
                            if (response.success && response.data.drafts.length > 0) {
                                renderDraftList(response.data.drafts);
                            } else {
                                $('#draft_list_container').html('<p class="text-gray-500 text-xs sm:text-sm">No drafts found for this title.</p>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading draft list:', error);
                            $('#draft_list_container').html('<p class="text-red-500 text-xs sm:text-sm">Error loading drafts. Please try again.</p>');
                        }
                    });
                }

                /**
                 * Render draft list items
                 */
                function renderDraftList(drafts) {
                    var html = '';
                    drafts.forEach(function(draft) {
                        html += '<div class="draft-item border border-gray-200 p-3 mb-2 flex items-center justify-between hover:bg-gray-50 transition-colors" data-draft-id="' + escapeHtml(draft.draft_id) + '">';
                        html += '<div class="flex-1">';
                        html += '<div class="font-semibold text-sm text-gray-800">' + escapeHtml(draft.title) + '</div>';
                        html += '<div class="text-xs text-gray-500 mt-1">';
                        html += '<span>' + escapeHtml(draft.agent_name) + '</span>';
                        html += ' <span class="mx-1">•</span> ';
                        html += '<span>' + escapeHtml(draft.date) + '</span>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="relative">';
                        html += '<button class="draft-menu-toggle p-2 hover:bg-gray-100 rounded focus:outline-none" data-draft-id="' + escapeHtml(draft.draft_id) + '">';
                        html += '<i class="fas fa-ellipsis-v text-gray-600"></i>';
                        html += '</button>';
                        html += '<div class="draft-menu-dropdown absolute right-0 mt-2 bg-white border border-gray-200 rounded shadow-lg hidden z-10 min-w-[180px]">';
                        html += '<button class="draft-load-btn block w-full text-left px-4 py-2 hover:bg-gray-100 text-sm text-gray-700" data-draft-id="' + escapeHtml(draft.draft_id) + '">Load to Main Editor</button>';
                        html += '<button class="draft-download-btn block w-full text-left px-4 py-2 hover:bg-gray-100 text-sm text-gray-700" data-draft-id="' + escapeHtml(draft.draft_id) + '">Download</button>';
                        html += '<button class="draft-delete-btn block w-full text-left px-4 py-2 hover:bg-red-100 text-red-600 text-sm" data-draft-id="' + escapeHtml(draft.draft_id) + '">Delete</button>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    $('#draft_list_container').html(html);
                }

                /**
                 * Escape HTML to prevent XSS
                 */
                function escapeHtml(text) {
                    var map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
                }

                /**
                 * Toggle dropdown menu on click
                 */
                $(document).on('click', '.draft-menu-toggle', function(e) {
                    e.stopPropagation();
                    var $dropdown = $(this).siblings('.draft-menu-dropdown');
                    var $allDropdowns = $('.draft-menu-dropdown');
                    
                    // Close all other dropdowns
                    $allDropdowns.not($dropdown).addClass('hidden');
                    
                    // Toggle current dropdown
                    $dropdown.toggleClass('hidden');
                });

                /**
                 * Close dropdown when clicking outside
                 */
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.draft-menu-toggle, .draft-menu-dropdown').length) {
                        $('.draft-menu-dropdown').addClass('hidden');
                    }
                });

                /**
                 * Load draft to main editor
                 */
                $(document).on('click', '.draft-load-btn', function(e) {
                    e.preventDefault();
                    var draftId = $(this).data('draft-id');
                    loadDraftToEditor(draftId);
                    $('.draft-menu-dropdown').addClass('hidden');
                });

                /**
                 * Download draft
                 */
                $(document).on('click', '.draft-download-btn', function(e) {
                    e.preventDefault();
                    var draftId = $(this).data('draft-id');
                    downloadDraft(draftId);
                    $('.draft-menu-dropdown').addClass('hidden');
                });

                /**
                 * Delete draft with confirmation
                 */
                $(document).on('click', '.draft-delete-btn', function(e) {
                    e.preventDefault();
                    var draftId = $(this).data('draft-id');
                    var $draftItem = $(this).closest('.draft-item');
                    
                    // Show confirmation popup
                    if (confirm('Are you sure you want to delete this draft?')) {
                        deleteDraft(draftId, $draftItem);
                    }
                    $('.draft-menu-dropdown').addClass('hidden');
                });

                /**
                 * Load draft content to TinyMCE main editor
                 */
                function loadDraftToEditor(draftId) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mae_v3_load_generated_draft',
                            nonce: cepNonce,
                            draft_id: draftId
                        },
                        success: function(response) {
                            if (response.success) {
                                var content = response.data.content;
                                
                                // Try to set content in TinyMCE editor
                                if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
                                    tinymce.get('author_content').setContent(content);
                                } else if ($('#author_content').length) {
                                    // Fallback to textarea
                                    $('#author_content').val(content);
                                }
                                
                                // Show success message
                                showToast('Draft loaded to editor successfully!', 'success');
                            } else {
                                showToast('Error loading draft: ' + (response.data || 'Unknown error'), 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading draft:', error);
                            showToast('Error loading draft. Please try again.', 'error');
                        }
                    });
                }

                /**
                 * Download draft as .txt file
                 */
                function downloadDraft(draftId) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mae_v3_download_generated_draft',
                            nonce: cepNonce,
                            draft_id: draftId
                        },
                        success: function(response) {
                            if (response.success) {
                                // Create blob and trigger download
                                var blob = new Blob([response.data.content], { type: 'text/plain;charset=utf-8' });
                                var url = window.URL.createObjectURL(blob);
                                var a = document.createElement('a');
                                a.href = url;
                                a.download = response.data.filename;
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                window.URL.revokeObjectURL(url);
                                
                                showToast('Draft downloaded successfully!', 'success');
                            } else {
                                showToast('Error downloading draft: ' + (response.data || 'Unknown error'), 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error downloading draft:', error);
                            showToast('Error downloading draft. Please try again.', 'error');
                        }
                    });
                }

                /**
                 * Delete draft with fade-out animation
                 */
                function deleteDraft(draftId, $draftItem) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mae_v3_delete_generated_draft',
                            nonce: cepNonce,
                            draft_id: draftId
                        },
                        success: function(response) {
                            if (response.success) {
                                // Fade out animation
                                $draftItem.fadeOut(300, function() {
                                    $(this).remove();
                                    
                                    // Reload draft list if container is empty or check if we need to show empty message
                                    var $container = $('#draft_list_container');
                                    if ($container.find('.draft-item').length === 0) {
                                        var authorProfileId = $('#select_title_ce').val();
                                        if (authorProfileId) {
                                            loadDraftList(authorProfileId);
                                        } else {
                                            $container.html('<p class="text-gray-500 text-xs sm:text-sm">Select a title above to view drafts.</p>');
                                        }
                                    }
                                });
                                
                                showToast('Draft deleted successfully!', 'success');
                            } else {
                                showToast('Error deleting draft: ' + (response.data || 'Unknown error'), 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error deleting draft:', error);
                            showToast('Error deleting draft. Please try again.', 'error');
                        }
                    });
                }

                /**
                 * Show toast notification
                 */
                function showToast(message, type) {
                    // Simple toast implementation
                    var bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
                    var toast = $('<div class="fixed top-4 right-4 ' + bgColor + ' text-white px-4 py-2 rounded shadow-lg z-50 text-sm">' + escapeHtml(message) + '</div>');
                    $('body').append(toast);
                    setTimeout(function() {
                        toast.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            });
            </script>



        
    </main>
    <?php
    return ob_get_clean();
}
if ( ! shortcode_exists( 'my_author_editor_test' ) ) {
    add_shortcode( 'my_author_editor_test', 'mae_v3_render_shortcode' );
}
// v3 Author Dashboard — use this tag on WordPress pages to avoid clashing with legacy `[my_author_editor_test]`.
if ( ! shortcode_exists( 'author_dashboard_v3' ) ) {
    add_shortcode( 'author_dashboard_v3', 'mae_v3_render_shortcode' );
}
// Preferred prefixed tag (avoids collisions with other plugins using generic names).
if ( ! shortcode_exists( 'mae_v3_author_dashboard' ) ) {
    add_shortcode( 'mae_v3_author_dashboard', 'mae_v3_render_shortcode' );
}

// Add rewrite rules (ensure this is present if you use permalinks for author profiles)
if ( ! function_exists( 'mae_v3_rewrite_rule' ) ) {
function mae_v3_rewrite_rule() {
    add_rewrite_rule(
        '^author-profile/([0-9]+)/?$',
        'index.php?post_type=author_profile&p=$matches[1]',
        'top'
    );
}
}
add_action( 'init', 'mae_v3_rewrite_rule' );

// Flush rewrite rules on plugin activation
function mae_v3_activate() {
    mae_v3_register_cpt(); // Ensure CPT is registered
    mae_v3_register_taxonomies(); // Ensure taxonomies are registered
    mae_v3_add_default_placement_terms(); // Add default terms for placement category
    mae_v3_seed_default_master_prompts();
    // Align with mae_v3_maybe_seed_master_prompts_upgrade() so admin_init does not repeat work after activation.
    update_option( 'mae_v3_master_prompts_seeded', '1', true );
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mae_v3_activate' );

// Flush rewrite rules on plugin deactivation
function mae_v3_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'mae_v3_deactivate' );


// AJAX handler for saving drafts (main editor form)
function mae_v3_save_draft_callback() {
    if ( ! check_ajax_referer( 'mae_v3_editor', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $post_id         = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $post_title      = sanitize_text_field( $_POST['post_title'] );
    $author_subtitle = sanitize_text_field( $_POST['author_subtitle'] );
    $author_photo_url = esc_url_raw( $_POST['author_photo_url'] );
    $author_body_content = wp_kses_post( $_POST['author_body_content'] );
    $author_category_slug = isset( $_POST['author_category'] ) ? sanitize_text_field( $_POST['author_category'] ) : '';
    $author_level_slug    = isset( $_POST['author_level'] ) ? sanitize_text_field( $_POST['author_level'] ) : '';
    $placement_category_slug = isset( $_POST['placement_category'] ) ? sanitize_text_field( $_POST['placement_category'] ) : ''; // New: placement_category
    $insert_link     = esc_url_raw( $_POST['insert_link'] );

    $post_args = array(
        'post_title'   => $post_title,
        'post_content' => $author_body_content,
        'post_type'    => 'author_profile',
        'post_status'  => 'draft',
    );

    if ( $post_id > 0 ) {
        $post_args['ID'] = $post_id;
        $new_post_id = wp_update_post( $post_args, true );
    } else {
        $new_post_id = wp_insert_post( $post_args, true );
    }

    if ( is_wp_error( $new_post_id ) ) {
        wp_send_json_error( $new_post_id->get_error_message() );
    }

    // Update custom fields
    update_post_meta( $new_post_id, 'author_subtitle', $author_subtitle );
    update_post_meta( $new_post_id, 'author_photo_url', $author_photo_url );
    update_post_meta( $new_post_id, 'insert_link', $insert_link );

    // Set taxonomies (using slugs now)
    if ( !empty( $author_category_slug ) ) {
        wp_set_object_terms( $new_post_id, $author_category_slug, 'author_category' );
    } else {
        wp_set_object_terms( $new_post_id, null, 'author_category' );
    }

    if ( !empty( $author_level_slug ) ) {
        wp_set_object_terms( $new_post_id, $author_level_slug, 'author_level' );
    } else {
        wp_set_object_terms( $new_post_id, null, 'author_level' );
    }

    if ( !empty( $placement_category_slug ) ) {
        wp_set_object_terms( $new_post_id, $placement_category_slug, 'placement_category' );
    } else {
        wp_set_object_terms( $new_post_id, null, 'placement_category' );
    }

    wp_send_json_success( array(
        'message' => 'Draft saved successfully!',
        'post_id' => $new_post_id
    ) );
}
add_action( 'wp_ajax_mae_v3_save_draft', 'mae_v3_save_draft_callback' );

// AJAX handler for publishing content (main editor form)
function mae_v3_publish_content_callback() {
    if ( ! check_ajax_referer( 'mae_v3_editor', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $post_id         = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $post_title      = sanitize_text_field( $_POST['post_title'] );
    $author_subtitle = sanitize_text_field( $_POST['author_subtitle'] );
    $author_photo_url = esc_url_raw( $_POST['author_photo_url'] );
    $author_body_content = wp_kses_post( $_POST['author_body_content'] );
    $author_category_slug = isset( $_POST['author_category'] ) ? sanitize_text_field( $_POST['author_category'] ) : '';
    $author_level_slug    = isset( $_POST['author_level'] ) ? sanitize_text_field( $_POST['author_level'] ) : '';
    $placement_category_slug = isset( $_POST['placement_category'] ) ? sanitize_text_field( $_POST['placement_category'] ) : ''; // New: placement_category
    $insert_link     = esc_url_raw( $_POST['insert_link'] );

    $post_args = array(
        'post_title'   => $post_title,
        'post_content' => $author_body_content,
        'post_type'    => 'author_profile',
        'post_status'  => 'publish', // Set status to publish
    );

    if ( $post_id > 0 ) {
        $post_args['ID'] = $post_id;
        $new_post_id = wp_update_post( $post_args, true );
    } else {
        $new_post_id = wp_insert_post( $post_args, true );
    }

    if ( is_wp_error( $new_post_id ) ) {
        wp_send_json_error( $new_post_id->get_error_message() );
    }

    // Update custom fields
    update_post_meta( $new_post_id, 'author_subtitle', $author_subtitle );
    update_post_meta( $new_post_id, 'author_photo_url', $author_photo_url );
    update_post_meta( $new_post_id, 'insert_link', $insert_link );

    // Set taxonomies (using slugs now)
    if ( !empty( $author_category_slug ) ) {
        wp_set_object_terms( $new_post_id, $author_category_slug, 'author_category' );
    } else {
        wp_set_object_terms( $new_post_id, null, 'author_category' );
    }

    if ( !empty( $author_level_slug ) ) {
        wp_set_object_terms( $new_post_id, $author_level_slug, 'author_level' );
    } else {
        wp_set_object_terms( $new_post_id, null, 'author_level' );
    }

    if ( !empty( $placement_category_slug ) ) {
        wp_set_object_terms( $new_post_id, $placement_category_slug, 'placement_category' );
    } else {
        wp_set_object_terms( $new_post_id, null, 'placement_category' );
    }

    wp_send_json_success( array(
        'message' => 'Content published successfully!',
        'post_id' => $new_post_id
    ) );
}
add_action( 'wp_ajax_mae_v3_publish_content', 'mae_v3_publish_content_callback' );

// AJAX handler for loading existing author content (main editor form)
function mae_v3_load_content_callback() {
    if ( ! check_ajax_referer( 'mae_v3_editor', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

    if ( $post_id <= 0 ) {
        wp_send_json_error( 'Invalid post ID.' );
    }

    $post = get_post( $post_id );

    if ( ! $post || $post->post_type !== 'author_profile' ) {
        wp_send_json_error( 'Author profile not found.' );
    }

    // Get custom fields
    $author_subtitle = get_post_meta( $post_id, 'author_subtitle', true );
    $author_photo_id = get_post_meta( $post_id, 'author_photo_id', true ); // Assuming you save ID
    $author_photo_url = get_post_meta( $post_id, 'author_photo_url', true ); // Assuming you save URL
    $insert_link     = get_post_meta( $post_id, 'insert_link', true );

    // Get categories, levels, and placement (slugs for display in select)
    $categories = wp_get_post_terms( $post_id, 'author_category', array( 'fields' => 'slugs' ) );
    $levels     = wp_get_post_terms( $post_id, 'author_level', array( 'fields' => 'slugs' ) );
    $placements = wp_get_post_terms( $post_id, 'placement_category', array( 'fields' => 'slugs' ) );

    wp_send_json_success( array(
        'post_title'      => $post->post_title,
        'author_subtitle' => $author_subtitle,
        'author_photo_id' => $author_photo_id, // Return ID
        'author_photo_url' => $author_photo_url,
        'author_body_content' => $post->post_content,
        'author_category' => empty( $categories ) ? '' : $categories[0], // Assuming single category/level
        'author_level'    => empty( $levels ) ? '' : $levels[0],
        'placement_category' => empty( $placements ) ? '' : $placements[0], // Return placement category
        'insert_link'     => $insert_link,
        'post_status'     => $post->post_status, // Return post status
    ) );
}
add_action( 'wp_ajax_mae_v3_load_content', 'mae_v3_load_content_callback' );


// AJAX handler for generating content from AI (individual agent)
function mae_v3_generate_ai_content_callback() {
    // Verify nonce for security
    if ( ! check_ajax_referer( 'mae_v3_ai', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $prompt        = sanitize_textarea_field( $_POST['prompt'] );
    $ai_model      = sanitize_text_field( $_POST['ai_model'] );
    $agent_id      = sanitize_text_field( $_POST['agent_id'] );
    $reference_file_content = '';

    // Handle reference file if uploaded
    if ( isset( $_FILES['reference_file'] ) && ! empty( $_FILES['reference_file']['tmp_name'] ) ) {
        $uploaded_file = $_FILES['reference_file'];
        $reference_file_content = file_get_contents( $uploaded_file['tmp_name'] );
    }

    if ( empty( $prompt ) ) {
        wp_send_json_error( 'Prompt cannot be empty.' );
    }

    $webhook_url = '';
    switch ( $ai_model ) {
        case 'deepseek_r1':
            $webhook_url = MAE_V3_WEBHOOK_URL_DEEPSEEK_R1;
            break;
        case 'deepseek_v3':
            $webhook_url = MAE_V3_WEBHOOK_URL_DEEPSEEK_V3;
            break;
        case 'qwen_2_5':
            $webhook_url = MAE_V3_WEBHOOK_URL_QWEN_2_5;
            break;
        default:
            wp_send_json_error( 'Invalid AI model selected.' );
    }

    if ( empty( $webhook_url ) || $webhook_url === 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391' ||
         $webhook_url === 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391' ||
         $webhook_url === 'https://n8n.srv835792.hstgr.cloud/webhook/271911c6-73ad-4b75-bdcb-6d9692b97391' ) {
        wp_send_json_error( 'AI Model Webhook URL for ' . $ai_model . ' is not configured in my-author-editor.php.' );
    }

    $payload = array(
        'prompt'    => $prompt,
        'ai_model'  => $ai_model,
        'agent_id'  => $agent_id,
    );

    if ( isset( $reference_file_content ) ) {
        $payload['reference_file_content'] = base64_encode( $reference_file_content ); // Encode content if sending directly
        $payload['reference_file_name'] = $uploaded_file['name'];
        $payload['reference_file_type'] = $uploaded_file['type'];
    }
    
    $response = wp_remote_post( $webhook_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body'    => json_encode( $payload ),
        'method'  => 'POST',
        'timeout' => 360, // 6 minutes for LLM generation
    ));

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Error sending data to AI webhook: ' . $response->get_error_message() );
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $body      = wp_remote_retrieve_body( $response );

    if ( $http_code === 200 || $http_code === 201 ) {
        $n8n_response_data = json_decode( $body, true );
        if ( json_last_error() === JSON_ERROR_NONE && isset( $n8n_response_data['generated_content'] ) ) {
            wp_send_json_success( array( 'content' => $n8n_response_data['generated_content'] ) );
        } else {
            wp_send_json_success( array( 'content' => $body, 'message' => 'Raw response from n8n webhook.' ) );
        }
    } else {
        wp_send_json_error( 'AI webhook returned an error: HTTP ' . $http_code . ' - ' . $body );
    }
}
add_action( 'wp_ajax_mae_v3_generate_ai_content', 'mae_v3_generate_ai_content_callback' );
add_action( 'wp_ajax_nopriv_mae_v3_generate_ai_content', 'mae_v3_generate_ai_content_callback' );


// AJAX handler for loading content based on title and draft (placeholder for AI integration)
function mae_v3_load_content_from_ai_callback() {
    if ( ! check_ajax_referer( 'mae_v3_editor', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $selected_title = sanitize_text_field( $_POST['selected_title'] );
    $selected_draft = sanitize_text_field( $_POST['selected_draft'] );

    // In a real scenario, you would query your AI content storage or external service
    // based on $selected_title and $selected_draft to retrieve the content.
    // For now, this is a placeholder.

    // Example dummy data based on draft selection
    $content = '';
    $title_prefix = '';

    if ($selected_title) {
        $title_prefix = "Content for '{$selected_title}' - ";
    }

    switch ($selected_draft) {
        case 'draft1':
            $content = $title_prefix . "This is content generated by Agent 1 (Deepseek R1). It focuses on outlining.";
            break;
        case 'draft2':
            $content = $title_prefix . "This is content generated by Agent 2 (Deepseek V3). It provides expanded details.";
            break;
        case 'draft3':
            $content = $title_prefix . "This is content generated by Agent 3 (Qwen 2.5). It refines the tone and adds specifics.";
            break;
        default:
            $content = "Please select a valid draft to load AI generated content.";
            break;
    }

    wp_send_json_success( array( 'content' => $content ) );
}
add_action( 'wp_ajax_mae_v3_load_content_from_ai', 'mae_v3_load_content_from_ai_callback' );
add_action( 'wp_ajax_nopriv_mae_v3_load_content_from_ai', 'mae_v3_load_content_from_ai_callback' );


// AJAX handler for generating content from last agent
function mae_v3_generate_from_last_agent_callback() {
    if ( ! check_ajax_referer( 'mae_v3_ai', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $previous_content = wp_kses_post( $_POST['previous_content'] );
    $last_agent_model = sanitize_text_field( $_POST['last_agent_model'] ); // e.g., 'qwen_2_5' for Agent 3
    $n8n_main_webhook_url = MAE_V3_N8N_MAIN_WEBHOOK_URL; // Using the main webhook for sequential processing

    if ( empty( $n8n_main_webhook_url ) || $n8n_main_webhook_url === 'YOUR_N8N_MAIN_WEBHOOK_URL_HERE' ) {
        wp_send_json_error( 'n8n Main Webhook URL is not configured for sequential agent processing.' );
    }

    // Construct a payload suitable for your n8n workflow for sequential agent processing.
    // This example assumes n8n will take 'previous_content' and 'target_model' to trigger the next step.
    $payload = array(
        'type'             => 'sequential_agent_trigger',
        'previous_content' => $previous_content,
        'target_model'     => $last_agent_model, // Tell n8n which agent/model to use next
    );

    $response = wp_remote_post( $n8n_main_webhook_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body'    => json_encode( $payload ),
        'method'  => 'POST',
        'timeout' => 360, // 6 minutes for LLM generation
    ));

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Error sending data to n8n for sequential processing: ' . $response->get_error_message() );
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $body      = wp_remote_retrieve_body( $response );

    if ( $http_code === 200 || $http_code === 201 ) {
        $n8n_response_data = json_decode( $body, true );
        if ( json_last_error() === JSON_ERROR_NONE && isset( $n8n_response_data['generated_content'] ) ) {
            wp_send_json_success( array( 'content' => $n8n_response_data['generated_content'] ) );
        } else {
            wp_send_json_success( array( 'content' => $body, 'message' => 'Raw response from n8n webhook.' ) );
        }
    } else {
        wp_send_json_error( 'n8n webhook for sequential processing returned an error: HTTP ' . $http_code . ' - ' . $body );
    }
}
add_action( 'wp_ajax_mae_v3_generate_from_last_agent', 'mae_v3_generate_from_last_agent_callback' );
add_action( 'wp_ajax_nopriv_mae_v3_generate_from_last_agent', 'mae_v3_generate_from_last_agent_callback' );

// AJAX handler for saving generated content as a new draft
function mae_v3_save_generated_content_as_draft_callback() {
    if ( ! check_ajax_referer( 'mae_v3_ai', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $post_title = sanitize_text_field( $_POST['post_title'] );
    $post_content = wp_kses_post( $_POST['post_content'] );

    if ( empty( $post_title ) || empty( $post_content ) ) {
        wp_send_json_error( 'Title and content cannot be empty.' );
    }

    $new_post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $post_content,
        'post_status'   => 'draft',
        'post_type'     => 'author_profile', // Save as author_profile draft
    );

    $new_post_id = wp_insert_post( $new_post_data, true );

    if ( is_wp_error( $new_post_id ) ) {
        wp_send_json_error( $new_post_id->get_error_message() );
    } else {
        wp_send_json_success( array( 'message' => 'New draft created successfully!', 'post_id' => $new_post_id ) );
    }
}
add_action( 'wp_ajax_mae_v3_save_generated_content_as_draft', 'mae_v3_save_generated_content_as_draft_callback' );
add_action( 'wp_ajax_nopriv_mae_v3_save_generated_content_as_draft', 'mae_v3_save_generated_content_as_draft_callback' );

// ============================================================================
// AJAX Handlers for Generated Drafts Management
// ============================================================================

/**
 * AJAX handler for fetching draft list for a selected author_profile
 */
function mae_v3_get_draft_list_callback() {
    // Verify nonce for security
    if ( ! check_ajax_referer( 'mae_v3_cep', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $author_profile_id = isset( $_POST['author_profile_id'] ) ? intval( $_POST['author_profile_id'] ) : 0;

    if ( $author_profile_id <= 0 ) {
        wp_send_json_error( 'Invalid author profile ID.' );
    }

    // Verify the post exists and is an author_profile
    $author_post = get_post( $author_profile_id );
    if ( ! $author_post || $author_post->post_type !== 'author_profile' ) {
        wp_send_json_error( 'Author profile not found.' );
    }

    // Get all drafts stored as post meta
    // Drafts are stored as: _generated_draft_{timestamp}_{unique_id} or _generated_draft_{id}
    $all_meta = get_post_meta( $author_profile_id );
    $drafts = array();

    foreach ( $all_meta as $key => $value ) {
        if ( strpos( $key, '_generated_draft_' ) === 0 ) {
            $draft_data = maybe_unserialize( $value[0] );
            if ( is_array( $draft_data ) && isset( $draft_data['content'] ) ) {
                // Extract timestamp from key (format: _generated_draft_{timestamp}_{id}) or use stored timestamp
                $parts = explode( '_', $key );
                $timestamp = 0;
                
                // Try to get timestamp from key (format: _generated_draft_{timestamp}_{id})
                if ( isset( $parts[3] ) && is_numeric( $parts[3] ) ) {
                    $timestamp = intval( $parts[3] );
                }
                
                // If timestamp not in key, try to get from draft_data
                if ( $timestamp === 0 && isset( $draft_data['timestamp'] ) ) {
                    $timestamp = intval( $draft_data['timestamp'] );
                }
                
                // If still no timestamp, use current time
                if ( $timestamp === 0 ) {
                    $timestamp = time();
                }

                $drafts[] = array(
                    'draft_id'   => $key, // Use the meta key as draft_id
                    'title'      => isset( $draft_data['title'] ) ? $draft_data['title'] : 'Untitled Draft',
                    'content'    => $draft_data['content'],
                    'timestamp'  => $timestamp,
                    'agent_name' => isset( $draft_data['agent_name'] ) ? $draft_data['agent_name'] : 'Unknown Agent',
                    'date'       => $timestamp > 0 ? date( 'Y-m-d H:i:s', $timestamp ) : 'Unknown date',
                );
            }
        }
    }

    // Sort by timestamp (newest first)
    usort( $drafts, function( $a, $b ) {
        return $b['timestamp'] - $a['timestamp'];
    } );

    wp_send_json_success( array( 'drafts' => $drafts ) );
}
add_action( 'wp_ajax_mae_v3_load_generated_draft_list', 'mae_v3_get_draft_list_callback' );

/**
 * AJAX handler for loading a generated draft to the main editor
 */
function mae_v3_load_generated_draft_callback() {
    // Verify nonce for security
    if ( ! check_ajax_referer( 'mae_v3_cep', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $draft_id = isset( $_POST['draft_id'] ) ? sanitize_text_field( $_POST['draft_id'] ) : '';

    if ( empty( $draft_id ) ) {
        wp_send_json_error( 'Draft ID is required.' );
    }

    // Find the post that has this meta key
    $posts = get_posts( array(
        'post_type'      => 'author_profile',
        'posts_per_page' => -1,
        'meta_key'       => $draft_id,
        'meta_compare'   => 'EXISTS',
    ) );

    if ( empty( $posts ) ) {
        wp_send_json_error( 'Draft not found.' );
    }

    $author_profile_id = $posts[0]->ID;
    $draft_data = get_post_meta( $author_profile_id, $draft_id, true );
    $draft_data = maybe_unserialize( $draft_data );

    if ( empty( $draft_data ) || ! is_array( $draft_data ) || ! isset( $draft_data['content'] ) ) {
        wp_send_json_error( 'Draft content not found.' );
    }

    wp_send_json_success( array(
        'content' => $draft_data['content'],
        'title'   => isset( $draft_data['title'] ) ? $draft_data['title'] : '',
    ) );
}
add_action( 'wp_ajax_mae_v3_load_generated_draft', 'mae_v3_load_generated_draft_callback' );

/**
 * AJAX handler for downloading a generated draft as .txt file
 * Returns content as JSON, JavaScript will trigger the download
 */
function mae_v3_download_generated_draft_callback() {
    // Verify nonce for security
    if ( ! check_ajax_referer( 'mae_v3_cep', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $draft_id = isset( $_POST['draft_id'] ) ? sanitize_text_field( $_POST['draft_id'] ) : '';

    if ( empty( $draft_id ) ) {
        wp_send_json_error( 'Draft ID is required.' );
    }

    // Find the draft
    $posts = get_posts( array(
        'post_type'      => 'author_profile',
        'posts_per_page' => -1,
        'meta_key'       => $draft_id,
        'meta_compare'   => 'EXISTS',
    ) );

    if ( empty( $posts ) ) {
        wp_send_json_error( 'Draft not found.' );
    }

    $author_profile_id = $posts[0]->ID;
    $draft_data = get_post_meta( $author_profile_id, $draft_id, true );
    $draft_data = maybe_unserialize( $draft_data );

    if ( empty( $draft_data ) || ! is_array( $draft_data ) || ! isset( $draft_data['content'] ) ) {
        wp_send_json_error( 'Draft content not found.' );
    }

    $title = isset( $draft_data['title'] ) ? sanitize_file_name( $draft_data['title'] ) : 'draft';
    $content = $draft_data['content'];
    
    // Strip HTML tags for plain text download
    $plain_content = wp_strip_all_tags( $content );

    wp_send_json_success( array(
        'content' => $plain_content,
        'filename' => $title . '_' . time() . '.txt',
    ) );
}
add_action( 'wp_ajax_mae_v3_download_generated_draft', 'mae_v3_download_generated_draft_callback' );

/**
 * AJAX handler for deleting a generated draft
 */
function mae_v3_delete_generated_draft_callback() {
    // Verify nonce for security
    if ( ! check_ajax_referer( 'mae_v3_cep', 'nonce', false ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
    }

    $draft_id = isset( $_POST['draft_id'] ) ? sanitize_text_field( $_POST['draft_id'] ) : '';

    if ( empty( $draft_id ) ) {
        wp_send_json_error( 'Draft ID is required.' );
    }

    // Find the draft
    $posts = get_posts( array(
        'post_type'      => 'author_profile',
        'posts_per_page' => -1,
        'meta_key'       => $draft_id,
        'meta_compare'   => 'EXISTS',
    ) );

    if ( empty( $posts ) ) {
        wp_send_json_error( 'Draft not found.' );
    }

    $author_profile_id = $posts[0]->ID;
    
    // Delete the draft meta
    $deleted = delete_post_meta( $author_profile_id, $draft_id );

    if ( $deleted ) {
        wp_send_json_success( array( 'message' => 'Draft deleted successfully.' ) );
    } else {
        wp_send_json_error( 'Failed to delete draft.' );
    }
}
add_action( 'wp_ajax_mae_v3_delete_generated_draft', 'mae_v3_delete_generated_draft_callback' );