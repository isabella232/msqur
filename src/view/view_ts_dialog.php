<?php

//$html["debug"] = print_r($msqMap["dialog"]["injectionSettings"], TRUE);
//$html["debug"] = print_r($msqMap["dialog"]["injectionBasic"], TRUE);
//$html["debug"] = print_r($msqMap["menu"]["&Base &Engine"], TRUE);

function printTsItem($mn) {
	return preg_replace(array("/&([A-Za-z])/", "/\"/"), array("<u>$1</u>", ""), $mn);
}

function getDialogTitle($msqMap, $dlg) {
	
	if (is_array($dlg["dialog"])) {
		$dlgName = $dlg["dialog"][0];
		$dlgTitle = printTsItem($dlg["dialog"][1]);
	} else {
		$dlgName = $dlg["dialog"];
		$dlgTitle = "";
	}
	if (empty($dlgTitle)) {
		// take the title from menu
		foreach ($msqMap["menu"] as $menu) {
			foreach ($menu["subMenu"] as $sub) {
				if ($sub[0] == $dlgName) {
					$dlgTitle = printTsItem($sub[1]);
				}
			}
		}
	}
	return $dlgTitle;
}

function printField($msqMap, $field, $isPanelDisabled) {
	global $rusefi;

	if (isset($field[1]) && isset($msqMap["Constants"][$field[1]])) {
		$disabled = FALSE;
		// disable the field if required
		if (isset($field[2])) {
			try
			{
				// see INI::parseExpression()
				$disabled = !eval($field[2]);
			} catch (Throwable $t) {
				// todo: should we react somehow?
			}
		}
		// hide the field if required
		$hidden = false;
		if (isset($field[3])) {
			try
			{
				// see INI::parseExpression()
				$hidden = !eval($field[3]);
			} catch (Throwable $t) {
				// todo: should we react somehow?
			}
		}
		if ($hidden)
			return;
		$disabled |= $isPanelDisabled;
		if ($disabled)
			$disabled = " disabled='disabled' ";

		// todo: add edit mode
		$readOnly = "readonly";

		$curValue = $rusefi->getMsqConstant($field[1]);
		$cons = $msqMap["Constants"][$field[1]];
		$units = "";
		if ($cons[0] == "scalar") {
			$units = printTsItem($cons[3]);
			if (!empty($units)) {
				$units = "(" . $units . ")";
			}
		}

		$hint = "";
		if (isset($msqMap["SettingContextHelp"][$field[1]])) {
			$hint = printTsItem($msqMap["SettingContextHelp"][$field[1]]);
			$hint = " title=\"".$hint."\"";
			//print_r($hint);
			//$hint
		}

		// print hint icon
		echo "<td class='ts-field-td-hint'>";
		if (!empty($hint))
			echo "<img src=\"view/img/ts-icons/hint.png\" ".$hint.">";
		echo "</td>\r\n";

		$addToLabel = "";
		// slider is a special case
		if ($field["key"] == "slider") {
			echo "<td colspan=2 class='ts-field-td-label'><table width='100%' class='ts-field-table' cellspacing='2' cellpadding='2'><tbody><tr>\r\n";
			echo "<td class='ts-field-td-slider'><div class='ts-slider' value='".$curValue."' input='".$field[1]."'></div></td>\r\n";
			echo "<td class='ts-field-td-item'><input id='".$field[1]."' class='ts-field-item-text' value='".$curValue."' >";
			echo "</td></tr><tr>";
			$addToLabel = " colspan=2 style='text-align:center'";
		}

		// print text label
		echo "<td class='ts-field-td-label' $addToLabel><label for=".$field[1]." class='ts-field-label' $disabled $readOnly ".$hint.">".printTsItem($field[0]).$units."</label></td>\r\n";

		// skip the rest
		if ($field["key"] == "slider") {
			echo "</tr></tbody></table></td>";
			return;
		}

		echo "<td class='ts-field-td-item'>\r\n";

		if ($cons[0] == "bits") {
			echo "<select class='ts-field-item ts-field-item-select' id='".$field[1]."' $disabled $readOnly>\r\n";
        	for ($i = 4; $i < count($cons); $i++) {
        		$selected = ($curValue == ($i - 4)) ? " selected='selected' " : "";
        		echo "<option class='ts-field-item-option' $selected>".printTsItem($cons[$i])."</option>\r\n";
        	}
      		echo "</select>\r\n";
		}
		else if ($cons[0] == "scalar") {
			echo "<input id='".$field[1]."' class='ui-spinner-input ts-field-item ts-field-item-text' value='".$curValue."' $disabled $readOnly>";
		}
		else if ($cons[0] == "string") {
			echo "<input id='".$field[1]."' class='ts-field-item' value='".$curValue."' $disabled $readOnly>";
		}
		echo "</td>";
		//print_r($cons);
		return;
	}

	if (is_array($field) && count($field) == 1)
		$field = $field[0];

	// text field?
	if (is_string($field)) {
		$field = printTsItem($field);
		// colorize some labels
		if (!empty($field) && ($field[0] == '#' || $field[0] == '!')) {
			$clr = ($field[0] == '#') ? "ts-label-blue" : "ts-label-red";
			$field = substr($field, 1);
		}
		else {
			$clr = "";
		}
		echo "<td class='ts-label $clr' colspan='3'>$field</td>\r\n";
	}
}

function printDialog($msqMap, $dialogId, $isPanel, $isDialogDisabled = false) {
	global $rusefi;

	if (isset($msqMap["dialog"][$dialogId])) {
		$dlg = $msqMap["dialog"][$dialogId];
		$dlgTitle = getDialogTitle($msqMap, $dlg);
	} else if (isset($msqMap["CurveEditor"][$dialogId])) {
		$curve = $msqMap["CurveEditor"][$dialogId];
		$dlgTitle = $curve['desc'];
		printCurve($msqMap, $dialogId, $curve);
		return $dlgTitle;
	} else if (isset($msqMap["TableEditor"][$dialogId])) {
		$curve = $msqMap["TableEditor"][$dialogId];
		$dlgTitle = $curve['desc'];
		printCurve($msqMap, $dialogId, $curve);
		return $dlgTitle;
	}

	$isHorizontal = (isset($dlg["dialog"][2]) && $dlg["dialog"][2] == "xAxis");

	// draw fields first, then panels
	// create a fake panel for dialogs without panels
	if (!$isPanel) {
?>
<fieldset class='ts-panel-notitle'><div class='ts-controlgroup ts-controlgroup-vertical'>
<?php
	}
?>
<table class='ts-field-table' cellspacing="2" cellpadding="2"><tbody>
<?php
	if (isset($dlg["field"])) {
		foreach ($dlg["field"] as $field) {
			//$f = getDialogTitle($msqMap, $field);
			//print_r($field);
			if (!$isHorizontal) {
?>
<tr>
<?php
			}
			printField($msqMap, $field, $isDialogDisabled);
			if (!$isHorizontal) {
?>
</tr>
<?php
			}
		}
	}
?>
</tbody></table>
<?php
	if (!$isPanel) {
?>
</div></fieldset>
<?php
	}

	// draw panels (recursive)
	if (isset($dlg["panel"])) {
		foreach ($dlg["panel"] as $panel) {
			$isDisabled = false;
			if (is_array($panel)) {
				if (isset($panel[1])) {
					try
					{
						// see INI::parseExpression()
						$isDisabled = !eval($panel[1]);
					} catch (Throwable $t) {
						// todo: should we react somehow?
					}
				}
				$panel = $panel[0];
			}
			if (isset($msqMap["dialog"][$panel])) {
				$p = $msqMap["dialog"][$panel];
				$pt = getDialogTitle($msqMap, $p);
			} else {
				$pt = "";
			}
			//print_r($p);
			$pClass = $isHorizontal ? "class='ts-controlgroup ts-controlgroup-horizontal'" : "class='ts-controlgroup ts-controlgroup-vertical'";
			$fClass = $isHorizontal ? "class='ts-panel ts-panel-horizontal'" : "class='ts-panel ts-panel-vertical'";
			if (!empty($pt)) {
?>
	<fieldset <?=$fClass;?> <?=$isDisabled ? "disabled":"";?>><legend><?=$pt;?></legend>
<?php
			} else {
			$fClass = $isHorizontal ? "class='ts-panel-notitle ts-panel-horizontal'" : "class='ts-panel-notitle'";
?>
	<fieldset  <?=$fClass;?>>
<?php
			}
?>
    <div <?=$pClass;?>>
<?php
			printDialog($msqMap, $panel, TRUE, $isDisabled);
?>
    </div>
  </fieldset>
<?php
		}
	}

	return $dlgTitle;
}

function printCurve($msqMap, $id, $curve) {
	global $rusefi;

	$help = array_key_exists('topicHelp', $curve) ? $curve['topicHelp'] : NULL;

	echo "<script>	var accordionOptions = {
		animate: false,
		active: true, collapsible: true, //to avoid hooking 'create' to make the first graph render
		heightStyle: 'content',
	};</script>";

	$tabId = "tab_" . $id;
	echo "<div id='".$tabId."'>";

	if (array_keys_exist($curve, 'desc', 'xBinConstant', 'yBinConstant', 'zBinConstant'))
	{
		$xBins = $rusefi->msq->findConstant($rusefi->msq->msq, $curve['xBinConstant']);
		$yBins = $rusefi->msq->findConstant($rusefi->msq->msq, $curve['yBinConstant']);
		$zBins = $rusefi->msq->findConstant($rusefi->msq->msq, $curve['zBinConstant']);
		$xAxis = preg_split("/\s+/", trim($xBins));
		$yAxis = preg_split("/\s+/", trim($yBins));
		$zData = preg_split("/\s+/", trim($zBins));//, PREG_SPLIT_NO_EMPTY); //, $limit);
		echo $rusefi->msq->msqTable3D($curve, $xAxis, $yAxis, $zData, $help, true);

	}	
	else if (array_keys_exist($curve, 'desc', 'xBinConstant', 'yBinConstant', 'xMin', 'xMax', 'yMin', 'yMax'))
	{
		$xBins = $rusefi->msq->findConstant($rusefi->msq->msq, $curve['xBinConstant']);
		$yBins = $rusefi->msq->findConstant($rusefi->msq->msq, $curve['yBinConstant']);
		$xAxis = preg_split("/\s+/", trim($xBins));
		$yAxis = preg_split("/\s+/", trim($yBins));
		echo $rusefi->msq->msqTable2D($curve, $curve['xMin'], $curve['xMax'], $xAxis, $curve['yMin'], $curve['yMax'], $yAxis, $help, true);
	}

	echo "</div><script>$('div#".$tabId."').accordion(accordionOptions);
		processChart($('div#".$tabId."'));
		colorizeTables();
		</script>";
	
}

?>