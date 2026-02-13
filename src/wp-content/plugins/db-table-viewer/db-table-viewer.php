<?php
/*
Plugin Name: DB Table Viewer
Description: View and paginate database table data in a user-friendly format.
Version: 1.0
Author: Vrutti22
Author URI: https://profiles.wordpress.org/vrutti22/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: db-table-viewer
Domain Path: /languages
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class DBTableViewer {

    public function __construct() {
        // Hooks for admin menu, scripts, and AJAX handlers.
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_get_table_data', [$this, 'get_table_data']);
    }

    /**
     * Add the plugin page to the Tools menu.
     */
    public function add_admin_menu() {
        add_management_page(
            __('DB Table Viewer', 'db-table-viewer'),
            __('DB Table Viewer', 'db-table-viewer'),
            'manage_options',
            'db-table-viewer',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue required scripts and styles for the plugin.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'tools_page_db-table-viewer') {
            return;
        }

        wp_enqueue_script(
            'db-table-viewer-js',
            plugin_dir_url(__FILE__) . 'assets/js/db-table-viewer.js',
            ['jquery'],
            '1.0',
            true
        );
        
        wp_localize_script('db-table-viewer-js', 'DBTableViewer', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);

        wp_enqueue_style(
            'db-table-viewer-css',
            plugin_dir_url(__FILE__) . 'assets/css/db-table-viewer.css',
            [],
            '1.0'
        );
    }

    /**
     * Render the admin page for the plugin.
     */
    public function render_admin_page() {
        global $wpdb;

        $tables = $wpdb->get_col('SHOW TABLES');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('DB Table Viewer', 'db-table-viewer'); ?></h1>
            <label for="db-tables"><?php esc_html_e('Select a Table:', 'db-table-viewer'); ?></label>
            <select id="db-tables">
                <option value=""><?php esc_html_e('-- Select a Table --', 'db-table-viewer'); ?></option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?php echo esc_attr($table); ?>"><?php echo esc_html($table); ?></option>
                <?php endforeach; ?>
            </select>
            <div id="table-data" style="margin-top: 20px;"></div>
        </div>
        <?php
    }

    /**
     * Handle AJAX requests for fetching table data.
     */
    public function get_table_data() {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'db-table-viewer'));
        }

        $table_name = sanitize_text_field($_POST['table_name']);
        $page = absint($_POST['page']);
        $rows_per_page = 10;

        if (empty($table_name)) {
            wp_send_json_error(__('Table name is required', 'db-table-viewer'));
        }

        $offset = ($page - 1) * $rows_per_page;
        $data = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `" . esc_sql($table_name) . "` LIMIT %d OFFSET %d", $rows_per_page, $offset),
            ARRAY_A
        );
        $total_rows = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `" . esc_sql($table_name) . "`"));

        if (empty($data)) {
            wp_send_json_error(__('No data found or table is empty', 'db-table-viewer'));
        }

        $output = '<table class="widefat striped"><thead><tr>';
        foreach (array_keys($data[0]) as $column) {
            $output .= '<th>' . esc_html($column) . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        foreach ($data as $row) {
            $output .= '<tr>';
            foreach ($row as $value) {
                $output .= '<td>' . esc_html($value) . '</td>';
            }
            $output .= '</tr>';
        }
        $output .= '</tbody></table>';

        // Pagination
        $total_pages = ceil($total_rows / $rows_per_page);
        $pagination = '<div class="pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $pagination .= sprintf(
                '<button class="page-button" data-page="%d">%d</button>',
                $i,
                $i
            );
        }
        $pagination .= '</div>';

        wp_send_json_success($output . $pagination);
    }
}

new DBTableViewer();
