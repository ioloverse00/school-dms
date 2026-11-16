<?php

return [
    // A practical submission ceiling, configurable for institution-specific requirements.
    'max_copies' => (int) env('DOCUMENT_REQUEST_MAX_COPIES', 100),
];
