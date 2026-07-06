<?php
/**
 * Model discovery via WordPress AI Client.
 *
 * @package PicotAioOptimizer
 */

if (!defined('ABSPATH')) {
    exit;
}

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * Lists Gemini models exposed by the Google connector.
 */
class PicotAioOptimizer_Model_Manager
{
    /**
     * @return array<int, array{id: string, name: string, provider: string}>
     */
    public function list_models()
    {
        return $this->list_models_for_capability(CapabilityEnum::textGeneration(), false);
    }

    /**
     * @return array<int, array{id: string, name: string, provider: string}>
     */
    public function list_image_models()
    {
        return $this->list_models_for_capability(CapabilityEnum::imageGeneration(), true);
    }

    /**
     * @param CapabilityEnum $capability Capability enum.
     * @param bool           $image_only Limit to image-like model IDs.
     * @return array<int, array{id: string, name: string, provider: string}>
     * @throws Exception When model discovery fails.
     */
    private function list_models_for_capability($capability, $image_only)
    {
        if (!PicotAioOptimizer_Ai_Client_Helper::is_available() || !class_exists(AiClient::class)) {
            return [];
        }

        if (!AiClient::isConfigured(PicotAioOptimizer_Ai_Client_Helper::GOOGLE_PROVIDER_ID)) {
            return [];
        }

        $registry = AiClient::defaultRegistry();
        $requirements = new ModelRequirements([$capability], []);

        try {
            $model_metadata_list = $registry->findProviderModelsMetadataForSupport(
                PicotAioOptimizer_Ai_Client_Helper::GOOGLE_PROVIDER_ID,
                $requirements
            );
        } catch (Throwable $e) {
            throw new Exception(
                esc_html__(
                    'Failed to fetch Gemini models. Check the Google Gemini connector API key.',
                    'picot-aio-ai-content-optimizer'
                )
            );
        }

        $models = [];

        foreach ($model_metadata_list as $model_meta) {
            $model_id = $model_meta->getId();
            $name = $model_meta->getName();
            $check_str = $model_id . ' ' . $name;
            $is_image = (bool) preg_match('/(imagen|banana|nano-|image-preview|gpt-image)/i', $check_str);

            if ($image_only && !$is_image) {
                continue;
            }
            if (!$image_only && $is_image) {
                continue;
            }

            $spec = PicotAioOptimizer_Ai_Client_Helper::format_model_spec(
                PicotAioOptimizer_Ai_Client_Helper::GOOGLE_PROVIDER_ID,
                $model_id
            );
            $models[] = [
                'id' => $spec,
                'name' => $name . ' (' . PicotAioOptimizer_Ai_Client_Helper::GOOGLE_PROVIDER_ID . '/' . $model_id . ')',
                'provider' => PicotAioOptimizer_Ai_Client_Helper::GOOGLE_PROVIDER_ID,
            ];
        }

        return $models;
    }
}
