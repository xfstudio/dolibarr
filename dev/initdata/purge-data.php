#!/usr/bin/env php
<?php
/* Copyright (C) 2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * THIS SCRIPT DELETE ALL MAIN TABLE CONTENT
 * WARNING, DO NOT USE ON A PRODUCTION INSTANCE
 */

/**
 *      \file       dev/initdata/purge-data.php
 *      \brief      Script to delete all main tables
 */

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test si mode batch
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
    exit(-1);
}

// Recupere root dolibarr
$path=preg_replace('/purge-data.php/i','',$_SERVER["PHP_SELF"]);
require ($path."../../htdocs/master.inc.php");
include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
include_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';

$langs->load("main");
$langs->load("errors");

// Global variables
$version=DOL_VERSION;
$error=0;

// List of sql to execute
$sqls=array(
    'bank'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'bank_account',
        'DELETE FROM '.MAIN_DB_PREFIX.'bank_class',
        'DELETE FROM '.MAIN_DB_PREFIX.'bank_url',
        'DELETE FROM '.MAIN_DB_PREFIX.'bank',
    ),
    'contract'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'contratdet',
        'DELETE FROM '.MAIN_DB_PREFIX.'contrat',
    ),
    'invoice'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'paiement_facture',
        'DELETE FROM '.MAIN_DB_PREFIX.'facture_rec',
        'DELETE FROM '.MAIN_DB_PREFIX.'facturedet',
        'DELETE FROM '.MAIN_DB_PREFIX.'facture WHERE fk_facture_source IS NOT NULL',
        'DELETE FROM '.MAIN_DB_PREFIX.'facture',
    ),
    'proposal'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'propaldet',
        'DELETE FROM '.MAIN_DB_PREFIX.'propal',
    ),
    'order'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'commandedet',
        'DELETE FROM '.MAIN_DB_PREFIX.'commande',
    ),
    'supplier_order'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet',
        'DELETE FROM '.MAIN_DB_PREFIX.'commande_fournisseur',
    ),
    'supplier_invoice'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'facture_fourn_det',
        'DELETE FROM '.MAIN_DB_PREFIX.'facture_fourn',
        'DELETE FROM '.MAIN_DB_PREFIX.'supplier_proposaldet',
        'DELETE FROM '.MAIN_DB_PREFIX.'supplier_proposal',
    ),
    'shipment'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'expeditiondet_batch',
        'DELETE FROM '.MAIN_DB_PREFIX.'expeditiondet_extrafields',
        'DELETE FROM '.MAIN_DB_PREFIX.'expeditiondet',
        'DELETE FROM '.MAIN_DB_PREFIX.'expedition_extrafields',
        'DELETE FROM '.MAIN_DB_PREFIX.'expedition',
    ),
    'delivery'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'livraisondet',
        'DELETE FROM '.MAIN_DB_PREFIX.'livraison',
    ),
    'intervention'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'fichinterdet',
        'DELETE FROM '.MAIN_DB_PREFIX.'fichinter',
    ),
    'product'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'categorie_product',
        'DELETE FROM '.MAIN_DB_PREFIX.'product_lang',
        'DELETE FROM '.MAIN_DB_PREFIX.'product_price',
        'DELETE FROM '.MAIN_DB_PREFIX.'product_fournisseur_price',
        'DELETE FROM '.MAIN_DB_PREFIX.'product',
    ),
    'project'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'projet_task_time',
        'DELETE FROM '.MAIN_DB_PREFIX.'projet_task',
        'DELETE FROM '.MAIN_DB_PREFIX.'projet',
    ),
    'contact'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'categorie_contact',
        'DELETE FROM '.MAIN_DB_PREFIX.'socpeople',
    ),
    'thirdparty'=>array(
        'DELETE FROM '.MAIN_DB_PREFIX.'cabinetmed_cons', 
        'UPDATE '.MAIN_DB_PREFIX.'adherent SET fk_soc = NULL',
        'DELETE FROM '.MAIN_DB_PREFIX.'categorie_fournisseur',
        'DELETE FROM '.MAIN_DB_PREFIX.'categorie_societe',
        'DELETE FROM '.MAIN_DB_PREFIX.'societe_remise_except',
        'DELETE FROM '.MAIN_DB_PREFIX.'societe',
    )
);




/*
 * Main
 */

@set_time_limit(0);
print "***** ".$script_file." (".$version.") pid=".dol_getmypid()." *****\n";
dol_syslog($script_file." launched with arg ".join(',',$argv));

$mode = $argv[1];
$option = $argv[2];

if (empty($mode) || ! in_array($mode,array('test','confirm'))) {
    print "Usage:  $script_file (test|confirm) (all|option)\n";
    print "\n";
    print "option can be ".join(',',array_keys($sqls))."\n";
    exit(-1);
}

if (empty($option) || ! in_array($option, array_merge(array('all'),array_keys($sqls))) ) {
    print "Usage:  $script_file (test|confirm) (all|option)\n";
    print "\n";
    print "option can be ".join(',',array_keys($sqls))."\n";
    exit(-1);
}


$ret=$user->fetch('','admin');
if (! $ret > 0)
{
	print 'A user with login "admin" and all permissions must be created to use this script.'."\n";
	exit;
}
$user->getrights();

print "Purge all data for this database:\n";
print "Server = ".$db->database_host."\n";
print "Database name = ".$db->database_name."\n";
print "Database port = ".$db->database_port."\n";
print "User = ".$db->database_user."\n";
print "\n";

if (! $confirmed)
{
    print "Hit Enter to continue or CTRL+C to stop...\n";
    $input = trim(fgets(STDIN));
}


$db->begin();

$oldfamily='';
foreach($sqls as $family => $familysql)
{
    if ($option && $option != 'all' && $option != $family) continue;

    if ($family != $oldfamily) print "Process action for family ".$family."\n";
    $oldfamily = $family;
    
    foreach($familysql as $sql)
    {
        print "Run sql: ".$sql."\n";
        $resql=$db->query($sql);
        if (! $resql) 
        {
            if ($db->errno() != 'DB_ERROR_NOSUCHTABLE')
            {
                $error++;
            }
        }
        
        if ($error)
        {
            print $db->lasterror();
            $error++;
            break 2;
        }
    }
}

if ($error || $mode != 'confirm')
{
    print "Rollback any changes.\n";
    $db->rollback();
}
else
{
    print "Commit all changes.\n";
    $db->commit();
}

$db->close();

