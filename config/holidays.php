<?php

return [
    'provider' => env('HOLIDAY_SUGGESTIONS_PROVIDER', 'openai'),
    'reference_pages' => [
        2026 => [
            'https://www.officialgazette.gov.ph/nationwide-holidays/2026/',
            'https://www.officialgazette.gov.ph/2026/09/17/proclamation-no-1447-s-2026/',
            'https://pco.gov.ph/news_releases/pbbm-issues-proclamation-declaring-regular-holidays-special-non-working-days-for-2026/',
            'https://pco.gov.ph/news_releases/palace-declares-nov-16-18-as-special-non-working-days-in-ncr-for-asean-summit/',
        ],
        2027 => [
            'https://www.officialgazette.gov.ph/nationwide-holidays/2027/',
            'https://pco.gov.ph/news_releases/pbbm-declares-2027-regular-and-special-holidays/',
        ],
    ],
];
