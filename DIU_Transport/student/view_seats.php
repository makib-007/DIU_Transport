<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get schedule ID from URL parameter
$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

// Get schedule details
$schedule = null;
if ($schedule_id > 0) {
    $sql = "SELECT s.*, b.bus_number, b.capacity, r.route_name, r.start_location, r.end_location, r.fare
            FROM schedules s
            JOIN buses b ON s.bus_id = b.id
            JOIN routes r ON s.route_id = r.id
            WHERE s.id = ? AND s.status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $schedule = $result->fetch_assoc();
    }
}

// Get user's booked seats for this schedule
$user_booked_seats = [];
if ($schedule) {
    $sql = "SELECT seat_number, booking_date, status FROM bookings 
            WHERE user_id = ? AND schedule_id = ? AND status IN ('pending', 'confirmed')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $user_booked_seats[] = $row;
    }
}

// Get all booked seats for this schedule
$all_booked_seats = [];
if ($schedule) {
    $sql = "SELECT seat_number, booking_date, status FROM bookings 
            WHERE schedule_id = ? AND status IN ('pending', 'confirmed')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $all_booked_seats[] = $row['seat_number'];
    }
}

// Get user's all bookings
$sql = "SELECT b.*, s.departure_time, s.arrival_time, r.route_name, r.start_location, r.end_location
        FROM bookings b
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE b.user_id = ? AND b.status IN ('pending', 'confirmed')
        ORDER BY b.booking_date ASC, s.departure_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_bookings = $stmt->get_result();

// Get available schedules for seat viewing
$sql = "SELECT s.*, b.bus_number, b.capacity, r.route_name, r.start_location, r.end_location, r.fare
        FROM schedules s
        JOIN buses b ON s.bus_id = b.id
        JOIN routes r ON s.route_id = r.id
        WHERE s.status = 'active' AND s.available_seats > 0
        ORDER BY s.departure_time ASC";
$schedules = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Seats - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .seat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        
        .seat {
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .seat.available {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .seat.booked {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
            cursor: not-allowed;
        }
        
        .seat.my-booking {
            background: #cce5ff;
            border-color: #007bff;
            color: #004085;
        }
        
        .seat:hover.available {
            background: #c3e6cb;
            transform: scale(1.05);
        }
        
        .seat-legend {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            justify-content: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-chair"></i> View Seats</h1>
                    <p>View your booked seats and available seats for schedules</p>
                </div>
                <div class="user-info">
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="schedules.php"><i class="fas fa-clock"></i> View Schedules</a></li>
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> My Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="content-header">
                    <h2>Seat Management</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <!-- My Booked Seats -->
                <div class="card">
                    <div class="card-header">
                        <h3>My Booked Seats</h3>
                        <span class="badge badge-primary"><?php echo $user_bookings->num_rows; ?> bookings</span>
                    </div>
                    
                    <?php if ($user_bookings->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Booking Code</th>
                                        <th>Route</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Seat</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = $user_bookings->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['start_location']); ?> → 
                                                <?php echo htmlspecialchars($booking['end_location']); ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($booking['departure_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($booking['arrival_time'])); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-confirmed">
                                                    Seat <?php echo $booking['seat_number']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view_seats.php?schedule_id=<?php echo $booking['schedule_id']; ?>" 
                                                   class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">
                                                    <i class="fas fa-eye"></i> View Seats
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px;">
                            <i class="fas fa-chair" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                            <p>No booked seats found. <a href="schedules.php">Book your first seat!</a></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Select Schedule for Seat Viewing -->
                <div class="card">
                    <div class="card-header">
                        <h3>View Seats for Schedule</h3>
                    </div>
                    
                    <form method="GET" class="schedule-selector">
                        <div class="form-group">
                            <label for="schedule_id">
                                <i class="fas fa-clock"></i>
                                Select Schedule
                            </label>
                            <select id="schedule_id" name="schedule_id" onchange="this.form.submit()">
                                <option value="">Choose a schedule to view seats</option>
                                <?php while ($sch = $schedules->fetch_assoc()): ?>
                                    <option value="<?php echo $sch['id']; ?>" 
                                            <?php echo $schedule_id == $sch['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sch['route_name']); ?> - 
                                        <?php echo date('g:i A', strtotime($sch['departure_time'])); ?> 
                                        (<?php echo $sch['available_seats']; ?> seats available)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                
                <!-- Seat Layout -->
                <?php if ($schedule): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Seat Layout - <?php echo htmlspecialchars($schedule['route_name']); ?></h3>
                        <div>
                            <span class="badge badge-info">Bus: <?php echo htmlspecialchars($schedule['bus_number']); ?></span>
                            <span class="badge badge-success"><?php echo $schedule['available_seats']; ?> seats available</span>
                        </div>
                    </div>
                    
                    <div class="schedule-info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div class="grid grid-3">
                            <div>
                                <p><strong>Route:</strong> <?php echo htmlspecialchars($schedule['start_location']); ?> → <?php echo htmlspecialchars($schedule['end_location']); ?></p>
                                <p><strong>Departure:</strong> <?php echo date('g:i A', strtotime($schedule['departure_time'])); ?></p>
                            </div>
                            <div>
                                <p><strong>Arrival:</strong> <?php echo date('g:i A', strtotime($schedule['arrival_time'])); ?></p>
                                <p><strong>Capacity:</strong> <?php echo $schedule['capacity']; ?> seats</p>
                            </div>
                            <div>
                                <p><strong>Fare:</strong> ৳<?php echo number_format($schedule['fare'], 2); ?></p>
                                <p><strong>Available:</strong> <?php echo $schedule['available_seats']; ?> seats</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seat Legend -->
                    <div class="seat-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #d4edda; border: 2px solid #28a745;"></div>
                            <span>Available</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #f8d7da; border: 2px solid #dc3545;"></div>
                            <span>Booked</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #cce5ff; border: 2px solid #007bff;"></div>
                            <span>My Booking</span>
                        </div>
                    </div>
                    
                    <!-- Seat Grid -->
                    <div class="seat-grid">
                        <?php for ($i = 1; $i <= $schedule['capacity']; $i++): ?>
                            <?php 
                            $is_booked = in_array($i, $all_booked_seats);
                            $is_my_booking = false;
                            foreach ($user_booked_seats as $user_seat) {
                                if ($user_seat['seat_number'] == $i) {
                                    $is_my_booking = true;
                                    break;
                                }
                            }
                            
                            $seat_class = 'available';
                            $seat_text = "Seat $i";
                            
                            if ($is_my_booking) {
                                $seat_class = 'my-booking';
                                $seat_text = "Seat $i (Yours)";
                            } elseif ($is_booked) {
                                $seat_class = 'booked';
                                $seat_text = "Seat $i (Booked)";
                            }
                            ?>
                            
                            <div class="seat <?php echo $seat_class; ?>" 
                                 title="<?php echo $seat_text; ?>">
                                <?php echo $seat_text; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- My Bookings for This Schedule -->
                    <?php if (!empty($user_booked_seats)): ?>
                    <div style="margin-top: 30px;">
                        <h4><i class="fas fa-user-check"></i> My Bookings for This Schedule</h4>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Seat</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_booked_seats as $user_seat): ?>
                                        <tr>
                                            <td><strong>Seat <?php echo $user_seat['seat_number']; ?></strong></td>
                                            <td><?php echo date('M j, Y', strtotime($user_seat['booking_date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user_seat['status']; ?>">
                                                    <?php echo ucfirst($user_seat['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view_booking.php?id=<?php echo $user_seat['id']; ?>" 
                                                   class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Seat Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Seat Information</h3>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <h4><i class="fas fa-info-circle"></i> Seat Selection</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li>Seats are allocated on a first-come, first-served basis</li>
                                <li>You can view available seats for any schedule</li>
                                <li>Your booked seats are highlighted in blue</li>
                                <li>Booked seats by others are shown in red</li>
                                <li>Available seats are shown in green</li>
                            </ul>
                        </div>
                        <div>
                            <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li>Seat numbers are fixed and cannot be changed after booking</li>
                                <li>Arrive 10 minutes before departure time</li>
                                <li>Bring your student ID for verification</li>
                                <li>Contact transport office for seat-related issues</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>
