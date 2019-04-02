<?php
/**
 * @package   AkeebaSubs
 * @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Subscriptions\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\Subscriptions\Admin\Helper\Email;
use FOF30\Container\Container;
use FOF30\Date\Date;
use FOF30\Model\DataModel;

/**
 * Model for the credit notes issued
 *
 * @property  int                $akeebasubs_invoice_id
 * @property  int                $creditnote_no
 * @property  string             $display_number
 * @property  string             $creditnote_date
 * @property  string             $html
 * @property  string             $atxt
 * @property  string             $btxt
 * @property  string             $filename
 * @property  string             $sent_on
 * @property  int                $enabled              Publish status of this record
 * @property  int                $created_by           ID of the user who created this record
 * @property  string             $created_on           Date/time stamp of record creation
 * @property  int                $modified_by          ID of the user who modified this record
 * @property  string             $modified_on          Date/time stamp of record modification
 * @property  int                $locked_by            ID of the user who locked this record
 * @property  string             $locked_on            Date/time stamp of record locking
 *
 * Filters:
 *
 * @method  $this  akeebasubs_invoice_id()          akeebasubs_invoice_id(int $v)
 * @method  $this  creditnote_no()                  creditnoteno(int $v)
 * @method  $this  display_number()                 display_number(string $v)
 * @method  $this  creditnote_date()                creditnote_date(string $v)
 * @method  $this  html()                           html(string $v)
 * @method  $this  atxt()                           atxt(string $v)
 * @method  $this  btxt()                           btxt(string $v)
 * @method  $this  filename()                       filename(string $v)
 * @method  $this  sent_on()                        sent_on(string $v)
 * @method  $this  enabled()                        enabled(bool $v)
 * @method  $this  created_on()                     created_on(string $v)
 * @method  $this  created_by()                     created_by(int $v)
 * @method  $this  modified_on()                    modified_on(string $v)
 * @method  $this  modified_by()                    modified_by(int $v)
 * @method  $this  locked_on()                      locked_on(string $v)
 * @method  $this  locked_by()                      locked_by(int $v)
 * @method  $this  subids()                            subids(array $v)
 *
 * @property-read  Invoices      $invoice              The invoice of this credit note
 * @property-read  Subscriptions $subscription         The subscription of this credit note
 */
class CreditNotes extends DataModel
{
	/**
	 * Public constructor. We override it to set up behaviours and relations
	 *
	 * @param   Container $container
	 * @param   array     $config
	 */
	public function __construct(Container $container, array $config = array())
	{
		// We have a non-standard PK field
		$config['idFieldName'] = 'akeebasubs_invoice_id';

		parent::__construct($container, $config);

		// Add the Filters behaviour
		$this->addBehaviour('Filters');

		// Some filters we will have to handle programmatically so we need to exclude them from the behaviour
		$this->blacklistFilters([
			'akeebasubs_invoice_id',
			'creditnote_date',
			'sent_date',
		]);

		// Set up relations. Note that the invoice ID is also the subscription ID.
		$this->hasOne('invoice', 'Invoices', 'akeebasubs_invoice_id', 'akeebasubs_subscription_id');
		$this->hasOne('subscription', 'Subscriptions', 'akeebasubs_invoice_id', 'akeebasubs_subscription_id');
	}

	/**
	 * Create a PDF representation of a credit note. This method returns the raw PDF binary file data created on the fly.
	 *
	 * @return  string
	 *
	 * @since   6.0.1
	 */
	public function getPDFData(): string
	{
		// Repair the input HTML
		if (function_exists('tidy_repair_string'))
		{
			$tidyConfig = array(
				'bare'                        => 'yes',
				'clean'                       => 'yes',
				'drop-proprietary-attributes' => 'yes',
				'output-html'                 => 'yes',
				'show-warnings'               => 'no',
				'ascii-chars'                 => 'no',
				'char-encoding'               => 'utf8',
				'input-encoding'              => 'utf8',
				'output-bom'                  => 'no',
				'output-encoding'             => 'utf8',
				'force-output'                => 'yes',
				'tidy-mark'                   => 'no',
				'wrap'                        => 0,
			);
			$repaired   = tidy_repair_string($this->html, $tidyConfig, 'utf8');

			if ($repaired !== false)
			{
				$this->html = $repaired;
			}
		}

		// Fix any relative URLs in the HTML
		$this->html = $this->fixURLs($this->html);

		//echo "<pre>" . htmlentities($invoiceRecord->html) . "</pre>"; die();

		// Create the PDF
		$pdf = $this->getTCPDF();
		$pdf->AddPage();
		$pdf->writeHTML($this->html, true, false, true, false, '');
		$pdf->lastPage();
		$pdfData = $pdf->Output('', 'S');

		unset($pdf);

		return $pdfData;
	}

	/**
	 * Set the default ordering
	 *
	 * @param   \JDatabaseQuery $query
	 *
	 * @return  void
	 */
	protected function onBeforeBuildQuery(\JDatabaseQuery &$query)
	{
		// Set the default ordering by ID, descending
		if (is_null($this->getState('filter_order', null, 'cmd')) && is_null($this->getState('filter_order_Dir', null, 'cmd')))
		{
			$this->setState('filter_order', $this->getIdFieldName());
			$this->setState('filter_order_Dir', 'DESC');
		}
	}


	/**
	 * Build the SELECT query for returning records. Overridden to apply custom filters.
	 *
	 * @param   \JDatabaseQuery $query          The query being built
	 * @param   bool            $overrideLimits Should I be overriding the limit state (limitstart & limit)?
	 *
	 * @return  void
	 */
	public function onAfterBuildQuery(\JDatabaseQuery $query, $overrideLimits = false)
	{
		$db = $this->getDbo();

		$id = $this->getState('akeebasubs_invoice_id', null, 'raw');

		if (is_array($id))
		{
			if (isset($id['method']) && ($id['method'] == 'exact'))
			{
				$id = (int) $id['value'];
			}
			else
			{
				$id = 0;
			}
		}
		else
		{
			$id = (int) $id;
		}

		$subIDs = $this->getState('subids', null, 'array');
		$subIDs = empty($subIDs) ? [] : $subIDs;

		// Search by user
		$user = $this->getState('user', null, 'string');

		if (!empty($user))
		{
			// First get the Joomla! users fulfilling the criteria
			/** @var JoomlaUsers $users */
			$users       = $this->container->factory->model('JoomlaUsers')->tmpInstance();
			$userIDs     = $users->search($user)->with([])->get(true)->modelKeys();
			$filteredIDs = [-1];

			if (!empty($userIDs))
			{
				// Now get the subscriptions IDs for these users
				/** @var Subscriptions $subs */
				$subs = $this->container->factory->model('Subscriptions')->tmpInstance();
				$subs->setState('user_id', $userIDs);
				$subs->with([]);

				$filteredIDs = $subs->get(true)->modelKeys();
				$filteredIDs = empty($filteredIDs) ? [-1] : $filteredIDs;
			}

			if (!empty($subIDs))
			{
				$subIDs = array_intersect($subIDs, $filteredIDs);
			}
			else
			{
				$subIDs = $filteredIDs;
			}

			unset($subs);
		}

		// Search by business information
		$business = $this->getState('business', null, 'string');

		if (!empty($business))
		{
			$search = '%' . $business . '%';

			/** @var Subscriptions $subs */
			$subs = $this->container->factory->model('Subscriptions')->tmpInstance();
			$subs->whereHas('user', function (\JDatabaseQuery $q) use ($search)
			{
				$q->where(
					$q->qn('businessname') . ' LIKE ' . $q->q($search)
				);
			});

			$subs->with([]);
			$filteredIDs = $subs->get(true)->modelKeys();
			$filteredIDs = empty($filteredIDs) ? [-1] : $filteredIDs;

			if (!empty($subIDs))
			{
				$subIDs = array_intersect($subIDs, $filteredIDs);
			}
			else
			{
				$subIDs = $filteredIDs;
			}

			unset($subs);
		}

		// Search by a list of subscription IDs
		if (is_numeric($id) && ($id > 0))
		{
			$query->where(
				$db->qn('akeebasubs_invoice_id') . ' = ' . $db->q((int) $id)
			);
		}
		elseif (!empty($subIDs))
		{
			$subIDs = array_unique($subIDs);
			$subIDs = array_map(array($db, 'q'), $subIDs);

			// Look for all credit notes having this subscription ID. Remember subscription ID = invoice ID = credit note ID
			$query->where(
				$db->qn('akeebasubs_invoice_id') . ' IN (' . implode(',', $subIDs) . ')'
			);
		}

		// Search by credit note number (raw or formatted)
		$creditNoteNumber = $this->getState('creditnote_number', null, 'string');

		if (!empty($creditNoteNumber))
		{
			// Unified invoice / display number search
			$query->where(
				'((' .
				$db->qn('creditnote_number') . ' = ' . $db->q((int) $creditNoteNumber)
				. ') OR (' .
				$db->qn('display_number') . ' LIKE ' . $db->q('%' . $creditNoteNumber . '%')
				. '))'
			);
		}

		// Prepare for date filtering
		$dateRegEx = '/^\d{1,4}(\/|-)\d{1,2}(\/|-)\d{2,4}[[:space:]]{0,}(\d{1,2}:\d{1,2}(:\d{1,2}){0,1}){0,1}$/';

		// Filter by invoice issue date
		$invoice_date        = $this->getState('creditnote_date', null, 'string');
		$invoice_date_before = $this->getState('creditnote_date_before', null, 'string');
		$invoice_date_after  = $this->getState('creditnote_date_after', null, 'string');

		if (!empty($invoice_date) && preg_match($dateRegEx, $invoice_date))
		{
			$jFrom = $this->container->platform->getDate($invoice_date);
			$jFrom->setTime(0, 0, 0);
			$jTo = clone $jFrom;
			$jTo->setTime(23, 59, 59);

			$query->where(
				$db->qn('creditnote_date') . ' BETWEEN ' . $db->q($jFrom->toSql()) .
				' AND ' . $db->q($jTo->toSql())
			);
		}
		elseif (!empty($invoice_date_before) || !empty($invoice_date_after))
		{
			if (!empty($invoice_date_before) && preg_match($dateRegEx, $invoice_date_before))
			{
				$date = $this->container->platform->getDate($invoice_date_before);
				$query->where($db->qn('creditnote_date') . ' <= ' . $db->q($date->toSql()));
			}
			if (!empty($invoice_date_after) && preg_match($dateRegEx, $invoice_date_after))
			{
				$date = $this->container->platform->getDate($invoice_date_after);
				$query->where($db->qn('creditnote_date') . ' >= ' . $db->q($date->toSql()));
			}
		}

		// Filter by invoice email sent date
		$sent_on        = $this->getState('sent_on', null, 'string');
		$sent_on_before = $this->getState('sent_on_before', null, 'string');
		$sent_on_after  = $this->getState('sent_on_after', null, 'string');

		if (!empty($sent_on) && preg_match($dateRegEx, $sent_on))
		{
			$jFrom = $this->container->platform->getDate($sent_on);
			$jFrom->setTime(0, 0, 0);
			$jTo = clone $jFrom;
			$jTo->setTime(23, 59, 59);

			$query->where(
				$db->qn('sent_on') . ' BETWEEN ' . $db->q($jFrom->toSql()) .
				' AND ' . $db->q($jTo->toSql())
			);
		}
		elseif (!empty($sent_on_before) || !empty($sent_on_after))
		{
			if (!empty($sent_on_before) && preg_match($dateRegEx, $sent_on_before))
			{
				$date = $this->container->platform->getDate($sent_on_before);
				$query->where($db->qn('sent_on') . ' <= ' . $db->q($date->toSql()));
			}
			if (!empty($sent_on_after) && preg_match($dateRegEx, $sent_on_after))
			{
				$date = $this->container->platform->getDate($sent_on_after);
				$query->where($db->qn('sent_on') . ' >= ' . $db->q($date->toSql()));
			}
		}
	}

	/**
	 * Formats an invoice number
	 *
	 * @param   string  $numberFormat     The invoice number format
	 * @param   integer $creditNoteNumber The plain invoice number
	 * @param   integer $timestamp        Optional timestamp, otherwise uses current timestamp
	 *
	 * @return  string  The formatted invoice number
	 */
	public function formatCreditNoteNumber($numberFormat, $creditNoteNumber, $timestamp = null)
	{
		// Tokenise the number format
		$formatstring = $numberFormat;
		$tokens       = array();
		$start        = strpos($formatstring, "[");
		while ($start !== false)
		{
			if ($start != 0)
			{
				$tokens[] = array('s', substr($formatstring, 0, $start));
			}

			$end = strpos($formatstring, ']', $start);

			if ($end == false)
			{
				$tokens[]     = array('s', substr($formatstring, $start));
				$formatstring = '';
				//$start        = false;
			}
			else
			{
				$innerContent = substr($formatstring, $start + 1, $end - $start - 1);
				$formatstring = substr($formatstring, $end + 1);
				$parts        = explode(':', $innerContent, 2);
				$tokens[]     = array(strtolower($parts[0]), $parts[1]);
			}

			$start = strpos($formatstring, "[");
		}

		// Parse the tokens
		if (empty($timestamp))
		{
			$timestamp = time();
		}
		$ret = '';
		foreach ($tokens as $token)
		{
			list($type, $param) = $token;
			switch ($type)
			{
				case 's':
					// String parameter
					$ret .= $param;
					break;
				case 'd':
					// Date parameter
					$ret .= date($param, $timestamp);
					break;
				case 'n':
					// Number format
					$param = (int) $param;
					$ret .= sprintf('%0' . $param . 'u', $creditNoteNumber);
					break;
			}
		}

		return $ret;
	}

	/**
	 * Send a credit note by email. If the credit note's PDF doesn't exist it will
	 * attempt to create it.
	 *
	 * @param   Invoices  $invoice  THe invoice against which we're emailing a credit note PDF
	 *
	 * @return string The filename of the PDF or false if the creation failed.
	 */
	public function emailPDF(Invoices $invoice)
	{
		$pdfData = $this->getPDFData();

		if (empty($pdfData))
		{
			return false;
		}

		// Get the subscription record
		if (empty($invoice))
		{
			$invoice = $this->invoice;
		}

		// Get the mailer
		$mailer = Email::getPreloadedMailer($invoice->subscription, 'PLG_AKEEBASUBS_INVOICES_CREDITNOTE');

		if ($mailer === false)
		{
			return false;
		}

		// Attach the PDF invoice
		$mailer->addStringAttachment($pdfData, 'credit_note.pdf', 'base64', 'application/pdf');

		// Set the recipient
		$mailer->addRecipient($invoice->subscription->juser->email);

		// Send it
		$result = $mailer->Send();
		$mailer = null;

		if ($result == true)
		{
			$this->sent_on = $this->container->platform->getDate()->toSql();
			$this->save();
		}

		return $result;
	}

	/**
	 * @return \TCPDF
	 */
	public function &getTCPDF()
	{
		$certificateFile = $this->container->params->get('invoice_certificatefile', 'certificate.cer');
		$secretKeyFile   = $this->container->params->get('invoice_secretkeyfile', 'secret.cer');
		$secretKeyPass   = $this->container->params->get('invoice_secretkeypass', '');
		$extraCertFile   = $this->container->params->get('invoice_extracert', 'extra.cer');

		$certificate = '';
		$secretkey   = '';
		$extracerts  = '';

		$path = JPATH_ADMINISTRATOR . '/components/com_akeebasubs/assets/tcpdf/certificates/';

		if (\JFile::exists($path . $certificateFile))
		{
			$certificate = @file_get_contents($path . $certificateFile);
		}
		if (!empty($certificate))
		{
			if (\JFile::exists($path . $secretKeyFile))
			{
				$secretkey = @file_get_contents($path . $secretKeyFile);
			}
			if (empty($secretkey))
			{
				$secretkey = $certificate;
			}

			if (\JFile::exists($path . $extraCertFile))
			{
				$extracerts = @file_get_contents($path . $extraCertFile);
			}
			if (empty($extracerts))
			{
				$extracerts = '';
			}
		}

		// Set up TCPDF
		$jreg     = self::getContainer()->platform->getConfig();
		$tmpdir   = $jreg->get('tmp_path');
		$tmpdir   = rtrim($tmpdir, '/' . DIRECTORY_SEPARATOR) . '/';
		$siteName = $jreg->get('sitename');

		$baseurl = \JUri::base();
		$baseurl = rtrim($baseurl, '/');

		define('K_TCPDF_EXTERNAL_CONFIG', 1);

		define('K_PATH_MAIN', JPATH_BASE . '/');
		define('K_PATH_URL', $baseurl);
		define('K_PATH_FONTS', JPATH_ROOT . '/media/com_akeebasubs/tcpdf/fonts/');
		define('K_PATH_CACHE', $tmpdir);
		define('K_PATH_URL_CACHE', $tmpdir);
		define('K_PATH_IMAGES', JPATH_ROOT . '/media/com_akeebasubs/tcpdf/images/');
		define('K_BLANK_IMAGE', K_PATH_IMAGES . '_blank.png');
		define('PDF_PAGE_FORMAT', 'A4');
		define('PDF_PAGE_ORIENTATION', 'P');
		define('PDF_CREATOR', 'Akeeba Subscriptions');
		define('PDF_AUTHOR', $siteName);
		define('PDF_UNIT', 'mm');
		define('PDF_MARGIN_HEADER', 5);
		define('PDF_MARGIN_FOOTER', 10);
		define('PDF_MARGIN_TOP', 27);
		define('PDF_MARGIN_BOTTOM', 25);
		define('PDF_MARGIN_LEFT', 15);
		define('PDF_MARGIN_RIGHT', 15);
		define('PDF_FONT_NAME_MAIN', 'dejavusans');
		define('PDF_FONT_SIZE_MAIN', 8);
		define('PDF_FONT_NAME_DATA', 'dejavusans');
		define('PDF_FONT_SIZE_DATA', 8);
		define('PDF_FONT_MONOSPACED', 'dejavusansmono');
		define('PDF_IMAGE_SCALE_RATIO', 1.25);
		define('HEAD_MAGNIFICATION', 1.1);
		define('K_CELL_HEIGHT_RATIO', 1.25);
		define('K_TITLE_MAGNIFICATION', 1.3);
		define('K_SMALL_RATIO', 2 / 3);
		define('K_THAI_TOPCHARS', true);
		define('K_TCPDF_CALLS_IN_HTML', false);

		require_once JPATH_ADMINISTRATOR . '/components/com_akeebasubs/assets/tcpdf/tcpdf.php';

		$pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor(PDF_AUTHOR);
		$pdf->SetTitle('Invoice');
		$pdf->SetSubject('Invoice');

		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdf->setHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->setFooterMargin(PDF_MARGIN_FOOTER);
		$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

		$pdf->setHeaderFont(array('dejavusans', '', 8, '', false));
		$pdf->setFooterFont(array('dejavusans', '', 8, '', false));
		$pdf->SetFont('dejavusans', '', 8, '', false);

		if (!empty($certificate))
		{
			$pdf->setSignature($certificate, $secretkey, $secretKeyPass, $extracerts);
		}

		return $pdf;
	}

	private function fixURLs($buffer)
	{
		$pattern           = '/(href|src)=\"([^"]*)\"/i';
		$number_of_matches = preg_match_all($pattern, $buffer, $matches, PREG_OFFSET_CAPTURE);

		if ($number_of_matches > 0)
		{
			$substitutions = $matches[2];
			$last_position = 0;
			$temp          = '';

			// Loop all URLs
			foreach ($substitutions as &$entry)
			{
				// Copy unchanged part, if it exists
				if ($entry[1] > 0)
				{
					$temp .= substr($buffer, $last_position, $entry[1] - $last_position);
				}
				// Add the new URL
				$temp .= $this->replaceDomain($entry[0]);
				// Calculate next starting offset
				$last_position = $entry[1] + strlen($entry[0]);
			}
			// Do we have any remaining part of the string we have to copy?
			if ($last_position < strlen($buffer))
			{
				$temp .= substr($buffer, $last_position);
			}

			return $temp;
		}

		return $buffer;
	}

	private function replaceDomain($url)
	{
		static $myDomain = null;

		if (empty($myDomain))
		{
			$myDomain = \JUri::base(false);

			if (substr($myDomain, -1) == '/')
			{
				$myDomain = substr($myDomain, 0, -1);
			}

			if (substr($myDomain, -13) == 'administrator')
			{
				$myDomain = substr($myDomain, 0, -13);
			}
		}

		// Do we have a domain name?
		if (substr($url, 0, 7) == 'http://')
		{
			return $url;
		}

		if (substr($url, 0, 8) == 'https://')
		{
			return $url;
		}

		return $myDomain . '/' . ltrim($url, '/');
	}

	public function getCreditNotePath()
	{
		$date     = new Date($this->creditnote_date);
		$timezone = self::getContainer()->platform->getConfig()->get('offset', null);

		if ($timezone && $timezone != 'UTC')
		{
			$date->setTimezone(new \DateTimeZone($timezone));
		}

		return JPATH_ADMINISTRATOR . '/components/com_akeebasubs/creditnotes/' . $date->format('Y-m', true, false) . '/';
	}

	protected function setHtmlAttribute($value)
	{
		return $this->container->crypto->encrypt($value);
	}

	protected function setAtxtAttribute($value)
	{
		return $this->container->crypto->encrypt($value);
	}

	protected function setBtxtAttribute($value)
	{
		return $this->container->crypto->encrypt($value);
	}

	protected function getHtmlAttribute($value)
	{
		return $this->container->crypto->decrypt($value);
	}

	protected function getAtxtAttribute($value)
	{
		return $this->container->crypto->decrypt($value);
	}

	protected function getBtxtAttribute($value)
	{
		return $this->container->crypto->decrypt($value);
	}
}
