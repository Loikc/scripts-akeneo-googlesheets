<?php
//Config global à tous les projets

define('PROJECT_ROOT_DIR', realpath(dirname(__FILE__)) . '/../');
define('PROJECT_TMP_DIR', PROJECT_ROOT_DIR . 'TMP/');

if (getenv('APPLICATION_ENV') == 'local') {
    define('DB_SERVER', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWD', 'root');
    define('DB_NAME', 'integration');
    define('API_SITENAME', 'http://local.api.groupe-cac.io/');
    define('API_LOG_DIR', '/Users/'.get_current_user().'/Sites/integration/logs');
} elseif (getenv('APPLICATION_ENV') == 'recette') {
    define('DB_SERVER', 'localhost');
    define('DB_USER', 'recette_integration');
    define('DB_PASSWD', 'Qv9M7ofSOS3f');
    define('DB_NAME', 'recette_integration');
    define('API_SITENAME', 'http://recette.api.groupe-cac.io/');
    define('API_LOG_DIR', '/home4/recette/recette.api.groupe-cac.io/logs');
} else {
    define('DB_SERVER', 'localhost');
    define('DB_USER', 'integration');
    define('DB_PASSWD', 'iUnIgMTIHa7meyVnU83M!');
    define('DB_NAME', 'integration');
    define('API_SITENAME', 'http://api.groupe-cac.io/');
    define('API_LOG_DIR', '/home/groupecac-api/logs');
}
