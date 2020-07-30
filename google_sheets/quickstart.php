<?php
putenv("APPLICATION_ENV=local");
require (realpath(dirname(__FILE__) . '/../FONCTIONS/AkeneoClient.php'));
require (realpath(dirname(__FILE__) . '/../CONFIG/config.php'));
require __DIR__ .'/vendor/autoload.php';

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Sheets API PHP Quickstart');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
    $client->setAuthConfig(__DIR__ .'/credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = __DIR__ .'/token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

$akeneo_client = new AkeneoClient();
$akeneo_client->setURI("/reference-entities/couleur/records");
$couleurs= array();
while ($page = $akeneo_client->getNextPage()) {
    for ($i = 0; $i < count($page); $i++) {
        $couleurs[] = [$page[$i]['code'],$page[$i]['values']['label'][0]['data']];
    }
}
print_r($couleurs);

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Sheets($client);

//Définition de la spreadsheet
$spreadsheetId = '1QaGAMc4e16CUVpK1YHu-H7K25lwMT6pUa8F6O1JsngU';
$range = 'Danger!A2:B';
$maFeuille= array();

// Suppression puis ajout des données des 5 feuilles, feuille par feuille.
//Danger
$range = 'Danger!A2:B';
$requestBody = new Google_Service_Sheets_ClearValuesRequest();
$response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

//Marque
$range = 'Marque!A2:B';
$requestBody = new Google_Service_Sheets_ClearValuesRequest();
$response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

//Gamme
$range = 'Gamme!A2:C';
$requestBody = new Google_Service_Sheets_ClearValuesRequest();
$response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

//Couleur
$range = 'Couleur!A2:B';
$requestBody = new Google_Service_Sheets_ClearValuesRequest();
$response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

$requestBody = new Google_Service_Sheets_ValueRange(array('values' => $couleurs));
$params = ['valueInputOption' => 'RAW'];
$response = $service->spreadsheets_values->update($spreadsheetId, $range, $requestBody, $params);

//Famille
$range = 'Famille!A2:B';
$requestBody = new Google_Service_Sheets_ClearValuesRequest();
$response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);


//Ajout des couleurs dans la feuille correspondante
$range = 'Couleur!A2:B';
$requestBody = new Google_Service_Sheets_ValueRange(array('values' => $couleurs));
$params = ['valueInputOption' => 'RAW'];
$response = $service->spreadsheets_values->update($spreadsheetId, $range, $requestBody, $params);

//Ajout des Danger dans la feuille correspondante



?>
