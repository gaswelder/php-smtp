# SMTP client

This client speaks SMTP protocol to deliver messages. This makes it possible to:

* send mail from where normal mail functions are not working;
* send the message to many recipients in one call.


## Basic usage example

```php
use gaswelder\smtp\Client;
use gaswelder\smtp\Mail;

$mail = new Mail();
$mail->subject = "Hey there";
$mail->body = ";)";

$returnPath = "bob@example.net";
$destinationPath = "alice@example.net";

$smtp = new Client();
$smtp->connect("smtp.example.net");
$smtp->login("bob", "****");
$smtp->send($mail, $returnPath, $destinationPath);
```


## Mail list example

```php
use gaswelder\smtp\Client;
use gaswelder\smtp\Mail;

$mail = new Mail();
$mail->to = "To whom it might concern";
$mail->subject = "Viagra!";
$mail->body = ";)";

$recipients = [
	"phb@fortune.com",
	"bob@example.net",
	"bill@example.net"
];
$smtp = new Client();
$smtp->connect("mail.net");
$smtp->login("mailer", "****");
$smtp->send($mail, "mailer@mail.net", $recipients);
```


## SSL

The client always uses SSL (`STARTTLS`) before logging in. It's possible to
tweak the SSL parameters by defining the `ssl` option to a map of SSL context
options according to
[http://php.net/manual/en/context.ssl.php](http://php.net/manual/en/context.ssl.php).
For example, to allow self-signed certificates:

```php
$client = new Client([
	'ssl' => [
		'allow_self_signed' => true
	]
]);
```


## Logging

To get log messages (which include client and server messages sent over the
connection) define the `logger` option to be a callable accepting the log line:

```php
$client = new Client([
	'logger' => function($line) {
		fwrite(STDERR, $line."\n");
	}
]);
```


## Installation

	composer require gaswelder/smtp
