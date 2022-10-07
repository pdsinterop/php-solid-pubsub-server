<?php

// $ composer require react/http react/socket # install example using Composer
// $ php example.php # run example on command line, requires no additional web server

require __DIR__ . '/../vendor/autoload.php';

function runWebHookServer() {
  $http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) {
    return React\Http\Message\Response::plaintext(
      "Hello World!\n"
    );
  });
  $host = '0.0.0.0:8080';
  $socket = new React\Socket\SocketServer($host);
  $http->listen($socket);
}