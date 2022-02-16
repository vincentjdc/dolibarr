<?php
/* Copyright (C) 2005-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
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
 *    	\file       htdocs/core/modules/propale/mod_propale_marbre.php
 *		\ingroup    propale
 *		\brief      File of class to manage commercial proposal numbering rules Marbre
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';


/**
 *	Class to manage customer order numbering rules Marbre
 */
class mod_progress_report_standard
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	public $prefix = 'PR';

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
		return $langs->trans("JDCProgressReportRef");
	}


	/**
	 *  Return an example of numbering module values
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		return "P22112-001-002";
	}


	/**
	 *  Checks if the numbers already in the database do not
	 *  cause conflicts that would prevent this numbering working.
	 *
	 *  @return     boolean     false if conflict, true if ok
	 */
	public function canBeActivated()
	{
		global $conf, $langs, $db;

		$pryymm = '';
		$max = '';

		return true;
	}

	public function getExistingProgressReports($object)
	{
		global $db;

		if ($object instanceof Project) {
			$sql = strtr(
				"SELECT rowid FROM {progress_report_table} WHERE fk_project = {project_id} ORDER BY ref DESC",
				[
					"{progress_report_table}" => MAIN_DB_PREFIX."jdc_progress_report",
					"{project_id}" => $object->id
				]
			);

		} else {
			$sql = strtr(
				"SELECT rowid FROM {progress_report_table} WHERE fk_order = {order_id} ORDER BY ref DESC",
				[
					"{progress_report_table}" => MAIN_DB_PREFIX."jdc_progress_report",
					"{order_id}" => $object->id
				]
			);

		}


		dol_syslog('VINCENT : '.$sql, LOG_DEBUG);

		$progressReports = [];

		$resql = $db->query($sql);
		if ($resql) {
			while(($obj = $db->fetch_object($resql))) {
				$progressReport = new ProgressReport($db);
				$progressReport->fetch($obj->rowid);

				$progressReports[] = $progressReport;
			}
		} else {
			return -1;
		}

		return $progressReports;
	}

	/**
	 *  Return next value
	 *
	 *  @param	Societe		$objsoc     Object third party
	 * 	@param	Propal		$propal		Object commercial proposal
	 *  @return string      			Next value
	 */
	public function getNextValue($progressReport)
	{
		global $db, $conf;

		if ($progressReport->fk_order == null) {
			// project progress report
			$project = new Project($db);
			$project->fetch($progressReport->fk_project);
			$progressReports = $this->getExistingProgressReports($project);
		} else {
			$order = new Commande($db);
			$order->fetch($progressReport->fk_order);
			$progressReports = $this->getExistingProgressReports($order);
		}

		$progressReportsCount = count($progressReports);

		$num = 1;
		if ($progressReportsCount > 0) {
			$num = $progressReportsCount+1;
		}

		if ($progressReport->fk_order == null) {
			$ref = $project->ref . '-EA' .str_pad($num, 4, "0", STR_PAD_LEFT);
		} else {
			$ref = $order->ref . '-' .str_pad($num, 4, "0", STR_PAD_LEFT);
		}

		//die('ref : '.$ref);

		return $ref;
	}

	/**
	 *  Return next free value
	 *
	 *  @param	Societe		$objsoc      	Object third party
	 * 	@param	Object		$objforref		Object for number to search
	 *  @return string      				Next free value
	 */
	public function getNumRef($object)
	{
		return $this->getNextValue($object);
	}
}
