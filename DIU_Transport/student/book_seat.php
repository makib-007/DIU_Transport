<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate required fields
$required_fields = ['schedule_id', 'booking_date', 'seat_number', 'passenger_name', 'phone'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$schedule_id = (int)$_POST['schedule_id'];
$booking_date = $_POST['booking_date'];
$seat_number = (int)$_POST['seat_number'];
$passenger_name = trim($_POST['passenger_name']);
$phone = trim($_POST['phone']);
$user_id = $_SESSION['user_id'];

// Validate booking date
$selected_date = new DateTime($booking_date);
$today = new DateTime();
$today->setTime(0, 0, 0);

if ($selected_date < $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot book for past dates']);
    exit();
}

// Check if booking is within 7 days
$max_date = clone $today;
$max_date->add(new DateInterval('P7D'));
if ($selected_date > $max_date) {
    echo json_encode(['success' => false, 'message' => 'Cannot book more than 7 days in advance']);
    exit();
}

// Verify schedule exists and is available
$sql = "SELECT s.*, r.fare FROM schedules s 
        JOIN routes r ON s.route_id = r.id 
        WHERE s.id = ? AND s.status = 'active' AND s.available_seats > 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Schedule not available']);
    exit();
}

$schedule = $result->fetch_assoc();

// Check if seat is already booked for this schedule and date
$sql = "SELECT id FROM bookings 
        WHERE schedule_id = ? AND seat_number = ? AND booking_date = ? 
        AND status IN ('pending', 'confirmed')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $schedule_id, $seat_number, $booking_date);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Seat is already booked for this date']);
    exit();
}

// Check if user already has a booking for this schedule and date
$sql = "SELECT id FROM bookings 
        WHERE user_id = ? AND schedule_id = ? AND booking_date = ? 
        AND status IN ('pending', 'confirmed')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $user_id, $schedule_id, $booking_date);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have a booking for this schedule and date']);
    exit();
}

// Generate unique booking code
$booking_code = 'BK' . strtoupper(substr(md5(uniqid()), 0, 6));

// Start transaction
$conn->begin_transaction();

try {
    // Create booking
    $sql = "INSERT INTO bookings (user_id, schedule_id, booking_date, seat_number, status, booking_code) 
            VALUES (?, ?, ?, ?, 'pending', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $user_id, $schedule_id, $booking_date, $seat_number, $booking_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create booking');
    }
    
    $booking_id = $conn->insert_id;
    
    // Update available seats
    $sql = "UPDATE schedules SET available_seats = available_seats - 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $schedule_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update schedule');
    }
    
    // Create payment record
    $sql = "INSERT INTO payments (booking_id, amount, payment_status) VALUES (?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("id", $booking_id, $schedule['fare']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create payment record');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_code' => $booking_code,
        'booking_id' => $booking_id,
        'redirect_url' => 'payments.php?booking_id=' . $booking_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Booking failed: ' . $e->getMessage()]);
}
?>
