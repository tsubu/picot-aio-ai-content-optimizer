<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PicotAioOptimizer Admin Views Class
 */
class PicotAioOptimizer_Admin_Views
{

    /**
     * Get localized strings for JavaScript
     */
    public static function get_localized_strings()
    {
        return array(
            'analyzing'                          => esc_html__('Analyzing...', 'picot-aio-ai-content-optimizer'),
            'rewriting'                          => esc_html__('Rewriting...', 'picot-aio-ai-content-optimizer'),
            'processing'                         => esc_html__('Processing...', 'picot-aio-ai-content-optimizer'),
            'discovering'                        => esc_html__('Discovering images...', 'picot-aio-ai-content-optimizer'),
            'confirm_rewrite'                    => esc_html__('Are you sure you want to rewrite content? The current title and content will be overwritten.', 'picot-aio-ai-content-optimizer'),
            'success_rewrite'                    => esc_html__('Rewrite completed successfully!', 'picot-aio-ai-content-optimizer'),
            'error'                              => esc_html__('Error occurred.', 'picot-aio-ai-content-optimizer'),
            'success'                            => esc_html__('Analysis completed successfully', 'picot-aio-ai-content-optimizer'),
            'no_content'                         => esc_html__('No content found.', 'picot-aio-ai-content-optimizer'),
            'analyze_btn'                        => esc_html__('SEO/AIO Analyze', 'picot-aio-ai-content-optimizer'),
            'rewrite_btn'                        => esc_html__('AI Rewrite', 'picot-aio-ai-content-optimizer'),
            'controls_title'                     => esc_html__('Control Panel', 'picot-aio-ai-content-optimizer'),
            'plugin_title'                       => esc_html__('Picot AIO AI Content Optimizer', 'picot-aio-ai-content-optimizer'),
            'label_summary'                      => esc_html__('Summary', 'picot-aio-ai-content-optimizer'),
            'label_structure'                    => esc_html__('Structure Analysis', 'picot-aio-ai-content-optimizer'),
            'label_content_advice'               => esc_html__('Content Advice', 'picot-aio-ai-content-optimizer'),
            'label_seo_advice'                   => esc_html__('SEO Advice', 'picot-aio-ai-content-optimizer'),
            'label_aio_advice'                   => esc_html__('AIO Advice', 'picot-aio-ai-content-optimizer'),
            'label_recommended'                  => esc_html__('Recommended Content', 'picot-aio-ai-content-optimizer'),
            'label_titles'                       => esc_html__('SEO Title Ideas', 'picot-aio-ai-content-optimizer'),
            'label_meta'                         => esc_html__('Meta Description Suggestions', 'picot-aio-ai-content-optimizer'),
            'label_history'                      => esc_html__('View History for this Post', 'picot-aio-ai-content-optimizer'),
            'discover_images_btn'                => esc_html__('Suggest Images', 'picot-aio-ai-content-optimizer'),
            'generate_btn'                       => esc_html__('Generate', 'picot-aio-ai-content-optimizer'),
            'generating'                         => esc_html__('Generating...', 'picot-aio-ai-content-optimizer'),
            'featured_image'                     => esc_html__('Featured Image', 'picot-aio-ai-content-optimizer'),
            'featured_image_prompt'              => esc_html__('Generate featured image for the article', 'picot-aio-ai-content-optimizer'),
            'no_suggestions'                     => esc_html__('No image suggestions found', 'picot-aio-ai-content-optimizer'),
            'gen_and_place'                      => esc_html__('Generate and Place', 'picot-aio-ai-content-optimizer'),
            'gen_all'                            => esc_html__('Batch Generate and Place', 'picot-aio-ai-content-optimizer'),
            'save_date'                          => esc_html__('Saved: ', 'picot-aio-ai-content-optimizer'),
            'featured_set'                       => esc_html__('Featured image set successfully!', 'picot-aio-ai-content-optimizer'),
            'placeholder_replaced'               => esc_html__('Placeholder replaced with image!', 'picot-aio-ai-content-optimizer'),
            'image_inserted'                     => esc_html__('Image inserted successfully!', 'picot-aio-ai-content-optimizer'),
            'insert_failed'                      => esc_html__('Failed to insert image.', 'picot-aio-ai-content-optimizer'),
            'batch_complete'                     => esc_html__('All image generation completed!', 'picot-aio-ai-content-optimizer'),
            'batch_progress'                     => esc_html__('Generating... ', 'picot-aio-ai-content-optimizer'),
            'batch_done'                         => esc_html__('Done!', 'picot-aio-ai-content-optimizer'),
            'thumbnail_label'                    => esc_html__('Thumbnail Prompt', 'picot-aio-ai-content-optimizer'),
            'copied'                             => esc_html__('Copied!', 'picot-aio-ai-content-optimizer'),
            'loading_history'                    => esc_html__('Loading history...', 'picot-aio-ai-content-optimizer'),
            'history_title'                      => esc_html__('Analysis History', 'picot-aio-ai-content-optimizer'),
            'show_btn'                           => esc_html__('View', 'picot-aio-ai-content-optimizer'),
            'view_detail'                        => esc_html__('View', 'picot-aio-ai-content-optimizer'),
            'expand_view'                        => esc_html__('Expand', 'picot-aio-ai-content-optimizer'),
            'no_history'                         => esc_html__('No history found for this post.', 'picot-aio-ai-content-optimizer'),
            'load_history_error'                 => esc_html__('Failed to load history.', 'picot-aio-ai-content-optimizer'),
            'analysis_result_title'              => esc_html__('Analysis Result', 'picot-aio-ai-content-optimizer'),
            'copy_btn'                           => esc_html__('Copy', 'picot-aio-ai-content-optimizer'),
            'clear_results_btn'                  => esc_html__('Clear Results', 'picot-aio-ai-content-optimizer'),
            'api_error'                          => esc_html__('API Error occurred.', 'picot-aio-ai-content-optimizer'),
            'format_warning'                     => esc_html__('AI response format was unexpected. Displaying as text.', 'picot-aio-ai-content-optimizer'),
            'rewrite_instructions_label'         => esc_html__('Rewrite Instructions', 'picot-aio-ai-content-optimizer'),
            'rewrite_instructions_placeholder'   => esc_html__('Enter rewrite instructions (e.g., make it friendlier, summarize shorter, etc.)', 'picot-aio-ai-content-optimizer'),
            'results_placeholder'                => esc_html__('Analysis results will appear here.', 'picot-aio-ai-content-optimizer'),
            'permission_error'                   => esc_html__('Permission denied. Please refresh the page and try again.', 'picot-aio-ai-content-optimizer'),
            'server_error'                       => esc_html__('Server error. Please check the error log.', 'picot-aio-ai-content-optimizer'),
            'network_error'                      => esc_html__('Network error. Please check your connection.', 'picot-aio-ai-content-optimizer'),
            'no_suggestions_near'                => esc_html__('No suitable image placement found', 'picot-aio-ai-content-optimizer'),
            'fetching_models'                    => esc_html__('Fetching...', 'picot-aio-ai-content-optimizer'),
            'fetch_models_done'                  => esc_html__('Done', 'picot-aio-ai-content-optimizer'),
            'fetch_models_error'                 => esc_html__('Error', 'picot-aio-ai-content-optimizer'),
            'no_data'                            => esc_html__('(No data)', 'picot-aio-ai-content-optimizer'),
        );
    }

    /**
     * Get image style options
     */
    public static function get_image_styles()
    {
        return array(
            'none'              => array(esc_html__('None (Prompt Only)', 'picot-aio-ai-content-optimizer'), ''),
            'professional'      => array(esc_html__('Professional (Photo)', 'picot-aio-ai-content-optimizer'), 'Professional photography, highly detailed, sharp focus, 8k resolution'),
            'photo_realistic'   => array(esc_html__('Photo Realistic', 'picot-aio-ai-content-optimizer'), 'Ultra-realistic photo, cinematic lighting, masterpiece'),
            'flat_illustration' => array(esc_html__('Flat Illustration', 'picot-aio-ai-content-optimizer'), 'Flat design illustration, vibrant colors, clean lines, modern'),
            'isometric'         => array(esc_html__('Isometric', 'picot-aio-ai-content-optimizer'), 'Isometric illustration, 3D perspective, clean, tech style'),
            'vector_art'        => array(esc_html__('Vector Art', 'picot-aio-ai-content-optimizer'), 'Vector art style, precise, scalable look, bright colors'),
            'infographic'       => array(esc_html__('Infographic', 'picot-aio-ai-content-optimizer'), 'Infographic style, data visualization, clean, informative'),
            'icon_style'        => array(esc_html__('Icon Style', 'picot-aio-ai-content-optimizer'), 'Icon style illustration, simplified, symbolic, bold shapes'),
            'watercolor'        => array(esc_html__('Watercolor', 'picot-aio-ai-content-optimizer'), 'Watercolor painting style, soft edges, flowing colors, artistic'),
            'oil_painting'      => array(esc_html__('Oil Painting', 'picot-aio-ai-content-optimizer'), 'Oil painting style, rich textures, classical, museum quality'),
            'digital_art'       => array(esc_html__('Digital Art', 'picot-aio-ai-content-optimizer'), 'Digital art, vibrant colors, modern, creative'),
            'concept_art'       => array(esc_html__('Concept Art', 'picot-aio-ai-content-optimizer'), 'Concept art style, imaginative, detailed, cinematic'),
            'sketch'            => array(esc_html__('Sketch', 'picot-aio-ai-content-optimizer'), 'Pencil sketch style, hand-drawn, artistic, rough texture'),
            'render_3d'         => array(esc_html__('3D Render', 'picot-aio-ai-content-optimizer'), '3D render, realistic materials, soft lighting, CGI quality'),
            'low_poly'          => array(esc_html__('Low Poly', 'picot-aio-ai-content-optimizer'), 'Low poly 3D style, geometric, colorful facets, modern'),
            'clay_render'       => array(esc_html__('Clay Render', 'picot-aio-ai-content-optimizer'), 'Clay render style, matte finish, soft shadows, clean'),
            'neon_3d'           => array(esc_html__('Neon 3D', 'picot-aio-ai-content-optimizer'), '3D render with neon lighting, glowing edges, futuristic'),
            'glassmorphism'     => array(esc_html__('Glassmorphism', 'picot-aio-ai-content-optimizer'), 'Glassmorphism style, frosted glass, transparency, modern UI'),
            'gradient_mesh'     => array(esc_html__('Gradient Mesh', 'picot-aio-ai-content-optimizer'), 'Gradient mesh style, smooth color transitions, abstract, vibrant'),
            'geometric'         => array(esc_html__('Geometric', 'picot-aio-ai-content-optimizer'), 'Geometric abstract, shapes, patterns, modern art'),
            'minimal'           => array(esc_html__('Minimalist', 'picot-aio-ai-content-optimizer'), 'Minimalist design, white space, simple, elegant, less is more'),
            'brutalist'         => array(esc_html__('Brutalist', 'picot-aio-ai-content-optimizer'), 'Brutalist design, raw, bold typography, unconventional'),
            'vintage'           => array(esc_html__('Vintage', 'picot-aio-ai-content-optimizer'), 'Vintage style, retro colors, aged texture, nostalgic'),
            'cyberpunk'         => array(esc_html__('Cyberpunk', 'picot-aio-ai-content-optimizer'), 'Cyberpunk style, neon colors, futuristic, dark urban'),
            'anime'             => array(esc_html__('Anime', 'picot-aio-ai-content-optimizer'), 'Anime style illustration, Japanese animation, colorful, expressive'),
            'pixel_art'         => array(esc_html__('Pixel Art', 'picot-aio-ai-content-optimizer'), 'Pixel art style, 8-bit, retro gaming, blocky'),
            'paper_cut'         => array(esc_html__('Paper Cut', 'picot-aio-ai-content-optimizer'), 'Paper cut art style, layered, shadows, craft aesthetic'),
            'collage'           => array(esc_html__('Collage', 'picot-aio-ai-content-optimizer'), 'Collage style, mixed media, cut-out elements, artistic'),
        );
    }

    /**
     * Get selected image style description
     */
    public static function get_selected_image_style_desc()
    {
        $image_style = get_option('picot_aio_optimizer_image_style', 'none');
        $styles      = self::get_image_styles();
        return isset($styles[$image_style]) ? $styles[$image_style][1] : '';
    }

    /**
     * Render meta box for Classic Editor
     */
    public static function render_meta_box($post)
    {
?>
        <div id="picot_aio_optimizer-classic-editor-ui">
            <div style="margin-bottom:15px;">
                <div style="margin-bottom:10px;">
                    <button type="button" class="button button-primary" id="picot_aio_optimizer-classic-analyze" style="width:100%; height:40px; justify-content:center; display:flex; align-items:center;">
                        <?php echo esc_html(self::get_localized_strings()['analyze_btn']); ?>
                    </button>
                </div>
                <div style="margin-bottom:10px;">
                    <textarea id="picot_aio_optimizer-classic-instructions" style="width:100%; height:60px; margin-bottom:5px;" placeholder="<?php echo esc_attr(self::get_localized_strings()['rewrite_instructions_placeholder']); ?>"></textarea>
                    <button type="button" class="button button-secondary" id="picot_aio_optimizer-classic-rewrite" style="width:100%; height:40px; justify-content:center; display:flex; align-items:center;">
                        <?php echo esc_html(self::get_localized_strings()['rewrite_btn']); ?>
                    </button>
                </div>
                <div style="margin-bottom:10px;">
                    <button type="button" class="button button-secondary" id="picot_aio_optimizer-classic-discover-images" style="width:100%; height:40px; justify-content:center; display:flex; align-items:center;">
                        <?php echo esc_html(self::get_localized_strings()['discover_images_btn']); ?>
                    </button>
                </div>
            </div>
            <div id="picot_aio_optimizer-classic-results" style="padding:10px; background:#f9f9f9; border:1px solid #ddd; min-height:50px;">
                <p style="color:#666; font-style:italic;">
                    <?php echo esc_html(self::get_localized_strings()['results_placeholder']); ?>
                </p>
            </div>
        </div>
    <?php
    }

    /**
     * Admin settings page
     */
    public static function admin_page()
    {
        $model            = PicotAioOptimizer_Ai_Client_Helper::normalize_model_spec(
            get_option('picot_aio_optimizer_model', PicotAioOptimizer_Client::DEFAULT_TEXT_MODEL)
        );
        $image_model      = PicotAioOptimizer_Ai_Client_Helper::normalize_model_spec(
            get_option('picot_aio_optimizer_image_model', PicotAioOptimizer_Client::DEFAULT_IMAGE_MODEL)
        );
        $enable_image_gen = get_option('picot_aio_optimizer_enable_image_gen', 0);
        $image_style      = get_option('picot_aio_optimizer_image_style', 'none');
        $available_models = PicotAioOptimizer_Ai_Client_Helper::get_model_list_option('picot_aio_optimizer_available_models');
        $available_image_models = PicotAioOptimizer_Ai_Client_Helper::get_model_list_option('picot_aio_optimizer_available_image_models');
        $ai_configured = PicotAioOptimizer_Ai_Client_Helper::supports_text_generation();
        $ai_settings_url = PicotAioOptimizer_Ai_Client_Helper::get_settings_url();
    ?>
        <div class="wrap picot-aio-optimizer-settings-wrap">
            <h1><?php esc_html_e('Picot AIO AI Content Optimizer Settings', 'picot-aio-ai-content-optimizer'); ?></h1>
            <?php
            if (current_user_can('manage_options') && filter_input(INPUT_GET, 'settings-updated') === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'picot-aio-ai-content-optimizer') . '</p></div>';
            }
            ?>
            <form method="post" action="" class="picot-settings-form">
                <?php wp_nonce_field('picot_aio_optimizer_save_settings', 'picot_aio_optimizer_save_nonce'); ?>
                <input type="hidden" name="picot_aio_optimizer_manual_save" value="1">

                <div class="picot-settings-row">
                    <div class="picot-settings-label"><?php esc_html_e('Google Gemini integration', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="picot-settings-field">
                        <?php if ($ai_configured) : ?>
                            <p style="margin: 0 0 10px; color: #155724;"><?php esc_html_e('Google Gemini connector is connected and text generation is available.', 'picot-aio-ai-content-optimizer'); ?></p>
                        <?php else : ?>
                            <p style="margin: 0 0 10px; color: #856404;"><?php esc_html_e('Google Gemini connector is not configured or does not support text generation.', 'picot-aio-ai-content-optimizer'); ?></p>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($ai_settings_url); ?>" class="button">
                            <?php esc_html_e('Open AI connector settings', 'picot-aio-ai-content-optimizer'); ?>
                        </a>
                        <p class="description">
                            <?php esc_html_e('This plugin uses the Google Gemini connector. Manage API keys under Settings → Connectors. Requests are sent through the WordPress AI Client.', 'picot-aio-ai-content-optimizer'); ?>
                        </p>
                    </div>
                </div>

                <div class="picot-settings-row">
                    <div class="picot-settings-label"><?php esc_html_e('Analysis Model', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="picot-settings-field">
                        <select name="picot_aio_optimizer_model" id="picot_aio_optimizer_model">
                            <?php if (!empty($available_models) && is_array($available_models)) : ?>
                                <?php foreach ($available_models as $model_id => $model_name) : ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($model, $model_id); ?>><?php echo esc_html($model_name); ?></option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="<?php echo esc_attr(PicotAioOptimizer_Client::DEFAULT_TEXT_MODEL); ?>" <?php selected($model, PicotAioOptimizer_Client::DEFAULT_TEXT_MODEL); ?>>
                                    <?php echo esc_html(PicotAioOptimizer_Client::DEFAULT_TEXT_MODEL); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                        <button type="button" class="button" id="picot_aio_optimizer_fetch_models"><?php esc_html_e('Refresh model list', 'picot-aio-ai-content-optimizer'); ?></button>
                        <span id="picot_aio_optimizer_fetch_status" style="margin-left: 10px;"></span>
                        <p class="description">
                            <?php esc_html_e('Refresh the model list after connecting the Google Gemini connector.', 'picot-aio-ai-content-optimizer'); ?>
                        </p>
                    </div>
                </div>

                <div class="picot-settings-row">
                    <div class="picot-settings-label"><?php esc_html_e('Image Generation', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="picot-settings-field">
                        <label>
                            <input type="checkbox" name="picot_aio_optimizer_enable_image_gen" id="picot_aio_optimizer_enable_image_gen" value="1" <?php checked($enable_image_gen, 1); ?>>
                            <?php esc_html_e('Enable', 'picot-aio-ai-content-optimizer'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Generate high-quality images using the selected image model.', 'picot-aio-ai-content-optimizer'); ?>
                        </p>
                    </div>
                </div>

                <div id="picot_aio_optimizer_image_model_row" class="picot-settings-row" style="<?php echo $enable_image_gen ? '' : 'display:none;'; ?>">
                    <div class="picot-settings-label"><?php esc_html_e('Image Generation Model', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="picot-settings-field">
                        <select name="picot_aio_optimizer_image_model" id="picot_aio_optimizer_image_model">
                            <?php if (!empty($available_image_models) && is_array($available_image_models)) : ?>
                                <?php foreach ($available_image_models as $model_id => $model_name) : ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($image_model, $model_id); ?>><?php echo esc_html($model_name); ?></option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="<?php echo esc_attr(PicotAioOptimizer_Client::DEFAULT_IMAGE_MODEL); ?>" <?php selected($image_model, PicotAioOptimizer_Client::DEFAULT_IMAGE_MODEL); ?>>
                                    <?php echo esc_html(PicotAioOptimizer_Client::DEFAULT_IMAGE_MODEL); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div id="picot_aio_optimizer_image_style_row" class="picot-settings-row" style="<?php echo $enable_image_gen ? '' : 'display:none;'; ?>">
                    <div class="picot-settings-label"><?php esc_html_e('Image Style', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="picot-settings-field">
                        <?php $styles = self::get_image_styles(); ?>
                        <select name="picot_aio_optimizer_image_style" id="picot_aio_optimizer_image_style">
                            <?php foreach ($styles as $key => $style_data) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($image_style, $key); ?>>
                                    <?php echo esc_html($style_data[0]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the style for generated images. It will be automatically added to the prompt.', 'picot-aio-ai-content-optimizer'); ?>
                        </p>
                    </div>
                </div>

                <div class="submit-row">
                    <?php submit_button(esc_html__('Save Settings', 'picot-aio-ai-content-optimizer')); ?>
                </div>
            </form>

            <hr>
            <div class="picot-history-wrap">
                <h2><?php esc_html_e('Analysis History', 'picot-aio-ai-content-optimizer'); ?></h2>
                <p><?php esc_html_e('Showing the last 20 analysis records. Click a post ID to open the editor, or use Expand to view results in a centered modal.', 'picot-aio-ai-content-optimizer'); ?></p>
                <div class="picot-history-list-header">
                    <div class="col-date"><?php esc_html_e('Date', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="col-id"><?php esc_html_e('Post ID', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="col-summary"><?php esc_html_e('Summary', 'picot-aio-ai-content-optimizer'); ?></div>
                    <div class="col-action"><?php esc_html_e('Action', 'picot-aio-ai-content-optimizer'); ?></div>
                </div>
                <div id="picot_aio_optimizer-history-list" class="picot-history-list-body">
                    <div class="picot-history-loading"><?php esc_html_e('Loading...', 'picot-aio-ai-content-optimizer'); ?></div>
                </div>
            </div>

            <!-- Modal for History Detail -->
            <div id="picot_aio_optimizer-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999999;">
                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; width:80%; max-width:800px; max-height:80vh; overflow:auto; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                    <div style="padding:20px; border-bottom:1px solid #ddd; background:#f0f0f1;">
                        <span id="picot_aio_optimizer-modal-close" style="float:right; cursor:pointer; font-size:24px; line-height:1;">&times;</span>
                        <h3 style="margin:0;" id="picot_aio_optimizer-modal-title"><?php esc_html_e('Analysis Details', 'picot-aio-ai-content-optimizer'); ?></h3>
                    </div>
                    <div id="picot_aio_optimizer-modal-content" style="padding:20px;"></div>
                </div>
            </div>

        </div>
<?php
    }
}
