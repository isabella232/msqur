<?php

/* @brief Custom MSQ Parse Exceptions
 */
class MSQ_ParseException extends Exception {
	protected $htmlMessage;

	public function __construct($message = null, $html = '', $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->htmlMessage = $html;
	}

	public function getHTMLMessage() {
		return $this->htmlMessage;
	}
}

class MSQ_ConfigException extends MSQ_ParseException { }

/*
 * @brief INI parsing
 * 
 */
class INI
{
	/**
	 * @brief Given a signature string, calculates the respective INI file.
	 * 
	 * Returns an array of the config file contents.
	 * @param $signature The signature string which will be modified into a firmware/version array.
	 */
	public static function getConfig(&$signature)
	{
		//sig is 19 bytes + \0
		//"MS1/Extra format 029y3 *********"
		//"MS3 Format 0262.09 "
		//"MS3 Format 0435.14P"
		//"MS2Extra comms332m2"
		//"MS2Extra comms333e2"
		//"MS2Extra Serial321 "
		//"MS2Extra Serial310 "
		//"MSII Rev 3.83000   "
		//"MSnS-extra format 024s *********"
		//"MSnS-extra format 024y3 ********"
		//"rusEFI v1.2020.4"
		
		//Get the signature from the MSQ
		$sig = explode(' ', $signature); //, 3); limit 3 buckets
		$msVersion = $sig[0];

		//Handle MS2 strings that don't have 'format' in them
		if ($msVersion == "MS2Extra" || $msVersion == "rusEFI") $fwVersion = $sig[1];
		else $fwVersion = $sig[2];
		
		debug("Firmware version: $msVersion/$fwVersion");
		
		//Parse msVersion
		switch ($msVersion)
		{
			case "MS1":
				$msDir = "ms1/";
				break;

			case "MS1/Extra":
			case "MSnS-extra":
				$msDir = "msns-extra/";
				break;

			case "MSII":
				$msDir = "ms2/";
				break;

			case "MS2Extra":
				$msDir = "ms2extra/";
				break;

			case "MS3":
				$msDir = "ms3/";
				break;
				
			case "rusEFI":
				$msDir = "rusefi/";
				break;

			default:
				throw new MSQ_ConfigException("Unknown/Invalid MSQ signature: $msVersion/$fwVersion");
		}
		
		if ($msVersion != "rusEFI") {
			//Setup firmware version for matching.
			//(explode() already trimmed the string of spaces) -- this isn't true a couple inis
			//If there's a decimal, remove any trailing zeros.
			if (strrpos($fwVersion, '.') !== FALSE)
				$fwVersion = rtrim($fwVersion, '0');
		}

		//store all our hardwork for use in the calling function
		$signature = array($msVersion, $fwVersion);

		if ($msVersion == "rusEFI") {
			$fwVersion = str_replace(".", "/", $fwVersion);
		}
		
		$iniFile = "ini/" . $msDir . $fwVersion . ".ini";

		debug("INI File: $iniFile");

		return $iniFile;
	}
	
	/**
	 * @brief Parse a MS INI file into sections.
	 * 
	 * Based on code from: goulven.ch@gmail.com (php.net comments) http://php.net/manual/en/function.parse-ini-file.php#78815
	 * 
	 * @param $file The path to the INI file that will be loaded and parsed.
	 * @returns A huge array of arrays, starting with sections.
	 */
	public static function parse($file, $msq, $optionValues)
	{
		try
		{
			$ini = rusefi_file($file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
		}
		catch (Exception $e)
		{
			//TODO I'm not sure `file` throws
			echo "<div class=\"error\">Error opening file: $file</div>";
			error("Exception opening: $file");
			error($e->getMessage());
			return null;
		}
		
		if ($ini == FALSE || count($ini) == 0)
		{
			error("Error or empty file: $file");
			throw new MSQ_ConfigException("Could not open MSQ config file: $file");
		}
		else if (DEBUG) debug("Opened: $file");
		
		$globals = array();
		$curve = array();
		$table = array();
		$currentSection = NULL;
		$values = array();
		$outputs = array();
		$conditions = array();

		$directives = array();
		$skipByDirective = false;
		$settings = array();
		$curSettingGroup = NULL;

		foreach ($ini as $line)
		{
			$line = trim($line);
			if ($line == '' || $line[0] == ';') continue;
			if ($line[0] == '#')
			{
				$skipByDirective = INI::processDirective($line, $directives, $settings);
				continue;
			}

			// see INI::processDirective()
			if ($skipByDirective)
				continue;
			
			//[ at the beginning of the line is the indicator of a new section.
			if ($line[0] == '[') //TODO until before ] not end of line
			{
				$currentSection = substr($line, 1, -1);
				$values[$currentSection] = array();
				if (DEBUG) debug("Reading section: $currentSection");
				continue;
			}
			
			//Pretty much anything left has an equals sign I think.
			//Key-value pair around equals sign
			list($key, $value) = explode('=', $line, 2);
			$key = trim($key);
			
			//We don't handle formulas/composites yet
			$condition = NULL;
			if (strpos($line, '{') !== FALSE)
			{
				//isolate expression, parse it, fill in variables from msq, give back result (true,false,42?)
				//These are used in the ReferenceTables, Menus, and output/logging sections
				
				//For the menu, this is whether the menu item is visible or enabled.
				$condition = INI::parseExpression($line, $msq, $outputs);
				if ($condition === NULL) {
					//if (DEBUG) debug("Skipping expression in line: $line");
					continue;
				}
			}
			
			//Remove any line end comment
			//This could be moved somewhere else, but works fine here I guess.
			$hasComment = strpos($value, ';');
			if ($hasComment !== FALSE) $value = substr($value, 0, $hasComment);
			
			$value = trim($value);
			
			switch ($currentSection)
			{
				case "Constants": //The start of our journey. Fill in details about variables.
					$values[$currentSection][$key] = INI::defaultSectionHandler($value, false, $msq, $outputs);
					break;
				
				case "SettingContextHelp": //Any help text for our variable
					$values[$currentSection][$key] = trim($value);
					break;
				
				case "Menu":
					$menu = INI::defaultSectionHandler($value, true, $msq, $outputs);
					if (is_array($menu)) {
						if ($condition !== NULL) {
							$menu[count($menu) - 1] = $condition;
						}
					}
					if ($key == "menu")
					{
						$curMenu = $menu;
					}
					if (isset($curMenu))
					{
						$values["menu"][$curMenu][$key][] = $menu;
					}
					break;
				case "UserDefined":
					if ($key == "dialog")
					{
						$curDialog = INI::defaultSectionHandler($value, false, $msq, $outputs);
						if (!is_array($curDialog))
							$curDialog = array($curDialog);
					}
					if (is_array($curDialog))
					{
						$dlg = INI::defaultSectionHandler($value, false, $msq, $outputs);
						if (is_array($dlg)) {
							if ($condition !== NULL) {
								foreach ($dlg as &$d) {
									if (strpos($d, '{') !== FALSE)
										$d = INI::parseExpression($d, $msq, $outputs);
								}
							}
							$dlg["key"] = $key;
						}
						if ($key == "dialog")
							$values["dialog"][$curDialog[0]][$key] = $dlg;
						else if ($key == "slider" || $key == "commandButton")
							$values["dialog"][$curDialog[0]]["field"][] = $dlg;
						else
							$values["dialog"][$curDialog[0]][$key][] = $dlg;
					}
					break;
				
				case "CurveEditor": //2D Graph //curve = coldAdvance, "Cold Ignition Advance Offset"
					switch ($key)
					{
						case "curve": //start of new curve
							if (!empty($curve))
							{//save the last one, if any
								if (DEBUG) debug('Parsed curve: ' . $curve['id']);
								//var_export($curve);
								$values[$currentSection][$curve['id']] = $curve;
							}
							
							$value = array_map('trim', explode(',', $value));
							if (count($value) == 2)
							{
								$curve = array();
								$curve['id'] = $value[0];
								$curve['desc'] = trim($value[1], '"');
							}
							else if (DEBUG) debug("Invalid curve: $key");
							break;
						case "topicHelp":
							if (is_array($curve))
							{
								$curve[$key] = $value;
							}
							break;
						case "columnLabel":
							$value = array_map('trim', explode(',', $value));
							if (count($value) == 2)
							{
								$curve['xLabel'] = $value[0];
								$curve['yLabel'] = $value[1];
							}
							else if (DEBUG) debug("Invalid curve column label: $key");
							break;
						case "xAxis":
							$value = array_map('trim', explode(',', $value));
							if (count($value) == 3)
							{
								$curve['xMin'] = $value[0];
								$curve['xMax'] = $value[1];
								$curve['xSomething'] = $value[2];
							}
							else if (DEBUG) debug("Invalid curve X axis: $key");
							break;
						case "yAxis":
							$value = array_map('trim', explode(',', $value));
							if (count($value) == 3)
							{
								$curve['yMin'] = $value[0];
								$curve['yMax'] = $value[1];
								$curve['ySomething'] = $value[2];
							}
							else if (DEBUG) debug("Invalid curve Y axis: $key");
							break;
						case "xBins":
							$value = array_map('trim', explode(',', $value));
							if (count($value) >= 1)
							{
								$curve['xBinConstant'] = $value[0];
								//$curve['xBinVar'] = $value[1]; //The value read from the ECU
								//Think they all have index 1 except bogus curves
							}
							else if (DEBUG) debug("Invalid curve X bins: $key");
							break;
						case "yBins":
							$value = array_map('trim', explode(',', $value));
							if (count($value) >= 1)
							{
								$curve['yBinConstant'] = $value[0];
							}
							else if (DEBUG) debug("Invalid curve Y bins: $key");
							break;
						case "gauge": //not all have this
							break;
					}
				break;
				
				case "TableEditor": //3D Table/Graph
					switch ($key)
					{
						case "table": //start of new curve
							if (!empty($table))
							{//save the last one, if any
								if (DEBUG) debug('Parsed table: ' . $table['id']);
								//var_export($curve);
								$values[$currentSection][$table['id']] = $table;
							}
							
							$value = array_map('trim', explode(',', $value));
							if (count($value) == 4)
							{
								$table = array();
								$table['id'] = $value[0];
								$table['map3d_id'] = $value[1];
								$table['desc'] = trim($value[2], '"');
								//$table['page'] = $value[3]; //Don't care for this one AFAIK.
							}
							else if (DEBUG) debug("Invalid table: $key");
							break;
						case "topicHelp":
							if (is_array($table))
							{
								$table[$key] = $value;
							}
							break;
						case "xBins":
							$value = array_map('trim', explode(',', $value));
							if (count($value) >= 1)
							{
								$table['xBinConstant'] = $value[0];
								//$table['xBinVar'] = $value[1]; //The value read from the ECU
								//Think they all have index 1 except bogus tables
							}
							else if (DEBUG) debug("Invalid table X bins: $key");
							break;
						case "yBins":
							$value = array_map('trim', explode(',', $value));
							if (count($value) >= 1)
							{
								$table['yBinConstant'] = $value[0];
							}
							else if (DEBUG) debug("Invalid table Y bins: $key");
							break;
						case "zBins": //not all have this
							$value = array_map('trim', explode(',', $value));
							if (count($value) >= 1)
							{
								$table['zBinConstant'] = $value[0];
							}
							else if (DEBUG) debug("Invalid table Z bins: $key");
							break;
					}
					break;
				
				case "OutputChannels": //These are for gauges and datalogging
					$v = INI::defaultSectionHandler($value, false, $msq, $outputs);
					// here we store only computable outputs with expressions
					if (isset($v[0]) && strpos($v[0], '{') !== FALSE && $condition !== NULL) {
						$outputs["outputs"][$key] = $condition;
					}
					break;
				case "SettingGroups": //misc settings
					$values = INI::defaultSectionHandler($value, false, $msq, $outputs);
					if ($key == "settingGroup") {
						$curSettingGroup = isset($settings[$key]) ? count($settings[$key]) : 0;
						// this will be the options list
						$values[] = array();
					} else {
						// store the current value
						$values[] = in_array($values[0], $optionValues) ? 1 : 0;
						// we also add this option to the current group list
						$settings["settingGroup"][$curSettingGroup][2][] = $values;
					}
					$settings[$key][] = $values;

					break;
				case "PcVariables":
					$values[$currentSection][$key] = INI::defaultSectionHandler($value, false, $msq, $outputs);
					break;
				//Don't care about these
				case "Datalog": //Not relevant
				case "MegaTune":
				case "ReferenceTables": //misc MAF stuff
				case "ConstantsExtensions": //misc reset required fields
				case "PortEditor": //not sure
				case "GaugeConfigurations": //Not relevant
				case "FrontPage": //Not relevant
				case "RunTime": //Not relevant
				case "Tuning": //Not relevant
				case "AccelerationWizard": //Not sure
				case "BurstMode": //Not relevant
					break;
				default:
					break;
				case NULL:
					//Should be global values (don't think any ini's have them)
					assert($currentSection === NULL);
					$globals[$key] = INI::defaultSectionHandler($value, false, $msq, $outputs);
				break;
			}
		}

		//var_export($values);
		return $values + $globals + $conditions + $settings + $outputs;
	}
	
	/**
	 * @brief Strip excess whitespace and cruft to get to value assignments
	 * @param $value
	 * @returns An array if there's a comma, or just the value.
	 */
	private static function defaultSectionHandler($value, $isLessStrict = false, $msq, $outputs)
	{
		//For things like "nCylinders      = bits,    U08,      0,"
		//split CSV into an array
		if (strpos($value, ',') !== FALSE || $isLessStrict) {
			// a simple explode() by comma is not enough since we have expressions like "text,text"
			$v = $value;
			if (preg_match_all("/\"[^\"]*\"|[A-Za-z0-9_\.\[\]\:]+|{[^\}]+}/", $value, $ret))
				$v = $ret[0];
			if (count($v) == 1)
				$v = $v[0];
		}
		else //otherwise just return the value
			$v = trim($value);
		// pre-parse const. expressions
		$exprList = is_array($v) ? $v : array($v);
		foreach ($exprList as &$e) {
			if (strpos($e, '{') === 0) {
				$outFlags = array();
				$newE = INI::parseExpression($e, $msq, $outputs, $outFlags);
				// if not constant expr (requires external vars), then skip
				if (count($outFlags) != 0)
					continue;
				// eval it now!
				try
				{
					$e = eval($newE);
				} catch (Throwable $t) {
					// todo: should we react somehow?
				}
			}
		}
		// save and return as a new value
		if (is_array($v))
			$v = $exprList;
		else
			$v = $exprList[0];

		return preg_replace("/\"([^\"]+)\"/", "$1", $v);
	}
	
	public static function parseExpression($line, $msq, $outputs, &$outFlags = NULL)
	{
		// we'll use eval() for these expressions, so we need to be extremely careful & paranoic here
		$separators = "\-\+\!\/\?\:=><&|(),";
		if (!preg_match ("/{([A-Za-z_0-9".$separators."\s]+)}/", $line, $ret)) {
			return NULL;
		}

		// filter out excessive whitespace
		$text = trim(preg_replace("/[\s]+/", " ", $ret[1]));
		// parse lexemes
		$lexemes = preg_split("/\s*([".$separators."]+)\s*/", $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		// process lexemes - add our getters for the variables and outputChannels
		foreach ($lexemes as &$l) {
			// check if we know this variable and have a value for it
			if (preg_match("/[A-Za-z_][A-Za-z_0-9]*/", $l)) {
				// first, search the variables
				$search = $msq->xpath('//constant[@name="' . $l . '"]');
				if ($search !== FALSE && count($search) > 0) {
					if ($outFlags !== NULL)
						$outFlags[] = $l;
					$l = "\$rusefi->getMsqConstant('".$l."')";
				}
				// try outputChannels?
				else if (isset($outputs["outputs"]) && array_key_exists($l, $outputs["outputs"])) {
					if ($outFlags !== NULL)
						$outFlags[] = $l;
					$l = "\$rusefi->getMsqOutput('".$l."')";
				}
				else {
					//echo "~~~~~~~~~~~ ".$l."\r\n";
					return NULL;
				}
			}
		}

		// reconstruct the expression
		return "return " . implode(" ", $lexemes) . ";";
	}

	public static function processDirective($line, &$directives, $settings)
	{
		$c = count($directives) - 1;
		if (preg_match("/#if\s+([A-Za-z0-9]+)/", $line, $ret)) {
			array_push($directives, INI::checkOption($ret[1], $settings));
		}
		else if (preg_match("/#elif\s+([A-Za-z0-9]+)/", $line, $ret)) {
			$directives[$c] = INI::checkOption($ret[1], $settings);
		}
		else if (preg_match("/#else/", $line, $ret)) {
			$directives[$c] = 1 - $directives[$c];
		}
		else if (preg_match("/#endif/", $line, $ret)) {
			array_pop($directives);
		}
		return count($directives) != 0 && end($directives) != 1;
	}

	public static function checkOption($optionName, $settings)
	{
		foreach ($settings["settingOption"] as $so) {
			if ($so[0] == $optionName)
				return $so[2];
		}
		return false;
	}
}
?>
