<?php

class Helper {
    // Retourne la valeur correspondant à "key" si elle se trouve dans $_GET ou $_POST
    public static function getRequestValue($key) {
        return $_GET[$key] ?: $_POST[$key];
    }
}
