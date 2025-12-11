<?php

require_once __DIR__ . '/PreviewInterface.php';
require_once __DIR__ . '/PreviewBase.php';

class PreviewOpenAi extends PreviewBase implements PreviewInterface {

	function generatePreview(string $inputImagePath): string {

		$prompt = "Create a high-resolution photorealistic mockup of a white ceramic mug on a plain white background. "
		. "Apply the provided artwork (use the uploaded image as the design) centered on the front of the mug, "
		. "scaled to cover approximately 60% of the mug's front face, preserving the original colors and transparency of the artwork. "
		. "Show slight perspective (mug handle visible on the right), soft studio lighting, natural shadows on the table, "
		. "and crisp edges. Make sure artwork looks printed (not floating) — include subtle gloss highlights from the mug surface. "
		. "Return a single square PNG image (1024x1024) with transparent background if possible; otherwise white background.";

		// Call OpenAI Images Edit endpoint (multipart/form-data)
		$openai_url = 'https://api.openai.com/v1/images/edits';

		$curl = curl_init();

		$path = $this->urlToPath($inputImagePath);
		$cfile = new CURLFile($path, mime_content_type($path));

		$post_fields = ['model' => 'gpt-image-1',
			// The API accepts multiple "image[]" fields — we send a single one here.
		'image[]' => $cfile,
		'prompt' => $prompt,
		'size' => '1024x1024',// 'response_format' => 'b64_json', // default for gpt-image-1 is b64_json in many examples
		];

		curl_setopt_array(
			$curl,
			[
				CURLOPT_URL => $openai_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FAILONERROR => false,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => [
					'Authorization: Bearer ' . $this->apiKey,
					// Note: Content-Type allowed to be set by cURL for multipart/form-data
				],
				CURLOPT_POSTFIELDS => $post_fields,
				CURLOPT_TIMEOUT => 120
			]
		);

		$resp = curl_exec($curl);

		if (curl_errno($curl)) {
			$err = curl_error($curl);
			curl_close($curl);
			throw new Exception('cURL error: ' . $err);
		}

		$http_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$body = json_decode($resp, true);
		curl_close($curl);

		if ($http_code !== 200 && $http_code !== 201) {
			$dbg = print_r($body, true);
			throw new Exception("OpenAI API error response: $dbg, http_code: $http_code");
		}

		if (!isset($body['data'][0]['b64_json'])) {
			$dbg = print_r($body, true);
			throw new Exception("Unexpected OpenAI response: $dbg");
		}

		$image_b64 = $body['data'][0]['b64_json'];
		$image_data = base64_decode($image_b64);

		if ($image_data === false) {
			throw new Exception("Failed to base64-decode image data: $image_b64");
		}

		// Save generated image to WP uploads
		$upload_dir = wp_get_upload_dir();
		$out_dir = trailingslashit($upload_dir['basedir']) . 'openai-mug/';
		if (!file_exists($out_dir)) {
			wp_mkdir_p($out_dir);
		}

		$filename = 'mug-' . time() . '.png';
		$out_path = $out_dir . $filename;
		$saved = file_put_contents($out_path, $image_data);

		if ($saved === false) {
			throw new Exception("Failed to save generated image on server: $out_path");
		}

		// Build public URL to saved image
		$out_url = trailingslashit($upload_dir['baseurl']) . 'openai-mug/' . $filename;

		// Optionally, you could register the image as an attachment in WP media library here.

		// Clean up uploaded design file if you want (optional)
		// @unlink( $uploaded_path );

		return $out_url;
	}

}
