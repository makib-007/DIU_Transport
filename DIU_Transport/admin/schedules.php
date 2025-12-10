<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Handle schedule deletion
if (isset($_POST['delete_schedule'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $sql = "DELETE FROM schedules WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $schedule_id);
        if ($stmt->execute()) {
            $success_message = "Schedule deleted successfully.";
        } else {
            $error_message = "Failed to delete schedule.";
        }
        $stmt->close();
    }
}

// Get all schedules with related data
$sql = "SELECT s.*, b.bus_number, b.capacity, r.route_name, r.start_location, r.end_location, r.fare
        FROM schedules s
        JOIN buses b ON s.bus_id = b.id
        JOIN routes r ON s.route_id = r.id
        ORDER BY s.departure_time, s.days_of_week";
$schedules = $conn->query($sql);

// Get buses for dropdown
$buses_sql = "SELECT id, bus_number, capacity FROM buses WHERE status = 'active' ORDER BY bus_number";
$buses = $conn->query($buses_sql);

// Get routes for dropdown
$routes_sql = "SELECT id, route_name, start_location, end_location, fare FROM routes WHERE status = 'active' ORDER BY route_name";
$routes = $conn->query($routes_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-clock"></i> Manage Schedules</h1>
                    <p>Transport Management System - Daffodil International University</p>
                </div>
                <div class="user-info">
                    <p><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="schedules.php" class="active"><i class="fas fa-clock"></i> Manage Schedules</a></li>
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> All Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
                    <li><a href="buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                    <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            <div class="content">
                <div class="content-header">
                    <h2>Bus Schedules</h2>
                    <a href="add_schedule.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Schedule
                    </a>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3>All Schedules</h3>
                        <div class="search-box">
                            <input type="text" id="scheduleSearch" placeholder="Search schedules...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Bus</th>
                                        <th>Route</th>
                                        <th>Departure</th>
                                        <th>Arrival</th>
                                        <th>Days</th>
                                        <th>Available Seats</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($schedules && $schedules->num_rows > 0): ?>
                                        <?php while ($schedule = $schedules->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $schedule['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($schedule['bus_number']); ?></strong><br>
                                                    <small>Capacity: <?php echo $schedule['capacity']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($schedule['route_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($schedule['start_location']); ?> → <?php echo htmlspecialchars($schedule['end_location']); ?></small><br>
                                                    <small>Fare: ৳<?php echo number_format($schedule['fare'], 2); ?></small>
                                                </td>
                                                <td><?php echo date('h:i A', strtotime($schedule['departure_time'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($schedule['arrival_time'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $days = explode(',', $schedule['days_of_week']);
                                                    foreach ($days as $day) {
                                                        echo '<span class="badge badge-info">' . trim($day) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $schedule['available_seats'] > 10 ? 'success' : ($schedule['available_seats'] > 0 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $schedule['available_seats']; ?> seats
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $schedule['status'] === 'active' ? 'success' : ($schedule['status'] === 'cancelled' ? 'danger' : 'secondary'); ?>">
                                                        <?php echo ucfirst($schedule['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="edit_schedule.php?id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                            <button type="submit" name="delete_schedule" class="btn btn-sm btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No schedules found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Schedule search functionality
        document.getElementById('scheduleSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.table tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
