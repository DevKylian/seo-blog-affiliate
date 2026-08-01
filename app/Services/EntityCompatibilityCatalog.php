<?php

namespace App\Services;

class EntityCompatibilityCatalog
{
    /**
     * Entités formellement rejetées par Indy.
     * Tout contenu généré mentionnant ces termes sera rejeté au planning.
     * 
     * @var string[]
     */
    public const FORBIDDEN_ENTITIES = [
        'association',
        'associations',
        'loi 1901',
        'cse',
        'comité social et économique',
        'comités sociaux et économiques',
        'agriculture',
        'agricole',
        'agricoles',
        'agriculteur',
        'agriculteurs',
        'lmp',
        'loueur meublé professionnel',
    ];

    /**
     * Vérifie si le texte mentionne une entité interdite.
     */
    public function isForbidden(string $text): bool
    {
        $normalized = mb_strtolower(str_replace(['-', '_'], ' ', $text));

        foreach (self::FORBIDDEN_ENTITIES as $forbidden) {
            if (preg_match('/\b' . preg_quote($forbidden, '/') . '\b/u', $normalized)) {
                return true;
            }
        }

        return false;
    }
}
