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

"use strict";

var msqur = angular.module('msqur', []);

msqur.controller('BrowseController', function ($scope) {
	return true;
});

msqur.controller('SearchController', function ($scope) {
	
});

function ham_burger() {
	var x = document.getElementById("navigation");
	var y = document.getElementById("loginstatus");
	if (x.className === "topnav") {
	    x.className += " responsive";
	} else {
	    x.className = "topnav";
	}
	if (y.className === "loginstatus") {
		y.className += " responsive";
	} else {
		y.className = "loginstatus";
	}

  } 

$(document).ready(function() {
    $('#browseMsqResults')
		.bind('dynatable:init', function(e, dynatable) {
		    dynatable.sorts.add('uploadedMsq', -1);
		})
    	.dynatable( {
			dataset: {
      			perPageDefault: 100
    		}
  		});

    $('#browseLogResults')
		.bind('dynatable:init', function(e, dynatable) {
		    dynatable.sorts.add('uploadedLog', -1);
		})
    	.dynatable( {
			dataset: {
      			perPageDefault: 100
    		}
  		});

	$('.deleteLink').unbind('click').bind('click', function () {
        return confirm('You are about to ' + $(this).attr('action') + ' the tune: "' + $(this).attr('info') + '".\r\n\r\nAre you sure?');
    });

    $('.tuneComment').tooltip({
        content:function() {
            return this.getAttribute("title");
        }
    });

	$('.diff-tune-check').on('change', function() {
		var numChecked = $('.diff-tune-check:checked').length;
		$('#diffTuneButton').attr("disabled", numChecked != 2);
	});

	$('#diffTuneButton').click(function(e) {
		var checked = $('.diff-tune-check:checked');
		if (checked.length != 2)
			return;
		window.location.href = "diff.php?msq1=" + checked[0].value + "&msq2=" + checked[1].value;
	});

});

//2D charts
function doChart(event, ui) {
	var that = ui.newPanel;

	processChart(that);
}

var chart3dIdx = 0;
function add3dChart(that) {
	// disable click on the table
	var tbl = that.find('div.table');
	tbl.prop("onclick", null).off("click");
	
	// add html elements
	var chartId = 'chart3dbut' + (++chart3dIdx);
	var $but = $('<label class="tsChart3dButton" for="' + chartId + '">3D</label><input class="tsChart3dButton" type="checkbox" id="' + chartId + '" />');
	var $container = $('<div class="chart3d"></div>');
    $but.insertBefore(tbl);
    $container.insertBefore(tbl);
	$('#' + chartId).button();

	$('#' + chartId).click(function() {
		$but.toggleClass('checked');
		if (this.checked) {
			// https://github.com/visjs/vis-graph3d
			var options = {
    			width:  tbl.width() + 20 + 'px',
        		height: tbl.height() - 10 + 'px',
        		style: 'surface',
        		showPerspective: true,
        		showGrid: true,
        		tooltip: true,
        		showShadow: false,
        		keepAspectRatio: false,
        		verticalRatio: 1.0,
        		yCenter: '40%'
      		};

  			// fill the data
  			var data = new vis.DataSet();
			var tbldata = tbl3data($(that[0]));
  			var counter = 0;
  			for (const d of tbldata) {
				data.add({id:counter++, x:d.x, y:d.y, z:d.z, style:d.z});
  			}
  			tbl.hide();
  			$container.show();
  			var graph = new vis.Graph3d($container[0], data, options);
  		} else {
  			tbl.show();
  			$container.hide();
  		}
	});
}

function processChart(that) {
	var is3D = that.find('div.table').length != 0;
	// sort ASC for 2D and DESC for 3D tables
	$('table').tablesorter({sortList: [[0, is3D ? 1 : 0]] });

	if (that.find('tbody').length == 0) return; //do nothing if panel is closing
	// add 3d optional view for 3d tables
	if (is3D) {	
		add3dChart(that);
	    return;
	}
	
	if (that.find('tbody').length == 0 || is3D) return; //do nothing if panel is closing, or if 3d table
	
	//Find data
	var tbl = that.find('tbody').get(0);
	var data = tbl2data($(tbl));
	
	var ctx = that.find('canvas').get(0).getContext("2d");
	
	var config = {
		type: 'line',
		data: data,
		options: {
			legend: { display: false },
			scales: {
				xAxes: [{
					type: 'linear',
				}]
			},
		},
	};

	var chart = new Chart(ctx, config);
}

function colorizeTables() 
{
	$('table.msq tbody').each(function(i) { 
		colorTable($(this), true); 
	});
}

function tbl2data(tbl)
{
	var rows = tbl.find('tr');
	var lbls = [];
	var cells = [];
	
	rows.each(function(i) {
		var that = $(this); //ick
		
		// round the x-axis values
		that.find('th,td').each(function(i) {
			if ($(this).attr("digits")) {
				var v = parseFloat(this.textContent);
				this.textContent = v.toFixed($(this).attr("digits"));
			}
		});

		//.html() gets first element in set, .text() all matched elements
		var xval = parseFloat(that.find('th').text());
		var yval = parseFloat(that.find('td').text());
		lbls.push(xval);
		cells.push({'x': xval, 'y': yval});
	});
	
	var data = {
		labels: lbls,
		datasets: [{
			label: "test", 
			data: cells,
			borderColor: 'blue',
		}],
	};
	
	return data;
}

function tbl3data(tbl)
{
	var xAxis = [];
	var cols = tbl.find('thead tr th');
	cols.each(function(i) {
		if (this.textContent)
			xAxis.push(parseFloat(this.textContent));
	});
	var rows = tbl.find('tbody tr');
	var data = [];
	
	rows.each(function(i) {
		var that = $(this); //ick

		// round the x-axis values
		that.find('th,td').each(function(i) {
			if ($(this).attr("digits")) {
				var v = parseFloat(this.textContent);
				this.textContent = v.toFixed($(this).attr("digits"));
				
			}
		});

		//.html() gets first element in set, .text() all matched elements
		var yval = parseFloat(that.find('th').text());
		var cols = that.find('td');
		cols.each(function(i) {
			var zval = parseFloat(this.textContent);
			var d = {'x':xAxis[i], 'y': yval, 'z': zval};
			data.push(d);
		});
	});
	
	return data;
}

function normalizeTable(table)
{
	var min = Number.MAX_SAFE_INTEGER;
	var max = Number.MIN_SAFE_INTEGER;
	var nmin = 5;
	var nmax = 250;
	
	//Find min and max
	table.find('td').each(function(i) {
		var v = parseFloat(this.textContent);
		if (v < min) min = v;
		if (v > max) max = v;
	});
	
	//Precalculate some stuff
	var a = (nmax - nmin) / (max - min);
	var b = nmin - (a * min);
	
	//apply normalization
	table.find('td').each(function(i) {
		var v = parseFloat(this.textContent);
		var r = Math.round(a * v + b);
		this.textContent = "" + r;
	});
}

function resetTable(table)
{
	//TODO Need to store old value (and new one if I care about client end)
}

function clearTableColor(table)
{
	table.find('td').each(function(i) {
		this.style.backgroundColor = '';
	});
}

function colorTable(table, reverseColor)
{//reverseColor could be an override, value passed in is currently ignored
	var min = Number.MAX_SAFE_INTEGER;
	var max = Number.MIN_SAFE_INTEGER;

	var schemeType = 0;	// color scheme = "TS"
	
	//Find min and max
	table.find('td').each(function(i) {
		var v = parseFloat(this.textContent);
		if (v < min) min = v;
		if (v > max) max = v;
	});
	
	if (table.attr('hot') == 'ascending')
		reverseColor = true;
	if (table.attr('hot') == 'descending')
		reverseColor = false;
	
	var range = (max - min);
	//console.debug("Range: " + range);
	var r = 0, g = 0, b = 0, percent = 0, intensity = 0.6;

	var kk = 0;
	table.find('td').each(function(i) {
		var v = parseFloat(this.textContent);
		percent = range == 0 ? 0 : (v - min) / range;

		if (reverseColor)
			percent = 1.0 - percent;
		
		//MegaTune coloring scheme
		if (schemeType == 1) {
			if (percent < 0.33)
			{
				r = 1.0;
				g = Math.min(1.0, (percent * 3));
				b = 0.0;
			}
			else if (percent < 0.66)
			{
				r = Math.min(1.0, ((0.66 - percent) * 3));
				g = 1.0;
				b = 0.0;
			}
			else
			{
				r = 0.0;
				g = Math.min(1.0, ((1.0 - percent) * 3));
				b = 1.0 - g;
			}

			r = Math.round((r * intensity + (1.0 - intensity)) * 255);
			g = Math.round((g * intensity + (1.0 - intensity)) * 255);
			b = Math.round((b * intensity + (1.0 - intensity)) * 255);
		}
		else {
			var ip = 1 - percent;
			if (ip < 0.5)
			{
				r = 0.53;
				g = r + (1 - r) * ip * 2;
				b = r + (1 - r) * (0.5 - ip) * 2;
			}
			else
			{
				b = 0.53;
				r = b + (1 - b) * (ip - 0.5) * 2;
				g = b + (1 - b) * (1 - ip) * 2;
			}
			r = 255 * r;
			g = 255 * g;
			b = 255 * b;
		}
		
		this.style.backgroundColor = 'rgb(' + r + ',' + g + ',' + b + ')';

		// also round the value
		if ($(this).attr("digits"))
			this.textContent = v.toFixed($(this).attr("digits"));
	});
}

$(function() {
	$('div#upload').dialog({
		modal: true,
		autoOpen: false,
		title: "Upload Tune or Log Files",
		width: "712px",
		buttons: hideUpload ? [
			{ text: "Cancel", click: function() { $(this).dialog('close'); } }
		] :
		[
			{ id: "dialogUpload", text: "Upload", click: uploadClick, },
			{ text: "Cancel", click: function() { $(this).dialog('close'); } }
		],
		dialogClass: 'upload-dialog'
	});
	
	enableUploadButton(false);
	$('#btnUpload').click(function(e) {
		if (window.File && window.FileReader && window.FileList && window.Blob)
		{
			$('div#upload').dialog('open');
		} else {
			alert('The File APIs are not fully supported in this browser.');
			//TODO no ajax file upload
		}
	});
	
	$('#settingsIcon').click(function(e) {
		$('#settingsPanel').toggle();
	});
	
	$('select#firmware').change(function(){
		getFirmwareVersions($(this).children(":selected").html());
	});
	
	function getFirmwareVersions(fw) {
		$.ajax({
			url: "api.php?req=",
		}).done(function( html ) {
			$( "#results" ).append( html );
		});
	};
	
	var accordionOptions = {
		animate: false,
		active: false, collapsible: true, //to avoid hooking 'create' to make the first graph render
		heightStyle: "content",
		activate: doChart
	};

	$('div#tabList').tabs();
	
	$('div#tab_tables').accordion(accordionOptions);
	$('div#tab_curves').accordion(accordionOptions);
	$('div#tab_constants').tooltip();
	
	Chart.defaults.global.animation = false;
	//Chart.defaults.global.responsive = true;
	
	$('input#colorizeData').change(function () {
		if (this.checked)
			$('table.msq tbody').each(function(i) { colorTable($(this), true); });
		else
			$('table.msq tbody').each(function(i) { clearTableColor($(this)); });
	});
	
	//default
	$('input#colorizeData').prop('checked', true);
	$('table.msq tbody').each(function(i) { colorTable($(this), true); });
	
	$('input#normalizeData').change(function () {
		if (this.checked)
			$('table.msq.ve').each(function(i) { normalizeTable($(this)); });
		else
			$('table.msq.ve').each(function(i) { resetTable($(this)); });
	});

	function uploadAdd(e)
	{
		e.stopPropagation();
		e.preventDefault();
		
		onUpload();
		
		var files = e.target.files || e.dataTransfer.files
		//TODO type check
		var output = [];
		output.push('<small>');
		for (var i = 0, f; f = files[i]; ++i)
		{
			var ft = getFileType(f.name);
			output.push('<li><strong>', escape(f.name), '</strong> (', ft || 'n/a', ') - ',
			f.size, ' bytes',
			f.lastModifiedDate ? ', last modified: ' + f.lastModifiedDate.toLocaleDateString() : '',
			'</li>');
			if (ft == "Tune")
			{
				startGettingEngineInfoFromTuneFile(f);
			}
			else if (ft.substring(0, 3) == "Log")
			{
				startGettingInfoFromLogFile(f, ft);
			}
		}
		output.push('</small>');
		$('output#fileList').html('<ul>' + output.join('') + '</ul>');
	}

	function uploadDragOver(e)
	{
		e.stopPropagation();
		e.preventDefault();
		e.dataTransfer.dropEffect = 'copy';
	}
	
	function simpleValidation(s)
	{
		if (typeof s === 'string' && s.length > 0)
			return true;
		else
			return false;
	}
	
	function uploadClick()
	{
		enableUploadButton(false);
		$('div#upload form').submit();
	}
	
	function searchClick()
	{
		$('form#search').submit();
	}

	//////////////////////////////////////////////////////////////////////////////
	// [andreika]: rusEFI scripts

	function onUpload()
	{
		enableUploadButton(false);
		$('#engineInfo').hide();
		$('#logInfo').hide();
		$('#tuneInfo').hide();
		$('output#processing').html("");
	}

	function getFileType(filename)
	{
		var ext = filename.split('.').pop();
		if (ext == "msq")
			return "Tune";
		else if (ext == "msl")
			return "LogText";
		else if (ext == "mlg")
			return "LogBinary";
		return null;
	}

	function startGettingEngineInfoFromTuneFile(file)
	{
		var fr = new FileReader();
		fr.onload = onFileRead;
        fr.readAsText(file);

		function onFileRead()
		{
			getEngineInfoFromTuneFile(fr.result);
		}
	}

	function getEngineInfoFromTuneFile(data)
	{
		var eInfo = {
			// "msqur_field_name": ["TS_tune_file_constant_name"]
			"name": ["vehicleName"],
			"make": ["engineMake"],
			"code": ["engineCode"],
			"displacement": ["displacement"],
			"compression": ["compressionRatio"],
			"induction": ["isForcedInduction"]
		};
		for (const field in eInfo)
		{
			var re = new RegExp('<constant.*?name="' + eInfo[field][0] + '"[^>]*>([^<]+)</constant>', 'g');
			var value = "", val = re.exec(data);
			if (val)
			{
				// strip quotes
				var value = val[1].replace(/['"]+/g, '');
				// round floats
				if (!isNaN(value))
				{
					value = parseFloat(parseFloat(value).toFixed(2));
				}
			}
			// store the value
			eInfo[field].push(value);
			var f = $('input#' + field);
			// highlight absent values
			f.css({ "border": (value == "") ? '#FF0000 1px solid' : '#707070 1px solid'});
			f.val(value == "" ? "?" : value);
		}
		$('#engineInfo').show();
		// center dialog
		$("div#upload").dialog("option", "position", {my: "center", at: "center", of: window});
		
		preprocessEngineInfo(eInfo);
	}

	function preprocessEngineInfo(eInfo)
	{
		//alert(JSON.stringify(eInfo, null, 4));
		$.get("api.php?method=preprocessTune", { einfo: eInfo }, function(data) {
			// output the text result
			$('output#processing').html(data.preprocessTune.text);

			var status = data.preprocessTune.status;
			// change the message color
			var clr = "red";
			if (status == "ok")
				clr = "green";
			else if (status == "warn")
				clr = "darkorange";
			$('output#processing').css("color", clr);
			
			// enable upload button if the status is good enough
			enableUploadButton(status == "ok" || status == "warn");

		}, "json");

	}

	////////////////////////////////////////////////////////////////
	var status = "";
	function clearStatus()
	{
		status = "";
		printStatus("");
	}

	function addStatus(st)
	{
		status += st + "<br />";
	}

	function printStatus(st = "")
	{
		$('output#processing').html(status + st);
	}

	function startGettingInfoFromLogFile(file, ft)
	{
		// This may take a while...
		$('output#processing').css("color", "darkorange");
		clearStatus();
		addStatus("Reading...");
		printStatus();

		var fr = new FileReader();
		fr.onload = onFileRead;
        if (ft == "LogText")
        	fr.readAsText(file);
		else
			fr.readAsArrayBuffer(file);

		function onFileRead()
		{
			getInfoFromLogFile(fr.result, ft);
		}
		// todo: add Log real-time parsing?
		enableUploadButton(true);
	}

	function getInfoFromLogFile(data, ft)
	{
		const maxPreprocessFileSize = 32*1024*1024;

		// we cannot preview too large files...
		var fileSize = data.byteLength;
		if (fileSize > maxPreprocessFileSize) {
			data = data.slice(0, maxPreprocessFileSize);
		}

		addStatus("Compressing...");
		printStatus();
		var output = pako.deflate(data);
		var content = new Blob([output], { type: 'application/zip' });
		var fd = new FormData();
		fd.append("log", content);

		printStatus("Sending...");

		$.ajax({
			url: "api.php?method=preprocessLog&type=" + ft + "&fullSize=" + fileSize,
			type: "POST",
			data: fd,
    		xhr: function() {
		        var xhr = new window.XMLHttpRequest();
		        xhr.upload.addEventListener("progress", function(evt) {
		            if (evt.lengthComputable) {
		                var percentComplete = Math.round(100.0 * evt.loaded / evt.total);
		                if (percentComplete > 0 && percentComplete < 100)
		                	printStatus("Sending... " + percentComplete + "%");
						else if (percentComplete >= 100) {
							addStatus("Analyzing...");
							printStatus();
						}
		            }
		       }, false);
		       return xhr;
		    },
    		processData: false,
			contentType: false,
			success: function(response) {
				var data;
				var status;
				var text;
				try {
					data = JSON.parse(response);
					status = data.preprocessLog.status;
					text = data.preprocessLog.text;
				} catch (e) {
					text = "There was an error while processing. Cannot read the processed results!";
					console.log("Error", e.stack);
					console.log("Error", e.name);
					console.log("Error", e.message);
					status = "deny";
				}
				// output the text result
				clearStatus();
				printStatus(text);
				
				if (status != "deny") {
					// output the fields
					$('output#logFields').html(data.preprocessLog.logFields);
					// center dialog
					$("div#upload").dialog("option", "position", {my: "center", at: "center", of: window});
					// display the table
					$('#logInfo').show();

					// todo: set a selected option from the log file data
					$('#tuneInfo').show();
				}

				// change the message color
				var clr = "red";
				if (status == "ok")
					clr = "green";
				else if (status == "warn")
					clr = "darkorange";
				$('output#processing').css("color", clr);
		
				// enable upload button if the status is good enough
				enableUploadButton(status == "ok" || status == "warn");
	       },
	       error: function(jqXHR, textStatus, errorMessage) {
	           console.log(errorMessage);
	       }
	    });

		//alert(data.length);
		//var array = new UInt8Array(data);
		//if (array[0] 
		//alert(JSON.stringify(array, null, 4));
		//alert(JSON.stringify(data[0], null, 4));
	}



	function enableUploadButton(isEnabled)
	{
		$('#dialogUpload').attr("disabled", !isEnabled).css("opacity", isEnabled ? 1.0 : 0.5);
	}
	

	
	$('input#fileSelect').change(uploadAdd);
	var dropZone = document.getElementById('fileDropZone');
	if (dropZone != null)
	{
		dropZone.addEventListener('dragover', uploadDragOver);
		dropZone.addEventListener('drop', uploadAdd);
	}

	$('.tsSettingsSelect').change(function() { 
	  	var options = $('#' + $(this).attr('id') + ' option');
	  	var optionsList = new Array();
		options.each(function() {
			optionsList.push($(this).val());
		});

	  	var url = new URL(window.location.href);
		var settings = url.searchParams.get('settings');
		settings = settings ? settings.split("|") : [];
		var settingsFiltered = new Array();
		// remove all settings included in this 
		settings.forEach(function(value, idx, object) {
			if (optionsList.indexOf(value) < 0)
				settingsFiltered.push(value);
		});
		
		settingsFiltered.push($(this).val());
		settings = settingsFiltered.join("|");
		url.searchParams.set('settings', settings);
		window.location.href = url.href;
	});
});
