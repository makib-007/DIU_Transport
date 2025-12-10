<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

if (!isset($_GET['payment_id'])) {
    header('Location: payments.php');
    exit();
}

$payment_id = (int)$_GET['payment_id'];
$user_id = $_SESSION['user_id'];

// Get payment details with booking information
$sql = "SELECT p.*, b.booking_code, b.booking_date, b.seat_number, b.status as booking_status,
               s.departure_time, s.arrival_time, r.route_name, r.start_location, r.end_location,
               u.name as passenger_name, u.student_id
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        JOIN users u ON b.user_id = u.id
        WHERE p.id = ? AND b.user_id = ? AND p.payment_status = 'completed'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: payments.php');
    exit();
}

$payment = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .receipt {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 30px;
            font-family: 'Courier New', monospace;
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .receipt-subtitle {
            font-size: 14px;
            color: #666;
        }
        
        .receipt-body {
            margin-bottom: 20px;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        
        .receipt-row.total {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 18px;
            margin-top: 20px;
            padding-top: 20px;
        }
        
        .receipt-footer {
            text-align: center;
            border-top: 2px solid #333;
            padding-top: 20px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        
        .print-button {
            text-align: center;
            margin: 20px 0;
        }
        
        @media print {
            .print-button, .nav-menu, .dashboard-header {
                display: none;
            }
            .receipt {
                border: none;
                margin: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-receipt"></i> Payment Receipt</h1>
                    <p>Download and print your payment receipt</p>
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
                    <li><a href="view_seats.php"><i class="fas fa-chair"></i> View Seats</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="print-button">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Payments
                    </a>
                </div>
                
                <div class="receipt">
                    <div class="receipt-header">
                        <div class="receipt-title">DAFFODIL INTERNATIONAL UNIVERSITY</div>
                        <div class="receipt-subtitle">Transport Management System</div>
                        <div class="receipt-subtitle">DiuTransport - Payment Receipt</div>
                    </div>
                    
                    <div class="receipt-body">
                        <div class="receipt-row">
                            <span><strong>Receipt No:</strong></span>
                            <span>#<?php echo $payment['id']; ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Date:</strong></span>
                            <span><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Time:</strong></span>
                            <span><?php echo date('g:i A', strtotime($payment['payment_date'])); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Booking Code:</strong></span>
                            <span><?php echo htmlspecialchars($payment['booking_code']); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Student ID:</strong></span>
                            <span><?php echo htmlspecialchars($payment['student_id']); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Passenger Name:</strong></span>
                            <span><?php echo htmlspecialchars($payment['passenger_name']); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Route:</strong></span>
                            <span><?php echo htmlspecialchars($payment['start_location']); ?> → <?php echo htmlspecialchars($payment['end_location']); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Travel Date:</strong></span>
                            <span><?php echo date('M j, Y', strtotime($payment['booking_date'])); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Departure Time:</strong></span>
                            <span><?php echo date('g:i A', strtotime($payment['departure_time'])); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Seat Number:</strong></span>
                            <span><?php echo $payment['seat_number']; ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Payment Method:</strong></span>
                            <span><?php echo strtoupper($payment['payment_method']); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Transaction ID:</strong></span>
                            <span><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Payer Name:</strong></span>
                            <span><?php echo htmlspecialchars($payment['payer_name']); ?></span>
                        </div>
                        
                        <div class="receipt-row">
                            <span><strong>Payer Phone:</strong></span>
                            <span><?php echo htmlspecialchars($payment['payer_phone']); ?></span>
                        </div>
                        
                        <div class="receipt-row total">
                            <span><strong>TOTAL AMOUNT:</strong></span>
                            <span><strong>৳<?php echo number_format($payment['amount'], 2); ?></strong></span>
                        </div>
                    </div>
                    
                    <div class="receipt-footer">
                        <p><strong>Thank you for using DiuTransport!</strong></p>
                        <p>Please keep this receipt for your records.</p>
                        <p>For any queries, contact: transport@diu.edu.bd</p>
                        <p>Phone: +8809617901233</p>
                        <p>Developed By: Deluwar id:(241-35-225)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>
