<?php

// $ composer require react/http react/socket # install example using Composer
// $ php example.php # run example on command line, requires no additional web server

require __DIR__ . '/../vendor/autoload.php';

class WebHookServer {
	public function __construct ($socket) {
		$this->socket = $socket;
	}
	public function listen() {
		$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) {
      $url = $request->getRequestTarget();
      $channel = substr($url, 1);
      $data = $request->getBody()->__toString();
      var_dump($url, $channel, $data);
			$this->socket->sendUpdate($data, $channel);
			return React\Http\Message\Response::plaintext(
				"Hello World!\n"
			);
		}
	);
		$host = '0.0.0.0:8082';
		$socket = new React\Socket\SocketServer($host);
		$http->listen($socket);
	}
}
