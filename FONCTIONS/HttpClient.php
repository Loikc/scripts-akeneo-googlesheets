<?php

class HttpClient {
    public static function get($url, $options = []) {
        return self::request($url, 'GET', $options);
    }

    public static function post($url, $options = []) {
        return self::request($url, 'POST', $options);
    }

    public static function patch($url, $options = []) {
        return self::request($url, 'PATCH', $options);
    }

    private static function request($url, $verb = 'GET', $options = []) {
        $ch = curl_init();

        switch($verb) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);

                $body = $options['body'];

                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');

                $body = $options['body'];

                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'GET':
            default:
                break;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($options['headers'])
            curl_setopt($ch,CURLOPT_HTTPHEADER, $options['headers']);

        // Si on demande à récupérer les headers de la réponse
        $headers = [];
        if($options['with_headers']) {
            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                function($curl, $header) use (&$headers)
                {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);

                    if (count($header) < 2) // ignore invalid headers
                        return $len;

                    $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                    return $len;
                }
            );
        }

        $output = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = [
            'code' => $http_code,
            'result' => $output,
            'error' => $error,
            'headers' => $options['with_headers'] ? $headers : null
        ];

        return $result;
    }
}
