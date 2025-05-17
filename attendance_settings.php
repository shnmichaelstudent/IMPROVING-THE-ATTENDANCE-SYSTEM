<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle time settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
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

// Handle manual attendance marking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $user_id = $_POST['user_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];
    $time_in = $_POST['time_in'] ?: null;
    $time_out = $_POST['time_out'] ?: null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO attendance (user_id, date, time_in, time_out, status) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                time_in = VALUES(time_in),
                time_out = VALUES(time_out),
                status = VALUES(status)
        ");
        $stmt->execute([$user_id, $date, $time_in, $time_out, $status]);
        $success = "Attendance marked successfully";
    } catch(PDOException $e) {
        $error = "Failed to mark attendance: " . $e->getMessage();
    }
}

// Get current time settings
$stmt = $pdo->prepare("SELECT * FROM attendance_settings LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch();

// Get all employees
$stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE role != 'admin' ORDER BY full_name");
$stmt->execute();
$employees = $stmt->fetchAll();

// Get attendance records for today
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date = CURDATE() 
    ORDER BY u.full_name
");
$stmt->execute();
$today_attendance = $stmt->fetchAll();

// Get attendance records for the past 7 days
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    ORDER BY a.date DESC, u.full_name
");
$stmt->execute();
$recent_attendance = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .attendance-status.present { background-color: #d1e7dd; }
        .attendance-status.late { background-color: #fff3cd; }
        .attendance-status.absent { background-color: #f8d7da; }
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
                    <li class="nav-item">
                        <a class="nav-link" href="admin_leave_requests.php">Leave Requests</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="attendance_settings.php">Attendance Settings</a>
                    </li>
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
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Time Settings -->
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

                <!-- Manual Attendance Marking -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Mark Attendance</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Employee</label>
                                <select class="form-select" name="user_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="present">Present</option>
                                    <option value="late">Late</option>
                                    <option value="absent">Absent</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Time In</label>
                                <input type="time" class="form-control" name="time_in">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Time Out</label>
                                <input type="time" class="form-control" name="time_out">
                            </div>
                            <button type="submit" name="mark_attendance" class="btn btn-primary">Mark Attendance</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Today's Attendance -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Today's Attendance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_attendance as $record): ?>
                                    <tr class="attendance-status <?php echo $record['status']; ?>">
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

                <!-- Recent Attendance -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Attendance (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $record): ?>
                                    <tr class="attendance-status <?php echo $record['status']; ?>">
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
    document.getElementById('timeSettingsForm').addEventListener('submit', function(e) {
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
    </script>
</body>
</html> 