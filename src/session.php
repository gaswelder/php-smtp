<?php

class response
{
	public $code;
	public $lines = [];
}

class Client
{
	/**
	 * Connection to the server.
	 *
	 * @var resource
	 */
	private $conn = null;

	/**
	 * List of EHLO extensions, as associative
	 * array {name => options}.
	 *
	 * @var array
	 */
	private $extensions = [];

	/**
	 * Logger function
	 *
	 * @var callable
	 */
	private $logfunc = null;

	/**
	 * @var array $options Associative array of ssl context options (http://php.net/manual/en/context.ssl.php)
	 */
	private $ssl_options = [];

	function __construct($options = [])
	{
		$this->ssl_options = $options['ssl'] ?? [];
		$this->logfunc = $options['logger'] ?? null;
	}

	/**
	 * Connects to the server at the given address.
	 *
	 * @param string $addr Address in form "tcp://hostname:port".
	 * @throws Exception
	 */
	function connect($addr)
	{
		$this->log("Connecting to $addr");
		/*
		 * Often the real mail server's address is different than
		 * the given DNS name, so we do some digging.
		 */
		$url = parse_url($addr);
		$host = $this->resolve($url['host']);
		$this->log("Resolved $url[host] to $host\n");
		$resolvedAddr = "$url[scheme]://$host:$url[port]";

		/*
		 * Connect to the server and start a session
		 */
		$this->conn = stream_socket_client($resolvedAddr);
		if (!$this->conn) {
			throw new Exception("couldn't connect to $addr");
		}
		$this->expect(220);

		$this->ehlo();
		$this->starttls();
	}

	private function resolve($addr)
	{
		$info = dns_get_record($addr, DNS_MX|DNS_CNAME);
		if(!$info) return $addr;
		foreach($info as $i) {
			return $i['target'];
		}
	}

	/**
	 * Performs authentication on the server.
	 *
	 * @param string $user
	 * @param string $pass
	 * @throws Exception
	 */
	function login($user, $pass)
	{
		if(!isset($this->extensions['AUTH'])) {
			throw new Exception("server doesn't support AUTH extension");
		}

		if(!in_array('PLAIN', $this->extensions['AUTH'])) {
			throw new Exception("Server doesn't support AUTH PLAIN");
		}

		$auth = base64_encode(chr(0).$user.chr(0).$pass);

		$this->writeLine("AUTH PLAIN $auth");
		$this->expect(235);
	}
	
	/**
	 * Upgrades current connection to SSL.
	 *
	 * @throws Exception
	 */
	private function starttls()
	{
		if(!isset($this->extensions['STARTTLS'])) {
			throw new Exception("server doesn't support STARTTLS");
		}

		$this->writeLine("STARTTLS");
		$this->expect(220);

		foreach ($this->ssl_options as $key => $value) {
			$ok = stream_context_set_option($this->conn, 'ssl', $key, $value);
			if (!$ok) {
				throw new Exception("failed to set ssl option '$key'");
			}
		}
		$ok = stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		if (!$ok) {
			$msg = "failed to enable crypto";
			$e = error_get_last();
			if ($e) {
				$msg .= ': '.$e['message'];
			}
			throw new Exception($msg);
		}

		$this->ehlo();
	}

	/*
	 * Send an EHLO greeting and parse the server's
	 * list of extensions
	 */
	private function ehlo()
	{
		$this->writeLine("EHLO " . php_uname('n'));
		$response = $this->expect(250);

		/*
		 * Parse the list of EHLO extensions
		 */
		$lines = $response->lines;
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

	/**
	 * Sends a message.
	 *
	 * @param Mail $mail
	 * @param string $from
	 * @param array $to List of recipient addresses
	 */
	function send(Mail $mail, $from, $to)
	{
		$data = $mail->compose();
		/*
		 * Prepend a dot to each line starting with a dot
		 * (https://tools.ietf.org/html/rfc5321#section-4.5.2)
		 */
		$data = str_replace("\r\n.", "\r\n..", $data);

		$this->writeLine("MAIL FROM:<$from>");
		$this->expect(250);

		foreach ($to as $des) {
			$this->writeLine("RCPT TO:<$des>");
			$this->expect(250);
		}

		$this->writeLine("DATA");
		$this->expect(354);

		$this->writeLine( $data );
		$this->writeLine( "." );
		$this->expect(250);
	}

	/**
	 * Disconnects from the server.
	 */
	function close()
	{
		if (!$this->conn) {
			return;
		}
		$this->writeLine("QUIT");
		$this->expect(221);
		fclose($this->conn);
		$this->conn = null;
	}

	function __destruct()
	{
		$this->close();
	}

	private function expect($code)
	{
		$response = $this->getResponse();
		if ($response->code != $code) {
			throw new Exception("expected $code, got $response->code ($response->lines[0])");
		}
		return $response;
	}

	private function getResponse()
	{
		$response = new response();

		while(1) {
			$line = fgets($this->conn);
			$this->log("S: $line");

			$response->code = substr($line, 0, 3);
			$sep = $line[3];
			$line = substr($line, 4);

			$response->lines[] = $line;

			if($sep == ' ') {
				break;
			}

			if ($sep != '-') {
				throw new Exception("malformed multiline response: ".implode('; ', $response->lines));
			}
		}
		return $response;
	}

	private function writeLine($line)
	{
		$this->log("C: $line");
		return fwrite($this->conn, $line."\r\n");
	}

	private function log($s) {
		$f = $this->logfunc;
		if (!$f) return;
		$f(rtrim($s, "\r\n"));
	}
}
