<?php
// Config liées à Akeneo

if (getenv('APPLICATION_ENV') == 'local') {
    define('AKENEO_AUTH_URL', 'https://cac-dev.cloud.akeneo.com/api/oauth/v1/token');
    define('AKENEO_BASE_URL', 'https://cac-dev.cloud.akeneo.com/api/rest/v1');
    define('AKENEO_BASIC_AUTH', 'MV8zOGs0ZWFnOTA5ZXNzNGt3NDBrOHNrb3dvNDBnZ2s4NGdvNGMwYzgwb2s0dzhzZ3dvNDo0aHg5a2YzbmF5bzBrc2NrY2s0czg0azA0c293Z2s4czBnc3M4a2s0NHdrMGtrNHMwNA==');
    define('AKENEO_USERNAME', 'ipassinit_5010');
    define('AKENEO_PASSWORD', '655ea5081');
    define('AKENEO_GRANT_TYPE', 'password');
} elseif (getenv('APPLICATION_ENV') == 'recette') {
    define('AKENEO_AUTH_URL', 'https://cac-dev.cloud.akeneo.com/api/oauth/v1/token');
    define('AKENEO_BASE_URL', 'https://cac-dev.cloud.akeneo.com/api/rest/v1');
    define('AKENEO_BASIC_AUTH', 'MV8zOGs0ZWFnOTA5ZXNzNGt3NDBrOHNrb3dvNDBnZ2s4NGdvNGMwYzgwb2s0dzhzZ3dvNDo0aHg5a2YzbmF5bzBrc2NrY2s0czg0azA0c293Z2s4czBnc3M4a2s0NHdrMGtrNHMwNA==');
    define('AKENEO_USERNAME', 'ipassinit_5010');
    define('AKENEO_PASSWORD', '655ea5081');
    define('AKENEO_GRANT_TYPE', 'password');
} else {
    define('AKENEO_AUTH_URL', 'https://cac.cloud.akeneo.com/api/oauth/v1/token');
    define('AKENEO_BASE_URL', 'https://cac.cloud.akeneo.com/api/rest/v1');
    define('AKENEO_BASIC_AUTH', '');
    define('AKENEO_USERNAME', '');
    define('AKENEO_PASSWORD', '');
    define('AKENEO_GRANT_TYPE', 'password');
}
