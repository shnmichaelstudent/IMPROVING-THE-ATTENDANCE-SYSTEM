<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'mark_read':
                $notification_id = $_POST['notification_id'] ?? null;
                if ($notification_id) {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                    $stmt->execute([$notification_id, $_SESSION['user_id']]);
                    echo json_encode(['success' => true]);
                }
                break;
                
            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                echo json_encode(['success' => true]);
                break;
                
            case 'get_notifications':
                $stmt = $pdo->prepare("
                    SELECT n.*, lr.id as leave_request_id 
                    FROM notifications n 
                    LEFT JOIN leave_requests lr ON n.message LIKE CONCAT('%Leave request #', lr.id, '%')
                    WHERE n.user_id = ? 
                    ORDER BY n.created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format dates and add relative time
                foreach ($notifications as &$notification) {
                    $notification['created_at'] = date('M d, Y H:i', strtotime($notification['created_at']));
                    $notification['time_ago'] = time_elapsed_string($notification['created_at']);
                }
                
                echo json_encode(['notifications' => $notifications]);
                break;
                
            case 'get_unread_count':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$_SESSION['user_id']]);
                $count = $stmt->fetchColumn();
                echo json_encode(['count' => $count]);
                break;
        }
    } catch(PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Helper function to get relative time
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d > 7) {
        return date('M d, Y', strtotime($datetime));
    }
    
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'Just now';
}
?> 