<?php
require_once __DIR__ . '/../../autoload.php';

$db = \Janstro\InventorySystem\Config\Database::connect();

// Get pending emails
$stmt = $db->query("
    SELECT * FROM notification_queue 
    WHERE status = 'pending' 
    LIMIT 10
");

$processed = 0;
while ($email = $stmt->fetch()) {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Janstro IMS <' . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@janstro.com') . '>'
    ];

    if (mail($email['recipient'], $email['subject'], $email['body'], implode("\r\n", $headers))) {
        $db->prepare("UPDATE notification_queue SET status = 'sent', sent_at = NOW() WHERE queue_id = ?")->execute([$email['queue_id']]);
        $processed++;
    }
}

echo "Processed $processed emails\n";
