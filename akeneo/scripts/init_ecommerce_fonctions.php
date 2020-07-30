<?php

require_once(realpath(dirname(__FILE__) . '/../../FONCTIONS/HttpClient.php'));
require_once(realpath(dirname(__FILE__) . '/../../FONCTIONS/Helper.php'));
require_once(realpath(dirname(__FILE__) . '/../../FONCTIONS/AkeneoClient.php'));
require_once(realpath(dirname(__FILE__) . '/../../FONCTIONS/DbJesuiscoiffeur.php'));

// Récupère la liste des produits qui n'ont pas encore été importés dans Akeneo
function getNotImportedProducts() {
    $db = DbJesuiscoiffeur::getInstance();

    return $db->query("
      SELECT p.id_product as `ID`, 
             p.ean13 as `EAN`, 
             IF((SELECT pa.id_product FROM ps_product_attribute pa WHERE p.id_product = pa.id_product LIMIT 1) IS NULL, 0, 1) as `HAS_COMBO`
      FROM ps_product p 
      WHERE (p.xtd_active IN (1, 3) OR p.xtd_invendable = 1)
        AND p.xtd_import_akeneo = 0
        AND p.id_product = 1516
      ORDER BY 3 ASC, p.id_category_default DESC
    
      LIMIT 1
    ");
}

// Récupère le produit correspondant à l'EAN donné depuis Akeneo
function getAkeneoProduct($client, $ean) {
    $client->setURI("/products/$ean");
    return $client->get();
}

// Récupère la catégorie par défaut d'un produit
function getProductDefaultCategory($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    return $db->getValue("
        SELECT id_category_default
        FROM ps_product 
        WHERE id_product = $product_id
    ");
}

// Récupère le code de la famille Akeneo d'un produit en fonction de sa catégorie par défaut
function getFamilyCodeAkeneo($id_categ_default) {
    $db = DbJesuiscoiffeur::getInstance();

    $code_famille_akeneo = $db->getValue("
            SELECT `akeneo_code` 
            FROM `ps_akeneo_association` 
            WHERE `ps_id` = '$id_categ_default'
              AND `ps_object` = 'category_family'
        ");

    return $code_famille_akeneo ?: 'brouillon';
}

// Récupère la liste des catégories d'un produit
function getProductCategories($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $categories = $db->query("
        SELECT aa.akeneo_value as `code`
        FROM ps_category_product cp
            JOIN ps_akeneo_association aa ON cp.id_category = aa.ps_id AND ps_object = 'category'
        WHERE cp.id_product = $product_id
    ");

    $categories = $categories ?: [];

    $categories = array_map(function($elem) {
        return $elem['code'];
    }, $categories);


    if(count($categories) === 0)
        $categories[] = 'ecommerce_brouillon';

    $categories[] = 'print_brouillon';

    return $categories;
}

// Récupère la liste des caractéristiques d'un produit ainsi que leur valeur
function getProductFeatures($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $features = $db->query("
        SELECT fp.id_product, fp.id_feature, fp.id_feature_value, fvl.value
        FROM ps_feature_product fp
            JOIN ps_feature_value_lang fvl ON fp.id_feature_value = fvl.id_feature_value AND fvl.id_lang = 1
        WHERE id_product = $product_id
    ");

    $features_data = [];
    foreach($features as $feature) {
        $feature_id = $feature['id_feature'];
        $feature_value_id = $feature['id_feature_value'];
        $feature_value = formatFeatureValue($feature);

        if(! $feature_value)
            continue;

        if($features_data[$feature_id])
            $features_data[$feature_id][] = $feature_value;
        else
            $features_data[$feature_id] = [$feature_value];
    }

    return $features_data;
}

// Formatte la valeur d'une caractéristique pour qu'elle corresponde à ce qu'attends Akeneo
function formatFeatureValue($feature) {
    $db = DbJesuiscoiffeur::getInstance();
    $feature_id = $feature['id_feature'];
    $feature_value_id = $feature['id_feature_value'];
    $feature_value = $feature['value'];

    switch((int) $feature_id) {
        case 26:
            $formatted_value = preg_replace('/sur /', '', $feature_value);
            break;
        case 51:
            $formatted_value = $feature_value === 'Sans fil' ? 'oui' : 'non';
            break;
        case 66:
            $amount = preg_replace('/mm/', '', $feature_value);
            $amount = preg_replace('/,/', '.', $amount);
            $amount = preg_replace('/ /', '', $amount);
            $formatted_value = $amount;
            break;
        case 64:
            $formatted_value = $feature_value;
            break;
        case 67:
            preg_match_all('/[0-9]+/', $feature_value, $matches);
            rsort($matches[0]);
            $formatted_value = $matches[0][0];
            break;
        case 31:
            $formatted_value = $feature_value_id == '1803' ? 'Gaucher' : null;
            break;
        case 36:
            $formatted_value = $db->getValue("
                SELECT akeneo_code 
                FROM ps_akeneo_association 
                WHERE ps_object LIKE 'range' 
                  AND ps_id = '$feature_value_id'
            ");
            break;
        default:
            $formatted_value = $db->getValue("
                SELECT akeneo_value 
                FROM ps_akeneo_association 
                WHERE ps_object LIKE 'feature_value' 
                  AND ps_id = '$feature_value_id'
            ");
            break;
    }

    return $formatted_value;
}

// Récupère le code Akeneo de la marque d'un produit
function getManufacturerAkeneoCode($product_id, $old_value) {
    if($old_value)
        return $old_value;

    $db = DbJesuiscoiffeur::getInstance();

    $manufacturer_code = $db->getValue("
        SELECT aa.akeneo_value
        FROM ps_product p 
            JOIN ps_akeneo_association aa ON aa.ps_id = p.id_manufacturer AND aa.ps_object = 'manufacturer' 
        WHERE p.id_product = '$product_id'
    ");

    return $manufacturer_code ?: '';
}

// Récupère l'ASIN du produit
function getProductASIN($product_id, $product_attribute_id = 0) {
    $db = DbJesuiscoiffeur::getInstance();

    $asin_fr = $db->getValue("
        SELECT asin 
        FROM ps_marketplace_product 
        WHERE id_marketplace = 1 
          AND country = 'FR' 
          AND id_product = $product_id 
          AND id_product_attribute = $product_attribute_id
    ");

    $asin_de = $db->getValue("
        SELECT asin 
        FROM ps_marketplace_product 
        WHERE id_marketplace = 1 
          AND country = 'DE' 
          AND id_product = $product_id 
          AND id_product_attribute = $product_attribute_id
    ");

    $asin_es = $db->getValue("
        SELECT asin 
        FROM ps_marketplace_product 
        WHERE id_marketplace = 1 
          AND country = 'ES' 
          AND id_product = $product_id 
          AND id_product_attribute = $product_attribute_id
    ");

    $asin_it = $db->getValue("
        SELECT asin 
        FROM ps_marketplace_product 
        WHERE id_marketplace = 1 
          AND country = 'IT' 
          AND id_product = $product_id 
          AND id_product_attribute = $product_attribute_id
    ");

    $asin_uk = $db->getValue("
        SELECT asin 
        FROM ps_marketplace_product 
        WHERE id_marketplace = 1 
          AND country = 'UK' 
          AND id_product = $product_id 
          AND id_product_attribute = $product_attribute_id
    ");

    $asin = [
        'fr_FR' => $asin_fr,
        'de_DE' => $asin_de,
        'es_ES' => $asin_es,
        'it_IT' => $asin_it,
        'en_GB' => $asin_uk
    ];

    $asin = array_filter($asin, function($elem) {
        return !!$elem;
    });

    $formatted_asin = [];
    foreach($asin as $locale => $value) {
        $formatted_asin[] = [
            'data' => $value,
            'locale' => $locale,
            'scope' => null
        ];
    }

    return $formatted_asin;
}

// Récupère l'EAN Cdiscount du produit
function getProductCdiscountEAN($product_id, $product_attribute_id = 0) {
    $db = DbJesuiscoiffeur::getInstance();

    return $db->getValue("
      SELECT ean 
      FROM ps_marketplace_product 
      WHERE id_marketplace = 4 
        AND id_product = $product_id
        AND id_product_attribute = $product_attribute_id
    ") ?: '';
}

// Récupère la liste des URLs matching
function getMatchingUrls($product_id, $product_attribute_id = 0) {
    $db = DbJesuiscoiffeur::getInstance();

    $url_laboutiqueducoiffeur = $db->getValue("SELECT ean FROM ps_marketplace_product WHERE id_marketplace = 5 AND id_product = $product_id AND id_product_attribute = $product_attribute_id");
    $url_bleulibellule = $db->getValue("SELECT ean FROM ps_marketplace_product WHERE id_marketplace = 6 AND id_product = $product_id AND id_product_attribute = $product_attribute_id");
    $url_gouiran = $db->getValue("SELECT ean FROM ps_marketplace_product WHERE id_marketplace = 7 AND id_product = $product_id AND id_product_attribute = $product_attribute_id");
    $url_beautycoiffure = $db->getValue("SELECT ean FROM ps_marketplace_product WHERE id_marketplace = 8 AND id_product = $product_id AND id_product_attribute = $product_attribute_id");
    $url_pascalcoste = $db->getValue("SELECT ean FROM ps_marketplace_product WHERE id_marketplace = 9 AND id_product = $product_id AND id_product_attribute = $product_attribute_id");
    $url_kalista = $db->getValue("SELECT ean FROM ps_marketplace_product WHERE id_marketplace = 10 AND id_product = $product_id AND id_product_attribute = $product_attribute_id");

    $matching_urls = [
        'url_beautycoiffure' => $url_beautycoiffure,
        'url_bleulibellule' => $url_bleulibellule,
        'url_gouiran' => $url_gouiran,
        'url_kalista' => $url_kalista,
        'url_laboutiqueducoiffeur' => $url_laboutiqueducoiffeur,
        'url_pascalcoste' => $url_pascalcoste,
    ];

    return $matching_urls;
}

// Récupère les restrictions par pays du produit
function getProductCountryRestrictions($product_id, $product_attribute_id = 0) {
    $db = DbJesuiscoiffeur::getInstance();

    $restrictions = $db->query("
      SELECT restreint as fr_FR, restreintUK as en_GB, restreintES as es_ES, restreintDE as de_DE, restreintIT as it_IT 
      FROM ps_marketplace_product 
      WHERE id_marketplace = 1 
        AND id_product = $product_id
        AND id_product_attribute = $product_attribute_id
      LIMIT 1
    ");

    if($restrictions)
        $restrictions = $restrictions[0];
    else
        $restrictions = [
            'fr_FR' => 0,
            'en_GB' => 0,
            'es_ES' => 0,
            'de_DE' => 0,
            'it_IT' => 0,
        ];

    $formatted_restrictions = [];
    foreach($restrictions as $locale => $is_restricted) {
        $formatted_restrictions[] = [
            'data' => $is_restricted ? ['amazon'] : [],
            'locale' => $locale,
            'scope' => null
        ];
    }

    return $formatted_restrictions;
}

// Récupère les descriptions d'un produit
function getProductDescriptions($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $descriptions = $db->query("
        SELECT aa.akeneo_value as locale, pl.description
        FROM ps_product_lang pl 
            JOIN ps_akeneo_association aa ON pl.id_lang = aa.ps_id AND aa.ps_object = 'lang'
        WHERE pl.id_product = $product_id
    ");

    $formatted_description = [];
    foreach ($descriptions as $description) {
        $formatted_description[] = [
            'data' => $description['description'],
            'locale' => $description['locale'],
            'scope' => 'ecommerce',
        ];
    }

    return $formatted_description;
}

// Récupère les descriptions courtes d'un produit
function getProductShortDescriptions($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $descriptions_short = $db->query("
        SELECT aa.akeneo_value as locale, pl.description_short
        FROM ps_product_lang pl 
            JOIN ps_akeneo_association aa ON pl.id_lang = aa.ps_id AND aa.ps_object = 'lang'
        WHERE pl.id_product = $product_id
    ");

    $formatted_description = [];
    foreach ($descriptions_short as $description) {
        $formatted_description[] = [
            'data' => $description['description_short'] ?: null,
            'locale' => $description['locale'],
            'scope' => 'ecommerce',
        ];
    }

    return $formatted_description;
}

// Récupère les noms d'un produit
function getProductNames($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $names = $db->query("
        SELECT aa.akeneo_value as locale, IF(pl.xtd_name_short != '', pl.xtd_name_short, pl.name) as designation
        FROM ps_product_lang pl 
            JOIN ps_akeneo_association aa ON pl.id_lang = aa.ps_id AND aa.ps_object = 'lang'
        WHERE pl.id_product = $product_id
    ");

    $formatted_names = [];
    foreach ($names as $name) {
        $formatted_names[] = [
            'data' => $name['designation'] ?: null,
            'locale' => $name['locale'],
            'scope' => 'ecommerce',
        ];
    }

    return $formatted_names;
}

// Détermine si un produit est destiné à la vente ou non
function isDestineVente($product_id, $old_value) {
    $db = DbJesuiscoiffeur::getInstance();

    if($old_value !== 'inconnu')
        return $old_value;

    $xtd_invendable = $db->getValue("
        SELECT xtd_invendable
        FROM ps_product 
        WHERE id_product = $product_id
    ");

    return $xtd_invendable == '2' ? 'oui' : 'non';
}

// Formatte la caractéristique Fixant Fixation
function formatFixantFixation($feature) {
    if(!$feature)
        return null;

    $feature = array_filter($feature, function($elem) {
        return $elem !== 'Longue durée';
    });

    return $feature[0];
}

// Détermine la gamme d'un produit
function getProductRange($feature, $old_value) {
    if($old_value)
        return $old_value;

    return $feature ? $feature[0] : null;
}

// Récupère les meta descriptions d'un produit
function getProductMetaDescriptions($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $meta_descriptions = $db->query("
        SELECT aa.akeneo_value as locale, pl.meta_description
        FROM ps_product_lang pl 
            JOIN ps_akeneo_association aa ON pl.id_lang = aa.ps_id AND aa.ps_object = 'lang'
        WHERE pl.id_product = $product_id
    ");

    $formatted_description = [];
    foreach ($meta_descriptions as $description) {
        $formatted_description[] = [
            'data' => $description['meta_description'] ?: null,
            'locale' => $description['locale'],
            'scope' => null,
        ];
    }

    return $formatted_description;
}

// Récupère les meta title d'un produit
function getProductMetaTitle($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $meta_titles = $db->query("
        SELECT aa.akeneo_value as locale, pl.meta_title
        FROM ps_product_lang pl 
            JOIN ps_akeneo_association aa ON pl.id_lang = aa.ps_id AND aa.ps_object = 'lang'
        WHERE pl.id_product = $product_id
    ");

    $formatted_meta_title = [];
    foreach ($meta_titles as $meta_title) {
        $formatted_meta_title[] = [
            'data' => $meta_title['meta_title'] ?: null,
            'locale' => $meta_title['locale'],
            'scope' => null,
        ];
    }

    return $formatted_meta_title;
}

// Récupère les meta URLs d'un produit
function getProductMetaUrls($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    $meta_urls = $db->query("
        SELECT aa.akeneo_value as locale, pl.link_rewrite
        FROM ps_product_lang pl 
            JOIN ps_akeneo_association aa ON pl.id_lang = aa.ps_id AND aa.ps_object = 'lang'
        WHERE pl.id_product = $product_id
    ");

    $formatted_urls = [];
    foreach ($meta_urls as $meta_url) {
        $formatted_urls[] = [
            'data' => $meta_url['link_rewrite'] ?: null,
            'locale' => $meta_url['locale'],
            'scope' => null,
        ];
    }

    return $formatted_urls;
}

// Récupère le xtd_qte_min d'un produit
function getProductQteMin($product_id, $product_attribute_id = 0) {
    $db = DbJesuiscoiffeur::getInstance();

    if($product_attribute_id)
        $qte_min = $db->getValue("
            SELECT pa.xtd_qte_min
            FROM ps_product_attribute pa
            WHERE pa.id_product = $product_id
              AND pa.id_product_attribute = $product_attribute_id
        ");
    else
        $qte_min = $db->getValue("
            SELECT p.xtd_qte_min
            FROM ps_product p
            WHERE p.id_product = $product_id
        ");

    return $qte_min;
}

function getSimpleProductJSON($data) {
    $ean = $data['ean'];
    $code_famille_akeneo = $data['code_famille_akeneo'];
    $categories = $data['categories'];
    $features = $data['features'];
    $asin = $data['asin'];
    $descriptions = $data['descriptions'];
    $descriptions_courtes = $data['descriptions_courtes'];
    $name = $data['name'];
    $destine_vente = $data['destine_vente'];
    $ean_cdiscount = $data['ean_cdiscount'];
    $range = $data['range'];
    $manufacturer_code = $data['manufacturer_code'];
    $meta_descriptions = $data['meta_descriptions'];
    $meta_titles = $data['meta_titles'];
    $meta_urls = $data['meta_urls'];
    $country_restrictions = $data['country_restrictions'];
    $qte_min = $data['qte_min'];
    $matching_urls = $data['matching_urls'];

    $formatted_product = [];
    $formatted_product['identifier'] = $ean;
    $formatted_product['family'] = $code_famille_akeneo;
    $formatted_product['categories'] = $categories;
    $formatted_product['values'] = [];

    $formatted_product['values']['accumulateur'] = [
        [
            'data' => $features[51][0],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['asin'] = $asin;
    $formatted_product['values']['casque_support'] = [
        [
            'data' => $features[26][0],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['cible'] = [
        [
            'data' => $features[40],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['ciseau_taille'] = [
        [
            'data' => [
                'amount' => $features[64][0],
                'unit' => 'pouce'
            ],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['ciseaux_specificite'] = [
        [
            'data' => $features[31],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['Description'] = $descriptions;
    $formatted_product['values']['description_courte'] = $descriptions_courtes;
    $formatted_product['values']['Designation'] = $name;
    $formatted_product['values']['destine_vente'] = [
        [
            'data' => $destine_vente,
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['ean_cdiscount'] = [
        [
            'data' => $ean_cdiscount,
            'locale' => 'fr_FR',
            'scope' => null
        ]
    ];
    $formatted_product['values']['fixant_effet'] = [
        [
            'data' => $features[39],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['fixant_fixation'] = [
        [
            'data' => formatFixantFixation($features[38]),
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['formulation'] = [
        [
            'data' => ($features[44] ?: []) + ($features[63] ?: []),
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['gamme'] = [
        [
            'data' => $range,
            'locale' => null,
            'scope' => null
        ]
    ];
    sort($features[66]);
    $formatted_product['values']['longueur_coupe'] = [
        [
            'data' => [
                'amount' => $features[66][0],
                'unit' => 'MILLIMETER'
            ],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['marque'] = [
        [
            'data' => $manufacturer_code,
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['matiere'] = [
        [
            'data' => ($features[53] ?: [])+ ($features[53] ?: []) + ($features[55] ?: []) ?: null,
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['meta_description'] = $meta_descriptions;
    $formatted_product['values']['meta_title'] = $meta_titles;
    $formatted_product['values']['meta_url'] = $meta_urls;
    $formatted_product['values']['puissance'] = [
        [
            'data' => [
                'amount' => $features[67][0],
                'unit' => 'WATT'
            ],
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['restriction_canaux_web'] = $country_restrictions;
    $formatted_product['values']['technologie_electrique'] = [
        [
            'data' => ($features[45] ?: [])+ ($features[49] ?: []) + ($features[56] ?: []) + ($features[57] ?: []) ?: null,
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['texture'] = [
        [
            'data' => (($features[58] ?: []) + ($features[59] ?: []))[0] ?: null,
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['traitement'] = [
        [
            'data' => (($features[33] ?: []) + ($features[60] ?: [])) ?: null,
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['type_de_cheveux'] = [
        [
            'data' => (($features[61] ?: []) + ($features[41] ?: [])) ?: null,
            'locale' => null,
            'scope' => null
        ]
    ];
    $formatted_product['values']['unite_vente_consommateur'] = [
        [
            'data' => $qte_min,
            'locale' => null,
            'scope' => null
        ]
    ];
    foreach($matching_urls as $key => $url) {
        $formatted_product['values'][$key] = [
            [
                'data' => $url,
                'locale' => null,
                'scope' => null
            ]
        ];
    }

    return json_encode($formatted_product);
}

// Récupère l'ID de l'image principale d'un produit
function getProductCoverImage($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    return $db->getValue("
        SELECT i.id_image
        FROM ps_image i 
        WHERE i.cover = 1
          AND i.id_product = $product_id
    ");
}

// Sauvegarde l'image principale d'un produit sur le serveur
function saveProductCoverImage($ean, $image_data) {
    $image_name = "photo_defaut_$ean.jpg";
    $image_path = PROJECT_TMP_DIR . $image_name;

    $fp = fopen($image_path, 'w');
    $written = fwrite($fp, $image_data);
    fclose($fp);

    return $written ? $image_path : null;
}

// Upload l'image principale d'un produit sur Akeneo
function uploadProductCoverImage($product_id, $ean, $photo_defaut, $prestashop_client, $akeneo_client) {
    $prestashop_client->setURI("/images/products/$product_id/$photo_defaut");
    $image_result = $prestashop_client->get();

    // S'il y a eu une erreur pendant la récupération de l'image
    if($image_result['code'] >= 300)
        return false;

    $image_data = $image_result['result'];
    $image_path = saveProductCoverImage($ean, $image_data);

    // S'il y a eu une erreur pendant l'écriture de l'image
    if(!$image_path)
        return false;

    $akeneo_json = [
        'identifier' => $ean,
        'code' => $ean,
        'attribute' => 'photo_defaut',
        'scope' => null,
        'locale' => null
    ];

    $body = [
        'file' => new CURLFile($image_path),
        'product' => json_encode($akeneo_json)
    ];

    $options = [
        'headers' => [
            'Content-Type: multipart/form-data'
        ]
    ];

    $akeneo_client->setURI('/media-files');
    $result = $akeneo_client->post($body, $options);

    $is_uploaded = $result['code'] < 300;

    // On supprime la copie locale de l'image quand on a finit
    unlink($image_path);

    return $is_uploaded;
}

// Récupère la liste des documents joints du produit
function getProductJoinedDocuments($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    return $db->query("
        SELECT file, file_name
        FROM `ps_attachment` a
        JOIN `ps_product_attachment` pa ON pa.`id_attachment` = a.`id_attachment`
           JOIN `ps_attachment_lang` al ON al.`id_attachment` = a.`id_attachment`
        WHERE al.`id_lang` = 1
            AND pa.id_product = $product_id
    ");
}

// Sauvegarde le document joint donné d'un produit sur le serveur
function saveJoinedDocument($image_name, $image_data) {
    $image_path = PROJECT_TMP_DIR . $image_name;

    $fp = fopen($image_path, 'w');
    $written = fwrite($fp, $image_data);
    fclose($fp);

    return $written ? $image_path : null;
}

// Upload sur Akeneo la liste des documents joints d'un produit
function uploadProductJoinedDocuments($product_id, $ean, $joined_documents, $akeneo_client) {
    $uploaded_files = [];
    $files_to_delete = [];
    foreach($joined_documents as $doc) {
        $file = $doc['file'];
        $file_name = $doc['file_name'];

        $doc_result = HttpClient::get(PRESTASHOP_MEDIA_URL . "/download/$file");

        // S'il y a eu un soucis lors de la récupération de l'image
        $code = $doc_result['code'];
        if($code !== 200)
            return false;

        $doc_data = $doc_result['result'];
        $file_path = saveJoinedDocument($file_name, $doc_data);

        // S'il y a eu un problème lors de l'upload du fichier
        if(!$file_path)
            return false;

        $body = [
            'file' => new CURLFile($file_path),
        ];

        $options = [
            'headers' => [
                'Content-Type: multipart/form-data'
            ],
            'with_headers' => 1
        ];

        $akeneo_client->setURI('/asset-media-files');
        $asset_result = $akeneo_client->post($body, $options);

        $is_uploaded = $asset_result['code'] < 300;
        if(!$is_uploaded)
            return false;

        $asset_headers = $asset_result['headers'];
        $joined_doc_json = [
            'code' => $file,
            'values' => [
                'label' => [
                    [
                        'locale' => 'fr_FR',
                        'channel' => null,
                        'data' => $file_name
                    ]
                ],
                'media' => [
                    [
                        'locale' => null,
                        'channel' => null,
                        'data' => $asset_headers['asset-media-file-code'][0],
                        '_links' => [
                            'download' => [
                                'href' => $asset_headers['location'][0]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $options = [
            'headers' => [
                'Content-Type: application/json'
            ],
            'with_headers' => 1
        ];

        // Création de l'asset dans Akeneo
        $akeneo_client->setURI("/asset-families/notice/assets/$file");
        $asset_link_result = $akeneo_client->patch(json_encode($joined_doc_json), $options);

        // S'il y a eu un problème lors de la création de l'asset dans Akeneo
        if($asset_link_result['code'] > 300)
            return false;

        $uploaded_files[] = $file;
        $files_to_delete[] = $file_name;
    }

    // S'il y a des pièces jointes à upload
    if($uploaded_files) {
        $body = [
            'identifier' => $ean,
            'values' => [
                'notice' => [
                    [
                        'data' => $uploaded_files,
                        'locale' => 'fr_FR',
                        'scope' => null
                    ]
                ]
            ]
        ];

        // Création de l'asset dans Akeneo
        $akeneo_client->setURI("/products/$ean");
        $notice_result = $akeneo_client->patch(json_encode($body));

        if($notice_result['code'] >= 300)
            return false;

        // On supprime tous les produits des fichiers temporaires
        foreach($files_to_delete as $file) {
            unlink(PROJECT_TMP_DIR . $file);
        }
    }

    return true;
}

// Récupère la liste des images secondaires d'un produit
function getProductSecondaryImages($product_id) {
    $db = DbJesuiscoiffeur::getInstance();

    return $db->query("
        SELECT i.id_image
        FROM ps_image i
        WHERE (i.cover IS NULL OR i.cover = '')
          AND i.id_product = $product_id
        ORDER BY i.position
    ");
}

// Upload sur Akeneo la liste des images secondaires d'un produit
function uploadProductSecondaryImages($product_id, $ean, $secondary_images, $prestashop_client, $akeneo_client) {
    $uploaded_files = [];
    $files_to_delete = [];

    foreach ($secondary_images as $index => $image) {
        $image_number = $index + 1;
        $image_id = $image['id_image'];

        // On va chercher l'image du produit dans Prestashop
        $prestashop_client->setURI("/images/products/$product_id/$image_id");
        $image_result = $prestashop_client->get();

        // S'il y a eu une erreur pendant la récupération de l'image
        if($image_result['code'] >= 300)
            return false;

        $image_data = $image_result['result'];
        $image_path = saveProductSecondaryImage($ean, $image_number, $image_data);

        // S'il y a eu une erreur pendant l'écriture de l'image
        if(!$image_path)
            return false;

        $body = [
            'file' => new CURLFile($image_path),
        ];

        $options = [
            'headers' => [
                'Content-Type: multipart/form-data'
            ],
            'with_headers' => 1
        ];

        $akeneo_client->setURI('/asset-media-files');
        $asset_result = $akeneo_client->post($body, $options);

        $is_uploaded = $asset_result['code'] < 300;
        if(!$is_uploaded)
            return false;

        $asset_headers = $asset_result['headers'];
        $secondary_image_json = [
            'code' => "secondary_image_$ean" . "_$image_number",
            'values' => [
                'label' => [
                    [
                        'locale' => 'fr_FR',
                        'channel' => null,
                        'data' => "secondary_image_$ean" . "_$image_number.jpg"
                    ]
                ],
                'media' => [
                    [
                        'locale' => null,
                        'channel' => null,
                        'data' => $asset_headers['asset-media-file-code'][0],
                        '_links' => [
                            'download' => [
                                'href' => $asset_headers['location'][0]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $options = [
            'headers' => [
                'Content-Type: application/json'
            ],
            'with_headers' => 1
        ];

        // Création de l'asset dans Akeneo
        $akeneo_client->setURI("/asset-families/photos/assets/secondary_image_$ean" . "_$image_number");
        $asset_link_result = $akeneo_client->patch(json_encode($secondary_image_json), $options);

        print_r($asset_link_result);

        // S'il y a eu un problème lors de la création de l'asset dans Akeneo
        if($asset_link_result['code'] > 300)
            return false;

        $uploaded_files[] = "secondary_image_$ean" . "_$image_number";
        $files_to_delete[] = "secondary_image_$ean" . "_$image_number.jpg";
    }

    // S'il y a des images secondaires à upload
    if($uploaded_files) {
        $body = [
            'identifier' => $ean,
            'values' => [
                'photos_complementaires' => [
                    [
                        'data' => $uploaded_files,
                        'locale' => null,
                        'scope' => null
                    ]
                ]
            ]
        ];

        // Création de l'asset dans Akeneo
        $akeneo_client->setURI("/products/$ean");
        $secondary_images_result = $akeneo_client->patch(json_encode($body));

        if($secondary_images_result['code'] >= 300)
            return false;

        // On supprime tous les produits des fichiers temporaires
        foreach($files_to_delete as $file) {
            unlink(PROJECT_TMP_DIR . $file);
        }
    }

    return true;
}

// Sauvegarde une image secondaire d'un produit
function saveProductSecondaryImage($ean, $index, $image_data) {
    $image_path = PROJECT_TMP_DIR . "secondary_image_$ean" . "_$index.jpg";

    $fp = fopen($image_path, 'w');
    $written = fwrite($fp, $image_data);
    fclose($fp);

    return $written ? $image_path : null;
}
