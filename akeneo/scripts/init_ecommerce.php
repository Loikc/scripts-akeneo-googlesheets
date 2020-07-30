<?php

header('Content-type: application/json');

require_once(realpath(dirname(__FILE__) . '/../../CONFIG/config.php'));
require_once(realpath(dirname(__FILE__) . '/../../FONCTIONS/PrestashopClient.php'));
require_once(realpath(dirname(__FILE__) . '/init_ecommerce_fonctions.php'));

$akeneo_client = new AkeneoClient();
$prestashop_client = new PrestashopClient();

// On récupère la liste des produits à importer dans Akeneo
$products = getNotImportedProducts();

foreach($products as $product) {
    $product_id = $product['ID'];
    $ean = $product['EAN'];
    $has_combo = $product['HAS_COMBO'];

    // Si le produit n'a pas d'EAN et pas de déclinaison, on ne le traite pas
    if(!$ean && !$has_combo)
        continue;

    $akeneo_response = getAkeneoProduct($akeneo_client, $ean);

    // Si le produit n'existe pas sur Akeneo, on ne fait rien
    if($akeneo_response['code'] !== 200)
        continue;


    // Si le produit a des déclinaisons, on ne fait rien pour l'instant
    if($has_combo)
        continue;


    $akeneo_product = json_decode($akeneo_response['result'], true);

    // On récupère les infos du produit
    $id_categ_default = getProductDefaultCategory($product_id);
    $code_famille_akeneo = $id_categ_default ? getFamilyCodeAkeneo($id_categ_default) : 'brouillon';
    $categories = getProductCategories($product_id);
    $features = getProductFeatures($product_id);
    $manufacturer_code = getManufacturerAkeneoCode($product_id, $akeneo_product['values']['marque'][0]['data']);
    $asin = getProductASIN($product_id);
    $ean_cdiscount = getProductCdiscountEAN($product_id);
    $matching_urls = getMatchingUrls($product_id);
    $country_restrictions = getProductCountryRestrictions($product_id);
    $descriptions = getProductDescriptions($product_id);
    $descriptions_courtes = getProductShortDescriptions($product_id);
    $name = getProductNames($product_id);
    $destine_vente = isDestineVente($product_id, $akeneo_product['values']['destine_vente'][0]['data']);
    $range = getProductRange($features[36], $akeneo_product['values']['gamme'][0]['data']);
    $meta_descriptions = getProductMetaDescriptions($product_id);
    $meta_titles = getProductMetaTitle($product_id);
    $meta_urls = getProductMetaUrls($product_id);
    $qte_min = getProductQteMin($product_id);
    $intrastat = $akeneo_product['values']['Intrastat'][0]['data'];
    $pays_origine = $akeneo_product['values']['pays_origine'][0]['data'];
    $photo_defaut = getProductCoverImage($product_id);
    $joined_documents = getProductJoinedDocuments($product_id);
    $secondary_images = getProductSecondaryImages($product_id);

    $data = [
        'ean' => $ean,
        'code_famille_akeneo' => $code_famille_akeneo,
        'categories' => $categories,
        'features' => $features,
        'asin' => $asin,
        'descriptions' => $descriptions,
        'descriptions_courtes' => $descriptions_courtes,
        'name' => $name,
        'destine_vente' => $destine_vente,
        'ean_cdiscount' => $ean_cdiscount,
        'range' => $range,
        'manufacturer_code' => $manufacturer_code,
        'meta_descriptions' => $meta_descriptions,
        'meta_titles' => $meta_titles,
        'meta_urls' => $meta_urls,
        'country_restrictions' => $country_restrictions,
        'qte_min' => $qte_min,
    ];

    $product_json = getSimpleProductJSON($data);

    // On update le produit sur Akeneo
    /*
    $akeneo_client->setURI("/products/$ean");
    $patch_result = $akeneo_client->patch($product_json);

    // Si le produit a une photo par défaut, on l'upload
    if($photo_defaut)
        $result = uploadProductCoverImage($product_id, $ean, $photo_defaut, $prestashop_client, $akeneo_client);

    // Si le produit a des documents joints, on les upload
    if($joined_documents)
        $result = uploadProductJoinedDocuments($product_id, $ean, $joined_documents, $akeneo_client);

    // Si le produit a des images secondaires, on les uploads
    if($secondary_images)
        $result = uploadProductSecondaryImages($product_id, $ean, $secondary_images, $prestashop_client, $akeneo_client);
    */


    //print_r($result);


    print_r($ean);
    echo "\n";
    /*
    print_r($patch_result);
    echo "\n";
    print_r($product_json);
    */
}


