<?php

interface ApiClient
{
    // Change la valeur du paramètre "key"
    public function setParam($key, $value);

    public function setFilter($key, $operator, $value);

    // Permet de changer l'URI
    public function setURI($uri);

    public function getNextPage();

    public function get($options = []);

    public function post($body, $options = []);

    public function patch($body, $options = []);

    public function buildURL();
}
