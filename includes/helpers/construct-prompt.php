<?php

namespace EZTranslate\Helpers;

class ConstructPrompt
{
    private string $title;
    private string $content;
    private string $language;

    public function __construct(string $title, string $content, string $language = 'es')
    {
        $this->title = $title;
        $this->content = $content;
        $this->language = $language;
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
}
