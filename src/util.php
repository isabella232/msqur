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

/**
 * @brief Check that multiple keys are in an array.
 * @param $array The array to check for $keys
 * @param $keys They keys to check for in the $array
 * @returns TRUE if each key was found in the array, FALSE otherwise.
 */
function array_keys_exist(array &$array, ...$keys)
{
	if (!is_array($array)) return FALSE;
	
	foreach ($keys as $k)
	{
		if (!array_key_exists($k, $array)) return FALSE;
	}
	
	return TRUE;
}

/**
 * @brief Parse query string in a safe way.
 * @param $s The parameter requested
 * @returns An output safe string(?) or null
 */
function parseQueryString($s)
{
	$ret = null;
	if (isset($_GET[$s]))
	{
		$ret = $_GET[$s];
		
		if (!is_array($ret))
		{
			$ret = htmlspecialchars($ret);
			if (strlen($ret) == 0) $ret = null;
		}
	}
	
	return $ret;
}

function rusefi_file($f)
{
	global $test;
	return isset($test) ? $test->file($f) : @file($f);
}

function rusefi_file_get_contents($f)
{
	global $test;
	return isset($test) ? $test->file_get_contents($f) : file_get_contents($f);
}

function rusefi_file_put_contents($f, $c)
{
	global $test;
	return isset($test) ? $test->file_put_contents($f, $c) : file_put_contents($f, $c);
}

?>
