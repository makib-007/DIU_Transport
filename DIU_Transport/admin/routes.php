<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Handle route deletion
if (isset($_POST['delete_route'])) {
    $route_id = (int)$_POST['route_id'];
    $sql = "DELETE FROM routes WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $route_id);
        if ($stmt->execute()) {
            $success_message = "Route deleted successfully.";
        } else {
            $error_message = "Failed to delete route.";
        }
        $stmt->close();
    }
}

// Handle route status update
if (isset($_POST['update_route_status'])) {
    $route_id = (int)$_POST['route_id'];
    $new_status = $_POST['new_route_status'];
    
    $sql = "UPDATE routes SET status = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $new_status, $route_id);
        if ($stmt->execute()) {
            $success_message = "Route status updated successfully.";
        } else {
            $error_message = "Failed to update route status.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$sql = "SELECT r.*, 
               COUNT(s.id) as total_schedules,
               COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_schedules,
               COUNT(DISTINCT bk.id) as total_bookings,
               AVG(r.fare) as avg_fare
        FROM routes r
        LEFT JOIN schedules s ON r.id = s.route_id
        LEFT JOIN bookings bk ON s.id = bk.schedule_id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_filter)) {
    $sql .= " AND (r.route_name LIKE ? OR r.start_location LIKE ? OR r.end_location LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$sql .= " GROUP BY r.id ORDER BY r.route_name";

$routes = null;
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $routes = $stmt->get_result();
    $stmt->close();
}

// Get route statistics
$stats = [];

// Total routes
$sql = "SELECT COUNT(*) as total FROM routes";
$result = $conn->query($sql);
$stats['total_routes'] = $result->fetch_assoc()['total'];

// Active routes
$sql = "SELECT COUNT(*) as total FROM routes WHERE status = 'active'";
$result = $conn->query($sql);
$stats['active_routes'] = $result->fetch_assoc()['total'];

// Total distance
$sql = "SELECT SUM(distance_km) as total FROM routes WHERE status = 'active'";
$result = $conn->query($sql);
$stats['total_distance'] = $result->fetch_assoc()['total'] ?? 0;

// Routes with schedules
$sql = "SELECT COUNT(DISTINCT r.id) as total FROM routes r 
        JOIN schedules s ON r.id = s.route_id 
        WHERE s.status = 'active'";
$result = $conn->query($sql);
$stats['routes_with_schedules'] = $result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routes - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-route"></i> Routes</h1>
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
                    <li><a href="routes.php" class="active"><i class="fas fa-route"></i> Routes</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            <div class="content">
                <div class="content-header">
                    <h2>Route Management</h2>
                    <div class="header-actions">
                        <a href="add_route.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Route
                        </a>
                        <button onclick="exportRoutes()" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
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

                <!-- Route Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_routes']; ?></h3>
                            <p>Total Routes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['active_routes']; ?></h3>
                            <p>Active Routes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-road"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_distance'], 1); ?> km</h3>
                            <p>Total Distance</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['routes_with_schedules']; ?></h3>
                            <p>With Schedules</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="form-inline">
                            <div class="form-group">
                                <label for="search">Search:</label>
                                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Route Name, Start Location, End Location">
                            </div>
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select name="status" id="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="routes.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>All Routes</h3>
                        <div class="search-box">
                            <input type="text" id="routeSearch" placeholder="Search routes...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Route Name</th>
                                        <th>Start Location</th>
                                        <th>End Location</th>
                                        <th>Distance</th>
                                        <th>Duration</th>
                                        <th>Fare</th>
                                        <th>Schedules</th>
                                        <th>Bookings</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($routes && $routes->num_rows > 0): ?>
                                        <?php while ($route = $routes->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($route['route_name']); ?></strong><br>
                                                    <small>ID: <?php echo $route['id']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($route['start_location']); ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($route['end_location']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $route['distance_km']; ?> km</span>
                                                </td>
                                                <td>
                                                    <strong><?php echo $route['estimated_time_minutes']; ?> min</strong>
                                                </td>
                                                <td>
                                                    <strong>৳<?php echo number_format($route['fare'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?php echo $route['total_schedules']; ?></strong> total<br>
                                                    <small><?php echo $route['active_schedules']; ?> active</small>
                                                </td>
                                                <td>
                                                    <strong><?php echo $route['total_bookings']; ?></strong> bookings
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $route['status'] === 'active' ? 'success' : ($route['status'] === 'inactive' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($route['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="viewRoute(<?php echo $route['id']; ?>)" class="btn btn-sm btn-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <a href="edit_route.php?id=<?php echo $route['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button onclick="editRouteStatus(<?php echo $route['id']; ?>, '<?php echo $route['status']; ?>')" class="btn btn-sm btn-warning" title="Change Status">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this route? This will also delete all related schedules.');">
                                                            <input type="hidden" name="route_id" value="<?php echo $route['id']; ?>">
                                                            <button type="submit" name="delete_route" class="btn btn-sm btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center">No routes found.</td>
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

    <!-- Status Update Modal -->
    <div id="routeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Route Status</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="routeForm">
                    <input type="hidden" name="route_id" id="modalRouteId">
                    <div class="form-group">
                        <label for="new_route_status">Status:</label>
                        <select name="new_route_status" id="new_route_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_route_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeRouteModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Route search functionality
        document.getElementById('routeSearch').addEventListener('keyup', function() {
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

        // Modal functionality
        function editRouteStatus(routeId, currentStatus) {
            document.getElementById('modalRouteId').value = routeId;
            document.getElementById('new_route_status').value = currentStatus;
            document.getElementById('routeModal').style.display = 'block';
        }

        function closeRouteModal() {
            document.getElementById('routeModal').style.display = 'none';
        }

        function viewRoute(routeId) {
            // Implement view route details functionality
            alert('View route details for ID: ' + routeId);
        }

        function exportRoutes() {
            // Implement export functionality
            alert('Export functionality will be implemented here');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('routeModal');
            if (event.target === modal) {
                closeRouteModal();
            }
        }

        // Close modal when clicking X
        document.querySelector('.close').onclick = function() {
            closeRouteModal();
        }
    </script>
</body>
</html>
