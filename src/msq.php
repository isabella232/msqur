<?php
/* msqur - MegaSquirt .msq file viewer web application
Copyright 2014-2019 Nicholas Earwood nearwood@gmail.com https://nearwood.dev

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>. */

include "msq.format.php";
include "util.php";

/**
 * @brief MSQ Parsing class.
 * 
 */
class MSQ
{
	public $msqMap;
	public $msq;

	/**
	 * @brief Format a constant to HTML
	 * @param $constant The constant name
	 * @param $value Its value
	 * @returns String HTML \<div\>
	 */
	private function msqConstant($constant, $value, $help)
	{
		//var_export($constant);
		//var_export($value);
		//var_export($help);
		return "<span class=\"constant\" title=\"$help\"><b>$constant:</b><span class=\"value\">$value</span></span>";
	}
	
	/**
	 * @brief Parse MSQ XML into an array of HTML 'groups'.
	 * @param $xml SimpleXML
	 * @param $engine 
	 * @param $metadata 
	 * @returns String HTML
	 */
	public function parseMSQ($xml, &$engine, &$metadata, $viewType, $settings)
	{
		$html = array();
		if (DEBUG) debug('Parsing XML...');
		$errorCount = 0; //Keep track of how many things go wrong.
		
		libxml_use_internal_errors(true);
		$msq = simplexml_load_string($xml);

		if ($msq === false) {
			error("Failed to parse XML.");
			foreach(libxml_get_errors() as $error) {
					error($error->message);
			}

			throw new MSQ_ParseException("Error parsing XML", '<div class="error">Unable to parse MSQ.</div>');
		} else if ($msq) {
			$this->msq = $msq;

			$msqHeader = '<div class="info">';
			$msqHeader .= "<div>Format Version: " . $msq->versionInfo['fileFormat'] . "</div>";
			$msqHeader .= "<div>MS Signature: " . $msq->versionInfo['signature'] . "</div>";
			$msqHeader .= "<div>Tuning SW: " . $msq->bibliography['author'] . "</div>";
			$msqHeader .= "<div>Date: " . $msq->bibliography['writeDate'] . "</div>";
			$msqHeader .= '</div>';
			$html['msqHeader'] = $msqHeader;
			
			$sig = $msq->versionInfo['signature'];
			$sigString = $sig;
			try {
				$iniFile = INI::getConfig($sig);
				$msqMap = INI::parse($iniFile, $msq, $settings);
				$this->msqMap = $msqMap;
			} catch (MSQ_ConfigException $e) {
				error('Error parsing config file: ' . $e->getMessage());
				$issueTitle = urlencode("INI Request: $sigString");
				$htmlMessage = $msqHeader . '<div class="error">Unable to load the corresponding configuration file for that MSQ. Please <a href="https://github.com/nearwood/msqur/issues/new?title=' . $issueTitle . '">file a bug!</a></div>';
				$this->msqMap = array();
				throw new MSQ_ParseException("Could not load configuration file for MSQ: " . $e->getMessage(), $htmlMessage, 100, $e);
			}
			
			//Calling function will update
			$metadata['fileFormat'] = $msq->versionInfo['fileFormat'];
			$metadata['signature'] = $sig[1];
			$metadata['firmware'] = $sig[0];
			$metadata['author'] = $msq->bibliography['author'];

			if ($viewType == "ts" || $viewType == "ts-dialog")
			{
				include_once "view/view_ts.php";
				return $html;
			}
			
			$constants = array();
			$helpTexts = array();
			$curves = array();
			$tables = array();
			
			if (array_key_exists('Constants', $msqMap)) $constants = $msqMap['Constants'];
			if (array_key_exists('SettingContextHelp', $msqMap)) $helpTexts = $msqMap['SettingContextHelp'];
			if (array_key_exists('CurveEditor', $msqMap)) $curves = $msqMap['CurveEditor'];
			if (array_key_exists('TableEditor', $msqMap)) $tables = $msqMap['TableEditor'];
			
			$html["tabList"] = <<<EOT
			<div id="tabList">
				<ul>
					<li><a href="#tab_tables">3D Tables</a></li>
					<li><a href="#tab_curves">2D Tables (Curves)</a></li>
					<li><a href="#tab_constants">Constants</a></li>
				</ul>
EOT;

			$html["tabList"] .= '<div id="tab_curves">';
			foreach ($curves as $curve)
			{
				if (in_array($curve['id'], $this->msq_curve_blacklist))
				{
					if (DEBUG) debug('Skipping curve: ' . $curve['id']);
					continue;
				}
				else if (DEBUG) debug('Curve: ' . $curve['id']);
				
				//id is just for menu (and our reference)
				//need to find xBin (index 0, 1 is the live meatball variable)
				//and find yBin and output those.
				//columnLabel also for labels
				//xAxis and yAxis are just for maximums?
				$help = NULL;
				if (array_key_exists('topicHelp', $curve))
					$help = $curve['topicHelp'];
				
				//var_export($curve);
				
				if (array_keys_exist($curve, 'desc', 'xBinConstant', 'yBinConstant', 'xMin', 'xMax', 'yMin', 'yMax'))
				{
					$digits = array(2, 2);
					$xBins = $this->findConstant($msq, $curve['xBinConstant'], $digits, true);
					$yBins = $this->findConstant($msq, $curve['yBinConstant'], $digits, true);
					$xAxis = preg_split("/\s+/", trim($xBins));
					$yAxis = preg_split("/\s+/", trim($yBins));
					$html["tabList"] .= $this->msqTable2D($curve, $curve['xMin'], $curve['xMax'], $xAxis, $curve['yMin'], $curve['yMax'], $yAxis, $help, false, $digits);
				}
				else if (DEBUG) debug('Missing/unsupported curve information: ' . $curve['id']);
			}
			$html["tabList"] .= '</div>';
			
			$html["tabList"] .= '<div id="tab_tables">';
			foreach ($tables as $table)
			{
				if (DEBUG) debug('Table: ' . $table['id']);
				
				$help = NULL;
				if (array_key_exists('topicHelp', $table))
					$help = $table['topicHelp'];
				
				//var_export($table);
				
				if (array_keys_exist($table, 'desc', 'xBinConstant', 'yBinConstant', 'zBinConstant'))
				{
					$digits = array(2, 2, 2);
					$xBins = $this->findConstant($msq, $table['xBinConstant'], $digits[0], true);
					$yBins = $this->findConstant($msq, $table['yBinConstant'], $digits[1], true);
					$zBins = $this->findConstant($msq, $table['zBinConstant'], $digits[2], true);
					$xAxis = preg_split("/\s+/", trim($xBins));
					$yAxis = preg_split("/\s+/", trim($yBins));
					$zData = preg_split("/\s+/", trim($zBins));//, PREG_SPLIT_NO_EMPTY); //, $limit);
					$html["tabList"] .= $this->msqTable3D($table, $xAxis, $yAxis, $zData, $help, false, $digits);
				}
				else if (DEBUG) debug('Missing/unsupported table information: ' . $table['id']);
			}
			$html["tabList"] .= '</div>';
			
			$html["tabList"] .= '<div id="tab_constants">';
			foreach ($constants as $key => $config)
			{
				if ($config[0] == "array") continue; //TODO Skip arrays until blacklist is done
				
				$value = $this->findConstant($msq, $key, $digits, true);
				
				//if (DEBUG) debug("Trying $key for engine data");
				if ($value !== NULL)
				{
					$value = trim($value, '"');
					$engineDbKey = getEngineDbKey($key, $metadata);
					if ($engineDbKey !== FALSE)
					{
						if (DEBUG) debug("* $engineDbKey = $value");
						$engine[$engineDbKey] = $value;
					}
					
					if (array_key_exists($key, $helpTexts))
						$help = $helpTexts[$key];
					
					$html["tabList"] .= $this->msqConstant($key, $value, $help);
				}
			}
			$html["tabList"] .= '</div>';
			$html["tabList"] .= '</div>';

		}
		
		return $html;
	}
	
	/**
	 * @brief Convenience function to display errors.
	 * @param $e The error to display.
	 * @returns String Error in HTML form.
	 */
	private function msqError($e)
	{
		echo '<div class="error">Error parsing MSQ. ';
		echo $e->getMessage();
		echo '</div>';
	}
	
	/**
	 * @brief Find constant value in MSQ XML.
	 * @param $xml SimpleXML
	 * @param $constant ID of constant to search for
	 * @returns String of constant value, or NULL if not found
	 */
	public function findConstant($xml, $constant, &$digits, $format = true)
	{
		$search = $xml->xpath('//constant[@name="' . $constant . '"]');
		if ($search === FALSE || count($search) == 0) return NULL;
		if (!isset($search[0]["digits"])) {
			// todo: what number is better?
			$digits = 2;
			return $search[0];
		}
		$digits = intval($search[0]["digits"]);
		if ($format)
		{
			$out = "";
			$rows = preg_split('/\n+/', $search[0][0], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($rows as $r)
			{
				$vals = preg_split('/\s+/', $r, -1, PREG_SPLIT_NO_EMPTY);
				foreach ($vals as $v)
				{
					$out .= number_format(floatval($v), $digits) . " ";
				}
				$out .= "\n";
			}
			$search[0][0] = $out;
		}
		return $search[0];
	}
	
	/**
	 * @brief Get an HTML table from 2D data.
	 * @param $curve Array of values I'm too lazy to parameterize.
	 * @param $xMin Minimum X axis value (NI)
	 * @param $xMax Maximum X axis value (NI)
	 * @param $xAxis Array of actual X set points
	 * @param $yMin Minimum Y axis value (NI)
	 * @param $yMax Maximum Y axis value (NI)
	 * @param $yAxis Array of actual Y set points
	 * @param $helpText Optional text to display for more information
	 * @returns A huge string containing a root <table> element
	 */
	public function msqTable2D($curve, $xMin, $xMax, $xAxis, $yMin, $yMax, $yAxis, $helpText, $hideHeader, $digits)
	{
		$output = "";
		$hot = 0;
		$xLabel = "";
		$yLabel = "";
		
		if (array_keys_exist($curve, 'xLabel', 'yLabel'))
		{
			//Get rid of quotes around the label strings.
			$xLabel = trim($curve['xLabel'], '"');
			$yLabel = trim($curve['yLabel'], '"');
		}
		
		//var_export($curve);
		
		//if (DEBUG) debug('Formatting curve: ' . $curve['id']);
		
		$dataCount = count($xAxis);
		if ($dataCount !== count($yAxis))
		{
			$output .= '<h3>' . $curve['desc'] . '</h3><div class="error">Axis lengths not equal for: ' . $curve['desc'] . '</div>';
			//if (DEBUG) $output .= "<div class=\"debug\">Found engine data: $key ($constant)</div>";
			return $output;
		}
		
		if (!$hideHeader) {
			$output .= '<h3>' . $curve['desc'] . '</h3>';
		}
		$output .= '<div><div class="curve"><table class="msq tablesorter 2d" hot="' . $hot . '">';
		//if ($helpText != null) $output .= '<caption>' . $helpText . '</caption>';
		
		$output .= '<thead><tr><th>' . $xLabel . '</th><th>' . $yLabel . '</th></tr></thead><tbody>';
		for ($c = 0; $c < $dataCount; $c++)
		{
			$output .= '<tr><th class="{sorter: false}" digits="'.$digits[0].'">' . $xAxis[$c] . '</th>';
			$output .= '<td digits="'.$digits[1].'">' . $yAxis[$c] . '</td></tr>';
		}
		
		$output .= '</tbody></table></div><div class="chart"><canvas id="' . $curve['id'] . '" class="curve" width="360" height="240"></canvas></div></div>';
		
		return $output;
	}
	
	/**
	 * @brief Get an HTML table from 3D data.
	 * @param $table Array of values I'm too lazy to parameterize.
	 * @param $xAxis Array of actual X set points
	 * @param $yAxis Array of actual Y set points
	 * @param $zBins Array of actual Z set points
	 * @param $helpText Optional text to display for more information
	 * @returns A huge string containing a root <table> element
	 */
	public function msqTable3D($table, $xAxis, $yAxis, $zBins, $helpText, $hideHeader, $digits)
	{
		$output = "";
		$hot = 0;
		$rows = count($yAxis);
		$cols = count($xAxis);
		
		//if (DEBUG) debug('Formatting table: ' . $table['id']);
		
		$dataCount = count($zBins);
		if ($dataCount !== $rows * $cols)
		{
			$output .= '<h3>' . $table['desc'] . '</h3><div class="error">Axis/data lengths not equal for: ' . $table['desc'] . '</div>';
			return $output;
		}
		
		if (!$hideHeader) {
			$output .= '<h3>' . $table['desc'] . '</h3><div>';
		}
		//TODO Probably there's a better way to do this (like on the front end)
		if (stripos($table['id'], "ve") === FALSE)
		{
			$output .= '<div class="table"><table class="msq tablesorter 3d" hot="' . $hot . '">';
		}
		else
		{
			$output .= '<div class="table"><table class="msq tablesorter 3d ve" hot="' . $hot . '">';
		}
		
		//if ($helpText != null) $output .= '<caption>' . $helpText . '</caption>';
		$output .= "<thead><tr><th></th>"; //blank cell for corner
		for ($c = 0; $c < $cols; $c++)
		{
			//TODO: This is not triggering tablesorter
			$output .= '<th class="{sorter: false}">' . $xAxis[$c] . "</th>";
		}
		$output .= "</tr></thead>";
		
		for ($r = 0; $r < $rows; $r++)
		{
			$output .= "<tr><th>" . $yAxis[$r] . "</th>";
			for ($c = 0; $c < $cols; $c++)
			{
				$output .= "<td digits='".$digits[2]."'>" . $zBins[$r * $rows + $c] . "</td>";
			}
		}
		
		$output .= "</tr>";
		$output .= '</table></div><!-- div class="chart"><canvas id="' . $table['id'] . '" class="table" width="360" height="240"></canvas></div --></div>';
		
		return $output;
	}
	
	private $msq_curve_blacklist = array("vmcurve", "s5curve");
	
	private $msq_constant_blacklist = array("afrTable1",
		"afrTable2",
		"veTable1",
		"veTable2",
		"veTable3"
	);
}

?>
