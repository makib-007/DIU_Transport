<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Get available schedules
$sql = "SELECT s.*, b.bus_number, b.capacity, r.route_name, r.start_location, r.end_location, r.fare, r.estimated_time_minutes
        FROM schedules s
        JOIN buses b ON s.bus_id = b.id
        JOIN routes r ON s.route_id = r.id
        WHERE s.status = 'active' AND s.available_seats > 0
        ORDER BY s.departure_time ASC";
$schedules = $conn->query($sql);

// Get routes for filter
$routes_sql = "SELECT * FROM routes WHERE status = 'active' ORDER BY route_name";
$routes = $conn->query($routes_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Schedules - DiuTransport</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-clock"></i> Bus Schedules</h1>
                    <p>View and book available transport schedules</p>
                </div>
                <div class="user-info">
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="schedules.php" class="active"><i class="fas fa-clock"></i> View Schedules</a></li>
                    <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> My Bookings</a></li>
                    <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="view_seats.php"><i class="fas fa-chair"></i> View Seats</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            
            <div class="content">
                <div class="content-header">
                    <h2>Available Schedules</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Filter Schedules</h3>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="route_filter">Route</label>
                            <select id="route_filter" class="schedule-filter">
                                <option value="">All Routes</option>
                                <?php while ($route = $routes->fetch_assoc()): ?>
                                    <option value="<?php echo $route['id']; ?>">
                                        <?php echo htmlspecialchars($route['route_name']); ?> 
                                        (<?php echo htmlspecialchars($route['start_location']); ?> → 
                                        <?php echo htmlspecialchars($route['end_location']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_filter">Date</label>
                            <input type="date" id="date_filter" class="schedule-filter" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="time_filter">Time</label>
                            <select id="time_filter" class="schedule-filter">
                                <option value="">All Times</option>
                                <option value="morning">Morning (6:00 AM - 12:00 PM)</option>
                                <option value="afternoon">Afternoon (12:00 PM - 6:00 PM)</option>
                                <option value="evening">Evening (6:00 PM - 12:00 AM)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Schedules Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Available Schedules</h3>
                        <div>
                            <button onclick="exportTable('schedules-table', 'csv')" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($schedules->num_rows > 0): ?>
                        <div class="table-container">
                            <table id="schedules-table">
                                <thead>
                                    <tr>
                                        <th>Route</th>
                                        <th>Bus</th>
                                        <th>Departure</th>
                                        <th>Arrival</th>
                                        <th>Duration</th>
                                        <th>Available Seats</th>
                                        <th>Fare</th>
                                        <th>Days</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($schedule = $schedules->fetch_assoc()): ?>
                                        <tr class="schedule-row" 
                                            data-route="<?php echo $schedule['route_id']; ?>"
                                            data-date="<?php echo date('Y-m-d'); ?>"
                                            data-time="<?php echo getTimeCategory($schedule['departure_time']); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($schedule['route_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($schedule['start_location']); ?> → 
                                                       <?php echo htmlspecialchars($schedule['end_location']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($schedule['bus_number']); ?></strong><br>
                                                <small>Capacity: <?php echo $schedule['capacity']; ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo date('g:i A', strtotime($schedule['departure_time'])); ?></strong>
                                            </td>
                                            <td>
                                                <strong><?php echo date('g:i A', strtotime($schedule['arrival_time'])); ?></strong>
                                            </td>
                                            <td><?php echo $schedule['estimated_time_minutes']; ?> min</td>
                                            <td>
                                                <span class="status-badge <?php echo $schedule['available_seats'] > 10 ? 'status-confirmed' : 'status-warning'; ?>">
                                                    <?php echo $schedule['available_seats']; ?> seats
                                                </span>
                                            </td>
                                            <td>
                                                <strong>৳<?php echo number_format($schedule['fare'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $days = explode(',', $schedule['days_of_week']);
                                                $shortDays = array_map(function($day) {
                                                    return substr($day, 0, 3);
                                                }, $days);
                                                echo implode(', ', $shortDays);
                                                ?>
                                            </td>
                                            <td>
                                                <button onclick="openBookingModal(<?php echo $schedule['id']; ?>)" 
                                                        class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">
                                                    <i class="fas fa-ticket-alt"></i> Book Seat
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px;">
                            <i class="fas fa-clock" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                            <p>No schedules available at the moment. Please check back later.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Booking Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Booking Information</h3>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <h4><i class="fas fa-info-circle"></i> How to Book</h4>
                            <ol style="margin-left: 20px; color: #666;">
                                <li>Select your preferred schedule from the table above</li>
                                <li>Click "Book Seat" to open the booking form</li>
                                <li>Choose your preferred seat number</li>
                                <li>Select your travel date</li>
                                <li>Complete the booking and make payment</li>
                            </ol>
                        </div>
                        <div>
                            <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                            <ul style="margin-left: 20px; color: #666;">
                                <li>Bookings are confirmed only after payment</li>
                                <li>Seats are allocated on first-come, first-served basis</li>
                                <li>You can cancel up to 2 hours before departure</li>
                                <li>Bring your student ID for verification</li>
                                <li>Arrive 10 minutes before departure time</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Booking Modal -->
    <div id="bookingModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div class="modal-content" style="background: white; margin: 5% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 600px; position: relative; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e9ecef; padding-bottom: 10px;">
                <h3 style="margin: 0; color: #333;">Book Your Seat</h3>
                <button onclick="closeModal('bookingModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
            <div id="bookingForm">
                <!-- Booking form will be loaded here via AJAX -->
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function openBookingModal(scheduleId) {
            // Show loading state
            document.getElementById('bookingForm').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #667eea;"></i><p style="margin-top: 10px;">Loading booking form...</p></div>';
            
            // Open modal first
            openModal('bookingModal');
            
            // Load booking form via AJAX
            fetch(`get_booking_form.php?schedule_id=${scheduleId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    document.getElementById('bookingForm').innerHTML = html;
                    // Initialize seat selection after form loads
                    setTimeout(() => {
                        initializeSeatSelection();
                    }, 100);
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('bookingForm').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #721c24;">
                            <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error loading booking form. Please try again.</p>
                            <button onclick="closeModal('bookingModal')" class="btn btn-secondary" style="margin-top: 10px;">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                    `;
                });
        }
        
        function getTimeCategory(time) {
            const hour = parseInt(time.split(':')[0]);
            if (hour >= 6 && hour < 12) return 'morning';
            if (hour >= 12 && hour < 18) return 'afternoon';
            return 'evening';
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
            const modal = document.getElementById('bookingModal');
            if (e.target === modal) {
                closeModal('bookingModal');
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('bookingModal');
                if (modal && modal.style.display === 'block') {
                    closeModal('bookingModal');
                }
            }
        });
        
        // Initialize seat selection after form loads
        function initializeSeatSelection() {
            document.querySelectorAll('.seat-btn').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove previous selection
                    document.querySelectorAll('.seat-btn').forEach(btn => {
                        btn.style.backgroundColor = 'white';
                        btn.style.borderColor = '#e1e5e9';
                        btn.style.color = '#333';
                    });
                    
                    // Select this seat
                    this.style.backgroundColor = '#667eea';
                    this.style.borderColor = '#667eea';
                    this.style.color = 'white';
                    
                    // Set the hidden input
                    document.getElementById('seat_number').value = this.dataset.seat;
                });
            });
        }
        
        // Alert function for booking form
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
            const form = document.querySelector('.booking-form');
            if (form) {
                form.parentNode.insertBefore(alertDiv, form);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        }
        
        // Booking submission function
        function submitBooking(form) {
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            fetch('book_seat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Booking successful! Your booking code is: ' + data.booking_code, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect_url || 'bookings.php';
                    }, 2000);
                } else {
                    showAlert(data.message || 'Booking failed. Please try again.', 'error');
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
function getTimeCategory($time) {
    $hour = (int)explode(':', $time)[0];
    if ($hour >= 6 && $hour < 12) return 'morning';
    if ($hour >= 12 && $hour < 18) return 'afternoon';
    return 'evening';
}
?>
