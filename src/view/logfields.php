<?php
	$logGeneral = array();
	if (isset($logValues["estimatedDuration"])) {
		$logGeneral["estimatedDuration"] = "Estimated duration: ~";
		$logGeneral["duration"] = "Partial duration:";
	}
	else {
		$logGeneral["duration"] = "Total duration:";
	}
	$logGeneral["dataRate"] = "Data Rate: ~";

	$logTable = array(
		"logStart" => array("Starting", array(
			"startingNum" => "Number of starts:",
			"startingNumCold" => "Cold starts:",
			"startingSuccessRate" => "Success rate:",
			"startingFastestTime" => "Fastest time:",
			"startingNumTriggerErrors" => "Trigger errors:",
		)),
		"logIdling" => array("Idling", array(
			"idlingDuration" => "Duration:",
			"idlingAverageRpm" => "Average RPM:",
			"idlingAfrAccuracy" => "AFR accuracy:",
			"idlingNumTriggerErrors" => "Trigger errors:",
		)),
		"logRunning" => array("Running", array(
			"runningDuration" => "Duration:",
			"runningMaxRpm" => "Max RPM:",
			"runningMaxThrottle" => "Max Throttle:",
			"runningMaxVe" => "Max VE:",
			"runningMaxSpeed" => "Max Speed:",
			"runningAfrAccuracy" => "AFR accuracy:",
		)),
	);			

	function formatLogNoteTime($secs) {
		// a trick to get non-padded minutes
		return ($secs >= 3600) ? date("G:i:s", $secs) : intval(date("i", $secs)) . date(":s", $secs);
	}

	function putField($lfn, $lf, $val, $oneLine = false) { ?>
	<div>
	<label for="<?=$lfn;?>" class=logLabel><?=$lf;?>
<?php 
	if ($oneLine) { 
		echo " " . $val . "</label>";
	} else { 
?>
	</label><input id="<?=$lfn;?>" name="<?=$lfn;?>" value="<?=$val;?>" type="text" maxlength="5" class=logInput readonly/>
<?php
	}
?>
	</div>
<?php }
	
if (isset($logValues["data"])) {
	// get partial data for graph display
	$data = array();
	$numGraphs = 4;
	$fieldNames = array_keys($logValues["data"]);
	for ($i = 0; $i < $numGraphs; $i++) {
		// todo: impl. graph selection
		$data[$fieldNames[$i]] = $logValues["data"][$fieldNames[$i]];
	}
?>
<table class=logContainer cellspacing="0" cellpadding="0"><tr>
<td class=logTd width="50%"><div>
	<canvas id="logCanvas" height="300"></canvas>
</div></td>

<script>
	$(document).ready(function() {
		var ctx = $('#logCanvas');
		var config = {
			type: 'LineWithLine',
			data: {
<?php
	// convert duration into seconds
	$numSeconds = $rusefi->getDurationInSeconds($logValues["duration"]);

	$labels = array();
	$numDataPoints = count(reset($data));
	for ($i = 0; $i < $numDataPoints; $i++) {
		$labels[$i] = "'".round($i * $numSeconds / $numDataPoints, 2) . "s'";
	}
?>
				labels: [<?=implode(",", $labels);?>],
				datasets: [
<?php
	$ldColors = array("white", "red", "green", "yellow");
	$ldYAxisDisplay = array("true", "false", "false", "false");
	$j = 0;
	foreach ($data as $ldName=>$ld) {
?>
				{ 
					data: [<?=implode(",", $ld);?>],
					label: "<?=$ldName;?>",
					yAxisID: "<?=$j;?>",
					borderColor: "<?=$ldColors[$j++];?>",
					fill: false
				},
<?php
	}
?>
				]
			},
			options: {
				legend: { display: true },
				responsive: true,
				maintainAspectRatio: false,
				elements: {
					point:{
						radius: 0
					}
				},
				tooltips: {
					intersect: false,
					axis: 'x'
				},
				scales: {
					xAxes: [{
						display: true,
						gridLines: {
							display: true,
							color: "#3F3F3F"
						},
						scaleLabel: {
							display: true,
							labelString: 'Time'
						},
					}],
					yAxes: [
<?php
	$j = 0;
	foreach ($data as $ldName=>$ld) {
?>
					{
						id: "<?=$j;?>",
						display: <?=$ldYAxisDisplay[$j++];?>,
						ticks: {
							beginAtZero: true
						},
						gridLines: {
							display: true,
							color: "#3F3F3F"
						},
						scaleLabel: {
							display: true,
							labelString: 'Value'
						}
					},
<?php
	}
?>
					]
				}
			}
		};

		// draw a vertical line on the chart under the cursor
		Chart.defaults.LineWithLine = Chart.defaults.line;
		Chart.controllers.LineWithLine = Chart.controllers.line.extend({
		   draw: function(ease) {
			  Chart.controllers.line.prototype.draw.call(this, ease);

			  if (this.chart.tooltip._active && this.chart.tooltip._active.length) {
				 var activePoint = this.chart.tooltip._active[0],
					 ctx = this.chart.ctx,
					 x = activePoint.tooltipPosition().x,
					 topY = this.chart.scales['0'].top,
					 bottomY = this.chart.scales['0'].bottom;

				 // draw line
				 ctx.save();
				 ctx.beginPath();
				 ctx.moveTo(x, topY);
				 ctx.lineTo(x, bottomY);
				 ctx.lineWidth = 2;
				 ctx.strokeStyle = '#07C';
				 ctx.stroke();
				 ctx.restore();
			  }
		   }
		});

		var chart = new Chart(ctx, config);

	});

</script>

<td class=logTd>
<?php
}

foreach ($logGeneral as $lfn=>$lf) {
	putField($lfn, $lf, $logValues[$lfn], true);
}

?>
<table class=logTable border=0><tr>
<?php

	foreach ($logTable as $ln=>$lt) { ?>
		<th class=<?=$ln;?>><?=$lt[0];?></th>
<?php
	}
?>
<tr>
<?php
	foreach ($logTable as $lt) { ?>
<td class=logStart><?php
		foreach ($lt[1] as $lfn=>$lf) {
			putField($lfn, $lf, $logValues[$lfn]);
	} ?>
</td>
<?php
} 
?>
</tr></table>
<?php
if (isset($logValues["data"])) {
?>
</td></tr></table>
<?php
}

if (isset($logValues["notes"])) {
?>

<table border=0>
<tr><th>Timeframe</th><th>Tune</th><th>Diff with prev</th><th>Note</th></tr>
<?php
	$notes = $logValues["notes"];
	// create a fake "unknown tune" if needed
	if (count($notes) == 0) {
		$notes[] = array("time_start"=>0, "time_end"=>isset($numSeconds) ? $numSeconds : 0, "tune_id"=>-1, "tuneComment"=>"");
	}
	$prevNote = null;
	$tuneIds = array();
	foreach ($notes as $i=>$note) {
		$timeFrame = formatLogNoteTime($note["time_start"]) . " - " . formatLogNoteTime($note["time_end"]);
		if ($note["tune_id"] > 0) {
			$diffUrl = $prevNote ? "<td><a href=\"diff.php?msq1=".$note["tune_id"]."&msq2=".$prevNote["tune_id"]."\">diff</a></td>" : "<td>&nbsp;</td>";
			$tuneUrl = "<td><a href=\"view.php?msq=".$note["tune_id"]."\">".$note["uploadDate"]."</a></td>";
			$prevNote = $note;
			$isGrayed = "";
			$tuneIds[] = $note["tune_id"];
		} else {
			$tuneUrl = "<td colspan=2>Unknown tune</td>";
			$diffUrl = "";
			$isGrayed = "class=log-note-grayed";
		}
?>
<tr <?=$isGrayed;?>><td><?=$timeFrame;?></td>
<?=$tuneUrl;?>
<?=$diffUrl;?>
<td><?=$note["tuneComment"];?></td>
</tr>
<?php
	}
	if ($logValues["tune_id"] > 0 && !in_array($logValues["tune_id"], $tuneIds)) {
		global $msqur;
		$tuneParams = $msqur->browse(array("m.id"=>$logValues["tune_id"]), 0, "msq");
		if (count($tuneParams) >= 1) {
			$tuneParams = $tuneParams[0];
		} else {
			$tuneParams = array("uploadDate"=>"tune", "tuneComment"=>"");
		}

?>
<tr class=log-note-grayed><td>User-specified tune:</td>
<td colspan=2><a href="view.php?msq=<?=$logValues["tune_id"];?>"><?=$tuneParams["uploadDate"];?></a></td>
<td><?=$tuneParams["tuneComment"];?></td></tr>
<?php
	}
?>
</table>
<?php
}
?>