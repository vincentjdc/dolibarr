<?php
/* Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *  \file       htdocs/core/modules/commande/mod_commande_marbre.php
 *  \ingroup    commande
 *  \brief      File of class to manage customer order numbering rules Marbre
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/modules_commande.php';

/**
 *	Class to manage customer order numbering rules Marbre
 */
class mod_commande_jdc extends ModeleNumRefCommandes
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	public $prefix = 'CO';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string name
	 */
	public $name = 'JDC';


	/**
	 *  Return description of numbering module
	 *
	 *  @return     string      Text with description
	 */
	public function info()
	{
		global $langs;
		return $langs->trans("JDCCustomerOrderRefModelDesc", $this->prefix);
	}


	/**
	 *  Return an example of numbering
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		return "P21156-CO001";
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
	 * 	Return next free value
	 *
	 *  @param	Societe		$objsoc     Object thirdparty
	 *  @param  Object		$object		Object we need next value for
	 *  @return string      			Value if KO, <0 if KO
	 */
	public function getNextValue($objsoc, $object)
	{
		global $db, $conf;

		$object->fetch_projet();

		$projectRef = $object->project->ref.'-CO';

		$start = 10;
		if ($object->array_options['options_internal']) {
			$projectRef = $object->project->ref.'-CI';
		}

		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$start.") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande";
		$sql .= " WHERE ref LIKE '".$db->escape($projectRef)."%'";

		$resql = $db->query($sql);
		if ($resql)
		{
			$obj = $db->fetch_object($resql);
			if ($obj) $max = intval($obj->max);
			else $max = 0;
		}

		if ($max >= (pow(10, 4) - 1)) $num = $max + 1; // If counter > 9999, we do not format on 4 chars, we take number as it is
		else $num = sprintf("%04s", $max + 1);

		return $projectRef.$num;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return next free value
	 *
	 *  @param	Societe		$objsoc     Object third party
	 * 	@param	string		$objforref	Object for number to search
	 *  @return string      			Next free value
	 */
	public function commande_get_num($objsoc, $objforref)
	{
		// phpcs:enable
		return $this->getNextValue($objsoc, $objforref);
	}
}
