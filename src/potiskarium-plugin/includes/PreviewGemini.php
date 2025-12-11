<?php

require_once __DIR__ . '/PreviewBase.php';
require_once __DIR__ . '/PreviewInterface.php';

class PreviewGemini extends PreviewBase implements PreviewInterface {

	function generatePreview(string $inputImagePath): string {
		// 1. Configuration
		$model_name = 'gemini-2.5-flash-image';
		$api_endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key={$this->apiKey}";

		// 2. Image Preparation
		if (!(str_starts_with($inputImagePath, 'http') || file_exists($inputImagePath))) {
			throw new Exception("Input image not found at: " . $inputImagePath);
		}

		$image_data = file_get_contents($inputImagePath);
		if ($image_data === false) {
			throw new Exception("Could not read image file.");
		}

		$base64_image = base64_encode($image_data);

		// Determine the MIME type (use wp_check_filetype for a more robust check in a real plugin)
		$mime_type = 'image/jpeg';
		if (str_ends_with($inputImagePath, '.png')) {
			$mime_type = 'image/png';
		}

		// 3. Prompt Setup
		$prompt_text = "Generate a photorealistic image (1024x1024 pixels is ideal) of a white ceramic coffee mug with the provided image seamlessly printed on the front. Place the mug on a desk in good lighting. **VERY IMPORTANT: Return ONLY the Base64-encoded IMAGE DATA for a high-quality JPEG output. Do not include any headers, footers, markdown, or text explanations.**";

		$request_body = [
			'contents' => [
				[
					'parts' => [
						// The custom print image (visual input)
						[
							'inlineData' => [
								'data' => $base64_image,
								'mimeType' => $mime_type,
							],
						],
						// The instruction for the model (text input)
						[
							'text' => $prompt_text,
						],
					],
				],
			],
		];

		// 4. WordPress HTTP API Call
		$response = wp_remote_post($api_endpoint, [
			'method' => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode($request_body),
			'timeout' => 45, // Set a generous timeout for multimodal requests
		]);

		// Check for WordPress/network errors
		if (is_wp_error($response)) {
			throw new Exception($response->get_error_message());
		}

		$http_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);
		$response_data = json_decode($response_body, true);

		// Check for API-specific errors (e.g., 400, 500)
		if ($http_code !== 200) {
			$error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : 'Unknown API error.';
			throw new Exception("API request failed with code {$http_code}: " . $error_message);
		}

		error_log(print_r($response_data, true));

		// 5. Process and Save the Output Image
		// The generated image (Base64 string) is usually embedded in the first part's text
		// Check for the desired content path
		$preview_image_base64 = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;

		if ($preview_image_base64) {
			$decoded_image = base64_decode($preview_image_base64);

			// Use the WordPress uploads directory for saving files
			$upload_dir = wp_upload_dir();
			$output_filename = 'mug_preview_' . time() . '.jpg';
			$output_path = $upload_dir['path'] . '/' . $output_filename;

			// Write the file contents
			$file_saved = file_put_contents($output_path, $decoded_image);

			if ($file_saved === false) {
				throw new Exception("Failed to save the generated image to: " . $output_path);
			}

			// Return the URL or path to the saved file
			return $upload_dir['url'] . '/' . $output_filename;

		} else {
			throw new Exception('Could not find generated image data in the Gemini API response.');
		}
	}
}
