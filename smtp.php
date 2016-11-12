<?php

class smtp_session
{
	/*
	 * A tp_client instance
	 */
	private $c;

	/*
	 * List of EHLO extensions, as associative
	 * array {name => options}.
	 */
	private $extensions;

	/*
	 * Connect to the server and send HELO/EHLO.
	 * $addr has URL form, for example "tcp://example.net:25".
	 */
	function __construct($addr)
	{
		$c = new tp_client($addr);
		$c->expect(220);

		$this->c = $c;
		$this->ehlo();
	}

	/*
	 * Send an EHLO greeting and parse the server's
	 * list of extensions
	 */
	private function ehlo()
	{
		$c = $this->c;
		$c->writeLine("EHLO %s", php_uname('n'));
		$c->expect(250, $lines);

		if($c->err()) return;

		/*
		 * Parse the list of EHLO extensions
		 */
		array_shift($lines);
		$ext = array();
		foreach($lines as $line) {
			$line = trim($line);
			$parts = explode(' ', $line);
			if(count($parts) == 1) {
				$ext[$line] = true;
			}
			else {
				$name = array_shift($parts);
				$ext[$name] = $parts;
			}
		}
		$this->extensions = $ext;
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
		if(!$this->conn) {
			$this->err = "Couldn't connect to $addr";
		}
	}

	function close() {
		if($this->conn) fclose($this->conn);
	}

	function err() {
		return $this->err;
	}

	function expect($code, &$lines = null)
	{
		if($this->err) {
			return false;
		}

		$rcode = $this->get_response($lines);
		if($this->err) return false;

		if($rcode != $code) {
			$this->err = "Expected $code, got $rcode ($lines[0])";
			return false;
		}
		return true;
	}

	private function get_response(&$lines)
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
