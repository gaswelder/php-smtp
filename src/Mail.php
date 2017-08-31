<?php

class Mail
{
	public $date;
	public $from;
	public $to;
	public $subject;
	public $body = '';

	function __construct()
	{
		$this->date = date('r');
	}

	function compose()
	{
		$headers = array_filter([
			"Date" => $this->date,
			"Subject" => $this->subject,
			"From" => $this->from,
			"To" => $this->to
		]);

		$data = "";
		foreach ($headers as $n => $v) {
			$data .= "$n: $v\r\n";
		}
		$data .= "\r\n";
		$data .= $text."\r\n";
		return $data;
	}
}
