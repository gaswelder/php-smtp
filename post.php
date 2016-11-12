<?php
require "src/_.php";
require "lib/opts.php";

exit(main($argv));

use function vacuum\parse_args;

function main( $args )
{
	$server = "tcp://localhost:25";
	$subj = "No subject";

	$syn = "post [-a <server>] [-s <subj>] <from> <to> < data";
	$opts = array(
		array('a', 'str', &$server, 'server address'),
		array('s', 'str', &$subj, 'mail subject')
	);
	$args = parse_args($args, $opts, $syn);

	$from = array_shift( $args );
	$to = array_shift( $args );


	if( !$from || !$to ) {
		usage();
		return 1;
	}

	if( !empty( $args ) ) {
		fwrite(STDERR, "Unexpected argument: $args[0]\n");
		return 1;
	}

	$h = array(
		"Date" => date( 'r' ),
		"Subject" => $subj,
		"From" => $from,
		"To" => $to
	);

	$data = "";
	foreach( $h as $n => $v ) {
		$data .= "$n: $v\r\n";
	}
	$data .= "\r\n";

	while( 1 ) {
		$line = fgets( STDIN );
		if( $line === false ) {
			break;
		}
		$data .= $line;
	}

	$err = send( $server, $from, $to, $data );
	if( $err ) {
		fwrite(STDERR, $err . "\n");
		return 1;
	}
	return 0;
}

function send( $server, $from, $to, $data )
{
	$c = new smtp_session($server);
	$c->send_mail($from, $to, $data);

	$err = $c->err();
	$c->close();

	return $err;
}

?>
