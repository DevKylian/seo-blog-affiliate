<?php

namespace App\Exceptions;

use App\Models\Article;
use RuntimeException;

class DuplicateContentException extends RuntimeException
{
    public function __construct(
        public readonly Article $similarArticle,
        public readonly float $duplicateScore,
        public readonly string $duplicateDecision,
    ) {
        parent::__construct(sprintf(
            'Doublon éditorial détecté à %.0f %% avec « %s ». Action recommandée : %s.',
            $duplicateScore,
            $similarArticle->title,
            match ($duplicateDecision) {
                'block' => 'conserver l’article existant',
                'merge_or_reangle' => 'fusionner ou choisir un angle réellement différent',
                default => 'différencier clairement le brief',
            },
        ));
    }
}
