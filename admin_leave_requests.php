<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle leave request approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    
    try {
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
        $stmt->execute([$action, $request_id]);
        
        // Get user info for notification
        $stmt = $pdo->prepare("
            SELECT lr.user_id, u.username 
            FROM leave_requests lr 
            JOIN users u ON lr.user_id = u.id 
            WHERE lr.id = ?
        ");
        $stmt->execute([$request_id]);
        $user = $stmt->fetch();
        
        // Notify employee
        $message = "Your leave request #" . $request_id . " has been " . $action;
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$user['user_id'], $message]);
        
        $success = "Leave request has been " . $action;
    } catch(PDOException $e) {
        $error = "Failed to process leave request: " . $e->getMessage();
    }
}

// Get all leave requests
$stmt = $pdo->prepare("
    SELECT lr.*, u.username, u.full_name 
    FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.id 
    ORDER BY 
        CASE 
            WHEN lr.status = 'pending' THEN 1
            ELSE 2
        END,
        lr.created_at DESC
");
$stmt->execute();
$leave_requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Requests - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .leave-request.pending {
            background-color: #fff3cd;
        }
        .leave-request.approved {
            background-color: #d1e7dd;
        }
        .leave-request.rejected {
            background-color: #f8d7da;
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
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_leave_requests.php">Leave Requests</a>
                    </li>
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

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Manage Leave Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Submitted On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_requests as $request): ?>
                            <tr class="leave-request <?php echo $request['status']; ?>">
                                <td>#<?php echo $request['id']; ?></td>
                                <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $request['status'] === 'approved' ? 'success' : 
                                            ($request['status'] === 'rejected' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="action" value="approved" class="btn btn-sm btn-success">Approve</button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 