<?php

return [
    'action' => [
        'label' => 'AI Filter',
        'modal_heading' => 'Filter with AI',
        'modal_description' => 'Describe what you want to find. Active filters are sent as context.',
        'modal_submit' => 'Apply',
        'placeholder' => 'e.g. users created last week whose email is verified',
    ],
    'notifications' => [
        'applied_title' => 'Filters applied',
        'applied_body' => ':count change(s) applied.',
        'no_match_title' => 'No matching filters',
        'no_match_body' => 'The AI could not translate your request into the available filters or search.',
        'error_title' => 'AI request failed',
    ],
];
