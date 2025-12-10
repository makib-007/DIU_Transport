<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$error_message = '';
$success_message = '';

// Get schedule ID from URL
$schedule_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($schedule_id <= 0) {
    header('Location: schedules.php');
    exit();
}

// Get schedule data
$sql = "SELECT * FROM schedules WHERE id = ?";
$schedule = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $schedule = $result->fetch_assoc();
    } else {
        header('Location: schedules.php');
        exit();
    }
    $stmt->close();
}

// Get buses for dropdown
$buses_sql = "SELECT id, bus_number, capacity FROM buses WHERE status = 'active' ORDER BY bus_number";
$buses = $conn->query($buses_sql);

// Get routes for dropdown
$routes_sql = "SELECT id, route_name, start_location, end_location, fare FROM routes WHERE status = 'active' ORDER BY route_name";
$routes = $conn->query($routes_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bus_id = (int)$_POST['bus_id'];
    $route_id = (int)$_POST['route_id'];
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $days_of_week = $_POST['days_of_week'];
    $available_seats = (int)$_POST['available_seats'];
    $status = $_POST['status'];

    // Validation
    if ($bus_id <= 0 || $route_id <= 0) {
        $error_message = 'Please select a valid bus and route.';
    } elseif (empty($departure_time) || empty($arrival_time)) {
        $error_message = 'Please enter both departure and arrival times.';
    } elseif (empty($days_of_week)) {
        $error_message = 'Please select at least one day of the week.';
    } elseif ($available_seats <= 0) {
        $error_message = 'Available seats must be greater than 0.';
    } else {
        // Update schedule
        $sql = "UPDATE schedules SET bus_id = ?, route_id = ?, departure_time = ?, arrival_time = ?, 
                days_of_week = ?, available_seats = ?, status = ? WHERE id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iisssisi", $bus_id, $route_id, $departure_time, $arrival_time, 
                             $days_of_week, $available_seats, $status, $schedule_id);
            
            if ($stmt->execute()) {
                $success_message = "Schedule updated successfully!";
                // Update local schedule data
                $schedule['bus_id'] = $bus_id;
                $schedule['route_id'] = $route_id;
                $schedule['departure_time'] = $departure_time;
                $schedule['arrival_time'] = $arrival_time;
                $schedule['days_of_week'] = $days_of_week;
                $schedule['available_seats'] = $available_seats;
                $schedule['status'] = $status;
            } else {
                $error_message = "Failed to update schedule. Please try again.";
            }
            $stmt->close();
        } else {
            $error_message = "Database error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-edit"></i> Edit Schedule</h1>
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
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>

            <div class="content">
                <div class="content-header">
                    <h2>Edit Bus Schedule</h2>
                    <a href="schedules.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Schedules
                    </a>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3>Schedule Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="bus_id">Bus *</label>
                                    <select name="bus_id" id="bus_id" required>
                                        <option value="">Select Bus</option>
                                        <?php if ($buses && $buses->num_rows > 0): ?>
                                            <?php while ($bus = $buses->fetch_assoc()): ?>
                                                <option value="<?php echo $bus['id']; ?>" <?php echo $schedule['bus_id'] == $bus['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($bus['bus_number']); ?> (Capacity: <?php echo $bus['capacity']; ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="route_id">Route *</label>
                                    <select name="route_id" id="route_id" required>
                                        <option value="">Select Route</option>
                                        <?php if ($routes && $routes->num_rows > 0): ?>
                                            <?php while ($route = $routes->fetch_assoc()): ?>
                                                <option value="<?php echo $route['id']; ?>" <?php echo $schedule['route_id'] == $route['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($route['route_name']); ?> (<?php echo htmlspecialchars($route['start_location']); ?> → <?php echo htmlspecialchars($route['end_location']); ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="departure_time">Departure Time *</label>
                                    <input type="time" name="departure_time" id="departure_time" value="<?php echo htmlspecialchars($schedule['departure_time']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="arrival_time">Arrival Time *</label>
                                    <input type="time" name="arrival_time" id="arrival_time" value="<?php echo htmlspecialchars($schedule['arrival_time']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Days of Week *</label>
                                <div class="checkbox-group">
                                    <?php
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    $selected_days = explode(',', $schedule['days_of_week']);
                                    foreach ($days as $day):
                                    ?>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="days[]" value="<?php echo $day; ?>" 
                                                   <?php echo in_array($day, $selected_days) ? 'checked' : ''; ?>>
                                            <?php echo $day; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="available_seats">Available Seats *</label>
                                    <input type="number" name="available_seats" id="available_seats" min="1" max="100" value="<?php echo htmlspecialchars($schedule['available_seats']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status">
                                        <option value="active" <?php echo $schedule['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="cancelled" <?php echo $schedule['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="completed" <?php echo $schedule['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Schedule
                                </button>
                                <a href="schedules.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Handle days of week selection
        document.querySelectorAll('input[name="days[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const selectedDays = Array.from(document.querySelectorAll('input[name="days[]"]:checked'))
                    .map(cb => cb.value);
                
                // Create hidden input for days_of_week
                let hiddenInput = document.querySelector('input[name="days_of_week"]');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'days_of_week';
                    document.querySelector('form').appendChild(hiddenInput);
                }
                hiddenInput.value = selectedDays.join(',');
            });
        });

        // Initialize days_of_week hidden input
        document.addEventListener('DOMContentLoaded', function() {
            const selectedDays = Array.from(document.querySelectorAll('input[name="days[]"]:checked'))
                .map(cb => cb.value);
            
            if (selectedDays.length > 0) {
                let hiddenInput = document.querySelector('input[name="days_of_week"]');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'days_of_week';
                    document.querySelector('form').appendChild(hiddenInput);
                }
                hiddenInput.value = selectedDays.join(',');
            }
        });
    </script>
</body>
</html>
