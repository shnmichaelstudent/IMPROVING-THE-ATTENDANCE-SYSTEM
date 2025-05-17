<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle marking notifications as read
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_read'])) {
        $notification_id = $_POST['notification_id'];
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $_SESSION['user_id']]);
            header("Location: view_notifications.php");
            exit();
        } catch(PDOException $e) {
            $error = "Failed to mark notification as read: " . $e->getMessage();
        }
    } elseif (isset($_POST['mark_all_read'])) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
            header("Location: view_notifications.php");
            exit();
        } catch(PDOException $e) {
            $error = "Failed to mark all notifications as read: " . $e->getMessage();
        }
    }
}

// Get all notifications for the user
$stmt = $pdo->prepare("
    SELECT n.*, lr.id as leave_request_id 
    FROM notifications n 
    LEFT JOIN leave_requests lr ON n.message LIKE CONCAT('%Leave request #', lr.id, '%')
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .notification-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            background-color: #e8f4ff;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .notification-dot {
            width: 8px;
            height: 8px;
            background-color: #0d6efd;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Attendance System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_leave_requests.php">Leave Requests</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="leave_requests.php">Leave Requests</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notifications</h5>
                <?php if ($unread_count > 0): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-sm btn-primary">
                        Mark all as read
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-bell-slash" style="font-size: 2rem;"></i>
                        <p class="mt-2">No notifications yet</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="d-flex align-items-start">
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="notification-dot"></span>
                                        <?php endif; ?>
                                        <div>
                                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="notification-time">
                                                <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm btn-link text-muted">
                                                Mark as read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 