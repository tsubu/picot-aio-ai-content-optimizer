=== Picot AIO AI Content Optimizer ===
Contributors: tsubu
Donate link: https://github.com/tsubu/picot-aio-ai-content-optimizer
Tags: ai, gemini, seo, content-quality, rewrite
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 1.1.1
Requires PHP: 8.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered content optimization using Google Gemini via WordPress AI Client. SEO/AIO advice, one-button rewrite, and AI image generation.

== Description ==

Picot AIO AI Content Optimizer is a WordPress plugin developed by Tsubu (Picot) that elevates your content quality using Google Gemini through the **WordPress AI Client**. It acts as your personal SEO and AIO consultant, providing actionable advice based on Google's Search Quality Rater Guidelines (E-E-A-T).

= Key Features =

* **E-E-A-T & Quality Guidelines Support**: Get precise advice to enhance your content's Experience, Expertise, Authoritativeness, and Trustworthiness.
* **One-Button SEO/AIO Analysis**: Analyze your existing articles and get structured optimization advice instantly.
* **One-Button Rewrite**: Rewrite your articles with custom instructions using Gemini AI.
* **AI Image Generation**: Generate and insert high-quality, relevant images for your posts.
* **Dual Panel UI**: Access the optimizer from the Gutenberg Document panel or the sidebar — always visible.
* **Analysis History**: Review past analyses per post directly in the editor.
* **Classic Editor Support**: Works in both the block editor and the classic editor.
* **WordPress AI Connector Integration**: Supports connector-based provider/model selection, readiness checks, connector-specific error guidance, and WordPress's experimental Connector Approvals feature.
* **Free-Tier Output Mode**: Gemini API free-tier requests are adjusted to request concise, simplified, complete responses.

= Requirements and Gemini API plans =

This plugin requires the official WordPress connector functionality and the official [AI plugin](https://wordpress.org/plugins/ai/). Install the **Google Gemini connector**, connect it under **Settings → Connectors**, and activate the official AI plugin. The plugin also supports WordPress's experimental Connector Approvals feature when that feature is enabled.

Gemini API free-tier usage is supported for text analysis and rewriting. However, free-tier token quotas, rate limits, model availability, and Google policies may change, and the plugin may become temporarily or permanently unavailable under those limits. Image generation is disabled when the free plan is selected in this plugin.

For more reliable operation, higher limits, and image generation with a supported model, a small amount of paid Gemini API usage with billing enabled is recommended.

== External services ==

This plugin sends AI requests through the **WordPress AI Client** (WordPress 7.0+) and requires both the [Google AI connector plugin](https://wordpress.org/plugins/ai-provider-for-google/) and the official [AI plugin](https://wordpress.org/plugins/ai/). It also supports the AI plugin's experimental Connector Approvals feature. Connect your Gemini API key under **Settings → Connectors** and, when Connector Approvals is enabled, approve connector access when prompted. Picot AIO AI Content Optimizer does not store or read provider API keys directly.

This plugin connects to the **Google Generative Language API (Gemini)** provided by Google LLC.

* **What the service is used for**: Content analysis for SEO/AIO advice, text rewriting based on user instructions, and image generation based on article context.
* **What data is sent and when**: The content of your post (title and body) and your custom AI prompts are sent to Google Gemini only when you manually trigger an analysis, rewrite, or image generation request by clicking the respective buttons. No data is sent automatically in the background.
* **Legal links**:
    * Service provider: Google LLC
    * Terms of Service: https://ai.google.dev/terms
    * Privacy Policy: https://policies.google.com/privacy

== Installation ==

1. Upload the `picot-aio-ai-content-optimizer` folder to the `/wp-content/plugins/` directory, or install through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress (requires WordPress 7.0 or later).
3. Install and activate the **Google (Gemini) AI connector** plugin, then open **Settings → Connectors** and connect your Gemini API key.
4. Install and activate the official **AI** plugin. If its experimental Connector Approvals feature is enabled, approve connector access when prompted.
5. Open **Settings → Picot AIO AI Content Optimizer**, select your Gemini models and API plan, then use the optimizer panel in a post editor.

== Frequently Asked Questions ==

= Which AI connector do I need? =

This plugin requires the **Google Gemini connector** (AI Provider for Google) and the official **AI** plugin. It also supports the AI plugin's experimental Connector Approvals feature. Other provider connectors such as OpenAI or Anthropic are not supported.

= Do I need to enter an API key in this plugin? =

No. Configure your Gemini API key under **Settings → Connectors** in WordPress. Picot AIO AI Content Optimizer uses the WordPress AI Client and does not manage credentials itself.

= Where do I get a Gemini API key? =

You can obtain a Gemini API key from [Google AI Studio](https://aistudio.google.com/) and register it in the Google connector under **Settings → Connectors**.

= Can I use the Gemini API free tier? =

Yes. The plugin itself is free, and text analysis and rewriting support the Gemini API free tier. Free-tier prompts request concise, simplified responses so they are more likely to complete within strict token limits. Image generation is disabled when the free plan is selected.

Free-tier quotas, token limits, rate limits, model availability, and Google policies can change without notice, so requests may fail or the service may become unavailable. For stable operation and supported image generation, a small amount of paid Gemini API usage with billing enabled is recommended.

= Does it work with the Classic Editor? =

Yes. When the Classic Editor is active, the plugin automatically shows a meta box in the post editor sidebar.

== Screenshots ==

1. The Picot AIO panel in the Gutenberg post editor providing SEO/AIO advice.
2. The plugin sidebar accessible from the Gutenberg toolbar.
3. Settings page where you configure Gemini models.

== Changelog ==

= 1.1.1 =
* Added detailed WordPress AI connector integration, including support for the experimental Connector Approvals feature, readiness checks, and clear setup guidance.
* Added explicit requirements for the Google Gemini connector and the official AI plugin.
* Added Gemini API free-tier support guidance and concise, simplified free-tier response instructions.
* Added a recommendation to use a small paid Gemini API allowance for more reliable operation.
* Added security updates for REST permissions, output escaping, image upload validation, logging, database initialization, and uninstall cleanup.
* Improved editor behavior, model selection, translations, error handling, and free/paid image-generation settings.

= 1.1.0 =
* Migrated all AI features to the WordPress AI Client (no direct provider HTTP calls).
* Removed plugin-owned API key settings; credentials are managed under Settings → Connectors.
* Fixed rewrite output being truncated on long articles (increased output token limit and improved response parsing).
* Updated settings UI for connector-based model selection.

= 1.0.1 =
* Raised minimum requirements to WordPress 7.0 and PHP 8.3.
* Fixed Classic Editor support for analyze, rewrite, and image suggestions.
* Improved script loading, REST API permissions, and image placement reliability.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.1 =
Security and connector integration update. Requires the Google Gemini connector and the official AI plugin; free-tier text use is supported with stricter limits.

= 1.1.0 =
Migrates to the WordPress AI Client. Install the Google Gemini connector and configure your API key under Settings → Connectors. Plugin API key settings are no longer used.

= 1.0.1 =
Classic Editor fixes, security improvements, and WordPress 7.0 / PHP 8.3 requirement update.

= 1.0.0 =
Initial version.
