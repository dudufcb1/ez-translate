<?php

namespace EZTranslate\Helpers;

class ConstructPrompt
{
    private string $title;
    private string $content;
    private string $language;
    private string $intention;

    public function __construct(string $title, string $content, string $language = 'es', string $intention = '')
    {
        $this->title = $title;
        $this->content = $content;
        $this->language = $language;
        $this->intention = $intention;
    }

    public function build(): array
    {
        return [
            'text' => sprintf(
                "Eres un experto en traducción de contenidos con enfoque en SEO. Tu tarea es traducir el siguiente texto y código html adaptándolo al idioma de destino (%s) para maximizar el engagement, deberás retornar una cadena de texto en el titulo y el código html con la traducción en el contenido. Captura la esencia del contenido original y adáptalo cultural y lingüísticamente al nuevo público. El título del contenido es: \"%s\". El texto a traducir es: \"%s\".",
                $this->language,
                $this->title,
                $this->content
            )

        ];
    }

    public function buildForSEO(): array
    {
        return [
            'text' => sprintf(
                "Eres un experto en verificación de contenidos y optimización de los mismos	 con enfoque en SEO. Tu tarea es interpretar y determinar las mejores palabras clave para el siguiente texto (%s), es importante, que consideres la intención global del sitio web, que busca: \"%s\". Por favor retorna un titulo optimizado para seo que haga sinergia con el contenido analizado así también una descripción, recuerda los titulos deben tener entre 40 y 50 caracteres y las descripciones entre 130 y 145 caracteres.",
                $this->intention,
                $this->language,
                $this->content
            )

        ];
    }

    /**
     * Build prompt for SEO field generation
     *
     * @return array Prompt data for generating SEO title, description, and og_title
     * @since 1.0.0
     */
    public function buildForSeoGeneration(): array
    {
        return [
            'text' => sprintf(
                "Eres un experto en SEO y marketing digital. Analiza el siguiente contenido y genera campos SEO optimizados:\n\nTítulo: \"%s\"\nContenido: \"%s\"\nIdioma: %s\n\nGenera:\n1. seo_title: Título optimizado para SEO (máximo 60 caracteres)\n2. seo_description: Descripción meta para buscadores (máximo 155 caracteres)\n3. og_title: Título para redes sociales (máximo 60 caracteres)\n\nAsegúrate de que cada campo sea único, atractivo y optimizado para el idioma especificado.",
                $this->title,
                substr($this->content, 0, 1000), // Limit content for API efficiency
                $this->language
            )
        ];
    }

    /**
     * Build prompt for generating shorter SEO content
     *
     * @param string $content Original content to shorten
     * @param string $type Type of content ('title' or 'description')
     * @param int $max_length Maximum allowed characters
     * @return array Prompt data for shortening content
     * @since 1.0.0
     */
    public function buildForShorterSeo(string $content, string $type, int $max_length): array
    {
        return [
            'text' => sprintf(
                "Eres un experto en SEO. Necesito que acortes el siguiente %s para que tenga máximo %d caracteres, manteniendo su esencia y optimización SEO.\n\n%s original: \"%s\"\n\nGenera una versión más corta que mantenga las palabras clave principales y el atractivo para el usuario.",
                $type === 'title' ? 'título' : 'descripción',
                $max_length,
                $type === 'title' ? 'Título' : 'Descripción',
                $content
            )
        ];
    }

    /**
     * Build prompt for generating alternative titles
     *
     * @param string $original_title Original title with similarity issues
     * @param array $similar_titles Array of similar existing titles
     * @return array Prompt data for alternative titles
     * @since 1.0.0
     */
    public function buildForAlternativeTitle(string $original_title, array $similar_titles): array
    {
        $similar_list = implode('", "', $similar_titles);

        return [
            'text' => sprintf(
                "Eres un experto en SEO y prevención de canibalización de contenido. El título \"%s\" es muy similar a estos títulos existentes: [\"%s\"].\n\nBasándote en el contenido: \"%s\"\n\nGenera 3 títulos alternativos únicos que:\n1. Eviten la similitud con los títulos existentes\n2. Mantengan la optimización SEO\n3. Tengan máximo 60 caracteres\n4. Sean atractivos para el usuario\n\nIdioma objetivo: %s",
                $original_title,
                $similar_list,
                substr($this->content, 0, 500),
                $this->language
            )
        ];
    }

    /**
     * Build prompt for similarity checking context
     *
     * @param string $title Title to analyze for similarity
     * @return array Prompt data for similarity analysis
     * @since 1.0.0
     */
    public function buildForSimilarityCheck(string $title): array
    {
        return [
            'text' => sprintf(
                "Eres un experto en análisis de contenido SEO. Analiza la similitud semántica entre el título \"%s\" y el contenido: \"%s\".\n\nEvalúa si el título representa adecuadamente el contenido y sugiere mejoras si es necesario.\n\nIdioma: %s",
                $title,
                substr($this->content, 0, 800),
                $this->language
            )
        ];
    }
}

