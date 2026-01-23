<?php
declare(strict_types=1);

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// ------------------------------------------------------------------
// Load Composer autoloader and project files
// websocket_server.php is likely in /server/ or root
// We need to go up 2 levels to reach project root (where vendor/, actions/, includes/ are)
$projectRoot = __DIR__;

require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/actions/db.php';
require_once $projectRoot . '/includes/helpers.php'; // For getClientAvatar(), getAdminAvatar()

// Verify database connection
if (!isset($conn) || !$conn instanceof mysqli) {
    error_log('WebSocket Server: Failed to connect to database (db.php did not provide $conn)');
    exit('Database connection failed.');
}

error_log('WebSocket Server: Successfully loaded dependencies and database connection.');

class Chat implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    protected mysqli $conn;
    protected string $dbHost;
    protected string $dbUser;
    protected string $dbPass;
    protected string $dbName;

    public function __construct(mysqli $dbConn, string $host = 'localhost', string $user = 'root', string $pass = '', string $dbName = 'jvb_travel_db')
    {
        $this->clients = new SplObjectStorage();
        $this->conn = $dbConn;
        $this->dbHost = $host;
        $this->dbUser = $user;
        $this->dbPass = $pass;
        $this->dbName = $dbName;
        error_log('WebSocket Chat Server initialized and ready.');
    }

    /**
     * Ensure database connection is alive, reconnect if needed
     */
    private function ensureConnection(): void
    {
        if (!$this->conn->ping()) {
            error_log('Database connection lost, reconnecting...');
            $this->conn->close();
            $this->conn = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
            
            if ($this->conn->connect_error) {
                throw new Exception('Database reconnection failed: ' . $this->conn->connect_error);
            }
            
            $this->conn->set_charset('utf8mb4');
            error_log('Database connection restored.');
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $conn->clientData = null; // Will store subscription info
        error_log("New connection established ({$conn->resourceId}) from {$conn->httpRequest->getUri()}");
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode($msg, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data['action'])) {
                throw new Exception('Invalid JSON or missing action');
            }

            switch ($data['action']) {
                case 'subscribe':
                    $this->handleSubscribe($from, $data);
                    break;

                case 'send_message':
                    $this->handleSendMessage($from, $data);
                    break;

                default:
                    throw new Exception("Unknown action: {$data['action']}");
            }
        } catch (Exception $e) {
            error_log("WebSocket message error (client {$from->resourceId}): " . $e->getMessage());
            $from->send(json_encode([
                'action' => 'error',
                'error'  => 'Invalid request'
            ]));
        }
    }

    private function handleSubscribe(ConnectionInterface $from, array $data): void
    {
        $required = ['user_id', 'user_type', 'recipient_id', 'recipient_type'];
        $missing = array_diff($required, array_keys($data));

        if ($missing) {
            $from->send(json_encode([
                'action' => 'error',
                'error'  => 'Missing fields: ' . implode(', ', $missing)
            ]));
            return;
        }

        $userId        = (int)$data['user_id'];
        $userType      = trim($data['user_type']);
        $recipientId   = (int)$data['recipient_id'];
        $recipientType = trim($data['recipient_type']);
        $threadId      = !empty($data['thread_id']) ? (int)$data['thread_id'] : null;

        $validTypes = ['admin', 'client'];
        if (!in_array($userType, $validTypes) || !in_array($recipientType, $validTypes)) {
            $from->send(json_encode(['action' => 'error', 'error' => 'Invalid user or recipient type']));
            return;
        }

        // Store subscription data on connection
        $from->clientData = [
            'user_id'         => $userId,
            'user_type'       => $userType,
            'recipient_id'    => $recipientId,
            'recipient_type'  => $recipientType,
            'thread_id'       => $threadId
        ];

        error_log("Client {$from->resourceId} subscribed to conversation: user=$userId ($userType) ↔ recipient=$recipientId ($recipientType)" .
                  ($threadId ? " [thread_id=$threadId]" : ""));
    }

    private function handleSendMessage(ConnectionInterface $from, array $data): void
    {
        error_log("WebSocket: handleSendMessage called with data: " . json_encode($data));

        // Ensure database connection is alive
        $this->ensureConnection();

        $required = ['sender_id', 'sender_type', 'recipient_id', 'recipient_type', 'message_text'];
        $missing = array_diff($required, array_keys($data));

        if ($missing) {
            $from->send(json_encode(['action' => 'error', 'error' => 'Missing required fields']));
            return;
        }

        $senderId      = (int)$data['sender_id'];
        $senderType    = trim($data['sender_type']);
        $recipientId   = (int)$data['recipient_id'];
        $recipientType = trim($data['recipient_type']);
        $messageText   = trim($data['message_text']);
        $threadId      = !empty($data['thread_id']) ? (int)$data['thread_id'] : null;
        $tempId        = $data['temp_id'] ?? null;

        if ($senderId <= 0 || $recipientId <= 0 || $messageText === '') {
            $from->send(json_encode(['action' => 'error', 'error' => 'Invalid data']));
            return;
        }

        $validTypes = ['admin', 'client'];
        if (!in_array($senderType, $validTypes) || !in_array($recipientType, $validTypes)) {
            $from->send(json_encode(['action' => 'error', 'error' => 'Invalid type']));
            return;
        }

        // Resolve thread_id (bidirectional)
        $resolvedThreadId = $this->resolveOrCreateThread($senderId, $senderType, $recipientId, $recipientType);

        // Insert message
        $sql = "INSERT INTO messages 
                (thread_id, sender_id, sender_type, recipient_id, recipient_type, message_text, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }

        $stmt->bind_param('iisiss', $resolvedThreadId, $senderId, $senderType, $recipientId, $recipientType, $messageText);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $messageId = $this->conn->insert_id;
        error_log("WebSocket: Message inserted with ID: $messageId, thread: $resolvedThreadId");
        $stmt->close();

        // Get the actual created_at timestamp from database
        $stmt = $this->conn->prepare("SELECT created_at FROM messages WHERE id = ?");
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $stmt->bind_result($createdAt);
        $stmt->fetch();
        $stmt->close();

        // Get sender info
        $senderInfo = $this->getSenderInfo($senderId, $senderType);
        $senderPhoto = $senderInfo['photo'] ?? null;
        $senderName  = $senderInfo['name'] ?? 'Unknown';

        // Build message payload
        $messagePayload = [
            'id'              => $messageId,
            'thread_id'       => $resolvedThreadId,
            'sender_id'       => $senderId,
            'sender_type'     => strtolower($senderType),
            'recipient_id'    => $recipientId,
            'recipient_type'  => strtolower($recipientType),
            'message_text'    => $messageText,
            'created_at'      => $createdAt,
            'sender_name'     => $senderName,
            'sender_photo'    => $senderPhoto,
            'temp_id'         => $tempId // Optional: for client-side optimistic UI
        ];

        // Broadcast to both participants in the conversation
        foreach ($this->clients as $client) {
            $sub = $client->clientData;

            if (!$sub) continue;

            $isParticipant = (
                ($sub['user_id'] == $senderId && $sub['user_type'] == $senderType && $sub['recipient_id'] == $recipientId && $sub['recipient_type'] == $recipientType) ||
                ($sub['user_id'] == $recipientId && $sub['user_type'] == $recipientType && $sub['recipient_id'] == $senderId && $sub['recipient_type'] == $senderType)
            );

            $matchesThread = (!$sub['thread_id'] || $sub['thread_id'] == $resolvedThreadId);

            if ($isParticipant && $matchesThread) {
                $client->send(json_encode([
                    'action'  => 'new_message',
                    'message' => $messagePayload
                ]));
            }
        }

        error_log("Message broadcasted (ID: $messageId) in thread $resolvedThreadId from $senderType:$senderId to $recipientType:$recipientId");
    }

    private function resolveOrCreateThread(int $userId, string $userType, int $recipientId, string $recipientType): int
    {
        $this->ensureConnection();

        $sql = "
            SELECT id FROM threads 
            WHERE (
                (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?) OR
                (user_id = ? AND user_type = ? AND recipient_id = ? AND recipient_type = ?)
            )
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) throw new Exception("Thread lookup prepare failed: " . $this->conn->error);

        $stmt->bind_param('isisisis', $userId, $userType, $recipientId, $recipientType, $recipientId, $recipientType, $userId, $userType);
        $stmt->execute();
        $stmt->bind_result($threadId);
        $stmt->fetch();
        $stmt->close();

        if ($threadId) {
            return $threadId;
        }

        // Create new thread
        $sql = "INSERT INTO threads (user_id, user_type, recipient_id, recipient_type, created_at) 
                VALUES (?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) throw new Exception("Thread insert prepare failed: " . $this->conn->error);

        // Bind recipient_type as string to avoid coercing to 0
        $stmt->bind_param('isis', $userId, $userType, $recipientId, $recipientType);
        $stmt->execute();
        $threadId = $this->conn->insert_id;
        $stmt->close();

        error_log("New thread created: ID $threadId");

        return $threadId;
    }

    private function getSenderInfo(int $senderId, string $senderType): array
    {
        $name = 'Unknown';
        $photo = null;

        if ($senderType === 'admin') {
            $sql = "SELECT CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) AS name, admin_photo 
                    FROM admin_accounts WHERE id = ?";
        } elseif ($senderType === 'client') {
            $sql = "SELECT full_name AS name, client_profile_photo FROM clients WHERE id = ?";
        } else {
            return ['name' => $name, 'photo' => $photo];
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return ['name' => $name, 'photo' => $photo];

        $stmt->bind_param('i', $senderId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result) {
            $name = trim($result['name'] ?? 'Unknown');
            $photoKey = ($senderType === 'admin') ? 'admin_photo' : 'client_profile_photo';
            $photoFile = $result[$photoKey] ?? null;

            if ($photoFile) {
                // Generate avatar URL directly (WebSocket context)
                if ($senderType === 'admin') {
                    $photo = '../Uploads/admin_photo/' . rawurlencode($photoFile);
                } else {
                    $photo = '../Uploads/client_profiles/' . rawurlencode($photoFile);
                }
            }
        }

        return ['name' => $name, 'photo' => $photo];
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        error_log("Connection closed: {$conn->resourceId}");
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        error_log("WebSocket error on connection {$conn->resourceId}: " . $e->getMessage());
        $conn->close();
    }
}

// ------------------------------------------------------------------
// Start the WebSocket server
try {
    // Pass database credentials for reconnection support
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new Chat($conn, $_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', $_ENV['DB_NAME'] ?? 'jvb_travel_db')
            )
        ),
        8080
    );

    error_log('WebSocket server started on ws://0.0.0.0:8080');
    $server->run();
} catch (Exception $e) {
    error_log('Failed to start WebSocket server: ' . $e->getMessage());
    exit(1);
}