<?php
require "src/_.php";

exit(main($argv));

function usage() {
	fwrite(STDERR, "Usage: post [-a <server>] [-s <subj>] <from> <to> < data\n");
}

function main( $args )
{
	$progname = array_shift( $args );

	$subj = "No subject";
	$server = "tcp://localhost:25";

	while( !empty( $args ) && $args[0][0] == '-' ) {
		$f = array_shift( $args );
		switch( $f ) {
			case "-a":
				$server = "tcp://" . array_shift( $args );
				break;
			case "-s":
				$subj = array_shift( $args );
				break;
			default:
				fwrite(STDERR, "Unknown flag: $f\n");
				return 1;
		}
	}

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
