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
}

