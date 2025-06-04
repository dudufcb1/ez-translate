<?php
namespace EZTranslate\Interfaces;

interface AIProviderInterface {
    /**
     * Genera texto a partir del input.
     *
     * @param string $input Texto de entrada.
     * @return array Texto generado con título y contenido traducido.
     */
    public function generarTexto(string $input): array;
}
