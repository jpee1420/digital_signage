<?php
// Simple script to fix the schedule message in the database
require_once 'config.php';

// Clean message with proper apostrophe
$fixed_message = "If you spot any errors in the current schedule, feel free to report them to the CS Lab Admin.";

// Update the message in the database
$stmt = $pdo->prepare("UPDATE ticker_messages SET message = ? WHERE type = 'schedule'");
$result = $stmt->execute([$fixed_message]);

if ($result) {
    echo "Schedule message updated successfully!";
} else {
    echo "Error updating message.";
}
?> 