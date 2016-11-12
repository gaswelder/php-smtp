<?php
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

class smtp_session
{
	private $c;
	private $extensions;

	function __construct($addr)
	{
		$this->c = new tp_client($addr);
		$this->c->expect(220);

		$this->c->writeLine("HELO %s", php_uname('n'));
		$this->c->expect(250, $lines);
	}

	function close()
	{
		$this->c->writeLine("QUIT");
		$this->c->expect(221);
		$this->c->close();
		$this->c = null;
	}

	function __destruct()
	{
		if($this->c) $this->close();
	}

	function err()
	{
		if(!$this->c) return null;
		return $this->c->err();
	}

	function send_mail($from, $to, $data)
	{
		$c = $this->c;

		$c->writeLine("MAIL FROM:<$from>");
		$c->expect(250);

		$c->writeLine("RCPT TO:<$to>");
		$c->expect(250);

		$c->writeLine("DATA");
		$c->expect(354);

		$c->writeLine( $data );
		$c->writeLine( "." );
		$c->expect(250);
	}
}

class tp_client
{
	private $err = null;
	private $conn = null;

	function __construct($addr) {
		$this->conn = stream_socket_client($addr);
	}

	function close() {
		fclose($this->conn);
	}

	function err() {
		return $this->err;
	}

	function expect($code, &$lines = null)
	{
		if($this->err) {
			return false;
		}

		$rcode = $this->getResponse($lines);
		if($this->err) return false;

		if($rcode != $code) {
			$this->err = "Expected $code, got $rcode ($lines[0])";
			return false;
		}
		return true;
	}

	private function getResponse(&$lines)
	{
		$rcode = 0;
		$lines = array();

		while(1) {
			$line = fgets($this->conn);
			fwrite(STDERR, "S: $line");

			$code = substr($line, 0, 3);
			$sep = $line[3];
			$line = substr($line, 4);

			if(!$rcode) {
				$rcode = $code;
			}
			else if($code != $rcode) {
				$this->err = "Incoherent codes in multiline response";
				return 0;
			}

			$lines[] = $line;

			if($sep == ' ') {
				break;
			}

			if($sep != '-') {
				$this->err = "Malformed multiline response";
				return 0;
			}
		}
		return $rcode;
	}

	function writeLine($fmt, $args___ = null)
	{
		if($this->err) {
			return false;
		}

		$args = func_get_args();
		$line = call_user_func_array('sprintf', $args);

		return $this->write($line."\r\n");
	}

	function write($s) {
		if($this->err) {
			return false;
		}
		fwrite(STDERR, "C: $s");
		return fwrite($this->conn, $s);
	}
}

?>
