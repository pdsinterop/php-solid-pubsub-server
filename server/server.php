<?php

use \WebSocket\Throwable;
use \WebSocket\Exception\ExceptionInterface;
use \WebSocket\Message\{
    Binary,
    Close,
    Ping,
    Pong,
    Text,
};
use \WebSocket\Middleware\{
    CloseHandler,
    CompressionExtension,
    PingResponder,
};
use \WebSocket\Middleware\CompressionExtension\DeflateCompressor;
use \WebSocket\Test\EchoLog;

require dirname(dirname( __FILE__ )) . '/vendor/autoload.php';

$options = [
    "port" => 8080,
    "ssl" => true,
    "timeout" => "60",
    "framesize" => "4096",
    "connections" => 30,
    "debug" => false,
    "deflate" => false
];

class SolidPubSub {
    private $options;
    private $clients;
    private $subscriptions;

    public function __construct($options) {
        $this->options = $options;
        $this->clients = [];
        $this->subscriptions = [];
        $this->server = $this->createServer();
    }
    
    public function onHandshake(
        \WebSocket\Server $server,
        \WebSocket\Connection $connection,
        $request,
        $response
    ) {
      // Store the new connection in $this->clients
        $this->clients[$connection->getRemoteName()] = $connection;
        echo "> [{$connection->getRemoteName()}] Client connected {$request->getUri()}\n";
    }

    public function onText(
        \WebSocket\Server $server,
        \WebSocket\Connection $connection,
        \WebSocket\Message\Text $message
    ) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";

        $messageInfo = explode(" ", $message->getContent(), 2);
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
                $this->subscriptions[$body][] = $connection;
                $connection->send(new \WebSocket\Message\Text("ack $body"));
            break;
            case "pub":
                echo "Client pub for $body\n";
                if (isset($this->subscriptions[$body])) {
                    foreach ( $this->subscriptions[$body] as $client ) {
                        $client->send(new \WebSocket\Message\Text("pub $body"));
                    }
                }
            break;
            default:
                echo "Client " . $connection->getRemoteName() . " said $message\n";
                foreach ( $this->clients as $client ) {
                    if ( $connection->getRemoteName() == $client->getRemoteName() ) {
                        continue;
                    }
                    $client->send(new \WebSocket\Message\Text("Client " . $connection->getRemoteName() . " said $message\n"));
                }
            break;
        }
    }

    public function onClose(
        \WebSocket\Server $server,
        \WebSocket\Connection $connection,
        \WebSocket\Message\Close $message
    ) {
        echo "Client " . $connection->getRemoteName() . " left\n";
        foreach ($this->subscriptions as $url => $subscribers) {
            foreach ($subscribers as $key => $client) {
                if ($client->getRemoteName() == $connection->getRemoteName()) {
                    echo "Removing subscription for $url\n";
                    unset($subscribers[$url][$key]);
                }
            }
        }
    }

    public function onDisconnect(
        \WebSocket\Server $server,
        \WebSocket\Connection $connection
    ) {
        echo "Client " . $connection->getRemoteName() . " disconnected\n";
        foreach ($this->subscriptions as $url => $subscribers) {
            foreach ($subscribers as $key => $client) {
                if ($client->getRemoteName() == $connection->getRemoteName()) {
                    echo "Removing subscription for $url\n";
                    unset($subscribers[$url][$key]);
                }
            }
        }
    }
    
    public function onError(
        \WebSocket\Server $server,
        \WebSocket\Connection|null $connection,
        ExceptionInterface $exception
    ) {
        $name = $connection ? "[{$connection->getRemoteName()}]" : "[-]";
        echo "> {$name} Error: {$exception->getMessage()}\n";
    }

    public function createServer() {
        // Initiate server.
        try {
            $server = new \WebSocket\Server(
                $this->options['port'] ?? 8080,
                $this->options['ssl'] ?? true
            );

            // Set up SSL with CA certificate
            $server->setContext(['ssl' => [
                'local_cert'        => 'certs/server.crt',
                'local_pk'          => 'certs/server.key',
                'verify_peer'       => false, // if false, accept SSL handshake without client certificate
                'verify_peer_name'  => false,
                'allow_self_signed' => false,
            ]]);

            $server
                ->addMiddleware(new CloseHandler())
                ->addMiddleware(new PingResponder())
                ;

            // If debug mode and logger is available
            if (($this->options['debug'] ?? false) && class_exists('WebSocket\Test\EchoLog')) {
                $server->setLogger(new EchoLog());
                echo "# Using logger\n";
            }
            $server->setTimeout($this->options['timeout'] ?? 60);
            echo "# Set timeout: {$this->options['timeout']}\n";

            $server->setFrameSize($this->options['framesize'] ?? 4096);
            echo "# Set frame size: {$this->options['framesize']}\n";

            $server->setMaxConnections($this->options['connections'] ?? 30);
            echo "# Set max connections: {$this->options['connections']}\n";

            if ($this->options['deflate'] ?? false) {
                $server->addMiddleware(new CompressionExtension(new DeflateCompressor()));
                echo "# Using per-message: deflate compression\n";
            }

            $server->addMiddleware(new WebSocket\Middleware\SubprotocolNegotiation([
                'solid-0.1'
            ]));
            echo "# Added subprotocol solid-0.1\n";

            echo "# Listening on port {$server->getPort()}\n";

            // Wiring the server handlers to our handlers;
            $server
                ->onHandshake(function (\WebSocket\Server $server, \WebSocket\Connection $connection, $request, $response) {
                    return $this->onHandshake($server, $connection, $request, $response);
                })
                ->onDisconnect(function(\WebSocket\Server $server, \WebSocket\Connection $connection) {
                    return $this->onDisconnect($server, $connection);
                })
                ->onText(function(\WebSocket\Server $server, \WebSocket\Connection $connection, \WebSocket\Message\Text $message) {
                    return $this->onText($server, $connection, $message);
                })
                ->onClose(function(\WebSocket\Server $server, \WebSocket\Connection $connection, \WebSocket\Message\Close $message) {
                    return $this->onClose($server, $connection, $message);
                })
                ->onError(function (\WebSocket\Server $server, \WebSocket\Connection|null $connection, ExceptionInterface $exception) {
                    return $this->onError($server, $connection, $exception);
                })
            ->start();
        } catch (\Throwable $e) {
            echo "> ERROR: {$e->getMessage()}\n";
        }
    }
}

$server = new SolidPubSub($options);