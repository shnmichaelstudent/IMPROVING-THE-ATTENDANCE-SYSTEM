<?php
require_once 'config/database.php';

try {
    // Create attendance_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            time_in_start TIME NOT NULL,
            time_in_end TIME NOT NULL,
            time_out_start TIME NOT NULL,
            time_out_end TIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Check if default settings exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM attendance_settings");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Insert default time settings
        $stmt = $pdo->prepare("
            INSERT INTO attendance_settings (time_in_start, time_in_end, time_out_start, time_out_end)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute(['08:00:00', '09:00:00', '17:00:00', '18:00:00']);
    }

    echo "Attendance settings table created and initialized successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 