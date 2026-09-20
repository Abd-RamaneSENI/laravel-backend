<?php

return [
    'pdftoppm_binary' => env('PDFTOPPM_BINARY'),
    'pdfinfo_binary' => env('PDFINFO_BINARY'),
    'resolution' => (int) env('PDF_PREVIEW_RESOLUTION', 140),
];
