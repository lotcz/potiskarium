<?php

class PreviewBase {

	protected string $apiKey;

	public function __construct(string $apiKey) {
		$this->apiKey = $apiKey;
	}

	protected function urlToPath($url) {
		if (!str_starts_with($url, "http")) {
			return $url;
		}

		$uploads = wp_get_upload_dir();
		if (strpos($url, $uploads['baseurl']) === false ) {
			// todo: not from this server, download file
			return $url;
		}

		$relative_path = str_replace( $uploads['baseurl'], '', $url );
		$file_path = $uploads['basedir'] . $relative_path;

		if (file_exists($file_path)) {
			return $file_path;
		}

		return $url;
	}

}
