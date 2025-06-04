<?php
namespace EZTranslate\Contracts;

use EZTranslate\Helpers\ConstructPrompt;

interface AIProviderInterface {
    /**
     * Genera texto a partir de un objeto ConstructPrompt.
     *
     * @param ConstructPrompt $prompt Objeto que contiene el título, contenido y lenguaje objetivo.
     * @return array Texto generado con título y contenido traducido.
     */
    public function generarTexto(ConstructPrompt $prompt): array;
}
