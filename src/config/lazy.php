<?php
// lazy laravel config file

return [
    /**
     * http response type
     * html: html response forever
     * json: json response forever
     * auto: response type depends on request
     */
    'response' => env('LAZY_RESPONSE', 'auto'),

    'model_path' => env('LAZY_MODEL_PATH', 'app/Models')
];