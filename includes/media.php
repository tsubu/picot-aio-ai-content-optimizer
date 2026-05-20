<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PicotAioOptimizer Media Handlers
 */
class PicotAioOptimizer_Media
{

    /**
     * Allowed image MIME types and their file extensions.
     *
     * @return array<string, string> MIME type => extension.
     */
    private static function get_allowed_image_types()
    {
        return array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );
    }

    /**
     * Delete a file using the WordPress API.
     *
     * @param string $file_path Absolute path to the file.
     */
    private static function delete_upload_file($file_path)
    {
        if (!empty($file_path)) {
            wp_delete_file($file_path);
        }
    }

    /**
     * Save Base64 Image to Media Library
     */
    public static function gar_upload_base64_image_to_wp($base64_data, $prompt_title)
    {
        $image_data = base64_decode($base64_data, true);
        if ($image_data === false || $image_data === '') {
            return new WP_Error('upload_error', 'Failed to decode base64 image');
        }

        $temp_file = wp_tempnam('picot-aio-optimizer-img');
        if (!$temp_file) {
            return new WP_Error('upload_error', 'Failed to create temp file');
        }

        if (false === file_put_contents($temp_file, $image_data)) {
            self::delete_upload_file($temp_file);
            return new WP_Error('upload_error', 'Failed to write temp image file');
        }

        $mime = wp_get_image_mime($temp_file);
        $allowed_types = self::get_allowed_image_types();

        if (!$mime || !isset($allowed_types[$mime])) {
            self::delete_upload_file($temp_file);
            return new WP_Error('upload_error', 'Invalid or unsupported image format');
        }

        $upload_dir = wp_upload_dir();
        $safe_name = sanitize_title($prompt_title);
        if (strlen($safe_name) > PICOT_AIO_OPTIMIZER_FILENAME_MAX_LENGTH) {
            $safe_name = substr($safe_name, 0, PICOT_AIO_OPTIMIZER_FILENAME_MAX_LENGTH);
        }
        $extension = $allowed_types[$mime];
        $filename = 'picot_aio_optimizer-' . $safe_name . '-' . time() . '.' . $extension;
        $file_path = $upload_dir['path'] . '/' . $filename;

        if (false === file_put_contents($file_path, $image_data)) {
            self::delete_upload_file($temp_file);
            return new WP_Error('upload_error', 'Failed to save image file');
        }

        self::delete_upload_file($temp_file);

        $wp_filetype = wp_check_filetype_and_ext($file_path, $filename);
        if (empty($wp_filetype['ext']) || empty($wp_filetype['type'])) {
            self::delete_upload_file($file_path);
            return new WP_Error('upload_error', 'Image file failed validation');
        }

        // Attachment info
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_text_field($prompt_title),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $file_path);

        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return array(
            'attachment_id' => $attach_id,
            'url'           => wp_get_attachment_url($attach_id)
        );
    }
}
