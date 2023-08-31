<?php
/* Copyright (C) 2010-2012	Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2010		Laurent Destailleur	<eldy@users.sourceforge.net>
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
 *	\file       htdocs/core/modules/project/mod_project_simple.php
 *	\ingroup    project
 *	\brief      File with class to manage the numbering module Simple for project references
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
require_once DOL_DOCUMENT_ROOT.'/custom/jdc/class/businessunit.class.php';

/**
 * 	Class to manage the numbering module Simple for project references
 */
class mod_project_jdc extends ModeleNumRefProjects
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	public $prefix = '';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string Nom du modele
	 * @deprecated
	 * @see $name
	 */
	public $nom = 'JDC';

	/**
	 * @var string model name
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
		return $langs->trans("JDCProjectQuotationRef");
	}


	/**
	 *  Return an example of numbering module values
	 *
	 * 	@return     string      Example
	 */
	public function getExample()
	{
		return "P22045 ou D21125";
	}


	/**
	 *  Checks if the numbers already in the database do not
	 *  cause conflicts that would prevent this numbering working.
	 *
	 *   @return     boolean     false if conflict, true if ok
	 */
	public function canBeActivated()
	{
		global $conf, $langs, $db;

		return true;

		$coyymm = '';
		$max = '';

		$posindice = strlen($this->prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."____-%'";
		$sql .= " AND entity = ".$conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$row = $db->fetch_row($resql);
			if ($row) {
				$coyymm = substr($row[0], 0, 6);
				$max = $row[0];
			}
		}
		if (!$coyymm || preg_match('/'.$this->prefix.'[0-9][0-9][0-9][0-9]/i', $coyymm)) {
			return true;
		} else {
			$langs->load("errors");
			$this->error = $langs->trans('ErrorNumRefModel', $max);
			return false;
		}
	}


	/**
	 *  Return next value
	 *
	 *  @param   Societe	$objsoc		Object third party
	 *  @param   Project	$project	Object project
	 *  @return	string				Value if OK, 0 if KO
	 */
	public function getNextValue($objsoc, $project)
	{
		global $db, $conf;

		$year = date('y', $project->date_c);
		$type = $project->array_options['options_type'];

		if ($type == 1) { // Devis
			$prefix = 'D'.$year;
		} else {
			$prefix = 'P'.$year;
		}

		$bu = new BusinessUnit($db);
		$bu->fetch($project->array_options['options_fk_business_unit']);

		dol_syslog('VINCENT : '.$project->array_options['options_fk_business_unit'], LOG_DEBUG);

		$startNumber = $bu->number_start;
		$stopNumber = $bu->number_stop;

		dol_syslog('VINCENT : '.$startNumber, LOG_DEBUG);
		dol_syslog('VINCENT : '.$stopNumber, LOG_DEBUG);

		// First, we get the max value
		$posindice = strlen($prefix) + 1;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet";
		$sql .= " WHERE (";
		for($i = $startNumber; $i <= $stopNumber; $i++) {
			if ($i > $startNumber) {
				$sql .= ' OR ';
			}
			$sql .= "ref LIKE '".$db->escape($prefix).$i."%'";
		}
		$sql .= ") AND entity = ".$conf->entity;

		dol_syslog('VINCENT : '.$sql, LOG_DEBUG);

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				if ($obj->max === null) {
					$max = $startNumber * 100;
				} else {
					$max = intval($obj->max);
				}
			} else {
				$max = 0;
			}
		} else {
			dol_syslog("mod_project_jdc::getNextValue", LOG_DEBUG);
			return -1;
		}

		if ($max >= (pow(10, 3) - 1)) {
			$num = $max + 1; // If counter > 999, we do not format on 4 chars, we take number as it is
		} else {
			$num = sprintf("%03s", $max + 1);
		}

		dol_syslog("VINCENT mod_project_jdc::getNextValue return ".$prefix.$num);
		return $prefix.$num;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return next reference not yet used as a reference
	 *
	 *  @param	Societe	$objsoc     Object third party
	 *  @param  Project	$project	Object project
	 *  @return string      		Next not used reference
	 */
	public function project_get_num($objsoc = 0, $project = '')
	{
		// phpcs:enable
		return $this->getNextValue($objsoc, $project);
	}
}
