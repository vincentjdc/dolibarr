<?php
/* Copyright (C) 2005-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *    	\file       htdocs/core/modules/supplier_order/mod_commande_fournisseur_muguet.php
 *		\ingroup    commande
 *		\brief      Fichier contenant la classe du modele de numerotation de reference de commande fournisseur Muguet
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/supplier_order/modules_commandefournisseur.php';


/**
 *	Classe du modele de numerotation de reference de commande fournisseur Muguet
 */
class mod_commande_fournisseur_jdc extends ModeleNumRefSuppliersOrders
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string Nom du modele
	 * @deprecated
	 * @see $name
	 */
	public $nom = 'jdc';

	/**
	 * @var string model name
	 */
	public $name = 'jdc';

	public $prefix = '';


	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $conf;

		//if ((float) $conf->global->MAIN_VERSION_LAST_INSTALL >= 5.0) $this->prefix = 'PO'; // We use correct standard code "PO = Purchase Order"
	}

	/**
	 * 	Return description of numbering module
	 *
	 *  @return     string      Text with description
	 */
	public function info()
	{
		global $langs;
		return $langs->trans("JDCSupplierOrderRefModelDesc", $this->prefix);
	}


	/**
	 * 	Return an example of numbering
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		return "P21156-PO001 (externe) ou P21156-POI001 (interne)";
	}

	public function isInternal($object)
	{
		global $db;

		if (!isset($object->array_options['options_fk_jdc_entity'])) {
			return false;
		}

		$soc = new Societe($db);
		$soc->fetch($object->socid);

		$jdcEntity = new JDCEntity($db);
		$jdcEntity->fetch($object->array_options['options_fk_jdc_entity']);

		return $jdcEntity->fk_soc == $soc->id;
	}

	/**
	 *  Checks if the numbers already in the database do not
	 *  cause conflicts that would prevent this numbering working.
	 *
	 *  @return     boolean     false if conflict, true if ok
	 */
	public function canBeActivated()
	{
		return true;
	}

	/**
	 * 	Return next value
	 *
	 *  @param	Societe		$objsoc     Object third party
	 *  @param  Object		$object		Object
	 *  @return string      			Value if OK, 0 if KO
	 */
	public function getNextValue($objsoc = 0, $object = '')
	{
		global $db, $conf;

		$object->fetch_projet();

		if (!$object->project) {
			return 'bof';
			//throw new \Exception('No project found for this order');
		}

		$projectRef = $object->project->ref;

		$internal = $this->isInternal($object);

		if (!$internal) {
			$start = 10;
			$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM " . $start . ") AS SIGNED)) as max";
			$sql .= " FROM " . MAIN_DB_PREFIX . "commande_fournisseur";
			$sql .= " WHERE ref LIKE '" . $db->escape($projectRef) . "-PO%' AND ref NOT LIKE '".$db->escape($projectRef)."-I-PO'";
		} else {
			$start = 11;
			$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM " . $start . ") AS SIGNED)) as max";
			$sql .= " FROM " . MAIN_DB_PREFIX . "commande_fournisseur";
			$sql .= " WHERE ref LIKE '" . $db->escape($projectRef) . "-I-PO%'";
		}

		// First, we get the max value
		//$posindice = strlen($this->prefix) + 6;
		//$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		//$sql .= " FROM ".MAIN_DB_PREFIX."commande_fournisseur";
		//$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."____-%'";
		//$sql .= " AND entity = ".$conf->entity;

		//$date = $object->date_commande; // Not always defined
		//if (empty($date)) {
		//      $date = $object->date; // Creation date is order date for suppliers orders
		//}
		//$yymm = strftime("%y%m", $date);


		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) $max = intval($obj->max);
			else $max = 0;
		}

		if ($max >= (pow(10, 4) - 1)) $num = $max + 1; // If counter > 9999, we do not format on 4 chars, we take number as it is
		else $num = sprintf("%04s", $max + 1);

		return $projectRef . ($internal ? "-I-PO" : "-PO") . $num;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * 	Renvoie la reference de commande suivante non utilisee
	 *
	 *  @param	Societe		$objsoc     Object third party
	 *  @param  Object	    $object		Object
	 *  @return string      			Texte descripif
	 */
	public function commande_get_num($objsoc = 0, $object = '')
	{
		// phpcs:enable
		return $this->getNextValue($objsoc, $object);
	}
}
