<?php
/**
 * SEO Gemini Provider for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate\Providers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Contracts\AISeoProviderInterface;
use EZTranslate\LanguageManager;
use EZTranslate\Helpers\ConstructPrompt;
use EZTranslate\Logger;
use Exception;

/**
 * SEO-specialized Gemini AI Provider
 *
 * Implements AISeoProviderInterface to provide SEO-specific
 * AI functionality using Google's Gemini API.
 *
 * @since 1.0.0
 */
class SeoGeminiProvider implements AISeoProviderInterface {

    /**
     * API key for Gemini
     *
     * @var string
     * @since 1.0.0
     */
    private $api_key;

    /**
     * Gemini model ID
     *
     * @var string
     * @since 1.0.0
     */
    private $model_id = "gemini-2.0-flash";

    /**
     * API endpoint for content generation
     *
     * @var string
     * @since 1.0.0
     */
    private $generate_content_api = "generateContent";

    /**
     * SEO character limits
     *
     * @var array
     * @since 1.0.0
     */
    private $seo_limits = array(
        'seo_title' => 60,
        'seo_description' => 155,
        'og_title' => 60
    );

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->api_key = LanguageManager::get_api_key();
        Logger::debug('SeoGeminiProvider initialized');
    }

    /**
     * Generate SEO fields using Gemini AI
     *
     * @param ConstructPrompt $prompt Prompt object with content and context
     * @return array Array with 'seo_title', 'seo_description', 'og_title' keys
     * @throws Exception If generation fails
     * @since 1.0.0
     */
    public function generateSeoFields(ConstructPrompt $prompt): array {
        if (empty($this->api_key)) {
            throw new Exception('Gemini API key not configured');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model_id}:{$this->generate_content_api}?key={$this->api_key}";
        
        $prompt_data = $prompt->buildForSeoGeneration();

        $payload = array(
            "contents" => array(
                array(
                    "role" => "user",
                    "parts" => array(
                        array(
                            "text" => $prompt_data['text']
                        )
                    )
                )
            ),
            "generationConfig" => array(
                "responseMimeType" => "application/json",
                "responseSchema" => array(
                    "type" => "object",
                    "properties" => array(
                        "seo_title" => array("type" => "string"),
                        "seo_description" => array("type" => "string"),
                        "og_title" => array("type" => "string")
                    ),
                    "required" => array("seo_title", "seo_description", "og_title")
                )
            )
        );

        Logger::info('SeoGeminiProvider: Generating SEO fields', array(
            'prompt_length' => strlen($prompt_data['text'])
        ));

        $response = $this->makeApiCall($url, $payload);
        
        // Validate response structure
        if (!isset($response['seo_title']) || !isset($response['seo_description']) || !isset($response['og_title'])) {
            throw new Exception('Invalid response structure from Gemini API');
        }

        // Validate character limits
        $validated_response = $this->validateAndTrimFields($response);

        Logger::info('SeoGeminiProvider: SEO fields generated successfully', array(
            'seo_title_length' => strlen($validated_response['seo_title']),
            'seo_description_length' => strlen($validated_response['seo_description']),
            'og_title_length' => strlen($validated_response['og_title'])
        ));

        return $validated_response;
    }

    /**
     * Generate shorter version of content
     *
     * @param string $content Original content that exceeds limits
     * @param string $type Type of content ('title' or 'description')
     * @param int $max_length Maximum allowed characters
     * @return string Shortened version
     * @throws Exception If generation fails
     * @since 1.0.0
     */
    public function generateShorterVersion(string $content, string $type, int $max_length): string {
        if (empty($this->api_key)) {
            throw new Exception('Gemini API key not configured');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model_id}:{$this->generate_content_api}?key={$this->api_key}";
        
        $prompt_text = sprintf(
            "Eres un experto en SEO. Necesito que acortes el siguiente %s para que tenga máximo %d caracteres, manteniendo su esencia y optimización SEO. El %s original es: \"%s\"",
            $type === 'title' ? 'título' : 'descripción',
            $max_length,
            $type === 'title' ? 'título' : 'descripción',
            $content
        );

        $payload = array(
            "contents" => array(
                array(
                    "role" => "user",
                    "parts" => array(
                        array("text" => $prompt_text)
                    )
                )
            ),
            "generationConfig" => array(
                "responseMimeType" => "application/json",
                "responseSchema" => array(
                    "type" => "object",
                    "properties" => array(
                        "shortened_content" => array("type" => "string")
                    ),
                    "required" => array("shortened_content")
                )
            )
        );

        Logger::info('SeoGeminiProvider: Generating shorter version', array(
            'type' => $type,
            'original_length' => strlen($content),
            'max_length' => $max_length
        ));

        $response = $this->makeApiCall($url, $payload);
        
        if (!isset($response['shortened_content'])) {
            throw new Exception('Invalid response structure for shorter version');
        }

        $shortened = trim($response['shortened_content']);
        
        // Ensure it's actually shorter
        if (strlen($shortened) > $max_length) {
            $shortened = substr($shortened, 0, $max_length - 3) . '...';
        }

        Logger::info('SeoGeminiProvider: Shorter version generated', array(
            'original_length' => strlen($content),
            'new_length' => strlen($shortened)
        ));

        return $shortened;
    }

    /**
     * Generate alternative title suggestions
     *
     * @param string $original_title Original title with high similarity
     * @param array $similar_titles Array of similar existing titles
     * @param string $content Page content for context
     * @return array Array of alternative suggestions
     * @throws Exception If generation fails
     * @since 1.0.0
     */
    public function generateAlternativeTitle(string $original_title, array $similar_titles, string $content): array {
        if (empty($this->api_key)) {
            throw new Exception('Gemini API key not configured');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model_id}:{$this->generate_content_api}?key={$this->api_key}";
        
        $similar_list = implode('", "', $similar_titles);
        $prompt_text = sprintf(
            "Eres un experto en SEO. El título \"%s\" es muy similar a estos títulos existentes: [\"%s\"]. Basándote en este contenido: \"%s\", genera 3 títulos alternativos únicos y optimizados para SEO que eviten la canibalización. Cada título debe tener máximo 60 caracteres.",
            $original_title,
            $similar_list,
            substr($content, 0, 500) // Limit content for API efficiency
        );

        $payload = array(
            "contents" => array(
                array(
                    "role" => "user",
                    "parts" => array(
                        array("text" => $prompt_text)
                    )
                )
            ),
            "generationConfig" => array(
                "responseMimeType" => "application/json",
                "responseSchema" => array(
                    "type" => "object",
                    "properties" => array(
                        "alternatives" => array(
                            "type" => "array",
                            "items" => array("type" => "string")
                        )
                    ),
                    "required" => array("alternatives")
                )
            )
        );

        Logger::info('SeoGeminiProvider: Generating alternative titles', array(
            'original_title' => $original_title,
            'similar_count' => count($similar_titles)
        ));

        $response = $this->makeApiCall($url, $payload);
        
        if (!isset($response['alternatives']) || !is_array($response['alternatives'])) {
            throw new Exception('Invalid response structure for alternative titles');
        }

        // Validate and trim alternatives
        $alternatives = array();
        foreach ($response['alternatives'] as $alt) {
            $trimmed = trim($alt);
            if (strlen($trimmed) <= $this->seo_limits['seo_title']) {
                $alternatives[] = $trimmed;
            } else {
                $alternatives[] = substr($trimmed, 0, $this->seo_limits['seo_title'] - 3) . '...';
            }
        }

        Logger::info('SeoGeminiProvider: Alternative titles generated', array(
            'count' => count($alternatives)
        ));

        return $alternatives;
    }

    /**
     * Check title similarity using simple string comparison
     *
     * @param string $title Title to check
     * @param array $existing_titles Existing titles to compare
     * @param float $threshold Similarity threshold
     * @return array Similarity results
     * @since 1.0.0
     */
    public function checkTitleSimilarity(string $title, array $existing_titles, float $threshold = 0.85): array {
        $max_similarity = 0.0;
        $similar_titles = array();

        foreach ($existing_titles as $existing) {
            $similarity = $this->calculateStringSimilarity($title, $existing);
            if ($similarity > $max_similarity) {
                $max_similarity = $similarity;
            }
            if ($similarity >= $threshold) {
                $similar_titles[] = $existing;
            }
        }

        $is_similar = $max_similarity >= $threshold;

        Logger::debug('SeoGeminiProvider: Title similarity check', array(
            'title' => $title,
            'max_similarity' => $max_similarity,
            'is_similar' => $is_similar,
            'similar_count' => count($similar_titles)
        ));

        return array(
            'is_similar' => $is_similar,
            'similarity_score' => $max_similarity,
            'similar_titles' => $similar_titles
        );
    }

    /**
     * Validate SEO content against best practices
     *
     * @param array $seo_data SEO data to validate
     * @return array Validation results
     * @since 1.0.0
     */
    public function validateSeoContent(array $seo_data): array {
        $results = array(
            'valid' => true,
            'warnings' => array(),
            'recommendations' => array()
        );

        foreach ($this->seo_limits as $field => $limit) {
            if (isset($seo_data[$field])) {
                $length = strlen($seo_data[$field]);
                $percentage = ($length / $limit) * 100;

                if ($length > $limit) {
                    $results['valid'] = false;
                    $results['warnings'][] = sprintf('%s exceeds limit (%d/%d characters)', $field, $length, $limit);
                } elseif ($percentage > 90) {
                    $results['recommendations'][] = sprintf('%s is near limit (%d/%d characters)', $field, $length, $limit);
                }
            }
        }

        return $results;
    }

    /**
     * Get SEO character limits
     *
     * @return array Character limits
     * @since 1.0.0
     */
    public function getSeoLimits(): array {
        return $this->seo_limits;
    }

    /**
     * Make API call to Gemini
     *
     * @param string $url API URL
     * @param array $payload Request payload
     * @return array Parsed response
     * @throws Exception If API call fails
     * @since 1.0.0
     */
    private function makeApiCall(string $url, array $payload): array {
        $json_payload = json_encode($payload);
        if ($json_payload === false) {
            throw new Exception('Failed to encode JSON payload: ' . json_last_error_msg());
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $error");
        }

        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("API returned HTTP $http_code: $response");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new Exception('Failed to decode API response: ' . json_last_error_msg());
        }

        // Handle Gemini streaming response format
        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $content = json_decode($decoded['candidates'][0]['content']['parts'][0]['text'], true);
            if ($content !== null) {
                return $content;
            }
        }

        throw new Exception('Invalid API response format');
    }

    /**
     * Validate and trim fields to limits
     *
     * @param array $fields Fields to validate
     * @return array Validated fields
     * @since 1.0.0
     */
    private function validateAndTrimFields(array $fields): array {
        $validated = array();
        
        foreach ($this->seo_limits as $field => $limit) {
            if (isset($fields[$field])) {
                $content = trim($fields[$field]);
                if (strlen($content) > $limit) {
                    $content = substr($content, 0, $limit - 3) . '...';
                }
                $validated[$field] = $content;
            }
        }

        return $validated;
    }

    /**
     * Calculate string similarity using Levenshtein distance
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0.0 to 1.0)
     * @since 1.0.0
     */
    private function calculateStringSimilarity(string $str1, string $str2): float {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) {
            return 1.0;
        }

        $max_len = max(strlen($str1), strlen($str2));
        if ($max_len === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        return 1.0 - ($distance / $max_len);
    }
}
