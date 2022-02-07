<?php
/* Copyright (C) 2005-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2018 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2013      Juanjo Menent		<jmenent@2byte.es>
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
 *	\file       htdocs/core/modules/facture/mod_facture_mars.php
 *	\ingroup    facture
 *	\brief      File containing class for numbering module Mars
 */
require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT . '/custom/jdc/class/jdcentity.class.php';

/**
 * 	Class to manage invoice numbering rules Mars
 */
class mod_facture_jdc extends ModeleNumRefFactures
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	public $prefixinvoice = 'F';

	public $prefixreplacement = 'F';

	public $prefixdeposit = '';

	public $prefixcreditnote = 'NC';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';


	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 *  Returns the description of the numbering model
	 *
	 *  @return     string      Texte descripif
	 */
	public function info()
	{
		global $langs;
		$langs->load("bills", "jdc@jdc");
		return $langs->trans('JDCInvoiceNumberFormat');
	}

	/**
	 *  Return an example of numbering
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		return "F210001 ou NC210001 (facture normale ou note de crédit) ou FI210001 ou NCI210001 (facture ou note crédit interne)";
	}

	/**
	 *  Checks if the numbers already in the database do not
	 *  cause conflicts that would prevent this numbering working.
	 *
	 *  @return     boolean     false if conflict, true if ok
	 */
	public function canBeActivated()
	{
		global $langs, $conf, $db;

		$langs->load("bills");

		return true;
	}

	/**
	 * Return next value not used or last value used
	 *
	 * @param	Societe		$objsoc		Object third party
	 * @param   Facture		$invoice	Object invoice
	 * @param   string		$mode       'next' for next value or 'last' for last value
	 * @return  string       			Value
	 */
	public function getNextValue($objsoc, $invoice, $mode = 'next')
	{
		global $db, $conf;

		$prefix = $this->prefixinvoice;
		if ($invoice->type == 1) {
			$prefix = $this->prefixreplacement;
		} elseif ($invoice->type == 2) {
			$prefix = $this->prefixcreditnote;
		} elseif ($invoice->type == 3) {
			$prefix = $this->prefixdeposit;
		}

		if ($invoice->array_options['options_internal']) {
			$prefix .= 'I';
		}

		$entity = new JdcEntity($db);
		$entity->fetch($invoice->array_options['options_fk_jdc_entity']);

		$startNumber = $entity->number_start;
		$stopNumber = $entity->number_stop;

		$year = date('y', $invoice->date);

		$startStr = $prefix . $year;

		$start = strlen($startStr) + 1;

		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM " . $start . ") AS SIGNED)) as max";
		$sql .= " FROM " . MAIN_DB_PREFIX . "facture";
		$sql .= " WHERE (";
		for ($i = $startNumber; $i <= $stopNumber; $i++) {
			if ($i > $startNumber) {
				$sql .= ' OR ';
			}
			$sql .= "ref LIKE '" . $db->escape($prefix) . $year . $i . "%'";
		}
		$sql .= ")";

		dol_syslog('VINCENT : ' . $sql, LOG_DEBUG);

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				if ($obj->max !== null) {
					$max = intval($obj->max);
				} else {
					$max = $startNumber * 1000;
				}
			} else $max = $startNumber * 1000;
		}

		if ($max >= (pow(10, 4) - 1)) $num = $max + 1; // If counter > 9999, we do not format on 4 chars, we take number as it is
		else $num = sprintf("%04s", $max + 1);

		return $startStr . $num;
	}

	/**
	 *  Return next free value
	 *
	 *  @param  Societe     $objsoc         Object third party
	 *  @param  string      $objforref      Object for number to search
	 *  @param  string      $mode           'next' for next value or 'last' for last value
	 *  @return string                      Next free value
	 */
	public function getNumRef($objsoc, $objforref, $mode = 'next')
	{
		return $this->getNextValue($objsoc, $objforref, $mode);
	}
}
