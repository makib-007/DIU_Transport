<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../config/database.php';

if (!isset($_GET['payment_id'])) {
    http_response_code(400);
    exit('Payment ID is required');
}

$payment_id = (int)$_GET['payment_id'];
$user_id = $_SESSION['user_id'];

// Get payment details with booking information
$sql = "SELECT p.*, b.booking_code, b.booking_date, b.seat_number, b.status as booking_status,
               s.departure_time, s.arrival_time, r.route_name, r.start_location, r.end_location
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE p.id = ? AND b.user_id = ? AND p.payment_status = 'pending'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('Payment not found or already completed');
}

$payment = $result->fetch_assoc();
?>

<div class="payment-form-container">
    <div class="payment-info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4>Payment Details</h4>
        <div class="grid grid-2">
            <div>
                <p><strong>Booking Code:</strong> <?php echo htmlspecialchars($payment['booking_code']); ?></p>
                <p><strong>Route:</strong> <?php echo htmlspecialchars($payment['start_location']); ?> → <?php echo htmlspecialchars($payment['end_location']); ?></p>
                <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($payment['booking_date'])); ?></p>
                <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($payment['departure_time'])); ?></p>
            </div>
            <div>
                <p><strong>Seat:</strong> <?php echo $payment['seat_number']; ?></p>
                <p><strong>Amount:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                <p><strong>Due Date:</strong> <?php echo date('M j, Y', strtotime($payment['created_at'] . ' + 24 hours')); ?></p>
                <p><strong>Status:</strong> <span class="status-badge status-pending">Pending</span></p>
            </div>
        </div>
    </div>

    <form class="payment-form" method="POST" onsubmit="processPayment(this); return false;">
        <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
        <input type="hidden" name="amount" value="<?php echo $payment['amount']; ?>">
        
        <div class="form-group">
            <label for="payment_method">
                <i class="fas fa-credit-card"></i>
                Payment Method
            </label>
            <select id="payment_method" name="payment_method" required onchange="showPaymentDetails()">
                <option value="">Select Payment Method</option>
                <option value="bkash">bKash</option>
                <option value="nagad">Nagad</option>
                <option value="rocket">Rocket</option>
                <option value="debit_card">Debit Card</option>
                <option value="one_card">1 Card</option>
                <option value="cash">Cash</option>
            </select>
        </div>
        
        <!-- Payment Method Details -->
        <div id="payment_details" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h5 id="method_title">Payment Method Details</h5>
            <div id="method_info"></div>
        </div>
        
        <div class="form-group">
            <label for="transaction_id">
                <i class="fas fa-receipt"></i>
                <span id="transaction_label">Transaction ID/Reference</span>
            </label>
            <input type="text" id="transaction_id" name="transaction_id" 
                   placeholder="Enter transaction ID or reference number" required>
            <small id="transaction_help" style="color: #666;">For mobile banking, enter the transaction ID. For cash, enter "CASH".</small>
        </div>
        
        <div class="form-group">
            <label for="payer_name">
                <i class="fas fa-user"></i>
                Payer Name
            </label>
            <input type="text" id="payer_name" name="payer_name" 
                   value="<?php echo htmlspecialchars($_SESSION['name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="payer_phone">
                <i class="fas fa-phone"></i>
                Payer Phone
            </label>
            <input type="tel" id="payer_phone" name="payer_phone" 
                   placeholder="Enter your phone number" required>
        </div>
        
        <div class="payment-summary" style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4>Payment Summary</h4>
            <div class="grid grid-2">
                <div>
                    <p><strong>Amount Due:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Payment Method:</strong> <span id="method-display">-</span></p>
                </div>
                <div>
                    <p><strong>Transaction ID:</strong> <span id="txn-display">-</span></p>
                    <p><strong>Status:</strong> <span class="status-badge status-pending">Pending</span></p>
                </div>
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" onclick="closeModal('paymentModal')" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" onclick="processDummyPayment()" class="btn btn-success" id="dummy_payment_btn" style="display: none;">
                <i class="fas fa-magic"></i> Process Dummy Payment
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-credit-card"></i> Process Payment
            </button>
        </div>
    </form>
</div>

<script>
// Payment method details
const paymentMethods = {
    bkash: {
        title: 'bKash Payment',
        info: `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p><strong>Account Number:</strong> 01712345678</p>
                    <p><strong>Account Type:</strong> Personal</p>
                    <p><strong>Transaction Type:</strong> Send Money</p>
                </div>
                <div>
                    <p><strong>Amount:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Reference:</strong> DIU-<?php echo $payment['booking_code']; ?></p>
                    <p><strong>Note:</strong> Enter transaction ID after payment</p>
                </div>
            </div>
        `,
        label: 'bKash Transaction ID',
        placeholder: 'Enter bKash transaction ID (e.g., TXN123456789)',
        help: 'Enter the transaction ID received from bKash after payment'
    },
    nagad: {
        title: 'Nagad Payment',
        info: `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p><strong>Account Number:</strong> 01712345678</p>
                    <p><strong>Account Type:</strong> Personal</p>
                    <p><strong>Transaction Type:</strong> Send Money</p>
                </div>
                <div>
                    <p><strong>Amount:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Reference:</strong> DIU-<?php echo $payment['booking_code']; ?></p>
                    <p><strong>Note:</strong> Enter transaction ID after payment</p>
                </div>
            </div>
        `,
        label: 'Nagad Transaction ID',
        placeholder: 'Enter Nagad transaction ID (e.g., NGD123456789)',
        help: 'Enter the transaction ID received from Nagad after payment'
    },
    rocket: {
        title: 'Rocket Payment',
        info: `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p><strong>Account Number:</strong> 01712345678</p>
                    <p><strong>Account Type:</strong> Personal</p>
                    <p><strong>Transaction Type:</strong> Send Money</p>
                </div>
                <div>
                    <p><strong>Amount:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Reference:</strong> DIU-<?php echo $payment['booking_code']; ?></p>
                    <p><strong>Note:</strong> Enter transaction ID after payment</p>
                </div>
            </div>
        `,
        label: 'Rocket Transaction ID',
        placeholder: 'Enter Rocket transaction ID (e.g., RKT123456789)',
        help: 'Enter the transaction ID received from Rocket after payment'
    },
    debit_card: {
        title: 'Debit Card Payment',
        info: `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p><strong>Card Types:</strong> Visa, Mastercard, Amex</p>
                    <p><strong>Processing:</strong> Secure SSL</p>
                    <p><strong>Currency:</strong> BDT</p>
                </div>
                <div>
                    <p><strong>Amount:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Reference:</strong> DIU-<?php echo $payment['booking_code']; ?></p>
                    <p><strong>Note:</strong> Enter authorization code</p>
                </div>
            </div>
        `,
        label: 'Authorization Code',
        placeholder: 'Enter card authorization code (e.g., AUTH123456)',
        help: 'Enter the authorization code received after card payment'
    },
    one_card: {
        title: '1 Card Payment',
        info: `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p><strong>Card Type:</strong> 1 Card</p>
                    <p><strong>Processing:</strong> Secure SSL</p>
                    <p><strong>Currency:</strong> BDT</p>
                </div>
                <div>
                    <p><strong>Amount:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Reference:</strong> DIU-<?php echo $payment['booking_code']; ?></p>
                    <p><strong>Note:</strong> Enter authorization code</p>
                </div>
            </div>
        `,
        label: '1 Card Authorization Code',
        placeholder: 'Enter 1 Card authorization code (e.g., 1CARD123456)',
        help: 'Enter the authorization code received after 1 Card payment'
    },
    cash: {
        title: 'Cash Payment',
        info: `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p><strong>Payment Location:</strong> DIU Transport Office</p>
                    <p><strong>Office Hours:</strong> 9:00 AM - 5:00 PM</p>
                    <p><strong>Contact:</strong> 01712345678</p>
                </div>
                <div>
                    <p><strong>Amount:</strong> ৳<?php echo number_format($payment['amount'], 2); ?></p>
                    <p><strong>Reference:</strong> DIU-<?php echo $payment['booking_code']; ?></p>
                    <p><strong>Note:</strong> Get receipt from office</p>
                </div>
            </div>
        `,
        label: 'Receipt Number',
        placeholder: 'Enter receipt number from transport office',
        help: 'Enter the receipt number received from transport office'
    }
};

// Show payment method details
function showPaymentDetails() {
    const method = document.getElementById('payment_method').value;
    const detailsDiv = document.getElementById('payment_details');
    const methodTitle = document.getElementById('method_title');
    const methodInfo = document.getElementById('method_info');
    const transactionLabel = document.getElementById('transaction_label');
    const transactionInput = document.getElementById('transaction_id');
    const transactionHelp = document.getElementById('transaction_help');
    const dummyBtn = document.getElementById('dummy_payment_btn');
    
    if (method && paymentMethods[method]) {
        const methodData = paymentMethods[method];
        methodTitle.textContent = methodData.title;
        methodInfo.innerHTML = methodData.info;
        transactionLabel.textContent = methodData.label;
        transactionInput.placeholder = methodData.placeholder;
        transactionHelp.textContent = methodData.help;
        detailsDiv.style.display = 'block';
        
        // Show dummy payment button for all methods except cash
        if (method !== 'cash') {
            dummyBtn.style.display = 'inline-block';
        } else {
            dummyBtn.style.display = 'none';
        }
    } else {
        detailsDiv.style.display = 'none';
        dummyBtn.style.display = 'none';
    }
    
    // Update payment summary
    const methodDisplay = document.getElementById('method-display');
    methodDisplay.textContent = method ? method.toUpperCase() : '-';
}

// Process dummy payment
function processDummyPayment() {
    const method = document.getElementById('payment_method').value;
    const payerName = document.getElementById('payer_name').value;
    const payerPhone = document.getElementById('payer_phone').value;
    
    if (!method) {
        alert('Please select a payment method first.');
        return;
    }
    
    if (!payerName || !payerPhone) {
        alert('Please fill in payer name and phone number.');
        return;
    }
    
    // Generate dummy transaction ID based on method
    let dummyTransactionId = '';
    switch(method) {
        case 'bkash':
            dummyTransactionId = 'BKASH' + Math.random().toString(36).substr(2, 9).toUpperCase();
            break;
        case 'nagad':
            dummyTransactionId = 'NGD' + Math.random().toString(36).substr(2, 9).toUpperCase();
            break;
        case 'rocket':
            dummyTransactionId = 'RKT' + Math.random().toString(36).substr(2, 9).toUpperCase();
            break;
        case 'debit_card':
            dummyTransactionId = 'AUTH' + Math.random().toString(36).substr(2, 9).toUpperCase();
            break;
        case 'one_card':
            dummyTransactionId = '1CARD' + Math.random().toString(36).substr(2, 9).toUpperCase();
            break;
    }
    
    // Fill in the transaction ID
    document.getElementById('transaction_id').value = dummyTransactionId;
    
    // Show success message
    alert(`Dummy payment processed successfully!\n\nPayment Method: ${method.toUpperCase()}\nTransaction ID: ${dummyTransactionId}\n\nYou can now submit the payment form.`);
}

// Update payment summary when form fields change
document.getElementById('transaction_id').addEventListener('input', function() {
    const txn = this.value;
    const txnDisplay = document.getElementById('txn-display');
    txnDisplay.textContent = txn || '-';
});

// Form validation
document.querySelector('.payment-form').addEventListener('submit', function(e) {
    const paymentMethod = document.getElementById('payment_method').value;
    const transactionId = document.getElementById('transaction_id').value;
    const payerName = document.getElementById('payer_name').value;
    const payerPhone = document.getElementById('payer_phone').value;
    
    if (!paymentMethod) {
        e.preventDefault();
        alert('Please select a payment method.');
        return;
    }
    
    if (!transactionId) {
        e.preventDefault();
        alert('Please enter a transaction ID or reference.');
        return;
    }
    
    if (!payerName) {
        e.preventDefault();
        alert('Please enter payer name.');
        return;
    }
    
    if (!payerPhone) {
        e.preventDefault();
        alert('Please enter payer phone number.');
        return;
    }
    
    // Phone number validation
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,15}$/;
    if (!phoneRegex.test(payerPhone)) {
        e.preventDefault();
        alert('Please enter a valid phone number.');
        return;
    }
});
</script>
