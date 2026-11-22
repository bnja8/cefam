<?php

$width = env('LABEL_WIDTH_MM');
$height = env('LABEL_HEIGHT_MM');
$provisionalDimensions = $width === null || trim((string) $width) === ''
    || $height === null || trim((string) $height) === '';

return [
    'width_mm' => $width === null || trim((string) $width) === '' ? 100 : $width,
    'height_mm' => $height === null || trim((string) $height) === '' ? 50 : $height,
    'provisional_dimensions' => $provisionalDimensions,
    'margin_mm' => env('LABEL_MARGIN_MM', 2),
    'font' => [
        'family' => env('LABEL_FONT_FAMILY', 'Arial, sans-serif'),
        'minimum_size_pt' => env('LABEL_MINIMUM_FONT_SIZE_PT', 8),
    ],
    'copies' => 1,
];
