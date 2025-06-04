<?php 

namespace EZTranslate\Providers;

use EZTranslate\Interfaces\AIProviderInterface;
use EZTranslate\LanguageManager;
use EZTranslate\Helpers\ConstructPrompt;
use Exception;

class GeminiProvider implements AIProviderInterface {
    private $api_key;
    private $model_id = "gemini-2.0-flash";
    private $generate_content_api = "streamGenerateContent";

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
            throw new Exception("Error al codificar JSON del payload.");
        }

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
            throw new Exception("Error HTTP: código $httpCode");
        }

        $result = json_decode($response, true);

        if ($result === null) {
            throw new Exception("Error al decodificar JSON de la respuesta.");
        }

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $translationData = json_decode($result['candidates'][0]['content']['parts'][0]['text'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error al decodificar la respuesta de traducción.");
            }

            if (!isset($translationData['translated_title']) || !isset($translationData['translated_content'])) {
                throw new Exception("La respuesta no contiene los campos requeridos de traducción.");
            }

            return [
                'title' => $translationData['translated_title'],
                'content' => $translationData['translated_content']
            ];
        }

        throw new Exception("Respuesta inesperada de la API.");
    }
}
