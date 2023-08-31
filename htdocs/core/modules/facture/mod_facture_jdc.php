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
		global $db, $conf, $langs;

		$date = $invoice->date; // This is invoice date (not creation date)
		$year4digits = strftime("%Y", $date);
		$year2digits = strftime("%y", $date);

		// Get the entity of the invoice
		$entityId = $invoice->array_options['options_fk_jdc_entity'];

		$entity = new JdcEntity($db);
		$entity->fetch($entityId);

		$journalAttribute = 'sales_invoice_journal';
		$journalMinAttribute = 'sales_invoice_journal_min_number';
		$creditNote = $invoice->type == FactureFournisseur::TYPE_CREDIT_NOTE;
		if ($creditNote) { // Credit note ?
			$journalAttribute = 'sales_credit_note_journal';
			$journalMinAttribute = 'sales_credit_note_journal_min_number';
		}

		$journalMask = $entity->$journalAttribute;
		$journalMin = intval($entity->$journalMinAttribute);

		// Replace Year (2 and 4 digits)
		$journalMask = preg_replace('/\{yy\}/', $year2digits, $journalMask);
		$journalMask = preg_replace('/\{yyyy\}/', $year4digits, $journalMask);

		// Get the base
		$journalBase = preg_replace('/\{0+\}/', '' , $journalMask);

		if ($journalBase == '') {
			$invoice->error = $langs->trans('NoJournalForThatEntity');
			return -1;
		}

		// Fetch the last count for that base
		$start = strlen($journalBase) + 1;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$start.") AS SIGNED)) as min";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture";
		$sql .= " WHERE ref LIKE '".$db->escape($journalBase)."%'";

		$resql = $db->query($sql);
		dol_syslog(get_class($this)."::getNextValue", LOG_DEBUG);

		if ($resql) {
			$obj = $db->fetch_object($resql);
			dol_syslog("VINCENT : ==========>" . $obj->min);
			if ($obj) {
				$min = intval($obj->min);
			} else {
				$min = 0;
			}
		} else {
			$invoice->error = 'error';
			return -1;
		}

		$min = max($min, $journalMin);
		//echo $sql;
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
