<?php

define("SOCKET_PORT_WPS", 8080);
define("SOCKET_PORT_WH2WS", 8081);

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
require dirname(dirname( __FILE__ )) . '/vendor/autoload.php';
require 'SocketWps';
require 'SocketWh2Ws';

$serverWps = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SocketWps()
        )
    ),
	SOCKET_PORT_WPS
);

$serverWh2Ws = IoServer::factory(
	new HttpServer(
			new WsServer(
					new SocketWps()
			)
	),
SOCKET_PORT_WH2WS
);

$serverWps->run();
$serverWh2Ws->run();
