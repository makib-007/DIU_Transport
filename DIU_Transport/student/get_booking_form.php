<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../config/database.php';

if (!isset($_GET['schedule_id'])) {
    http_response_code(400);
    exit('Schedule ID is required');
}

$schedule_id = (int)$_GET['schedule_id'];

// Get schedule details
$sql = "SELECT s.*, b.bus_number, b.capacity, r.route_name, r.start_location, r.end_location, r.fare, r.estimated_time_minutes
        FROM schedules s
        JOIN buses b ON s.bus_id = b.id
        JOIN routes r ON s.route_id = r.id
        WHERE s.id = ? AND s.status = 'active' AND s.available_seats > 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit('Schedule not found or not available');
}

$schedule = $result->fetch_assoc();

// Get booked seats for this schedule
$sql = "SELECT seat_number FROM bookings WHERE schedule_id = ? AND status IN ('pending', 'confirmed')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$booked_seats_result = $stmt->get_result();

$booked_seats = [];
while ($row = $booked_seats_result->fetch_assoc()) {
    $booked_seats[] = $row['seat_number'];
}

// Generate available seats
$available_seats = [];
for ($i = 1; $i <= $schedule['capacity']; $i++) {
    if (!in_array($i, $booked_seats)) {
        $available_seats[] = $i;
    }
}
?>

<div class="booking-form-container">
    <div class="schedule-info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4>Schedule Details</h4>
        <div class="grid grid-2">
            <div>
                <p><strong>Route:</strong> <?php echo htmlspecialchars($schedule['route_name']); ?></p>
                <p><strong>From:</strong> <?php echo htmlspecialchars($schedule['start_location']); ?></p>
                <p><strong>To:</strong> <?php echo htmlspecialchars($schedule['end_location']); ?></p>
                <p><strong>Bus:</strong> <?php echo htmlspecialchars($schedule['bus_number']); ?></p>
            </div>
            <div>
                <p><strong>Departure:</strong> <?php echo date('g:i A', strtotime($schedule['departure_time'])); ?></p>
                <p><strong>Arrival:</strong> <?php echo date('g:i A', strtotime($schedule['arrival_time'])); ?></p>
                <p><strong>Duration:</strong> <?php echo $schedule['estimated_time_minutes']; ?> minutes</p>
                <p><strong>Fare:</strong> ৳<?php echo number_format($schedule['fare'], 2); ?></p>
            </div>
        </div>
    </div>

    <form class="booking-form" method="POST" onsubmit="submitBooking(this); return false;">
        <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
        <input type="hidden" name="fare" value="<?php echo $schedule['fare']; ?>">
        
        <div class="form-group">
            <label for="booking_date">
                <i class="fas fa-calendar"></i>
                Travel Date
            </label>
            <input type="date" id="booking_date" name="booking_date" required 
                   min="<?php echo date('Y-m-d'); ?>" 
                   max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
        </div>
        
        <div class="form-group">
            <label>
                <i class="fas fa-chair"></i>
                Select Seat
            </label>
            <div class="seat-selection" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-top: 10px;">
                <?php foreach ($available_seats as $seat): ?>
                    <button type="button" class="seat-btn" data-seat="<?php echo $seat; ?>" 
                            style="padding: 10px; border: 2px solid #e1e5e9; background: white; border-radius: 5px; cursor: pointer;">
                        Seat <?php echo $seat; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="seat_number" name="seat_number" required>
        </div>
        
        <div class="form-group">
            <label for="passenger_name">
                <i class="fas fa-user"></i>
                Passenger Name
            </label>
            <input type="text" id="passenger_name" name="passenger_name" 
                   value="<?php echo htmlspecialchars($_SESSION['name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="phone">
                <i class="fas fa-phone"></i>
                Contact Phone
            </label>
            <input type="tel" id="phone" name="phone" required>
        </div>
        
        <div class="booking-summary" style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4>Booking Summary</h4>
            <p><strong>Total Fare:</strong> ৳<?php echo number_format($schedule['fare'], 2); ?></p>
            <p><strong>Payment:</strong> Required within 24 hours</p>
            <p><strong>Booking Code:</strong> Will be generated after confirmation</p>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" onclick="closeModal('bookingModal')" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-ticket-alt"></i> Confirm Booking
            </button>
        </div>
    </form>
</div>

<script>
// Form validation
document.querySelector('.booking-form').addEventListener('submit', function(e) {
    const seatNumber = document.getElementById('seat_number').value;
    const bookingDate = document.getElementById('booking_date').value;
    
    if (!seatNumber) {
        e.preventDefault();
        alert('Please select a seat.');
        return;
    }
    
    if (!bookingDate) {
        e.preventDefault();
        alert('Please select a travel date.');
        return;
    }
    
    // Check if date is valid (not in the past)
    const selectedDate = new Date(bookingDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
        e.preventDefault();
        alert('Please select a future date.');
        return;
    }
});
</script>
