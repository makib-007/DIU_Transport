<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Handle bus deletion
if (isset($_POST['delete_bus'])) {
    $bus_id = (int)$_POST['bus_id'];
    $sql = "DELETE FROM buses WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $bus_id);
        if ($stmt->execute()) {
            $success_message = "Bus deleted successfully.";
        } else {
            $error_message = "Failed to delete bus.";
        }
        $stmt->close();
    }
}

// Handle bus status update
if (isset($_POST['update_bus_status'])) {
    $bus_id = (int)$_POST['bus_id'];
    $new_status = $_POST['new_bus_status'];
    
    $sql = "UPDATE buses SET status = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $new_status, $bus_id);
        if ($stmt->execute()) {
            $success_message = "Bus status updated successfully.";
        } else {
            $error_message = "Failed to update bus status.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$sql = "SELECT b.*, 
               COUNT(s.id) as total_schedules,
               COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_schedules,
               COUNT(DISTINCT bk.id) as total_bookings
        FROM buses b
        LEFT JOIN schedules s ON b.id = s.bus_id
        LEFT JOIN bookings bk ON s.id = bk.schedule_id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_filter)) {
    $sql .= " AND (b.bus_number LIKE ? OR b.model LIKE ? OR b.driver_name LIKE ? OR b.driver_phone LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$sql .= " GROUP BY b.id ORDER BY b.bus_number";

$buses = null;
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $buses = $stmt->get_result();
    $stmt->close();
}

// Get bus statistics
$stats = [];

// Total buses
$sql = "SELECT COUNT(*) as total FROM buses";
$result = $conn->query($sql);
$stats['total_buses'] = $result->fetch_assoc()['total'];

// Active buses
$sql = "SELECT COUNT(*) as total FROM buses WHERE status = 'active'";
$result = $conn->query($sql);
$stats['active_buses'] = $result->fetch_assoc()['total'];

// Total capacity
$sql = "SELECT SUM(capacity) as total FROM buses WHERE status = 'active'";
$result = $conn->query($sql);
$stats['total_capacity'] = $result->fetch_assoc()['total'] ?? 0;

// Buses in service
$sql = "SELECT COUNT(DISTINCT b.id) as total FROM buses b 
        JOIN schedules s ON b.id = s.bus_id 
        WHERE s.status = 'active'";
$result = $conn->query($sql);
$stats['buses_in_service'] = $result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buses - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-bus"></i> Buses</h1>
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
                    <li><a href="buses.php" class="active"><i class="fas fa-bus"></i> Buses</a></li>
                    <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            <div class="content">
                <div class="content-header">
                    <h2>Bus Management</h2>
                    <div class="header-actions">
                        <a href="add_bus.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Bus
                        </a>
                        <button onclick="exportBuses()" class="btn btn-secondary">
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

                <!-- Bus Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bus"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_buses']; ?></h3>
                            <p>Total Buses</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bus-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['active_buses']; ?></h3>
                            <p>Active Buses</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_capacity']; ?></h3>
                            <p>Total Capacity</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['buses_in_service']; ?></h3>
                            <p>In Service</p>
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
                                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Bus Number, Model, Driver Name, Phone">
                            </div>
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select name="status" id="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="retired" <?php echo $status_filter === 'retired' ? 'selected' : ''; ?>>Retired</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="buses.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>All Buses</h3>
                        <div class="search-box">
                            <input type="text" id="busSearch" placeholder="Search buses...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Bus Number</th>
                                        <th>Model</th>
                                        <th>Capacity</th>
                                        <th>Driver</th>
                                        <th>Schedules</th>
                                        <th>Bookings</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($buses && $buses->num_rows > 0): ?>
                                        <?php while ($bus = $buses->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($bus['bus_number']); ?></strong><br>
                                                    <small>ID: <?php echo $bus['id']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($bus['model']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $bus['capacity']; ?> seats</span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($bus['driver_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($bus['driver_phone']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo $bus['total_schedules']; ?></strong> total<br>
                                                    <small><?php echo $bus['active_schedules']; ?> active</small>
                                                </td>
                                                <td>
                                                    <strong><?php echo $bus['total_bookings']; ?></strong> bookings
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $bus['status'] === 'active' ? 'success' : ($bus['status'] === 'maintenance' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($bus['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="viewBus(<?php echo $bus['id']; ?>)" class="btn btn-sm btn-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <a href="edit_bus.php?id=<?php echo $bus['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button onclick="editBusStatus(<?php echo $bus['id']; ?>, '<?php echo $bus['status']; ?>')" class="btn btn-sm btn-warning" title="Change Status">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this bus? This will also delete all related schedules.');">
                                                            <input type="hidden" name="bus_id" value="<?php echo $bus['id']; ?>">
                                                            <button type="submit" name="delete_bus" class="btn btn-sm btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No buses found.</td>
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
    <div id="busModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Bus Status</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="busForm">
                    <input type="hidden" name="bus_id" id="modalBusId">
                    <div class="form-group">
                        <label for="new_bus_status">Status:</label>
                        <select name="new_bus_status" id="new_bus_status" required>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_bus_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeBusModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Bus search functionality
        document.getElementById('busSearch').addEventListener('keyup', function() {
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
        function editBusStatus(busId, currentStatus) {
            document.getElementById('modalBusId').value = busId;
            document.getElementById('new_bus_status').value = currentStatus;
            document.getElementById('busModal').style.display = 'block';
        }

        function closeBusModal() {
            document.getElementById('busModal').style.display = 'none';
        }

        function viewBus(busId) {
            // Implement view bus details functionality
            alert('View bus details for ID: ' + busId);
        }

        function exportBuses() {
            // Implement export functionality
            alert('Export functionality will be implemented here');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('busModal');
            if (event.target === modal) {
                closeBusModal();
            }
        }

        // Close modal when clicking X
        document.querySelector('.close').onclick = function() {
            closeBusModal();
        }
    </script>
</body>
</html>
