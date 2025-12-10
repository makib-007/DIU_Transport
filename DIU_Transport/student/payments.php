<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get payment statistics
$sql = "SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as total_amount
        FROM payments p 
        JOIN bookings b ON p.booking_id = b.id 
        WHERE b.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get all payments with booking details
$sql = "SELECT p.*, b.booking_code, b.booking_date, b.seat_number, b.status as booking_status,
               s.departure_time, s.arrival_time, r.route_name, r.start_location, r.end_location
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE b.user_id = ?
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result();

// Get pending payments for quick actions
$sql = "SELECT p.*, b.booking_code, b.booking_date, b.seat_number,
               s.departure_time, r.route_name, r.start_location, r.end_location
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE b.user_id = ? AND p.payment_status = 'pending'
        ORDER BY b.booking_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_payments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-credit-card"></i> Payments</h1>
                    <p>Manage your transport payments and view payment history</p>
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
                    <li><a href="payments.php" class="active"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="view_seats.php"><i class="fas fa-chair"></i> View Seats</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="content-header">
                    <h2>Payment Overview</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <!-- Payment Statistics -->
                <div class="grid grid-4">
                    <div class="stats-card">
                        <h3><?php echo $stats['total_payments']; ?></h3>
                        <p><i class="fas fa-credit-card"></i> Total Payments</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $stats['completed_payments']; ?></h3>
                        <p><i class="fas fa-check-circle"></i> Completed</p>
                    </div>
                    <div class="stats-card">
                        <h3><?php echo $stats['pending_payments']; ?></h3>
                        <p><i class="fas fa-clock"></i> Pending</p>
                    </div>
                    <div class="stats-card">
                        <h3>৳<?php echo number_format($stats['total_amount'], 2); ?></h3>
                        <p><i class="fas fa-money-bill-wave"></i> Total Paid</p>
                    </div>
                </div>
                
                <!-- Pending Payments -->
                <?php if ($pending_payments->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Pending Payments</h3>
                        <span class="badge badge-warning"><?php echo $pending_payments->num_rows; ?> pending</span>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking Code</th>
                                    <th>Route</th>
                                    <th>Date & Time</th>
                                    <th>Seat</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $pending_payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($payment['booking_code']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['start_location']); ?> → 
                                            <?php echo htmlspecialchars($payment['end_location']); ?>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($payment['booking_date'])); ?><br>
                                            <small><?php echo date('g:i A', strtotime($payment['departure_time'])); ?></small>
                                        </td>
                                        <td><?php echo $payment['seat_number']; ?></td>
                                        <td><strong>৳<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                        <td>
                                            <?php 
                                            $due_date = date('M j, Y', strtotime($payment['created_at'] . ' + 24 hours'));
                                            echo $due_date;
                                            ?>
                                        </td>
                                        <td>
                                            <button onclick="openPaymentModal(<?php echo $payment['id']; ?>)" 
                                                    class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">
                                                <i class="fas fa-credit-card"></i> Pay Now
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- All Payments -->
                <div class="card">
                    <div class="card-header">
                        <h3>Payment History</h3>
                        <div>
                            <button onclick="exportTable('payments-table', 'csv')" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($payments->num_rows > 0): ?>
                        <div class="table-container">
                            <table id="payments-table">
                                <thead>
                                    <tr>
                                        <th>Payment ID</th>
                                        <th>Booking Code</th>
                                        <th>Route</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payment = $payments->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $payment['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($payment['booking_code']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['start_location']); ?> → 
                                                <?php echo htmlspecialchars($payment['end_location']); ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($payment['booking_date'])); ?></td>
                                            <td><strong>৳<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                            <td>
                                                <?php if ($payment['payment_method']): ?>
                                                    <i class="fas fa-<?php echo getPaymentMethodIcon($payment['payment_method']); ?>"></i>
                                                    <?php echo ucfirst($payment['payment_method']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                                    <?php echo ucfirst($payment['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                            <td>
                                                <?php if ($payment['payment_status'] === 'completed'): ?>
                                                    <a href="download_receipt.php?payment_id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem;">
                                                        <i class="fas fa-download"></i> Receipt
                                                    </a>
                                                <?php elseif ($payment['payment_status'] === 'pending'): ?>
                                                    <button onclick="openPaymentModal(<?php echo $payment['id']; ?>)" 
                                                            class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">
                                                        <i class="fas fa-credit-card"></i> Pay
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px;">
                            <i class="fas fa-credit-card" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                            <p>No payment history found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Payment Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Payment Information</h3>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <h4><i class="fas fa-info-circle"></i> Payment Methods</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li><strong>Mobile Banking:</strong> bKash, Nagad, Rocket</li>
                                <li><strong>Bank Transfer:</strong> Direct bank transfer</li>
                                <li><strong>Cash:</strong> At transport office</li>
                                <li><strong>Card:</strong> Credit/Debit cards</li>
                            </ul>
                        </div>
                        <div>
                            <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li>Payments must be completed within 24 hours of booking</li>
                                <li>Keep your payment receipt for verification</li>
                                <li>Refunds are processed within 3-5 business days</li>
                                <li>Contact transport office for payment issues</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div class="modal-content" style="background: white; margin: 5% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 600px; position: relative; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e9ecef; padding-bottom: 10px;">
                <h3 style="margin: 0; color: #333;">Make Payment</h3>
                <button onclick="closeModal('paymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
            <div id="paymentForm">
                <!-- Payment form will be loaded here via AJAX -->
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function openPaymentModal(paymentId) {
            // Show loading state
            document.getElementById('paymentForm').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #667eea;"></i><p style="margin-top: 10px;">Loading payment form...</p></div>';
            
            // Open modal first
            openModal('paymentModal');
            
            // Load payment form via AJAX
            fetch(`get_payment_form.php?payment_id=${paymentId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    document.getElementById('paymentForm').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('paymentForm').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #721c24;">
                            <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error loading payment form. Please try again.</p>
                            <button onclick="closeModal('paymentModal')" class="btn btn-secondary" style="margin-top: 10px;">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                    `;
                });
        }
        
        // Override the openModal function to properly handle scrolling
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                // Prevent background scrolling
                document.body.style.overflow = 'hidden';
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
            }
        }
        
        // Override the closeModal function to restore scrolling
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                // Restore background scrolling
                document.body.style.overflow = '';
                document.body.style.position = '';
                document.body.style.width = '';
            }
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('paymentModal');
            if (e.target === modal) {
                closeModal('paymentModal');
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('paymentModal');
                if (modal && modal.style.display === 'block') {
                    closeModal('paymentModal');
                }
            }
        });
        
        // Alert function for payment form
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.style.cssText = `
                padding: 12px 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
                color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
                border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
            `;
            
            const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
            alertDiv.innerHTML = `<i class="fas fa-${icon}" style="margin-right: 8px;"></i>${message}`;
            
            // Insert at the top of the form
            const form = document.querySelector('.payment-form');
            if (form) {
                form.parentNode.insertBefore(alertDiv, form);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        }
        
        // Payment submission function
        function processPayment(form) {
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            fetch('process_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Payment processed successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message || 'Payment failed. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
    </script>
</body>
</html>

<?php
function getPaymentMethodIcon($method) {
    switch ($method) {
        case 'bkash': return 'mobile-alt';
        case 'nagad': return 'mobile-alt';
        case 'rocket': return 'mobile-alt';
        case 'card': return 'credit-card';
        case 'bank': return 'university';
        case 'cash': return 'money-bill-wave';
        default: return 'credit-card';
    }
}
?>
