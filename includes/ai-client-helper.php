<?php
/**
 * WordPress AI Client helpers.
 *
 * @package PicotAioOptimizer
 */

if (!defined('ABSPATH')) {
    exit;
}

use WordPress\AiClient\AiClient;

/**
 * Shared helpers for the WordPress 7.0+ AI Client API.
 */
class PicotAioOptimizer_Ai_Client_Helper
{
    /**
     * Google Gemini provider slug in the WordPress AI Client registry.
     */
    public const GOOGLE_PROVIDER_ID = 'google';

    /**
     * Admin URL for site-level AI provider configuration.
     *
     * @return string
     */
    public static function get_settings_url()
    {
        return admin_url('options-connectors.php');
    }

    /**
     * Whether core AI Client functions are available.
     *
     * @return bool
     */
    public static function is_available()
    {
        return function_exists('wp_ai_client_prompt') && wp_supports_ai();
    }

    /**
     * Whether the Google Gemini connector is configured.
     *
     * @return bool
     */
    public static function is_google_configured()
    {
        if (!self::is_available() || !class_exists(AiClient::class)) {
            return false;
        }

        return AiClient::isConfigured(self::GOOGLE_PROVIDER_ID);
    }

    /**
     * @param string|null $prompt Optional prompt text.
     * @return WP_AI_Client_Prompt_Builder|null
     */
    public static function create_prompt_builder($prompt = null)
    {
        if (!self::is_available()) {
            return null;
        }

        return wp_ai_client_prompt($prompt);
    }

    /**
     * @param string|null $prompt Optional prompt text.
     * @return WP_AI_Client_Prompt_Builder|null
     */
    public static function create_google_prompt_builder($prompt = null)
    {
        $builder = self::create_prompt_builder($prompt);
        if (!$builder) {
            return null;
        }

        return $builder->using_provider(self::GOOGLE_PROVIDER_ID);
    }

    /**
     * @return bool
     */
    public static function supports_text_generation()
    {
        if (!self::is_google_configured()) {
            return false;
        }

        $builder = self::create_google_prompt_builder(null);
        return $builder && $builder->is_supported_for_text_generation();
    }

    /**
     * @return bool
     */
    public static function supports_image_generation()
    {
        if (!self::is_google_configured()) {
            return false;
        }

        $builder = self::create_google_prompt_builder(null);
        return $builder && $builder->is_supported_for_image_generation();
    }

    /**
     * Split a stored model value into provider and model ID.
     *
     * @param string $model Stored value such as "google/gemini-2.5-flash".
     * @return array{0: string, 1: string}
     */
    public static function parse_model_spec($model)
    {
        $model = trim((string) $model);
        if ($model === '') {
            return ['', ''];
        }

        if (strpos($model, '/') !== false) {
            $parts = explode('/', $model, 2);
            return [sanitize_key($parts[0]), $parts[1]];
        }

        return [self::GOOGLE_PROVIDER_ID, $model];
    }

    /**
     * @param string $provider Provider slug.
     * @param string $model_id Model ID.
     * @return string
     */
    public static function format_model_spec($provider, $model_id)
    {
        return sanitize_key($provider) . '/' . $model_id;
    }

    /**
     * Normalize legacy model options to provider/model specs.
     *
     * @param string $model Stored model option.
     * @return string
     */
    public static function normalize_model_spec($model)
    {
        [$provider, $model_id] = self::parse_model_spec($model);
        if ($provider === '' || $model_id === '') {
            return '';
        }

        return self::format_model_spec($provider, $model_id);
    }

    /**
     * @param mixed $result
     * @return array
     * @throws Exception When generation fails.
     */
    public static function result_to_legacy_response($result)
    {
        if (is_wp_error($result)) {
            throw new Exception(esc_html($result->get_error_message()));
        }

        $text = method_exists($result, 'toText') ? $result->toText() : '';
        $additional = method_exists($result, 'getAdditionalData') ? $result->getAdditionalData() : [];
        $candidates = [];

        if (!empty($additional['candidates']) && is_array($additional['candidates'])) {
            $candidates = $additional['candidates'];
        } else {
            $candidates[] = [
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ];
        }

        return [
            'candidates' => $candidates,
        ];
    }

    /**
     * Extract full generated text from a legacy Gemini-style response array.
     *
     * @param array $response Response array with candidates.
     * @return string
     */
    public static function extract_text_from_legacy_response(array $response)
    {
        $text_parts = array();

        if (empty($response['candidates']) || !is_array($response['candidates'])) {
            return '';
        }

        foreach ($response['candidates'] as $candidate) {
            if (empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                continue;
            }
            foreach ($candidate['content']['parts'] as $part) {
                if (!empty($part['text']) && is_string($part['text'])) {
                    $text_parts[] = $part['text'];
                }
            }
        }

        return implode('', $text_parts);
    }

    /**
     * @return string
     */
    public static function unavailable_message()
    {
        return esc_html__(
            'WordPress AI Client is not available. Install and configure the Google Gemini connector under Settings → Connectors.',
            'picot-aio-ai-content-optimizer'
        );
    }

    /**
     * Read a stored model list option in id => label format.
     *
     * Clears legacy Google API model arrays (pre–WordPress AI Client).
     *
     * @param string $option_name Option name.
     * @return array<string, string>
     */
    public static function get_model_list_option($option_name)
    {
        $models = get_option($option_name, array());
        if (!is_array($models) || $models === array()) {
            return array();
        }

        $first_value = reset($models);
        if (is_array($first_value)) {
            delete_option($option_name);
            return array();
        }

        $normalized = array();
        foreach ($models as $id => $label) {
            if (!is_string($id) || !is_string($label) || $label === '') {
                continue;
            }
            $normalized[$id] = $label;
        }

        return $normalized;
    }
}
