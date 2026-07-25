<?php

/**
 * Plugin Name: Picot AIO AI Content Optimizer
 * Plugin URI: https://github.com/tsubu/picot-aio-ai-content-optimizer
 * Description: AI-powered content analysis and optimization using Google Gemini via the WordPress AI Client. Provides SEO advice, content recommendations, and automated image generation for WordPress posts and pages.
 * Version: 1.1.1
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: tsubu
 * Author URI: https://picot.tokyo/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: picot-aio-ai-content-optimizer
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}


// Define plugin constants
define('PICOT_AIO_OPTIMIZER_VERSION', '1.1.1');
define('PICOT_AIO_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PICOT_AIO_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Configuration Constants
define('PICOT_AIO_OPTIMIZER_HISTORY_LIMIT', 20);              // int - Number of history items to display
define('PICOT_AIO_OPTIMIZER_FILENAME_MAX_LENGTH', 50);        // int - Maximum length for generated image filenames
define('PICOT_AIO_OPTIMIZER_IMAGE_DENSITY_THRESHOLD', 500);   // int - Reserved for future use
define('PICOT_AIO_OPTIMIZER_API_TIMEOUT', 120);               // int - Timeout in seconds for text generation API calls
define('PICOT_AIO_OPTIMIZER_IMAGE_API_TIMEOUT', 90);          // int - Timeout in seconds for image generation API calls
define('PICOT_AIO_OPTIMIZER_REWRITE_MAX_TOKENS', 65536);      // int - Max output tokens for rewrite (long articles)
define('PICOT_AIO_OPTIMIZER_LOG_RETENTION_DAYS', 90);         // int - Number of days to retain analysis logs before auto-deletion

// Modular Includes
require_once PICOT_AIO_OPTIMIZER_PLUGIN_PATH . 'includes/ai-client-helper.php';
require_once PICOT_AIO_OPTIMIZER_PLUGIN_PATH . 'includes/model-manager.php';
require_once PICOT_AIO_OPTIMIZER_PLUGIN_PATH . 'includes/gemini-client.php';
require_once PICOT_AIO_OPTIMIZER_PLUGIN_PATH . 'includes/database.php';
require_once PICOT_AIO_OPTIMIZER_PLUGIN_PATH . 'includes/admin-views.php';
require_once PICOT_AIO_OPTIMIZER_PLUGIN_PATH . 'includes/rest-handlers.php';
require_once PICOT_AIO_OPTIMIZER_PLUGIN_PATH . 'includes/media.php';

/**
 * Main plugin class
 */
class PicotAioOptimizer
{
    public function __construct()
    {
        // Hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'maybe_show_ai_plugin_notice'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        add_action('rest_api_init', array($this, 'gar_register_rest_routes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('picot_aio_optimizer_cleanup_old_logs', array('PicotAioOptimizer_Database', 'cleanup_old_logs'));

        // Classic Editor support
        add_action('add_meta_boxes', array($this, 'add_meta_box'));

        // DB 初期化は管理画面のみ。フロントの毎リクエスト実行を避ける。
        add_action('admin_init', array('PicotAioOptimizer_Database', 'gar_init_db'));
    }

    /**
     * Prompt to install/activate the official AI plugin on the post editor.
     *
     * @return void
     */
    public function maybe_show_ai_plugin_notice()
    {
        if (!current_user_can('activate_plugins') || PicotAioOptimizer_Ai_Client_Helper::is_ai_plugin_active()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->base, array('post', 'post-new'), true)) {
            return;
        }

        if (!PicotAioOptimizer_Ai_Client_Helper::is_google_configured()) {
            return;
        }

        PicotAioOptimizer_Ai_Client_Helper::print_ai_plugin_requirement_notice();
    }

    public function admin_init()
    {
        // Manual Settings Save Handler
        if (isset($_POST['picot_aio_optimizer_manual_save']) && $_POST['picot_aio_optimizer_manual_save'] === '1') {
            if (!isset($_POST['picot_aio_optimizer_save_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['picot_aio_optimizer_save_nonce'])), 'picot_aio_optimizer_save_settings')) {
                wp_die(esc_html__('Security check failed', 'picot-aio-ai-content-optimizer'));
            }
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to change these options.', 'picot-aio-ai-content-optimizer'));
            }

            // Save Inputs
            if (isset($_POST['picot_aio_optimizer_api_plan'])) {
                update_option(
                    'picot_aio_optimizer_api_plan',
                    PicotAioOptimizer_Ai_Client_Helper::sanitize_api_plan(
                        sanitize_text_field(wp_unslash($_POST['picot_aio_optimizer_api_plan']))
                    )
                );
            }
            if (isset($_POST['picot_aio_optimizer_model'])) {
                $model = PicotAioOptimizer_Ai_Client_Helper::normalize_model_spec(
                    sanitize_text_field(wp_unslash($_POST['picot_aio_optimizer_model']))
                );
                if ($model !== '') {
                    update_option('picot_aio_optimizer_model', $model);
                }
            }
            // 有償かつチェックボックスが操作可能なときだけ更新。無償時は disabled で POST されないため、
            // 保存済みの「有効」設定を消さない。free→paid 同一保存で JS 無効の場合も既存値を維持する。
            if (PicotAioOptimizer_Ai_Client_Helper::is_paid_api_plan()) {
                if (
                    isset($_POST['picot_aio_optimizer_enable_image_gen'])
                    || isset($_POST['picot_aio_optimizer_image_gen_editable'])
                ) {
                    $enable_gen = isset($_POST['picot_aio_optimizer_enable_image_gen']) ? 1 : 0;
                    update_option('picot_aio_optimizer_enable_image_gen', $enable_gen);
                }
            }

            if (isset($_POST['picot_aio_optimizer_image_style'])) {
                update_option(
                    'picot_aio_optimizer_image_style',
                    PicotAioOptimizer_Admin_Views::sanitize_image_style(
                        sanitize_text_field(wp_unslash($_POST['picot_aio_optimizer_image_style']))
                    )
                );
            }
            if (isset($_POST['picot_aio_optimizer_image_model'])) {
                $image_model = PicotAioOptimizer_Ai_Client_Helper::normalize_model_spec(
                    sanitize_text_field(wp_unslash($_POST['picot_aio_optimizer_image_model']))
                );
                if ($image_model !== '') {
                    update_option('picot_aio_optimizer_image_model', $image_model);
                }
            }

            // Force Table Check on Save (just to be safe)
            PicotAioOptimizer_Database::gar_init_db();

            // Redirect back
            $redirect_url = add_query_arg('settings-updated', 'true', admin_url('options-general.php?page=picot_aio_optimizer'));
            wp_safe_redirect($redirect_url);
            exit;
        }

        register_setting('picot_aio_optimizer_settings_group', 'picot_aio_optimizer_api_plan', array(
            'sanitize_callback' => array('PicotAioOptimizer_Ai_Client_Helper', 'sanitize_api_plan'),
            'default'           => 'paid',
        ));
        register_setting('picot_aio_optimizer_settings_group', 'picot_aio_optimizer_model', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('picot_aio_optimizer_settings_group', 'picot_aio_optimizer_enable_image_gen', array('sanitize_callback' => 'absint'));
        register_setting('picot_aio_optimizer_settings_group', 'picot_aio_optimizer_image_style', array(
            'sanitize_callback' => array('PicotAioOptimizer_Admin_Views', 'sanitize_image_style'),
            'default'           => 'none',
        ));
        register_setting('picot_aio_optimizer_settings_group', 'picot_aio_optimizer_image_model', array('sanitize_callback' => 'sanitize_text_field'));
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="options-general.php?page=picot_aio_optimizer">' . esc_html__('Settings', 'picot-aio-ai-content-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_admin_menu()
    {
        add_options_page(
            __('Picot AIO AI Content Optimizer Settings', 'picot-aio-ai-content-optimizer'),
            __('Picot AIO AI Content Optimizer', 'picot-aio-ai-content-optimizer'),
            'manage_options',
            'picot_aio_optimizer',
            array('PicotAioOptimizer_Admin_Views', 'admin_page')
        );
    }

    public function add_meta_box()
    {
        // Skip registration in Gutenberg (Block Editor) - sidebar plugin handles it instead
        $screen = get_current_screen();
        if ($screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
            return;
        }

        add_meta_box(
            'picot_aio_optimizer_meta_box',
            __('Picot AIO AI Content Optimizer', 'picot-aio-ai-content-optimizer'),
            array('PicotAioOptimizer_Admin_Views', 'render_meta_box'),
            array('post', 'page'),
            'side',
            'default'
        );
    }

    /**
     * Register REST API Routes
     */
    public function gar_register_rest_routes()
    {
        $ns = 'picot_aio_optimizer/v1';

        register_rest_route($ns, '/rewrite', array(
            'methods' => 'POST',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'rewrite_article'),
            'permission_callback' => array('PicotAioOptimizer_REST_Handlers', 'can_edit_post_param'),
        ));

        register_rest_route($ns, '/models', array(
            'methods' => 'POST',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'fetch_models'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ));

        register_rest_route($ns, '/analyze', array(
            'methods' => 'POST',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'analyze_content'),
            'permission_callback' => array('PicotAioOptimizer_REST_Handlers', 'can_edit_post_param'),
        ));

        register_rest_route($ns, '/generate-image', array(
            'methods' => 'POST',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'generate_image'),
            'permission_callback' => array('PicotAioOptimizer_REST_Handlers', 'can_generate_image'),
        ));

        register_rest_route($ns, '/history', array(
            'methods' => 'GET',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'fetch_history'),
            'permission_callback' => array('PicotAioOptimizer_REST_Handlers', 'can_fetch_history'),
        ));

        register_rest_route($ns, '/suggest-images', array(
            'methods' => 'POST',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'suggest_images'),
            'permission_callback' => array('PicotAioOptimizer_REST_Handlers', 'can_edit_post_param'),
        ));

        register_rest_route($ns, '/save-image-suggestions', array(
            'methods' => 'POST',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'save_suggestions'),
            'permission_callback' => array('PicotAioOptimizer_REST_Handlers', 'can_edit_post_param'),
        ));

        register_rest_route($ns, '/load-image-suggestions', array(
            'methods' => 'GET',
            'callback' => array('PicotAioOptimizer_REST_Handlers', 'load_suggestions'),
            'permission_callback' => array('PicotAioOptimizer_REST_Handlers', 'can_edit_post_param'),
        ));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on post edit pages and the plugin settings page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin page slug only, not processing form data
        $is_settings_page = (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'picot_aio_optimizer');

        if ('post.php' !== $hook && 'post-new.php' !== $hook && ! $is_settings_page) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $is_settings_page && $screen && ! in_array($screen->post_type, array('post', 'page'), true)) {
            return;
        }

        wp_enqueue_style(
            'picot_aio_optimizer-admin-css',
            plugins_url('admin/admin.css', __FILE__),
            array(),
            PICOT_AIO_OPTIMIZER_VERSION
        );

        $is_block_editor = $screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor();

        // Block editor needs @wordpress/* packages; classic editor only needs jQuery.
        if ($is_settings_page) {
            $deps = array('jquery');
        } elseif ($is_block_editor) {
            $deps = array('jquery', 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-dom-ready');
        } else {
            $deps = array('jquery');
        }

        wp_enqueue_script(
            'picot_aio_optimizer-admin',
            plugins_url('admin/admin.js', __FILE__),
            $deps,
            PICOT_AIO_OPTIMIZER_VERSION,
            true
        );

        // Block-editor-only styles; classic editor does not use block markup.
        if ($is_block_editor) {
            $inline_css = "
            .block-editor-block-list__block[data-type='core/html'] .picot_aio_optimizer-suggestion-marker,
            .block-editor-block-list__block[data-type='core/html'] .picot_aio_optimizer-suggestions-data { display: none !important; }
            .block-editor-block-list__block[data-type='core/html']:has(.picot_aio_optimizer-suggestion-marker),
            .block-editor-block-list__block[data-type='core/html']:has(.picot_aio_optimizer-suggestions-data) {
                display: none !important; visibility: hidden !important; height: 0 !important; margin: 0 !important; padding: 0 !important; min-height: 0 !important;
            }
            .picot_aio_optimizer-suggestion-marker { display: none !important; }
        ";
            wp_add_inline_style('picot_aio_optimizer-admin-css', $inline_css);
        }

        wp_add_inline_script('picot_aio_optimizer-admin', 'window.picot_aio_optimizer = ' . wp_json_encode(array(
            'ajax_url'                  => admin_url('admin-ajax.php'),
            'admin_url'                 => admin_url(),
            'is_post_editor'            => ! $is_settings_page,
            'is_block_editor'           => ! $is_settings_page && $is_block_editor,
            'rest_url_rewrite'          => rest_url('picot_aio_optimizer/v1/rewrite'),
            'rest_url_analyze'          => rest_url('picot_aio_optimizer/v1/analyze'),
            'rest_url_history'          => rest_url('picot_aio_optimizer/v1/history'),
            'rest_url_generate_image'   => rest_url('picot_aio_optimizer/v1/generate-image'),
            'rest_url_suggest_images'   => rest_url('picot_aio_optimizer/v1/suggest-images'),
            'rest_url_save_suggestions' => rest_url('picot_aio_optimizer/v1/save-image-suggestions'),
            'rest_url_load_suggestions' => rest_url('picot_aio_optimizer/v1/load-image-suggestions'),
            'rest_url_models'           => rest_url('picot_aio_optimizer/v1/models'),
            'rest_nonce'                => wp_create_nonce('wp_rest'),
            'nonce'                     => wp_create_nonce('picot_aio_optimizer_nonce'),
            'enable_image_gen'          => (bool) get_option('picot_aio_optimizer_enable_image_gen', 0)
                && PicotAioOptimizer_Ai_Client_Helper::is_paid_api_plan(),
            'image_style_desc'          => PicotAioOptimizer_Admin_Views::get_selected_image_style_desc(),
            'debug_mode'                => (defined('WP_DEBUG') && WP_DEBUG),
            'strings'                   => PicotAioOptimizer_Admin_Views::get_localized_strings(),
        )), 'before');
    }



    /**
     * Plugin deactivation cleanup
     */
    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('picot_aio_optimizer_cleanup_old_logs');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'picot_aio_optimizer_cleanup_old_logs');
        }
    }
}

// Plugin activation/deactivation hooks
register_activation_hook(__FILE__, array('PicotAioOptimizer_Database', 'gar_init_db'));
register_deactivation_hook(__FILE__, array('PicotAioOptimizer', 'deactivate'));

// Initialize plugin
new PicotAioOptimizer();
