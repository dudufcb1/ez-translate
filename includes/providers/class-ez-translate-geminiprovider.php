<?php 

namespace EZTranslate\Providers;

use EZTranslate\Contracts\AIProviderInterface;
use EZTranslate\LanguageManager;
use EZTranslate\Helpers\ConstructPrompt;
use Exception;

class GeminiProvider implements AIProviderInterface {
    private $api_key;
    private $model_id = "gemini-2.0-flash";
    private $generate_content_api = "generateContent";

    public function __construct() {
        $this->api_key = LanguageManager::get_api_key();
    }

    /**
     * Genera texto a partir del ConstructPrompt usando la API de Gemini.
     *
     * @param ConstructPrompt $prompt Objeto que contiene el título, contenido y lenguaje objetivo.
     * @return array Texto generado con título y contenido traducido.
     * @throws Exception Si ocurre algún error en la petición o en la respuesta.
     */
    public function generarTexto(ConstructPrompt $prompt): array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model_id}:{$this->generate_content_api}?key={$this->api_key}";

        $promptData = $prompt->build();

        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        [
                            "text" => $promptData['text'],
                        ],
                    ],
                ],
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json",
                "responseSchema" => [
                    "type" => "object",
                    "properties" => [
                        "translated_title" => [
                            "type" => "string"
                        ],
                        "translated_content" => [
                            "type" => "string"
                        ]
                    ],
                    "required" => [
                        "translated_title",
                        "translated_content"
                    ]
                ]
            ],
        ];

        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            \EZTranslate\Logger::error('GeminiProvider: Error al codificar JSON del payload', array(
                'payload' => $payload,
                'json_error' => json_last_error_msg()
            ));
            throw new Exception("Error al codificar JSON del payload.");
        }

        \EZTranslate\Logger::info('GeminiProvider: Enviando request a la API', array(
            'url' => $url,
            'payload_size' => strlen($jsonPayload),
            'prompt_text_length' => strlen($promptData['text'])
        ));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error en cURL: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            \EZTranslate\Logger::error('GeminiProvider: Error HTTP de la API', array(
                'http_code' => $httpCode,
                'response' => $response,
                'url' => $url
            ));
            throw new Exception("Error HTTP: código $httpCode");
        }

        $result = json_decode($response, true);

        if ($result === null) {
            \EZTranslate\Logger::error('GeminiProvider: Error al decodificar JSON de la respuesta', array(
                'response' => $response,
                'json_error' => json_last_error_msg()
            ));
            throw new Exception("Error al decodificar JSON de la respuesta.");
        }

        // Log the complete API response for debugging
        \EZTranslate\Logger::info('GeminiProvider: Respuesta completa de la API', array(
            'response_structure' => $this->getResponseStructure($result),
            'full_response' => $result
        ));

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $translationText = $result['candidates'][0]['content']['parts'][0]['text'];

            \EZTranslate\Logger::info('GeminiProvider: Texto de traducción extraído', array(
                'translation_text' => $translationText
            ));

            $translationData = json_decode($translationText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                \EZTranslate\Logger::error('GeminiProvider: Error al decodificar la respuesta de traducción', array(
                    'translation_text' => $translationText,
                    'json_error' => json_last_error_msg()
                ));
                throw new Exception("Error al decodificar la respuesta de traducción.");
            }

            \EZTranslate\Logger::info('GeminiProvider: Datos de traducción decodificados', array(
                'translation_data' => $translationData
            ));

            if (!isset($translationData['translated_title']) || !isset($translationData['translated_content'])) {
                \EZTranslate\Logger::error('GeminiProvider: La respuesta no contiene los campos requeridos', array(
                    'translation_data' => $translationData,
                    'has_title' => isset($translationData['translated_title']),
                    'has_content' => isset($translationData['translated_content'])
                ));
                throw new Exception("La respuesta no contiene los campos requeridos de traducción.");
            }

            \EZTranslate\Logger::info('GeminiProvider: Traducción exitosa', array(
                'translated_title' => $translationData['translated_title'],
                'content_length' => strlen($translationData['translated_content'])
            ));

            return [
                'title' => $translationData['translated_title'],
                'content' => $translationData['translated_content']
            ];
        }

        \EZTranslate\Logger::error('GeminiProvider: Estructura de respuesta inesperada', array(
            'response_structure' => $this->getResponseStructure($result),
            'expected_path' => 'candidates[0].content.parts[0].text'
        ));

        throw new Exception("Respuesta inesperada de la API.");
    }

    /**
     * Helper method to get the structure of the API response for debugging
     *
     * @param array $data The response data
     * @return array Structure description
     */
    private function getResponseStructure($data) {
        if (!is_array($data)) {
            return ['type' => gettype($data)];
        }

        $structure = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = [
                    'type' => 'array',
                    'count' => count($value),
                    'keys' => is_array($value) && !empty($value) ? array_keys($value) : []
                ];
            } else {
                $structure[$key] = [
                    'type' => gettype($value),
                    'length' => is_string($value) ? strlen($value) : null
                ];
            }
        }
        return $structure;
    }
}
