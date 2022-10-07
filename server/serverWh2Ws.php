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

require './server/serverReact.php';

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
		// Store the new connection in $this->clients
		$this->clients->attach($conn);
		echo "New connection! ({$conn->resourceId})\n";
	}

	public function onMessage(ConnectionInterface $from, $message) {
		$messageInfo = explode(" ", $message);
		$command = $messageInfo[0];
		$body = trim($messageInfo[1]);
		
		switch ($command) {
			case "auth":
			case "dpop":
				// FIXME: we should check that the client is allowed to listen
			break;
			case "sub":
				echo "Client sub for $body\n";
				if (!isset($this->subscriptions[$body])) {
					$this->subscriptions[$body] = array();
				}
				$this->subscriptions[$body][] = $from;
				$from->send("ack $body");
			break;
			case "pub":
				echo "Client pub for $body\n";
				if (isset($this->subscriptions[$body])) {
					foreach ( $this->subscriptions[$body] as $client ) {
						$client->send("pub $body");
					}
				}
			break;
			default:
				echo "Client $from->resourceId said $message\n";
				foreach ( $this->clients as $client ) {

					if ( $from->resourceId == $client->resourceId ) {
						continue;
					}

					$client->send("Client $from->resourceId said $message\n");
				}
			break;
		}
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

$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			new Socket()
		)
	),
	SOCKET_PORT
);

echo "run 1";
runWebHookServer();
echo "run 2";
$server->run();
echo "run 3";
