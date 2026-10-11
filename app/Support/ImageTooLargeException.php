<?php

namespace App\Support;

use RuntimeException;

/** Thrown when an uploaded image declares more pixels than ImageOptimizer::MAX_PIXELS. */
class ImageTooLargeException extends RuntimeException
{
    public function __construct(int $width, int $height)
    {
        parent::__construct(sprintf(
            'La imagen es demasiado grande (%dx%d px). El máximo es de %d megapíxeles; redúcela antes de subirla.',
            $width,
            $height,
            intdiv(ImageOptimizer::MAX_PIXELS, 1_000_000)
        ));
    }
}
