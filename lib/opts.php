<?php

function _usage($progname, $defs, $synopsis = null)
{
	if(!$synopsis) {
		$synopsis = "$progname [opts] [args]";
	}
	fwrite(STDERR, "Usage: $synopsis\n");
	foreach($defs as $def) {
		$s = '-'.$def[0];
		if($def[1] != 'bool') {
			$s .= ' '.$def[1];
		}
		fwrite(STDERR, "	$s	$def[3]\n");
	}
}

function err($msg) {
	fwrite(STDERR, $msg."\n");
}

/*
 * Parses command line arguments.
 */
function parse_args($args, $defs, $synopsis = null)
{
	$progname = array_shift( $args );

	$vals = array(
		'bool' => false,
		'str[]' => array(),
		'str' => ''
	);

	/*
	 * Reorganize opt definitions into a map
	 */
	$opts = array();
	foreach($defs as $i => $def) {
		if(count($def) != 4) {
			err("parse_args: wrong format at index $i");
			_usage($progname, $defs, $synopsis);
			exit(1);
		}
		list($key, $type, $ref, $desc) = $def;
		if(!in_array($type, array_keys($vals))) {
			err("parse_args: unknown opt key '$type'");
			_usage($progname, $defs, $synopsis);
			exit(1);
		}
		$opts[$key] = $def;
	}

	while(!empty($args) && $args[0][0] == '-' && strlen($args[0]) > 1)
	{
		$arg = array_shift($args);
		if($arg == '--') break;

		$n = strlen($arg);
		for($i = 1; $i < $n; $i++) {
			$f = $arg[$i];
			if(!isset($opts[$f])) {
				err("Unknown flag: $f");
				_usage($progname, $defs, $synopsis);
				exit(1);
			}

			$spec = $opts[$f];

			if($spec[1] == 'bool' ) {
				$spec[2] = true;
				continue;
			}

			if($i != $n-1) {
				err("$f flag requires an argument");
				_usage($progname, $defs, $synopsis);
				exit(1);
			}

			$arg = array_shift($args);
			if(!$arg) {
				err("$f flag requires an argument");
				_usage($progname, $defs, $synopsis);
				exit(1);
			}

			if($spec[1] == 'str[]') {
				$spec[2][] = $arg;
			}
			else {
				$spec[2] = $arg;
			}
		}
	}

	return $args;
}

?>
