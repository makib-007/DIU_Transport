<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Get date range for reports
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Overall Statistics
$stats = [];

// Total students
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
$result = $conn->query($sql);
$stats['total_students'] = $result->fetch_assoc()['total'];

// Total bookings
$sql = "SELECT COUNT(*) as total FROM bookings";
$result = $conn->query($sql);
$stats['total_bookings'] = $result->fetch_assoc()['total'];

// Total revenue
$sql = "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'completed'";
$result = $conn->query($sql);
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Total buses
$sql = "SELECT COUNT(*) as total FROM buses";
$result = $conn->query($sql);
$stats['total_buses'] = $result->fetch_assoc()['total'];

// Total routes
$sql = "SELECT COUNT(*) as total FROM routes";
$result = $conn->query($sql);
$stats['total_routes'] = $result->fetch_assoc()['total'];

// Monthly Statistics
$monthly_stats = [];

// Monthly bookings
$sql = "SELECT COUNT(*) as total FROM bookings WHERE DATE(created_at) BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $monthly_stats['bookings'] = $result->fetch_assoc()['total'];
    $stmt->close();
}

// Monthly revenue
$sql = "SELECT SUM(p.amount) as total FROM payments p 
        JOIN bookings b ON p.booking_id = b.id 
        WHERE p.payment_status = 'completed' AND DATE(b.created_at) BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $monthly_stats['revenue'] = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Monthly new students
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student' AND DATE(created_at) BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $monthly_stats['new_students'] = $result->fetch_assoc()['total'];
    $stmt->close();
}

// Top Routes by Bookings
$sql = "SELECT r.route_name, r.start_location, r.end_location, r.fare,
               COUNT(b.id) as booking_count,
               SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as revenue
        FROM routes r
        LEFT JOIN schedules s ON r.id = s.route_id
        LEFT JOIN bookings b ON s.id = b.schedule_id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE DATE(b.created_at) BETWEEN ? AND ?
        GROUP BY r.id
        ORDER BY booking_count DESC
        LIMIT 5";
$top_routes = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $top_routes = $stmt->get_result();
    $stmt->close();
}

// Top Students by Bookings
$sql = "SELECT u.name, u.email, u.student_id,
               COUNT(b.id) as booking_count,
               SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_paid
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE u.role = 'student' AND DATE(b.created_at) BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY booking_count DESC
        LIMIT 5";
$top_students = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $top_students = $stmt->get_result();
    $stmt->close();
}

// Payment Status Distribution
$sql = "SELECT payment_status, COUNT(*) as count, SUM(amount) as total_amount
        FROM payments
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_status";
$payment_distribution = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $payment_distribution = $stmt->get_result();
    $stmt->close();
}

// Booking Status Distribution
$sql = "SELECT status, COUNT(*) as count
        FROM bookings
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status";
$booking_distribution = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $booking_distribution = $stmt->get_result();
    $stmt->close();
}

// Daily Bookings (Last 30 days)
$sql = "SELECT DATE(created_at) as date, COUNT(*) as count
        FROM bookings
        WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date";
$daily_bookings = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
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
                    <li><a href="schedules.php"><i class="fas fa-clock"></i> Manage Schedules</a></li>
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> All Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
                    <li><a href="buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                    <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                    <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            <div class="content">
                <div class="content-header">
                    <h2>Reports & Analytics</h2>
                    <div class="header-actions">
                        <button onclick="exportReport()" class="btn btn-primary">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                        <button onclick="printReport()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Date Range Filter -->
                <div class="card">
                    <div class="card-header">
                        <h3>Date Range</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="form-inline">
                            <div class="form-group">
                                <label for="start_date">Start Date:</label>
                                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date:</label>
                                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filter
                                </button>
                                <a href="reports.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_students']; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_bookings']; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h3>৳<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bus"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_buses']; ?></h3>
                            <p>Total Buses</p>
                        </div>
                    </div>
                </div>

                <!-- Monthly Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $monthly_stats['bookings']; ?></h3>
                            <p>Bookings This Period</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3>৳<?php echo number_format($monthly_stats['revenue'], 2); ?></h3>
                            <p>Revenue This Period</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $monthly_stats['new_students']; ?></h3>
                            <p>New Students This Period</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_routes']; ?></h3>
                            <p>Total Routes</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3>Payment Status Distribution</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3>Booking Status Distribution</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="bookingChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Routes and Students -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3>Top Routes by Bookings</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Route</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($top_routes && $top_routes->num_rows > 0): ?>
                                                <?php while ($route = $top_routes->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($route['route_name']); ?></strong><br>
                                                            <small><?php echo htmlspecialchars($route['start_location']); ?> → <?php echo htmlspecialchars($route['end_location']); ?></small>
                                                        </td>
                                                        <td><?php echo $route['booking_count']; ?></td>
                                                        <td>৳<?php echo number_format($route['revenue'], 2); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No data available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3>Top Students by Bookings</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Bookings</th>
                                                <th>Total Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($top_students && $top_students->num_rows > 0): ?>
                                                <?php while ($student = $top_students->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                                                            <small><?php echo htmlspecialchars($student['student_id']); ?></small>
                                                        </td>
                                                        <td><?php echo $student['booking_count']; ?></td>
                                                        <td>৳<?php echo number_format($student['total_paid'], 2); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No data available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Bookings Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3>Daily Bookings (Last 30 Days)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart" width="800" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Payment Status Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentData = {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
                borderWidth: 1
            }]
        };

        <?php if ($payment_distribution && $payment_distribution->num_rows > 0): ?>
            <?php while ($payment = $payment_distribution->fetch_assoc()): ?>
                paymentData.labels.push('<?php echo ucfirst($payment['payment_status']); ?>');
                paymentData.datasets[0].data.push(<?php echo $payment['count']; ?>);
            <?php endwhile; ?>
        <?php endif; ?>

        new Chart(paymentCtx, {
            type: 'doughnut',
            data: paymentData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Booking Status Chart
        const bookingCtx = document.getElementById('bookingChart').getContext('2d');
        const bookingData = {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
                borderWidth: 1
            }]
        };

        <?php if ($booking_distribution && $booking_distribution->num_rows > 0): ?>
            <?php while ($booking = $booking_distribution->fetch_assoc()): ?>
                bookingData.labels.push('<?php echo ucfirst($booking['status']); ?>');
                bookingData.datasets[0].data.push(<?php echo $booking['count']; ?>);
            <?php endwhile; ?>
        <?php endif; ?>

        new Chart(bookingCtx, {
            type: 'doughnut',
            data: bookingData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Daily Bookings Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = {
            labels: [],
            datasets: [{
                label: 'Bookings',
                data: [],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true
            }]
        };

        <?php if ($daily_bookings && $daily_bookings->num_rows > 0): ?>
            <?php while ($daily = $daily_bookings->fetch_assoc()): ?>
                dailyData.labels.push('<?php echo date('M j', strtotime($daily['date'])); ?>');
                dailyData.datasets[0].data.push(<?php echo $daily['count']; ?>);
            <?php endwhile; ?>
        <?php endif; ?>

        new Chart(dailyCtx, {
            type: 'line',
            data: dailyData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        function exportReport() {
            // Implement export functionality
            alert('Export functionality will be implemented here');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
