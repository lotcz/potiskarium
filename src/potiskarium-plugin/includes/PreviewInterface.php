<?php

interface PreviewInterface {

	public function generatePreview(string $inputImagePath, string $prompt): string;

}
