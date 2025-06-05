<?php
/**
 * AI SEO Provider Interface for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate\Contracts;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Helpers\ConstructPrompt;

/**
 * Interface for AI providers specialized in SEO operations
 *
 * This interface extends the basic AI functionality to include
 * specialized SEO operations like generating optimized titles,
 * descriptions, and checking for content similarity.
 *
 * @since 1.0.0
 */
interface AISeoProviderInterface {

    /**
     * Generate SEO fields (title, description, og_title) for a given content
     *
     * @param ConstructPrompt $prompt Prompt object with content and context
     * @return array Array with 'seo_title', 'seo_description', 'og_title' keys
     * @throws Exception If generation fails
     * @since 1.0.0
     */
    public function generateSeoFields(ConstructPrompt $prompt): array;

    /**
     * Generate a shorter version of SEO content when it exceeds limits
     *
     * @param string $content Original content that exceeds character limits
     * @param string $type Type of content ('title' or 'description')
     * @param int $max_length Maximum allowed characters
     * @return string Shortened version of the content
     * @throws Exception If generation fails
     * @since 1.0.0
     */
    public function generateShorterVersion(string $content, string $type, int $max_length): string;

    /**
     * Generate alternative title suggestions to avoid SEO cannibalization
     *
     * @param string $original_title Original title that has high similarity
     * @param array $similar_titles Array of similar existing titles
     * @param string $content Page content for context
     * @return array Array of alternative title suggestions
     * @throws Exception If generation fails
     * @since 1.0.0
     */
    public function generateAlternativeTitle(string $original_title, array $similar_titles, string $content): array;

    /**
     * Check similarity between a title and existing titles
     *
     * @param string $title Title to check
     * @param array $existing_titles Array of existing titles to compare against
     * @param float $threshold Similarity threshold (0.0 to 1.0)
     * @return array Array with 'is_similar' boolean and 'similarity_score' float
     * @throws Exception If check fails
     * @since 1.0.0
     */
    public function checkTitleSimilarity(string $title, array $existing_titles, float $threshold = 0.85): array;

    /**
     * Validate SEO content against best practices
     *
     * @param array $seo_data Array with 'seo_title', 'seo_description', 'og_title'
     * @return array Validation results with recommendations
     * @since 1.0.0
     */
    public function validateSeoContent(array $seo_data): array;

    /**
     * Get recommended character limits for different SEO fields
     *
     * @return array Array with field names as keys and limits as values
     * @since 1.0.0
     */
    public function getSeoLimits(): array;
}
