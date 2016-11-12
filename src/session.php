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
	 * If $userpass is given, it must be an array with username and
	 * password.
	 */
	function __construct($addr, $userpass = null)
	{
		/*
		 * Often the real mail server's address is different than
		 * the given DNS name, so we do some digging.
		 */
		$url = parse_url($addr);
		$host = $this->resolve($url['host']);
		fwrite(STDERR, "$url[host] -> $host\n");
		$addr = "$url[scheme]://$host:$url[port]";

		$c = new tp_client($addr);
		$c->expect(220);

		$this->c = $c;
		$this->ehlo();

		if(!$this->starttls()) {
			return;
		}

		if($userpass) {
			list($user, $pass) = $userpass;
			$this->auth($user, $pass);
		}
	}

	private function resolve($addr)
	{
		$info = dns_get_record($addr, DNS_MX|DNS_CNAME);
		if(!$info) return $addr;
		foreach($info as $i) {
			return $i['target'];
		}
	}

	private function starttls()
	{
		if(!isset($this->extensions['STARTTLS'])) {
			trigger_error("Server doesn't support STARTTLS");
			return false;
		}

		$c = $this->c;
		$c->writeLine("STARTTLS");
		if(!$c->expect(220)) {
			return false;
		}

		if(!$c->startssl()) {
			return false;
		}

		$this->ehlo();

		return true;
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

	private function auth($user, $pass)
	{
		if(!isset($this->extensions['AUTH'])) {
			trigger_error("AUTH extension not supported");
			return false;
		}

		if(!in_array('PLAIN', $this->extensions['AUTH'])) {
			trigger_error("Server doesn't support AUTH PLAIN");
			return false;
		}

		$auth = base64_encode(chr(0).$user.chr(0).$pass);

		$c = $this->c;
		$c->writeLine("AUTH PLAIN $auth");
		return $c->expect(235);
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

?>
