<?php

class tp_client
{
	private $err = null;
	private $conn = null;

	private $logfunc = null;

	/**
	 * Connects to the given address.
	 *
	 * @param string $addr
	 * @throws Exception
	 */
	function connect($addr)
	{
		$this->conn = stream_socket_client($addr);
		if (!$this->conn) {
			throw new Exception("couldn't connect to $addr");
		}
	}

	/**
	 * Sets a logger callback.
	 *
	 * @param callable $func
	 */
	function setLogger($func)
	{
		$this->logfunc = $func;
	}

	function startssl() {
		if(!$this->conn) return false;

		stream_context_set_option($this->conn, 'ssl', 'verify_peer', false);

		return stream_socket_enable_crypto($this->conn, true,
			STREAM_CRYPTO_METHOD_TLS_CLIENT);
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
			$this->msg("S: $line");

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
		$this->msg("C: $s");
		return fwrite($this->conn, $s);
	}

	function msg($s) {
		$f = $this->logfunc;
		if(!$f) return;
		$f($s);
	}
}

?>
