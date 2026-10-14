<?php
/*
Plugin Name: Custom Role-Based Dashboard
Description: Provides a custom dashboard for specific user roles to access Calendar, Project Management, Notes, and Publish Content.
Version: 1.2
Author: Muhamad Fikri
*/

// Redirect non-admin users trying to access /wp-admin to custom dashboard
add_action('admin_init', function () {
    // admin-ajax.php and admin-post.php also fire admin_init; front-end logins, lesson player and forms depend on them.
    if (wp_doing_ajax() || wp_doing_cron() || (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'admin-post.php')) {
        return;
    }
    if (!current_user_can('administrator') && is_admin()) {
        wp_redirect(site_url('/website-administrator-dashboard/'));
        exit;
    }
});

// Force redirect after login for non-admin users to custom dashboard
add_filter('login_redirect', function ($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles) && !in_array('administrator', $user->roles)) {
        return site_url('/website-administrator-dashboard/');
    }
    return $redirect_to;
}, 10, 3);

// Shortcode to display the dashboard or login form
add_shortcode('custom_role_dashboard', function () {
    if (!is_user_logged_in()) {
        ob_start();
        echo '<div class="login-notice"><p>Please log in to access the dashboard.</p></div>';
        wp_login_form([
            'redirect' => site_url('/website-administrator-dashboard/'),
        ]);
        return ob_get_clean();
    }

    ob_start();
    ?>
    <div class="custom-dashboard">
        <h2>Welcome to Your Dashboard</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <a href="https://caastedu.com/school-management-login-page/" class="dashboard-card">📅 Calendar</a>
            <a href="https://caastedu.com/wp-admin/admin.php?page=fluent-boards#/" class="dashboard-card">🗂️ Project Management</a>
            <a href="https://caastedu.com/student-notes/" class="dashboard-card">📝 Notes</a>
            <a href="https://caastedu.com/content-editor-frontend/" class="dashboard-card">🚀 Publish Content</a>
        </div>
        <style>
            .dashboard-card {
                padding: 20px;
                background: #f1f1f1;
                border-radius: 10px;
                text-decoration: none;
                font-weight: bold;
                font-size: 1.2em;
                flex: 1 1 200px;
                text-align: center;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                transition: transform 0.2s ease;
            }
            .dashboard-card:hover {
                transform: translateY(-5px);
                background: #e0e0e0;
            }
        </style>
    </div>
    <?php
    return ob_get_clean();
});

// Register custom dashboard endpoint
add_action('init', function () {
    add_rewrite_rule('^custom-dashboard/?$', 'index.php?custom_dashboard=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'custom_dashboard';
    return $vars;
});

// Load dashboard page content
add_action('template_redirect', function () {
    if (get_query_var('custom_dashboard')) {
        status_header(200);
        echo do_shortcode('[custom_role_dashboard]');
        exit;
    }
});