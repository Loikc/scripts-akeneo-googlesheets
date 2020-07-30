<?php

require_once(realpath(dirname(__FILE__) . '/../CONFIG/prestashop.php'));
require_once(realpath(dirname(__FILE__) . '/ApiClient.php'));

class PrestashopClient implements ApiClient {
    // URI de la ressource à aller chercher dans l'API
    private $uri;

    // URL vers la page suivante
    private $next_page;

    // Contient les paramètres GET de la requête
    private $params;

    // Contient la liste des filtres de la requête
    private $filters;

    function __construct() {
        $this->uri = '';
        $this->next_page = '';

        $this->params = [];
        $this->filters = [];
    }

    public function setParam($key, $value) {}

    public function setFilter($key, $operator, $value) {}

    public function setURI($uri) {
        $this->uri = $uri;

        $this->buildURL();
    }

    public function getNextPage() {}

    public function get($options = []) {
        if(!$options)
            $options = [
                'headers' => [
                    'Connection: Keep-alive'
                ]
            ];

        $this->params = [];
        $this->filters = [];

        $this->buildURL();

        $result = HttpClient::get($this->next_page, $options);

        return $result;
    }


    public function post($body, $options = []) {}

    public function patch($body, $options = []) {}

    public function buildURL() {
        $this->next_page = PRESTASHOP_BASE_URL . $this->uri;
    }
}
