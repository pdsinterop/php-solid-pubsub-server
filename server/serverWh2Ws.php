<?php
// "WH2WS" stands for WebHooks-to-WebSockets and can be used
// in combination with e.g. https://github.com/pdsinterop/solid-nextcloud
// for https://solidproject.org/TR/2022/websocket-subscription-2021-20220509
	
define("SOCKET_PORT", 8081);
	
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsServerInterface;
use Ratchet\ConnectionInterface;

require dirname(dirname( __FILE__ )) . '/vendor/autoload.php';

require './server/webhookServer.php';

class Socket implements MessageComponentInterface, WsServerInterface {
	public function __construct() {
		$this->clients = new \SplObjectStorage;
		$this->subscriptions = array();
		$this->subprotocols = array("solid-0.1");
	}

	public function getSubProtocols() {
		return $this->subprotocols;
	}
	public function onOpen(ConnectionInterface $conn) {
		$token = substr($conn->httpRequest->getUri()->getPath(), 1);

		// Store the new connection in $this->clients
		$this->clients->attach($conn);
		echo "New connection! ({$conn->resourceId})\n";

		echo "Client sub for $token\n";
		if (!isset($this->subscriptions[$token])) {
			$this->subscriptions[$token] = array();
		}
		$this->subscriptions[$token][] = $conn;
		// $conn->send("ack $token");
	}

	public function sendUpdate($msg, $token) {
		if (isset($this->subscriptions[$token])) {
			foreach ( $this->subscriptions[$token] as $client ) {
				$client->send($msg);
			}
		}
	}

	public function onMessage(ConnectionInterface $from, $message) {
		echo "Client $from->resourceId said $message, ignoring\n";
	}

	public function onClose(ConnectionInterface $conn) {
		echo "Client $conn->resourceId left\n";
		foreach ($this->subscriptions as $url => $subscribers) {
			foreach ($subscribers as $key => $client) {
				if ($client->resourceId == $conn->resourceId) {
					echo "Removing subscription for $url\n";
					unset($subscribers[$url][$key]);
				}
			}
		}
	}

	public function onError(ConnectionInterface $conn, \Exception $e) {
		foreach ($this->subscriptions as $url => $subscribers) {
			foreach ($subscribers as $key => $client) {
				if ($client->resourceId == $conn->resourceId) {
					echo "Removing subscription for $url\n";
					unset($subscribers[$url][$key]);
				}
			}
		}
	}
}

$socket = new Socket();
$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			$socket
		)
	),
	SOCKET_PORT
);

echo "run 1";
$wh = new WebHookServer($socket);
$wh->listen();
echo "run 2";
$server->run();
echo "run 3";
