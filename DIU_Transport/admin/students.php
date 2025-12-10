<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Handle student deletion
if (isset($_POST['delete_student'])) {
    $student_id = (int)$_POST['student_id'];
    $sql = "DELETE FROM users WHERE id = ? AND role = 'student'";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            $success_message = "Student deleted successfully.";
        } else {
            $error_message = "Failed to delete student.";
        }
        $stmt->close();
    }
}

// Handle student status update
if (isset($_POST['update_student_status'])) {
    $student_id = (int)$_POST['student_id'];
    $new_status = $_POST['new_student_status'];
    
    $sql = "UPDATE users SET status = ? WHERE id = ? AND role = 'student'";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $new_status, $student_id);
        if ($stmt->execute()) {
            $success_message = "Student status updated successfully.";
        } else {
            $error_message = "Failed to update student status.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$sql = "SELECT u.*, 
               COUNT(b.id) as total_bookings,
               COUNT(CASE WHEN b.status IN ('pending', 'confirmed') THEN 1 END) as active_bookings,
               SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_paid
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE u.role = 'student'";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_filter)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$students = null;
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $students = $stmt->get_result();
    $stmt->close();
}

// Get student statistics
$stats = [];

// Total students
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
$result = $conn->query($sql);
$stats['total_students'] = $result->fetch_assoc()['total'];

// Active students
$sql = "SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = 'active'";
$result = $conn->query($sql);
$stats['active_students'] = $result->fetch_assoc()['total'];

// Students with bookings
$sql = "SELECT COUNT(DISTINCT u.id) as total FROM users u 
        JOIN bookings b ON u.id = b.user_id 
        WHERE u.role = 'student'";
$result = $conn->query($sql);
$stats['students_with_bookings'] = $result->fetch_assoc()['total'];

// New students this month
$sql = "SELECT COUNT(*) as total FROM users 
        WHERE role = 'student' AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$result = $conn->query($sql);
$stats['new_students_month'] = $result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-users"></i> Students</h1>
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
                    <li><a href="students.php" class="active"><i class="fas fa-users"></i> Students</a></li>
                    <li><a href="buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                    <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            <div class="content">
                <div class="content-header">
                    <h2>Student Management</h2>
                    <div class="header-actions">
                        <a href="add_student.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Student
                        </a>
                        <button onclick="exportStudents()" class="btn btn-secondary">
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

                <!-- Student Statistics -->
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
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['active_students']; ?></h3>
                            <p>Active Students</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['students_with_bookings']; ?></h3>
                            <p>Students with Bookings</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['new_students_month']; ?></h3>
                            <p>New This Month</p>
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
                                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Name, Email, Student ID, Phone">
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
                                <a href="students.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>All Students</h3>
                        <div class="search-box">
                            <input type="text" id="studentSearch" placeholder="Search students...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Bookings</th>
                                        <th>Total Paid</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($students && $students->num_rows > 0): ?>
                                        <?php while ($student = $students->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student['student_id']); ?></strong><br>
                                                    <small>ID: <?php echo $student['id']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($student['email']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student['phone']); ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?php echo $student['total_bookings']; ?></strong> total<br>
                                                    <small><?php echo $student['active_bookings']; ?> active</small>
                                                </td>
                                                <td>
                                                    <strong>৳<?php echo number_format($student['total_paid'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $student['status'] === 'active' ? 'success' : ($student['status'] === 'inactive' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($student['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($student['created_at'])); ?></strong><br>
                                                    <small><?php echo date('h:i A', strtotime($student['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="viewStudent(<?php echo $student['id']; ?>)" class="btn btn-sm btn-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button onclick="editStudentStatus(<?php echo $student['id']; ?>, '<?php echo $student['status']; ?>')" class="btn btn-sm btn-warning" title="Change Status">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this student? This will also delete all their bookings.');">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <button type="submit" name="delete_student" class="btn btn-sm btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No students found.</td>
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
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Student Status</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="studentForm">
                    <input type="hidden" name="student_id" id="modalStudentId">
                    <div class="form-group">
                        <label for="new_student_status">Status:</label>
                        <select name="new_student_status" id="new_student_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_student_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeStudentModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Student search functionality
        document.getElementById('studentSearch').addEventListener('keyup', function() {
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
        function editStudentStatus(studentId, currentStatus) {
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('new_student_status').value = currentStatus;
            document.getElementById('studentModal').style.display = 'block';
        }

        function closeStudentModal() {
            document.getElementById('studentModal').style.display = 'none';
        }

        function viewStudent(studentId) {
            // Implement view student details functionality
            alert('View student details for ID: ' + studentId);
        }

        function exportStudents() {
            // Implement export functionality
            alert('Export functionality will be implemented here');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target === modal) {
                closeStudentModal();
            }
        }

        // Close modal when clicking X
        document.querySelector('.close').onclick = function() {
            closeStudentModal();
        }
    </script>
</body>
</html>
