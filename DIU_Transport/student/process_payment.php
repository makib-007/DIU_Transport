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
$required_fields = ['payment_id', 'payment_method', 'transaction_id', 'payer_name', 'payer_phone'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$payment_id = (int)$_POST['payment_id'];
$payment_method = trim($_POST['payment_method']);
$transaction_id = trim($_POST['transaction_id']);
$payer_name = trim($_POST['payer_name']);
$payer_phone = trim($_POST['payer_phone']);
$user_id = $_SESSION['user_id'];

// Validate payment method
$valid_methods = ['bkash', 'nagad', 'rocket', 'card', 'bank', 'cash'];
if (!in_array($payment_method, $valid_methods)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit();
}

// Verify payment exists and belongs to user
$sql = "SELECT p.*, b.user_id, b.status as booking_status 
        FROM payments p 
        JOIN bookings b ON p.booking_id = b.id 
        WHERE p.id = ? AND b.user_id = ? AND p.payment_status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Payment not found or already completed']);
    exit();
}

$payment = $result->fetch_assoc();

// Check if booking is still valid
if ($payment['booking_status'] !== 'pending' && $payment['booking_status'] !== 'confirmed') {
    echo json_encode(['success' => false, 'message' => 'Booking is no longer valid for payment']);
    exit();
}

// Check if payment is within 24 hours of booking
$booking_time = new DateTime($payment['created_at']);
$current_time = new DateTime();
$time_diff = $current_time->diff($booking_time);

if ($time_diff->days > 0 || $time_diff->h > 24) {
    echo json_encode(['success' => false, 'message' => 'Payment time has expired. Please contact transport office.']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Update payment record
    $sql = "UPDATE payments SET 
            payment_status = 'completed',
            payment_method = ?,
            transaction_id = ?,
            payer_name = ?,
            payer_phone = ?,
            payment_date = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $payment_method, $transaction_id, $payer_name, $payer_phone, $payment_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update payment');
    }
    
    // Update booking status to confirmed
    $sql = "UPDATE bookings SET status = 'confirmed' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $payment['booking_id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update booking status');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully!',
        'transaction_id' => $transaction_id,
        'redirect_url' => 'payments.php'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Payment failed: ' . $e->getMessage()]);
}
?>
