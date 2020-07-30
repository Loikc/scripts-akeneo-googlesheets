<?php
/*
 * Script de déploiement ou rollback.
 * Paramètres $_GET ou $_POST (raw url encoded) :
 *      - repository_url : adresse du repo GIT de la forme git@gitlab.com:studiocloud/probst.git
 *      - root_dir : chemin absolu vers la racine du projet comme /home/studiocloud/staging.probst.lu
 *      - branch : la branche GIT à déployer
 *      - rollback : exécute un rollback si ce paramètre est défini (repository_url et branch ne sont donc pas requis)
 * Protection par mot de passe BASIC : deploy:deploy2013
 */

ini_set('display_errors', 0);
set_time_limit(0);
define('KEEP_RELEASES', 3); //conserve au maximum 3 dossiers de releases
define('TOKEN_SALT', 'yhwGLfA9P1KyvlV'); //sel pour le md5 qui constitue le token

$response = array(
    'success' => true,
    'output' => array(),
);

//récupère et décode les paramètres $_REQUEST
$request_token = empty($_REQUEST['request_token']) ? null : rawurldecode($_REQUEST['request_token']);
$request_time = empty($_REQUEST['request_time']) ? null : rawurldecode($_REQUEST['request_time']);
$repository_url = empty($_REQUEST['repository_url']) ? null : rawurldecode($_REQUEST['repository_url']);
$root_dir = empty($_REQUEST['root_dir']) ? null : rtrim(rawurldecode($_REQUEST['root_dir']), '/');
$branch = empty($_REQUEST['branch']) ? 'master' : rawurldecode($_REQUEST['branch']);
$rollback = isset($_REQUEST['rollback']);

//génère le token attendu
$original_token = md5(TOKEN_SALT.$request_time);

//vérifie la présence des paramètres requis
if (empty($request_token)) {
    $response['success'] = false;
    $response['output'][] = "Missing required parameter `request_token`";
}
if (empty($request_time)) {
    $response['success'] = false;
    $response['output'][] = "Missing required parameter `request_time`";
}
if (!$rollback && empty($repository_url)) {
    $response['success'] = false;
    $response['output'][] = "Missing required parameter `repository_url`";
}
if (empty($root_dir)) {
    $response['success'] = false;
    $response['output'][] = "Missing required parameter `root_dir`";
}

//vérifie le contenu des paramètres
if ($request_token && $original_token != $request_token) {
    $response['success'] = false;
    $response['output'][] = "Invalid `request_token`";
}
if ($repository_url && !$rollback && !preg_match('#[a-zA-Z0-9_-]+\@[a-zA-Z0-9_.-]+\:[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+\.git#', $repository_url)) {
    $response['success'] = false;
    $response['output'][] = "Invalid parameter `repository_url` with value : $repository_url\nShould be like: user@example.com:group/project.git";
}
if ($root_dir && (!file_exists($root_dir) || !is_dir($root_dir))) {
    $response['success'] = false;
    $response['output'][] = "$root_dir does not exist or is not a directory";
}
if (file_exists($root_dir.'/releases') && !is_writable($root_dir.'/releases') && $root_dir) {
    $response['success'] = false;
    $response['output'][] = "$root_dir/releases is not writeable";
}
if ($root_dir && $rollback && count(scandir("$root_dir/releases")) < 4) {
    $response['success'] = false;
    $response['output'][] = "No previous release to rollback to.";
}

//paramètres ok : exécute le script
if ($response['success']) {
    $new_release = date('YmdHis');

    if ($rollback) {
        //script de rollback
        $commands = array(
            "echo 'Rolling back from previous release of $root_dir...'",
            "cd $root_dir/releases"
            ." && mv $(find . -maxdepth 1 -type d | sort -dr | grep -E '^(\.\/)?[0-9]{14}$' | sed -n 2p) $new_release"  //renomme la release précédente avec le nouveau numéro
            ." && rm -f $root_dir/www && cd $root_dir && ln -sf releases/$new_release www",                             //refait le lien symbolique vers cette release
        );
    } else {
        //script de déploiement
        $commands = array(
            "echo 'Deploying release $new_release of $repository_url from branch $branch...'",
            //"eval $(ssh-agent -s) && ssh-add ~/.ssh/id_rsa",                                                                //ajoute la clé rsa dans le trousseau de la session
            "mkdir -p $root_dir/releases/$new_release",                                                                     //crée le(s) dossier(s) nécessaire(s)
            "git clone --branch $branch --depth=1 $repository_url $root_dir/releases/$new_release",                         //clone la branche sélectionnée vers le nouveau dossier de release
            "rm -rf $root_dir/releases/$new_release/.git*",                                                                 //supprime .git et .gitlab-ci.yml qui ne servent à rien ici
            "rm -f $root_dir/www || rm -rf $root_dir/www && cd $root_dir && ln -sf releases/$new_release www",              //refait le lien symbolique vers la nouvelle release
            "cd $root_dir/releases && rm -rf `ls -r | grep -E '^(\.\/)?[0-9]{14}$' | awk 'NR>".KEEP_RELEASES."'`"           //supprime les anciens répertoires pour n'en garder que 3
        );
    }

    foreach ($commands as $command) {
        $return_var = 0;
        //ajoute la commande exécutée à l'output, sauf si c'est un echo
        if (!preg_match('#^echo#', $command)) {
            $response['output'][] .= '$ '.$command;
        }
        exec($command.' 2>&1', $response['output'], $return_var);

        //interrompt le script si une commande échoue
        if ((int)$return_var !== 0) {
            $response['success'] = false;
            $response['output'][] = "Command `$command` failed with error status $return_var";
            break;
        }
    }
}

header('Content-type: text/plain; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Expires: Mon, 11 May 1992 00:00:00 GMT");
header("Pragma: no-cache");
echo implode("\n", $response['output'])."\n";
echo $response['success'] ? "SUCCESS\n" : "FAILURE\n";
