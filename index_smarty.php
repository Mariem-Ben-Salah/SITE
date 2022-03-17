<?php

// Config de samrty : 
require_once("smarty-4.1.0/libs/Smarty.class.php");

$Smarty = new Smarty();

$Smarty->setTemplateDir('templates/');
$Smarty->setCompileDir('templates_c/');
$Smarty->setConfigDir('configs/');
$Smarty->setCacheDir('cache/');

// ajout connexion bdd 
require_once 'config.php';

// evoyer à index.tpl si le user isset ( les fichiers .tpl connaissent pas isset)
session_start();
$Smarty -> assign('issetUsr',isset($_SESSION['user']) );

// envoyer le msg_co qui est le nom de l'untilisateur connecté et l'afficher dans menu.tpl
$req = $bdd->prepare('SELECT * FROM utilisateurs WHERE token = ?');
$req ->execute($_SESSION['user']);
$data = $req->fetch();
$Smarty -> assign ('msg_co',  $data['pseudo']);


//----------------------------------connection BD--------------------------------------------

//saisir les nom et mdp de la bdd
$user = 'pgtidal';
$passwd = 'tidal';

// new PDO($dsn , $usr , $passwd)
// avec $dsn : data source name : uri:host=..;dbname=.. :
// uri: suivi par une URI qui définit la localisation du fichier contenant la chaîne de DSN.
$dbh = new PDO("pgsql:host=localhost;dbname=acudb",$user,$passwd);


//----------------------------------recuperer les Données--------------------------------------------


// on prepare une requete sql et on l'execute (dans ce cas on recupère toute la table patho "*")
$sql =" SELECT * FROM patho ;";
$sth = $dbh->prepare( $sql );
$sth->execute();

$pathos = $sth->fetchAll(PDO::FETCH_OBJ);

//$sql =" SELECT * FROM patho WHERE mer = :codeMeridien ; ";
//$sth->execute(array(':codeMeridien' => 'P'));
//$pathos = $sth->fetchAll(PDO::FETCH_ASSOC);




//------------------- Filtres multiples ( mer, type, caracteristiques ) --------------------------------------------

// on initialise la requete qu'on va y ajouter à la fin les WHERE ..
$sql_filtre = " SELECT * FROM patho " ;

// tester si on a rempli les checkbox des filtre mer ou type
$isset_mer = isset($_POST['filtre_mer']);
$isset_type = isset($_POST['filtre_type']);

if(isset($_POST['filtre_mer'])){
    $sql_filtre = $sql_filtre . "WHERE mer = " . "'". $_POST['filtre_mer']. "'" ;
}

if(isset($_POST['filtre_type'])){
    if($isset_mer){
        $sql_filtre = $sql_filtre . " AND type LIKE '%" . $_POST['filtre_type'] ."'" ;
    }
    else{
        $sql_filtre = $sql_filtre . " WHERE type LIKE '%" . $_POST['filtre_type'] ."'" ;
    }
}

$List_cara=array();

if (isset($_POST['filtre_car_p'])){array_push($List_cara, $_POST['filtre_car_p']);}
if (isset($_POST['filtre_car_v'])){array_push($List_cara, $_POST['filtre_car_v']);}
if (isset($_POST['filtre_car_f'])){array_push($List_cara, $_POST['filtre_car_f']);}
if (isset($_POST['filtre_car_c'])){array_push($List_cara, $_POST['filtre_car_c']);}
if (isset($_POST['filtre_car_i'])){array_push($List_cara, $_POST['filtre_car_i']);}
if (isset($_POST['filtre_car_e'])){array_push($List_cara, $_POST['filtre_car_e']);}


if(!empty($List_cara)){
    if($isset_type or $isset_mer){
        foreach ($List_cara as $cara ){
            $sql_filtre = $sql_filtre . " AND type LIKE '%" . $cara . "%'";
        }

    }
    else {
        foreach ($List_cara as $cara ){
            $sql_filtre = $sql_filtre . " WHERE type LIKE '%" . $cara . "%'";
        }

    }
}

$sql_filtre = $sql_filtre . ";";

// echo $sql_filtre; //on obtient une requete de type :
// SELCT * FROM patho WHERE mer = 'P' AND type LIKE 'tf%' AND type LIKE '%plein%' ; 

$sth_filtre = $dbh->prepare( $sql_filtre );
$sth_filtre->execute();
$pathos = $sth_filtre->fetchAll(PDO::FETCH_OBJ);

//var_dump($pathos);

$var=array(array(1,2),array(1,2));
//var_dump($var);



//--------------------------- Filtre mot_clef ------------------------------------------------

// si on a saisi qlqch et pas vide
if (isset($_POST["input"]) && $_POST["input"]!='' ){

    //on prend d'abort les idk de la table keywords qui coreespendent au mot saisi

    $sql_idk = " SELECT idk FROM keywords WHERE name IN ('".  $_POST["input"]  ."') ;";
    $sth_idk = $dbh->prepare( $sql_idk );
    $sth_idk->execute();
    $idk = $sth_idk->fetchAll();

    // après on recupere les ids qui correspendent au idk trouvés gràce à la table keysympt

    $sql_ids = " SELECT ids FROM keysympt WHERE idk IN ('".  $idk[0]["idk"]  ."') ;";
    $sth_ids = $dbh->prepare( $sql_ids );
    $sth_ids->execute();
    $ids = $sth_ids->fetchAll();

    // array to string pour pouvoir l'ajouter à la requete sql

    $les_ids = array_column($ids, 'ids');
    $les_ids_string = implode(",", $les_ids);
    //echo $les_ids_string;

    // après on recupère les idp <-> ids gràce à la table symptpatho

    $sql_idp = " SELECT idp from symptpatho where ids in (" . $les_ids_string . ") ;";
    //echo $sql_idp."\n";
    $sth_idp = $dbh->prepare( $sql_idp );
    $sth_idp->execute();
    $idp = $sth_idp->fetchAll();
    //var_dump($idp);
    $les_idp = array_column($idp, 'idp');
    $les_idp_string = implode(",", $les_idp);
    //echo $les_idp_string;

    // enfin on recupere les patho qui ont le idp trouvé

    $sql_pathos=" SELECT * from patho where idp in (".$les_idp_string.")";
    //echo $sql_pathos;
    $sth_pathos = $dbh->prepare( $sql_pathos );
    $sth_pathos->execute();
    $pathos = $sth_pathos->fetchAll(PDO::FETCH_OBJ);

    $Smarty -> assign('pathos',$pathos);

    //var_dump($pathos);

    // on constate que si on saisie "froid" par exemple 
    // on ne recupere pas des descriptions ou il y a le mot froid
    // mais même si on le fait à la main, on trouve la même chose que le filtre..
}



//----------------------------------- display de la page souhaitée-------------

// à l'aide de la méthode GET, l'affichage de toutes les pages passe par le même ficher index_smarty.php
// en effet dans notre menu.tpl on rederige vers index_smarty.php?page=page_souhaitée
// ainsi on recupere cette valeure et on display la page .tpl souhaitée

// et si aucun parametre n'est passée ( le cas par defaut) -> on affiche index.tpl


$Smarty -> assign("pathos" , $pathos);
if(isset($_GET['page'])){
    if ($_GET['page']=="index"): 
        $Smarty -> display("templates/index.tpl"); 
    elseif ($_GET['page']=="page_filtree"): 
        $Smarty -> display("templates/page_filtree.tpl");
    elseif ($_GET['page']=="Contact"):
        $Smarty -> display("templates/Contact.tpl");
    elseif ($_GET['page']=="mot_clef"):
        $Smarty -> display("templates/mot_clef.tpl");
    endif;
}
else {
    $Smarty -> display("templates/index.tpl");
}



?>

