<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PicotAioOptimizer REST Handlers Class
 */
class PicotAioOptimizer_REST_Handlers
{

    /**
     * REST permission check for routes that accept an optional post_id.
     */
    public static function can_edit_post_param($request)
    {
        $post_id = absint($request->get_param('post_id'));
        // 新投稿でも auto-draft の ID を必ず送る。投稿未指定の広い edit_posts フォールバックは使わない。
        if ($post_id <= 0) {
            return false;
        }

        return current_user_can('edit_post', $post_id);
    }

    /**
     * Image generation needs upload rights in addition to post edit rights.
     */
    public static function can_generate_image($request)
    {
        if (!current_user_can('upload_files')) {
            return false;
        }

        return self::can_edit_post_param($request);
    }

    /**
     * History: post-specific logs require edit_post; global list requires manage_options.
     */
    public static function can_fetch_history($request)
    {
        $post_id = absint($request->get_param('post_id'));
        if ($post_id > 0) {
            return current_user_can('edit_post', $post_id);
        }

        return current_user_can('manage_options');
    }

    /**
     * @param Throwable $e Exception or error.
     * @param string    $context User-facing context label.
     */
    private static function sanitize_exception_message($e, $context)
    {
        $localized = PicotAioOptimizer_Ai_Client_Helper::localize_api_error_message($e->getMessage());
        $message = trim($context . ' ' . $localized);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message .= ' [' . $e->getFile() . ':' . $e->getLine() . ']';
        }

        return $message;
    }

    /**
     * Analyze Content handler
     */
    public static function analyze_content($request)
    {
        self::relax_execution_limit();

        $content = (string) $request->get_param('content');
        $post_id = absint($request->get_param('post_id'));

        if (trim($content) === '') {
            return new WP_Error('missing_content', __('Article content is required.', 'picot-aio-ai-content-optimizer'), array('status' => 400));
        }

        if (!PicotAioOptimizer_Ai_Client_Helper::is_ready()) {
            return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::readiness_error_message(), array('status' => 400));
        }

        if (!PicotAioOptimizer_Ai_Client_Helper::supports_text_generation()) {
            return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::unavailable_message(), array('status' => 400));
        }

        $model = get_option('picot_aio_optimizer_model', PicotAioOptimizer_Client::DEFAULT_TEXT_MODEL);

        try {
            $result = PicotAioOptimizer_Client::call_gemini_api($content, $model);
        } catch (Throwable $e) {
            return new WP_Error('fatal_error', self::sanitize_exception_message($e, __('Analysis failed.', 'picot-aio-ai-content-optimizer')), array('status' => 500));
        }

        if (is_wp_error($result)) {
            return $result;
        }

        // Save log to DB
        if ($post_id > 0) {
            PicotAioOptimizer_Database::gar_save_analysis_log($post_id, $result);
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => $result
        ));
    }

    /**
     * Relax PHP limits for long-running AI requests when the host allows it.
     */
    private static function relax_execution_limit()
    {
        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for long-running AI API requests that exceed default PHP timeout
            @set_time_limit(0);
        }
    }

    /**
     * Rewrite Article handler
     */
    public static function rewrite_article($request)
    {
        self::relax_execution_limit();

        try {
            $params = $request->get_json_params();
            $title = isset($params['title']) ? $params['title'] : $request->get_param('title');
            $content = isset($params['content']) ? $params['content'] : $request->get_param('content');
            $instructions = isset($params['instructions']) ? $params['instructions'] : $request->get_param('instructions', '');

            $title = sanitize_text_field((string) $title);
            $content = (string) $content;
            $instructions = sanitize_textarea_field((string) $instructions);

            if (!PicotAioOptimizer_Ai_Client_Helper::is_ready()) {
                return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::readiness_error_message(), array('status' => 400));
            }

            if (!PicotAioOptimizer_Ai_Client_Helper::supports_text_generation()) {
                return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::unavailable_message(), array('status' => 400));
            }

            $model_id = get_option('picot_aio_optimizer_model', PicotAioOptimizer_Client::DEFAULT_TEXT_MODEL);
            // 画像生成は有償プランのみ有効。
            $gen_img = get_option('picot_aio_optimizer_enable_image_gen', 0)
                && PicotAioOptimizer_Ai_Client_Helper::is_paid_api_plan();

            // UTF-8 Sanitization
            if (function_exists('mb_convert_encoding')) {
                if (!empty($content) && strlen($content) > 1000) {
                    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                }
            }

            $full_prompt = "Title: {$title}\n\nContent: {$content}";
            $result = PicotAioOptimizer_Client::gar_call_gemini_api_rewrite($full_prompt, $model_id, $gen_img, $instructions);

            if (is_wp_error($result)) {
                return $result;
            }


            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'title' => $title,
                    'content' => isset($result['content']) ? wp_kses_post((string) $result['content']) : ''
                )
            ));
        } catch (Throwable $e) {
            return new WP_Error('fatal_error', self::sanitize_exception_message($e, __('Rewrite failed.', 'picot-aio-ai-content-optimizer')), array('status' => 500));
        }
    }

    /**
     * Suggest Images handler
     */
    public static function suggest_images($request)
    {
        self::relax_execution_limit();

        try {
            $params = $request->get_json_params();
            $title = isset($params['title']) ? $params['title'] : $request->get_param('title');
            $content = isset($params['content']) ? $params['content'] : $request->get_param('content');

            $title = sanitize_text_field((string) $title);
            $content = (string) $content;

            if (!PicotAioOptimizer_Ai_Client_Helper::is_ready()) {
                return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::readiness_error_message(), array('status' => 400));
            }

            if (!PicotAioOptimizer_Ai_Client_Helper::supports_text_generation()) {
                return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::unavailable_message(), array('status' => 400));
            }

            $model_id = get_option('picot_aio_optimizer_model', PicotAioOptimizer_Client::DEFAULT_TEXT_MODEL);

            // UTF-8 Sanitization to prevent json_encode crashes (if mbstring is available)
            if (function_exists('mb_convert_encoding')) {
                if (!empty($content) && strlen($content) > 1000) {
                    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                }
                if (!empty($title)) {
                    $title = mb_convert_encoding($title, 'UTF-8', 'UTF-8');
                }
            }

            // Try to extract existing thumbnail information from content
            $existing_thumb_prompt = '';
            // Match "サムネイル用プロンプト", "アイキャッチ用プロンプト", "サムネイルの内容", etc.
            if (preg_match('/(サムネイル|アイキャッチ)(用プロンプト|の内容)[：:](.+?)(?=\n|\[|$)/u', $content, $matches)) {
                $existing_thumb_prompt = trim($matches[3]);
            }

            // Detect article language from content/title to pass as context
            $locale = get_locale();
            $lang_code = substr($locale, 0, 2);

            // Build a universal system instruction that adapts to the article's language
            $system_instruction = "You are a visual editor for blog articles. Analyze the article and:
                1. Identify the language used in the article (e.g. Japanese, English, Chinese, etc.).
                2. Suggest a short, catchy text (max 10 characters in CJK, max 5 words in Latin) for the featured image thumbnail - written in THE SAME LANGUAGE as the article.
                3. Suggest up to 8 image placement opportunities within the article body.

                CRITICAL LANGUAGE RULE: All text that appears INSIDE generated images (featured_text, and any text rendered within prompts) MUST be written in THE SAME LANGUAGE as the article. Do NOT output English text inside images if the article is in Japanese, Chinese, Korean, etc.";

            if ($existing_thumb_prompt) {
                $system_instruction .= "\n\nEXISTING THUMBNAIL PROMPT FOUND IN ARTICLE:\n\"" . $existing_thumb_prompt . "\"\n**TOP PRIORITY**: Base the featured_text and featured_prompt on this existing prompt as faithfully as possible.\n";
            }

            $system_instruction .= "\n\nReturn ONLY a valid JSON object with this exact structure:
                {
                    \"featured_text\": \"Short catchy text for thumbnail image (in the article's language)\",
                    \"featured_prompt\": \"Detailed English prompt for featured image generation. IMPORTANT: Any text rendered IN the image must be in the article's language (NOT English if article is non-English).\",
                    \"suggestions\": [
                        {
                            \"location\": \"Exact quote from near the beginning of the article (15-30 chars)\",
                            \"description\": \"Description of image 1 (in article's language)\",
                            \"prompt\": \"English image generation prompt. IMPORTANT: Any text rendered IN the image must match the article's language.\"
                        },
                        {
                            \"location\": \"Exact quote from the middle of the article (15-30 chars)\",
                            \"description\": \"Description of image 2 (in article's language)\",
                            \"prompt\": \"English image generation prompt. IMPORTANT: Any text rendered IN the image must match the article's language.\"
                        }
                    ]
                }

                RULES:
                - Suggest up to 8 images maximum.
                - Do NOT suggest images at the very beginning of the article (first 2 paragraphs) - that position is reserved for the featured image.
                - Space images evenly throughout the article.
                - Each suggestion must include an exact text quote from the article as 'location'.
                - Return ONLY valid JSON, no markdown or extra text.
                - 'prompt' fields must be in English, but any text RENDERED IN the image must be in the same language as the article.";


            $full_text = "Title: {$title}\n\nContent: {$content}";

            // We use the rewrite helper logic but with different instruction
            $result = PicotAioOptimizer_Client::gar_perform_gemini_request($model_id, $system_instruction, $full_text);

            if (is_wp_error($result)) {
                return $result;
            }

            $content_raw = $result['content'];
            // Clean trailing commas which are invalid in standard JSON
            $content_clean = preg_replace('/,\s*([\]\}])/', '$1', $content_raw);

            $parsed = json_decode($content_clean, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fallback: try to clean markdown
                $clean = preg_replace('/^```json\s*|\s*```$/i', '', trim($content_raw));
                $clean = preg_replace('/,\s*([\]\}])/', '$1', $clean);
                $parsed = json_decode($clean, true);
            }

            // Normalize response structure
            $suggestions = isset($parsed['suggestions']) ? $parsed['suggestions'] : (isset($parsed[0]) ? $parsed : array());
            $data = array(
                'featured_text'   => isset($parsed['featured_text']) ? sanitize_text_field((string) $parsed['featured_text']) : '',
                'featured_prompt' => isset($parsed['featured_prompt']) ? sanitize_textarea_field((string) $parsed['featured_prompt']) : '',
                'suggestions'     => self::sanitize_suggestions($suggestions),
            );

            return rest_ensure_response(array(
                'success' => true,
                'data'    => $data
            ));
        } catch (Throwable $e) {
            return new WP_Error('fatal_error', self::sanitize_exception_message($e, __('Image suggestion failed.', 'picot-aio-ai-content-optimizer')), array('status' => 500));
        }
    }

    /**
     * Normalize AI-provided image suggestions into plain text fields.
     *
     * @param mixed $suggestions Raw suggestion list.
     * @return array
     */
    private static function sanitize_suggestions($suggestions)
    {
        if (!is_array($suggestions)) {
            return array();
        }

        $clean = array();
        foreach ($suggestions as $suggestion) {
            if (!is_array($suggestion)) {
                continue;
            }

            $clean[] = array(
                'location'    => isset($suggestion['location']) ? sanitize_text_field((string) $suggestion['location']) : '',
                'description' => isset($suggestion['description']) ? sanitize_text_field((string) $suggestion['description']) : '',
                'prompt'      => isset($suggestion['prompt']) ? sanitize_textarea_field((string) $suggestion['prompt']) : '',
            );
        }

        return $clean;
    }

    /**
     * Generate Image handler
     */
    public static function generate_image($request)
    {
        if (!PicotAioOptimizer_Ai_Client_Helper::is_paid_api_plan()) {
            return new WP_Error(
                'free_plan',
                __('Image generation requires a paid Gemini API plan. Set Gemini API plan to Paid on the settings screen.', 'picot-aio-ai-content-optimizer'),
                array('status' => 403)
            );
        }

        if (!get_option('picot_aio_optimizer_enable_image_gen', 0)) {
            return new WP_Error('disabled', __('Image generation is disabled in settings.', 'picot-aio-ai-content-optimizer'), array('status' => 403));
        }

        $prompt = sanitize_textarea_field((string) $request->get_param('prompt'));
        if ($prompt === '') {
            return new WP_Error('missing_prompt', __('Image prompt is required.', 'picot-aio-ai-content-optimizer'), array('status' => 400));
        }

        $post_id = absint($request->get_param('post_id'));

        if (!PicotAioOptimizer_Ai_Client_Helper::is_ready()) {
            return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::readiness_error_message(), array('status' => 400));
        }

        if (!PicotAioOptimizer_Ai_Client_Helper::supports_image_generation()) {
            return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::unavailable_message(), array('status' => 400));
        }

        $image_model = get_option('picot_aio_optimizer_image_model', PicotAioOptimizer_Client::DEFAULT_IMAGE_MODEL);

        try {
            $image_data = PicotAioOptimizer_Client::gar_generate_image_via_api($prompt, $image_model);

            if (is_wp_error($image_data)) {
                return $image_data;
            }

            $upload_result = PicotAioOptimizer_Media::gar_upload_base64_image_to_wp($image_data, $prompt, $post_id);
        } catch (Throwable $e) {
            return new WP_Error('fatal_error', self::sanitize_exception_message($e, __('Image generation failed.', 'picot-aio-ai-content-optimizer')), array('status' => 500));
        }

        if (is_wp_error($upload_result)) {
            return $upload_result;
        }


        return rest_ensure_response(array(
            'success' => true,
            'data' => $upload_result
        ));
    }

    /**
     * Save Suggestions handler
     */
    public static function save_suggestions($request)
    {
        $post_id = absint($request->get_param('post_id'));
        $suggestions_raw = $request->get_param('suggestions');

        if ($post_id <= 0) {
            return new WP_Error('missing_post_id', __('Post ID is required.', 'picot-aio-ai-content-optimizer'), array('status' => 400));
        }

        if (empty($suggestions_raw)) {
            delete_post_meta($post_id, '_picot_aio_optimizer_image_suggestions');
            delete_post_meta($post_id, '_picot_aio_optimizer_featured_text');
            delete_post_meta($post_id, '_picot_aio_optimizer_featured_prompt');
            delete_post_meta($post_id, '_picot_aio_optimizer_image_suggestions_updated');
            return rest_ensure_response(array('success' => true));
        }

        $parsed = is_string($suggestions_raw) ? json_decode($suggestions_raw, true) : $suggestions_raw;

        if (is_array($parsed) && isset($parsed['suggestions'])) {
            $suggestions = $parsed['suggestions'];
            $featured_text = isset($parsed['featured_text']) ? $parsed['featured_text'] : '';
            $featured_prompt = isset($parsed['featured_prompt']) ? $parsed['featured_prompt'] : '';
        } else {
            $suggestions = is_array($parsed) ? $parsed : array();
            $featured_text = '';
            $featured_prompt = '';
        }

        update_post_meta($post_id, '_picot_aio_optimizer_image_suggestions', wp_json_encode(self::sanitize_suggestions($suggestions)));
        update_post_meta($post_id, '_picot_aio_optimizer_featured_text', sanitize_text_field($featured_text));
        update_post_meta($post_id, '_picot_aio_optimizer_featured_prompt', sanitize_textarea_field($featured_prompt));
        update_post_meta($post_id, '_picot_aio_optimizer_image_suggestions_updated', current_time('mysql'));

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Load Suggestions handler
     */
    public static function load_suggestions($request)
    {
        $post_id = absint($request->get_param('post_id'));
        if ($post_id <= 0) {
            return new WP_Error('missing_post_id', __('Post ID is required.', 'picot-aio-ai-content-optimizer'), array('status' => 400));
        }

        $suggestions_meta = get_post_meta($post_id, '_picot_aio_optimizer_image_suggestions', true);
        $featured_text = get_post_meta($post_id, '_picot_aio_optimizer_featured_text', true);
        $featured_prompt = get_post_meta($post_id, '_picot_aio_optimizer_featured_prompt', true);
        $updated = get_post_meta($post_id, '_picot_aio_optimizer_image_suggestions_updated', true);

        $suggestions = is_string($suggestions_meta) ? json_decode($suggestions_meta, true) : $suggestions_meta;

        // Recover from old format where the entire payload was stored in 'suggestions'
        if (is_array($suggestions) && isset($suggestions['suggestions'])) {
            if (empty($featured_text) && isset($suggestions['featured_text'])) {
                $featured_text = $suggestions['featured_text'];
            }
            if (empty($featured_prompt) && isset($suggestions['featured_prompt'])) {
                $featured_prompt = $suggestions['featured_prompt'];
            }
            $suggestions = $suggestions['suggestions'];
        }

        if (empty($suggestions) || !is_array($suggestions)) {
            $suggestions = array();
        }

        // 旧形式や meta 直接改ざんに備え、返却前に必ず再サニタイズする。
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'suggestions' => self::sanitize_suggestions($suggestions),
                'featured_text' => $featured_text ? sanitize_text_field($featured_text) : '',
                'featured_prompt' => $featured_prompt ? sanitize_textarea_field($featured_prompt) : ''
            ),
            'updated' => $updated ? sanitize_text_field($updated) : null
        ));
    }

    /**
     * Fetch History handler
     */
    public static function fetch_history($request)
    {
        global $wpdb;
        $post_id = $request->get_param('post_id');
        $limit = absint($request->get_param('limit') ?: PICOT_AIO_OPTIMIZER_HISTORY_LIMIT);

        $cache_key = 'picot_aio_optimizer_history_' . ($post_id ? $post_id : 'all') . '_' . $limit;
        $results = wp_cache_get($cache_key, 'picot_aio_optimizer');

        if (false === $results) {
            if (!empty($post_id)) {
                $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}picot_aio_optimizer_logs WHERE post_id = %d ORDER BY created_at DESC LIMIT %d",
                        absint($post_id),
                        $limit
                    )
                );
            } else {
                $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}picot_aio_optimizer_logs ORDER BY created_at DESC LIMIT %d",
                        $limit
                    )
                );
            }
            wp_cache_set($cache_key, $results, 'picot_aio_optimizer', 300); // Cache for 5 minutes
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => $results ? $results : array()
        ));
    }

    /**
     * Fetch Models handler
     */
    public static function fetch_models($request)
    {
        if (!PicotAioOptimizer_Ai_Client_Helper::is_ready()) {
            return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::readiness_error_message(), array('status' => 400));
        }

        try {
            $manager = new PicotAioOptimizer_Model_Manager();
            $text_items = $manager->list_models();
            $image_items = $manager->list_image_models();
        } catch (Throwable $e) {
            return new WP_Error('api_error', PicotAioOptimizer_Ai_Client_Helper::localize_api_error_message($e->getMessage()), array('status' => 500));
        }

        $text_models = array();
        foreach ($text_items as $item) {
            $text_models[$item['id']] = $item['name'];
        }

        $image_models = array();
        foreach ($image_items as $item) {
            $image_models[$item['id']] = $item['name'];
        }

        if (empty($text_models) && empty($image_models)) {
            return new WP_Error(
                'no_models',
                __('No Gemini models were found. Check the Google Gemini connector connection.', 'picot-aio-ai-content-optimizer'),
                array('status' => 400)
            );
        }

        update_option('picot_aio_optimizer_available_models', $text_models);
        update_option('picot_aio_optimizer_available_image_models', $image_models);

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'text_models' => $text_models,
                'image_models' => $image_models,
            ),
        ));
    }
}
