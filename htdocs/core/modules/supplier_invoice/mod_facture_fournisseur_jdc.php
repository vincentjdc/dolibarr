<?php
/* Copyright (C) 2005-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2013-2018 Philippe Grand       <philippe.grand@atoo-net.com>
 * Copyright (C) 2016      Alexandre Spangaro   <aspangaro@open-dsi.fr>
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
 *    	\file       htdocs/core/modules/supplier_invoice/mod_facture_fournisseur_cactus.php
 *		\ingroup    supplier invoice
 *		\brief      File containing class for the numbering module Cactus
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/supplier_invoice/modules_facturefournisseur.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';



/**
 *  Cactus Class of numbering models of suppliers invoices references
 */
class mod_facture_fournisseur_jdc extends ModeleNumRefSuppliersInvoices
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
	public $nom = 'Jdc';

	/**
	 * @var string model name
	 */
	public $name = 'Jdc';


	/**
	 *  Return description of numbering model
	 *
	 *  @return     string      Text with description
	 */
	public function info()
	{
		global $langs;
		$langs->load("bills");
		return $langs->trans("JDCNumRefModelDesc1", $this->prefixinvoice, $this->prefixcreditnote, $this->prefixdeposit);
	}


	/**
	 *  Returns a numbering example
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		return "FGES20210001";
	}


	/**
	 * 	Tests if the numbers already in force in the database do not cause conflicts that would prevent this numbering.
	 *
	 *  @return     boolean     false if conflict, true if ok
	 */
	public function canBeActivated()
	{
		global $conf, $langs, $db;
		$langs->load("bills");

		return true;
	}

	/**
	 * Return next value
	 *
	 * @param	Societe		$objsoc     Object third party
	 * @param  	Object		$object		Object invoice
	 * @param   string		$mode       'next' for next value or 'last' for last value
	 * @return 	string      			Value if OK, 0 if KO
	 */
	public function getNextValue($objsoc, $object, $mode = 'next')
	{
		global $db, $conf;

		$date = $object->date; // This is invoice date (not creation date)
		$year4digits = strftime("%Y", $date);
		$year2digits = strftime("%y", $date);

		// Get the journal mask according to the entity and the type of document
		$entityId = $object->entity;
		$entity = new DaoMulticompany($db);
		$entity->fetch($entityId);

		$journalAttribute = 'invoice_journal';
		$journalMinAttribute = 'invoice_journal_min_number';
		$creditNote = $object->type == FactureFournisseur::TYPE_CREDIT_NOTE;
		if ($creditNote) { // Credit note ?
			$journalAttribute = 'credit_note_journal';
			$journalMinAttribute = 'credit_note_journal_min_number';
		}

		$journalMask = $entity->array_options['options_' . $journalAttribute];
		$journalMin = intval($entity->array_options['options_' . $journalMinAttribute]);

		// Replace Year (2 and 4 digits)
		$journalMask = preg_replace('/\{yy\}/', $year2digits, $journalMask);
		$journalMask = preg_replace('/\{yyyy\}/', $year4digits, $journalMask);

		// Get the base
		$journalBase = preg_replace('/\{0+\}/', '' , $journalMask);

		// Fetch the last count for that base
		$start = strlen($journalBase) + 1;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$start.") AS SIGNED)) as min";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn";
		$sql .= " WHERE ref LIKE '".$db->escape($journalBase)."%'";

		$resql = $db->query($sql);
		dol_syslog(get_class($this)."::getNextValue", LOG_DEBUG);

		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$min = intval($obj->min);
			} else {
				$min = 0;
			}
		} else {
			return -1;
		}

		$min = max($min, $journalMin);

		// Replace the counter of the mask with the need one (last or next)
		$ref = preg_replace_callback('/\{(0+)\}/', function($matches) use ($min, $mode) {
			$numberOfDigits = strlen($matches[1]);

			// Check if number is greater that the max number possible with the number of digits
			// If it's the case, just increment without formatting. Otherwise, format with the number of digits desired
			if ($min >= (pow(10, $numberOfDigits) - 1)) {
				$nextNum = ($mode == 'last') ? $min : $min+1;
			} else {
				$nextNum = sprintf("%0".$numberOfDigits."s", ($mode == 'last') ? $min : $min+1);
			}

			return $nextNum;

		}, $journalMask);

		dol_syslog(get_class($this)."::getNextValue return ".$ref);

		return $ref;
	}


	/**
	 * Return next free value
	 *
	 * @param	Societe		$objsoc     	Object third party
	 * @param	string		$objforref		Object for number to search
	 * @param   string		$mode       	'next' for next value or 'last' for last value
	 * @return  string      				Next free value
	 */
	public function getNumRef($objsoc, $objforref, $mode = 'next')
	{
		return $this->getNextValue($objsoc, $objforref, $mode);
	}
}
