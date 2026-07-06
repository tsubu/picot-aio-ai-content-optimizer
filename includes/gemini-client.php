<?php
if (!defined('ABSPATH')) {
    exit;
}

use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

/**
 * PicotAioOptimizer Client Class for AI interactions via WordPress AI Client.
 */
class PicotAioOptimizer_Client
{
    public const DEFAULT_TEXT_MODEL = 'google/gemini-2.5-flash';
    public const DEFAULT_IMAGE_MODEL = 'google/gemini-2.5-flash-image';

    /**
     * Call Gemini API for Analysis.
     *
     * @param string $content Content to analyze.
     * @param string $model   Provider/model spec.
     * @return array|WP_Error
     */
    public static function call_gemini_api($content, $model)
    {
        $model = self::resolve_text_model($model);
        $system_instruction = self::get_analysis_system_instruction();

        $response = self::generate_text(
            $model,
            $system_instruction . "\n\n" . $content,
            array(
                'temperature' => 0.7,
                'response_mime_type' => 'application/json',
            ),
            PICOT_AIO_OPTIMIZER_API_TIMEOUT
        );

        if (is_wp_error($response)) {
            return $response;
        }

        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('api_error', 'Invalid API response structure');
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'];
        $text = preg_replace('/^```json\s*|\s*```$/i', '', trim($text));
        $text = preg_replace('/^```\s*|\s*```$/i', '', trim($text));
        $clean_text = preg_replace('/,\s*([\]\}])/', '$1', $text);

        $json_data = json_decode($clean_text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        }

        $json_data = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        }

        return new WP_Error('json_error', 'Failed to parse JSON response: ' . $text);
    }

    /**
     * Rewrite content via Gemini.
     *
     * @param string $content      Prompt content.
     * @param string $model_id     Provider/model spec.
     * @param int    $gen_img      Whether to include image prompt instructions.
     * @param string $instructions Optional rewrite instructions.
     * @return array|WP_Error
     */
    public static function gar_call_gemini_api_rewrite($content, $model_id, $gen_img, $instructions = '')
    {
        $system_instruction = self::build_rewrite_system_instruction((bool) $gen_img, $instructions);
        return self::gar_perform_gemini_request($model_id, $system_instruction, $content);
    }

    /**
     * Perform a text generation request.
     *
     * @param string $model_id           Provider/model spec.
     * @param string $system_instruction System prompt.
     * @param string $content            User content.
     * @return array|WP_Error
     */
    public static function gar_perform_gemini_request($model_id, $system_instruction, $content)
    {
        $model_id = self::resolve_text_model($model_id);

        $response = self::generate_text(
            $model_id,
            $system_instruction . "\n\n" . $content,
            array(
                'temperature' => 0.7,
                'max_tokens'  => PICOT_AIO_OPTIMIZER_REWRITE_MAX_TOKENS,
            ),
            PICOT_AIO_OPTIMIZER_API_TIMEOUT
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $text = PicotAioOptimizer_Ai_Client_Helper::extract_text_from_legacy_response($response);
        if ($text === '') {
            return new WP_Error('api_error', 'Unexpected API response structure');
        }

        $text = preg_replace('/^```html\s*|\s*```$/i', '', trim($text));

        return array('content' => $text);
    }

    /**
     * Generate image bytes via WordPress AI Client.
     *
     * @param string $prompt   Image prompt.
     * @param string $model_id Provider/model spec.
     * @return string|WP_Error Base64 image data.
     */
    public static function gar_generate_image_via_api($prompt, $model_id = '')
    {
        if (!PicotAioOptimizer_Ai_Client_Helper::is_available()) {
            return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::unavailable_message());
        }

        $model_id = self::resolve_image_model($model_id);
        [$provider, $model_name] = PicotAioOptimizer_Ai_Client_Helper::parse_model_spec($model_id);

        if ($provider === '' || $model_name === '') {
            return new WP_Error('missing_model', __('Image model is not set. Choose one on the settings screen.', 'picot-aio-ai-content-optimizer'));
        }

        $style_desc = PicotAioOptimizer_Admin_Views::get_selected_image_style_desc();
        $final_prompt = $prompt;
        if ($style_desc !== '') {
            $final_prompt .= ', ' . $style_desc;
        }

        $request_options = new RequestOptions();
        $request_options->setTimeout((float) PICOT_AIO_OPTIMIZER_IMAGE_API_TIMEOUT);

        $builder = wp_ai_client_prompt($final_prompt)
            ->using_provider($provider)
            ->using_model_preference($provider, $model_name)
            ->using_request_options($request_options)
            ->as_output_file_type(FileTypeEnum::inline());

        if (!$builder->is_supported_for_image_generation()) {
            return new WP_Error(
                'unsupported_model',
                __('The selected Gemini model does not support image generation. Check the Google Gemini connector settings.', 'picot-aio-ai-content-optimizer')
            );
        }

        try {
            $result = $builder->generate_image_result();
            if (is_wp_error($result)) {
                return $result;
            }

            $image_file = $result->toImageFile();
            $base64 = sanitize_text_field(trim($image_file->getBase64Data() ?? ''));
            if ($base64 === '') {
                return new WP_Error('api_error', __('No image data was returned.', 'picot-aio-ai-content-optimizer'));
            }

            return $base64;
        } catch (Throwable $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * @param string $model  Provider/model spec.
     * @param string $prompt Prompt text.
     * @param array  $options Generation options.
     * @param int    $timeout Request timeout.
     * @return array|WP_Error
     */
    private static function generate_text($model, $prompt, $options = array(), $timeout = PICOT_AIO_OPTIMIZER_API_TIMEOUT)
    {
        if (!PicotAioOptimizer_Ai_Client_Helper::is_available()) {
            return new WP_Error('ai_unavailable', PicotAioOptimizer_Ai_Client_Helper::unavailable_message());
        }

        $model = self::resolve_text_model($model);
        [$provider, $model_id] = PicotAioOptimizer_Ai_Client_Helper::parse_model_spec($model);

        if ($provider === '' || $model_id === '') {
            return new WP_Error('missing_model', __('Text model is not set. Choose one on the settings screen.', 'picot-aio-ai-content-optimizer'));
        }

        if ($prompt === '') {
            return new WP_Error('empty_prompt', __('The prompt is empty.', 'picot-aio-ai-content-optimizer'));
        }

        $request_options = new RequestOptions();
        $request_options->setTimeout((float) $timeout);

        $builder = wp_ai_client_prompt($prompt)
            ->using_provider($provider)
            ->using_model_preference($provider, $model_id)
            ->using_max_tokens((int) ($options['max_tokens'] ?? 8192))
            ->using_temperature((float) ($options['temperature'] ?? 0.7))
            ->using_request_options($request_options);

        if (!empty($options['response_mime_type']) && $options['response_mime_type'] === 'application/json') {
            $builder->as_json_response();
        }

        if (!$builder->is_supported_for_text_generation()) {
            return new WP_Error(
                'unsupported_model',
                __('The selected Gemini model does not support text generation. Check the Google Gemini connector settings.', 'picot-aio-ai-content-optimizer')
            );
        }

        try {
            $result = $builder->generate_text_result();
            if (is_wp_error($result)) {
                return $result;
            }

            if (method_exists($result, 'toText')) {
                $text = $result->toText();
                if ($text !== '') {
                    return array(
                        'candidates' => array(
                            array(
                                'content' => array(
                                    'parts' => array(
                                        array('text' => $text),
                                    ),
                                ),
                            ),
                        ),
                    );
                }
            }

            return PicotAioOptimizer_Ai_Client_Helper::result_to_legacy_response($result);
        } catch (Throwable $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * @param string $model Stored model option.
     * @return string
     */
    private static function resolve_text_model($model)
    {
        $model = PicotAioOptimizer_Ai_Client_Helper::normalize_model_spec($model);
        return $model !== '' ? $model : self::DEFAULT_TEXT_MODEL;
    }

    /**
     * @param string $model Stored model option.
     * @return string
     */
    private static function resolve_image_model($model)
    {
        $model = PicotAioOptimizer_Ai_Client_Helper::normalize_model_spec($model);
        return $model !== '' ? $model : self::DEFAULT_IMAGE_MODEL;
    }

    /**
     * @return string
     */
    private static function get_analysis_system_instruction()
    {
        $locale = get_locale();
        $lang_code = substr($locale, 0, 2);

        if ($lang_code === 'ja') {
            return 'あなたは熟練のSEOスペシャリスト兼WordPress編集者です。以下のブログ記事のコンテンツを分析し、構造化されたフィードバックを提供してください。

            以下のキーを持つ有効なJSONフォーマットのみで回答してください：
            - "summary": 記事の簡潔な要約（最大200文字）。
            - "structure_analysis": 見出し（H2, H3）や論理構成を評価する文字列の配列。
            - "content_advice": 読みやすさ、エンゲージメント、価値向上のための改善案の配列.
            - "seo_advice": SEOに関する推奨事項（キーワード、内部リンクなど）の配列。
            - "aio_advice": AIO（Answer Engine Optimization：直接回答、スニペット）のための改善案の配列。
            - "seo_title_ideas": SEOに強いタイトル案3つの配列。
            - "meta_description_suggestions": メタディスクリプション案3つの配列。
            - "recommended_content": 記事をより包括的にするために追加すべき3〜5つのサブトピックやセクションの配列。

            【重要】
            - 回答はすべて「日本語」で行ってください。
            - 必ずJSONオブジェクトのみを返してください。Markdownフォーマット（```json ... ```）は含めないでください。';
        }

        if ($lang_code === 'zh') {
            return '您是熟練的 SEO 專家和 WordPress 編輯。請分析以下部落格文章內容，並提供結構化的意見回饋。

            請僅以包含以下鍵的有效 JSON 格式進行回答：
            - "summary": 文章的簡潔摘要（最多200字）。
            - "structure_analysis": 評估標題（H2、H3）和邏輯流向的字串數組。
            - "content_advice": 針對可讀性、參與度和價值提升的改進建議數組。
            - "seo_advice": 提供 SEO 建議（關鍵字、內部連結等）的字串數組。
            - "aio_advice": 針對「AI 概覽（AIO）」和「答案引擎優化（AEO）」的改進建議數組。
            - "seo_title_ideas": 3 個 SEO 友好標題建議的數組。
            - "meta_description_suggestions": 3 個元敘述建議的數組。
            - "recommended_content": 應該增加以使文章更全面的 3-5 個子主題或章節的數組。

            【重要】
            - 所有回答必須用「繁體中文」編寫。
            - 務必僅返回 JSON 對象。請勿包含 Markdown 格式（```json ... ```）。';
        }

        $lang_name = 'English';
        if ($lang_code === 'fr') {
            $lang_name = 'French';
        } elseif ($lang_code === 'de') {
            $lang_name = 'German';
        } elseif ($lang_code === 'es') {
            $lang_name = 'Spanish';
        }

        return "You are an expert SEO specialist and WordPress editor. Analyze the following blog post content and provide structured feedback.

            Return the response strictly in valid JSON format with the following keys:
            - \"summary\": A concise summary of the article (max 200 chars).
            - \"structure_analysis\": Array of strings evaluating headings (H2, H3) and logical flow.
            - \"content_advice\": Array of strings suggesting improvements for readability, engagement, and value.
            - \"seo_advice\": Array of strings providing SEO recommendations (keywords, internal links, etc.).
            - \"aio_advice\": Array of strings for \"Answer Engine Optimization\" (direct answers, snippets).
            - \"seo_title_ideas\": Array of 3 SEO-friendly title suggestions.
            - \"meta_description_suggestions\": Array of 3 meta description suggestions.
            - \"recommended_content\": Array of 3-5 subtopics or sections that should be added to make the article more comprehensive.

            IMPORTANT: All answers must be in $lang_name. Return ONLY the JSON object. Do not include markdown formatting (```json ... ```).";
    }

    /**
     * @param bool   $gen_img      Whether image prompts should be generated.
     * @param string $instructions Optional rewrite instructions.
     * @return string
     */
    private static function build_rewrite_system_instruction($gen_img, $instructions = '')
    {
        $locale = get_locale();
        $lang_code = substr($locale, 0, 2);

        if ($lang_code === 'ja') {
            $base_instruction = 'あなたは熟練のWordPress編集者です。以下のブログ記事の品質、流れ、視覚的な魅力を向上させつつ、核となるメッセージを維持するように書き換えてください。
            出力はWordPressブロックエディターに適した有効なHTML形式で行ってください。

            以下のルールに従ってください：
            - **コンテンツの強化**: 語彙、文構造、明快さを向上させてください。
            - **HTMLタグ**: 適切なセマンティックHTML（<h2>, <h3>, <p>, <ul>, <li>, <strong>）を使用してください。
            - **画像とブロックの維持**: 既存の <img> および <figure> タグは必ず維持してください。また、<!-- wp:image -->, <!-- wp:paragraph --> などのWordPressブロックコメントもすべて維持してください。これらを削除または変更してはいけません。
            - **Head/Bodyタグなし**: 記事の本文HTMLのみを返してください。<html>, <head>, <body> タグは含めないでください。
            - **Markdownなし**: Markdownのコードブロック（```html）で囲まないでください。

            {{IMAGE_PROMPT_INSTRUCTION}}

            書き換えたHTMLコンテンツのみを返してください。';

            $image_prompt_on_text = "               - **重要**: 画像プロンプトを必ず生成してください。
                - **アイキャッチ**: 出力の最上部に「アイキャッチ画像のプロンプト」（記事と同じ言語で）を作成し、`<div style=\"background:#e6eeff;padding:15px;border:2px solid #4d4dff;margin-bottom:20px;\"><strong>{{THUMBNAIL_LABEL}}:</strong><br>...</div>` で囲んでください。
                - **挿入画像**: 画像があると良いセクションには、`[画像プロンプト: 画像の説明]` を使ってインラインでプロンプトを挿入してください。画像が存在すると仮定せず、プロンプトのみを記述してください。";

            $image_prompt_off_text = '               - この行より上の画像プロンプトルールは無視してください。画像プロンプトを生成しないでください。';
            $additional_label = '追加の指示: ';
        } elseif ($lang_code === 'zh') {
            $base_instruction = '您是專業的 WordPress 編輯。請在保持其核心訊息的同時，重寫以下部落格文章內容以提高其品質、流暢度和視覺吸引力。
            請以適用於 WordPress 區塊編輯器的有效 HTML 格式輸出。

            請遵循以下規則：
            - **內容增強**：改進詞彙、句子結構和清晰度。
            - **HTML 標籤**：使用正確的語義 HTML（<h2>、<h3>、<p>、<ul>、<li>、<strong>）。
            - **保留圖片與區塊**：您必須保留所有現有的 <img> 和 <figure> 標籤。此外，保留所有 WordPress 區塊注釋，如 <!-- wp:image -->、<!-- wp:paragraph --> 等。請勿刪除或修改這些注釋。
            - **無 Head/Body**：僅返回文章的內部 HTML 內容。請勿包含 <html>、<head> 或 <body> 標籤。
            - **無 Markdown**：請勿將回應包裝在 Markdown 代碼塊（```html）中。

            {{IMAGE_PROMPT_INSTRUCTION}}

            僅返回重寫後的 HTML 內容。';

            $image_prompt_on_text = "               - **重要**：您必須生成圖片提示詞：
                - **縮略圖**：在輸出的最頂部創建一個「縮略圖圖片提示詞」（與文章使用相同語言），並包裝在 `<div style=\"background:#e6eeff;padding:15px;border:2px solid #4d4dff;margin-bottom:20px;\"><strong>{{THUMBNAIL_LABEL}}:</strong><br>...</div>` 中。
                - **內嵌圖片**：如果某個章節配上圖片效果更好，請使用以下格式插入提示詞：`[圖片提示詞：圖片描述]`。不要假設圖片已存在；只需寫下提示詞。";

            $image_prompt_off_text = '               - 忽略與此行相關的圖片提示詞規則。不要生成圖片提示詞。';
            $additional_label = '額外指示: ';
        } else {
            $base_instruction = 'You are a professional WordPress editor. Rewrite the following blog post content to improve its quality, flow, and visual appeal while maintaining its core message.
            Format the output in valid HTML suitable for the WordPress Block Editor.

            Follow these rules:
            - **Content Enhancement**: Improve the vocabulary, sentence structure, and clarity.
            - **HTML Tags**: Use proper semantic HTML (<h2>, <h3>, <p>, <ul>, <li>, <strong>).
            - **PRESERVE IMAGES & BLOCKS**: You MUST keep all existing <img> and <figure> tags. ALSO, preserve all WordPress block comments like <!-- wp:image -->, <!-- wp:paragraph -->, etc. DO NOT delete or modify these comments.
            - **No Head/Body**: Return ONLY the inner HTML content of the article. Do not include <html>, <head>, or <body> tags.
            - **No Markdown**: Do not wrap the response in markdown code blocks (```html).

            {{IMAGE_PROMPT_INSTRUCTION}}

            Return ONLY the rewritten HTML content.';

            $image_prompt_on_text = "               - **IMPORTANT**: You MUST generate Image Prompts:
                - **Thumbnail**: Create a \"Thumbnail Image Prompt\" (in the SAME LANGUAGE as the article) at the VERY TOP of the output, wrapped in `<div style=\"background:#e6eeff;padding:15px;border:2px solid #4d4dff;margin-bottom:20px;\"><strong>{{THUMBNAIL_LABEL}}:</strong><br>...</div>`.
                - **Inline Images**: If a section works better with an image, insert a prompt inline using: `[Image Prompt: description of the image]`. Do not assume the image exists; just write the prompt.";

            $image_prompt_off_text = '               - Ignore the image prompt rules relative to this line. Do NOT generate image prompts.';
            $additional_label = 'Additional Instructions: ';
        }

        $replacement = $gen_img ? $image_prompt_on_text : $image_prompt_off_text;
        $system_instruction = str_replace('{{IMAGE_PROMPT_INSTRUCTION}}', $replacement, $base_instruction);
        $thumb_label = __('Thumbnail Prompt', 'picot-aio-ai-content-optimizer');
        $system_instruction = str_replace('{{THUMBNAIL_LABEL}}', $thumb_label, $system_instruction);

        if (!empty($instructions)) {
            $system_instruction .= "\n\n" . $additional_label . $instructions;
        }

        return $system_instruction;
    }
}
