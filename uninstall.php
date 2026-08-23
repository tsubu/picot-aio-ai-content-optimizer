<?php

/**
 * Uninstall routine for Picot AIO AI Content Optimizer.
 *
 * @package PicotAioOptimizer
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

(function () {
    global $wpdb;

    // ログテーブルの削除
    $picot_aio_optimizer_table = $wpdb->prefix . 'picot_aio_optimizer_logs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $wpdb->query("DROP TABLE IF EXISTS $picot_aio_optimizer_table");

    // オプションの削除
    $picot_aio_optimizer_options = array(
        'picot_aio_optimizer_api_plan',
        'picot_aio_optimizer_model',
        'picot_aio_optimizer_image_model',
        'picot_aio_optimizer_image_style',
        'picot_aio_optimizer_enable_image_gen',
        'picot_aio_optimizer_common_rewrite_prompt',
        'picot_aio_optimizer_common_image_prompt',
        'picot_aio_optimizer_available_models',
        'picot_aio_optimizer_available_image_models',
    );

    foreach ($picot_aio_optimizer_options as $picot_aio_optimizer_option) {
        delete_option($picot_aio_optimizer_option);
    }

    // 一時データの削除
    delete_transient('picot_aio_optimizer_table_exists');
    delete_transient('picot_aio_optimizer_index_checked');

    // 投稿メタの削除
    $picot_aio_optimizer_meta_keys = array(
        '_picot_aio_optimizer_image_suggestions',
        '_picot_aio_optimizer_featured_text',
        '_picot_aio_optimizer_featured_prompt',
        '_picot_aio_optimizer_image_suggestions_updated',
    );

    foreach ($picot_aio_optimizer_meta_keys as $picot_aio_optimizer_meta_key) {
        delete_post_meta_by_key($picot_aio_optimizer_meta_key);
    }

    // スケジュールの解除
    wp_clear_scheduled_hook('picot_aio_optimizer_cleanup_old_logs');
})();
