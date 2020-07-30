<?php

require_once(realpath(dirname(__FILE__) . '/../CONFIG/akeneo.php'));
require_once(realpath(dirname(__FILE__) . '/ApiClient.php'));
require_once(realpath(dirname(__FILE__) . '/HttpClient.php'));


class AkeneoClient implements ApiClient {
    // Token de connexion à Akeneo
    private $token;

    // URI de la ressource à aller chercher dans l'API
    private $uri;

    // URL vers la page suivante
    private $next_page;

    // Contient les paramètres GET de la requête
    private $params;

    // Contient la liste des filtres de la requête
    private $filters;

    function __construct() {
        $this->token = $this->getToken();

        $this->params = [
            'pagination_type' => 'page',
            'limit' => 100
        ];

        $this->filters = [];
    }

    // Change la valeur du paramètre "key"
    public function setParam($key, $value) {
        $this->params[$key] = $value;

        $this->buildURL();
    }

    public function setFilter($key, $operator, $value) {
        $this->filters[$key][] = [
            'operator' => $operator,
            'value' => $value
        ];

        $this->buildURL();
    }

    // Permet de changer l'URI
    public function setURI($uri) {
        $this->uri = $uri;

        $this->buildURL();
    }

    // Récupère les données de la page suivante, puis passe à la page suivante s'il y en a une
    public function getNextPage() {
        if($this->next_page) {
            $page_data = json_decode($this->fetch()['result'], true);

            $this->next_page = $page_data['_links']['next']['href'];

            $items = $page_data['_embedded']['items'];

            return $items;
        }

        return false;
    }

    // Récupère les données depuis l'API, sans params
    public function get($options = []) {
        $this->params = [];

        $this->buildURL();

        return $this->fetch($options);
    }

    // Récupère les données depuis l'API
    private function fetch($options = []) {
        if(!$options)
            $options = [
                'headers' => [
                    'Authorization: Bearer ' . $this->token
                ]
            ];
        else
            $options['headers'][] = 'Authorization: Bearer ' . $this->token;

        $result = HttpClient::get($this->next_page, $options);

        return $result;
    }

    // Effectue une requête PATCH
    public function post($body, $options = []) {
        if(!$options)
            $options = [
                'headers' => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json'
                ],
                'body' => $body
            ];
        else {
            $options['headers'][] = 'Authorization: Bearer ' . $this->token;
            $options['body'] = $body;
        }

        $this->params = [];
        $this->buildURL();

        return HttpClient::post($this->next_page, $options);
    }

    // Effectue une requête PATCH
    public function patch($body, $options = []) {
        if(!$options)
            $options = [
                'headers' => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json'
                ],
                'body' => $body
            ];
        else {
            $options['headers'][] = 'Authorization: Bearer ' . $this->token;
            $options['body'] = $body;
        }

        $this->params = [];
        $this->buildURL();

        return HttpClient::patch($this->next_page, $options);
    }

    // Récupère le token de connexion à Akeneo
    private function getToken() {
        $akeneo_credentials = json_encode([
            'username' => AKENEO_USERNAME,
            'password' => AKENEO_PASSWORD,
            'grant_type' => AKENEO_GRANT_TYPE,
        ]);

        $options = [
            'body' => $akeneo_credentials,
            'headers' => [
                'Authorization: Basic ' . AKENEO_BASIC_AUTH,
                'Content-type: application/json'
            ]
        ];

        $result = HttpClient::post(AKENEO_AUTH_URL, $options);

        /*
        if($result['code'] !== 200)
            throw new Exception($result['result']);
        */

        $json_response = json_decode($result['result'], true);

        return $json_response['access_token'];
    }

    // Construit l'URL de l'API à partir de l'URL de base, de l'URI, des paramètres et des filtres
    public function buildURL() {
        $params = [];
        foreach($this->params as $key => $value) {
            $params[] = "$key=$value";
        }

        if($this->filters) {
            $params[] = 'search=' . urlencode(json_encode($this->filters));
        }

        $this->next_page = AKENEO_BASE_URL . $this->uri . '?' . implode('&', $params);
    }
}
