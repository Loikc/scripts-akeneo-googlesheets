<?php
putenv("APPLICATION_ENV=local");
require_once(realpath(dirname(__FILE__) . '/../FONCTIONS/AkeneoClient.php'));
require_once(realpath(dirname(__FILE__) . '/../CONFIG/config.php'));

$akeneo_client = new AkeneoClient();
$akeneo_client->setURI("/reference-entities/couleur/records");
$mesDonnees= array();
while($page = $akeneo_client->getNextPage()){
    for( $i=0 ; $i<count($page) ; $i++) {
        $mesDonnees[$page[$i]['code']] = $page[$i]['values']['label'][0]['data'];
    }
}
print_r($mesDonnees);
