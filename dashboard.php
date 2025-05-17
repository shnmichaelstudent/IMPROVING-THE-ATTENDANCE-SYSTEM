<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle time settings update (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings']) && $_SESSION['role'] === 'admin') {
    $time_in_start = $_POST['time_in_start'];
    $time_in_end = $_POST['time_in_end'];
    $time_out_start = $_POST['time_out_start'];
    $time_out_end = $_POST['time_out_end'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO attendance_settings (time_in_start, time_in_end, time_out_start, time_out_end) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                time_in_start = VALUES(time_in_start),
                time_in_end = VALUES(time_in_end),
                time_out_start = VALUES(time_out_start),
                time_out_end = VALUES(time_out_end)
        ");
        $stmt->execute([$time_in_start, $time_in_end, $time_out_start, $time_out_end]);
        $success = "Time settings updated successfully";
    } catch(PDOException $e) {
        $error = "Failed to update time settings: " . $e->getMessage();
    }
}

// Handle time in/out
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['time_in'])) {
        // Get current time settings
        $stmt = $pdo->prepare("SELECT * FROM attendance_settings LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        
        // Get current time
        $current_time = date('H:i:s');
        
        // Determine status based on time
        $status = 'present';
        if ($current_time > $settings['time_in_end']) {
            $status = 'late';
        }
        
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, time_in, status) VALUES (?, CURDATE(), NOW(), ?) ON DUPLICATE KEY UPDATE time_in = NOW(), status = ?");
        $stmt->execute([$_SESSION['user_id'], $status, $status]);
    } elseif (isset($_POST['time_out'])) {
        // Get current time settings
        $stmt = $pdo->prepare("SELECT * FROM attendance_settings LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        
        // Get current time
        $current_time = date('H:i:s');
        
        // Update time out
        $stmt = $pdo->prepare("UPDATE attendance SET time_out = NOW() WHERE user_id = ? AND date = CURDATE()");
        $stmt->execute([$_SESSION['user_id']]);
    }
}

// Get current time settings
$stmt = $pdo->prepare("SELECT * FROM attendance_settings LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch();

// Get attendance records
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY a.date DESC, u.full_name
");
$stmt->execute();
$attendance_records = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Attendance System</title>
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
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
                    <li class="nav-item">
                        <a class="nav-link" href="view_notifications.php">
                            Notifications
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                            $stmt->execute([$_SESSION['user_id']]);
                            $unread_count = $stmt->fetchColumn();
                            if ($unread_count > 0):
                            ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-light position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="display: none;">
                                0
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" style="width: 350px;">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <h6 class="mb-0">Notifications</h6>
                                <button class="btn btn-sm btn-link" id="markAllRead">Mark all as read</button>
                            </div>
                            <div id="notificationsList" style="max-height: 400px; overflow-y: auto;">
                                <!-- Notifications will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Time Settings for Admin -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Shift Time Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="timeSettingsForm">
                            <div class="mb-3">
                                <label class="form-label">1st Shift</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                            <input type="time" class="form-control" name="time_in_start" 
                                                   value="<?php echo $settings['time_in_start'] ?? '08:00'; ?>" required>
                                        </div>
                                        <small class="text-muted">Start Time</small>
                                    </div>
                                    <div class="col">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                            <input type="time" class="form-control" name="time_in_end" 
                                                   value="<?php echo $settings['time_in_end'] ?? '09:00'; ?>" required>
                                        </div>
                                        <small class="text-muted">End Time</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">2nd Shift</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                            <input type="time" class="form-control" name="time_out_start" 
                                                   value="<?php echo $settings['time_out_start'] ?? '17:00'; ?>" required>
                                        </div>
                                        <small class="text-muted">Start Time</small>
                                    </div>
                                    <div class="col">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                            <input type="time" class="form-control" name="time_out_end" 
                                                   value="<?php echo $settings['time_out_end'] ?? '18:00'; ?>" required>
                                        </div>
                                        <small class="text-muted">End Time</small>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attendance Records -->
            <div class="<?php echo $_SESSION['role'] === 'admin' ? 'col-md-8' : 'col-md-12'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Attendance Records</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($_SESSION['role'] !== 'admin'): ?>
                        <div class="text-end mb-3">
                            <form method="POST" class="d-inline">
                                <button type="submit" name="time_in" class="btn btn-success me-2">Time In</button>
                                <button type="submit" name="time_out" class="btn btn-danger">Time Out</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee Name</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                        <td><?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-'; ?></td>
                                        <td><?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $record['status'] === 'present' ? 'success' : 
                                                    ($record['status'] === 'late' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Time settings validation
    document.getElementById('timeSettingsForm')?.addEventListener('submit', function(e) {
        const timeInStart = document.querySelector('input[name="time_in_start"]').value;
        const timeInEnd = document.querySelector('input[name="time_in_end"]').value;
        const timeOutStart = document.querySelector('input[name="time_out_start"]').value;
        const timeOutEnd = document.querySelector('input[name="time_out_end"]').value;

        // Validate time ranges
        if (timeInStart >= timeInEnd) {
            e.preventDefault();
            alert('1st Shift start time must be before end time');
            return;
        }

        if (timeOutStart >= timeOutEnd) {
            e.preventDefault();
            alert('2nd Shift start time must be before end time');
            return;
        }

        if (timeInEnd >= timeOutStart) {
            e.preventDefault();
            alert('1st Shift end time must be before 2nd Shift start time');
            return;
        }
    });

    // Notifications functionality
    function loadNotifications() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_notifications'
        })
        .then(response => response.json())
        .then(data => {
            const notificationsList = document.getElementById('notificationsList');
            notificationsList.innerHTML = '';
            
            if (data.notifications.length === 0) {
                notificationsList.innerHTML = '<div class="p-3 text-center text-muted">No notifications</div>';
                return;
            }
            
            data.notifications.forEach(notification => {
                const div = document.createElement('div');
                div.className = `dropdown-item notification-item ${notification.is_read ? '' : 'unread'}`;
                div.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>${notification.message}</div>
                        <small class="notification-time">${notification.time_ago}</small>
                    </div>
                `;
                
                if (!notification.is_read) {
                    div.addEventListener('click', () => markAsRead(notification.id));
                }
                
                notificationsList.appendChild(div);
            });
        });
    }

    function markAsRead(notificationId) {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_read&notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                updateUnreadCount();
            }
        });
    }

    function markAllAsRead() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_all_read'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                updateUnreadCount();
            }
        });
    }

    function updateUnreadCount() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_unread_count'
        })
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('notificationBadge');
            if (data.count > 0) {
                badge.style.display = 'block';
                badge.textContent = data.count;
            } else {
                badge.style.display = 'none';
            }
        });
    }

    // Initial load
    loadNotifications();
    updateUnreadCount();

    // Set up event listeners
    document.getElementById('markAllRead')?.addEventListener('click', markAllAsRead);

    // Refresh notifications every 30 seconds
    setInterval(() => {
        loadNotifications();
        updateUnreadCount();
    }, 30000);
    </script>
</body>
</html> 