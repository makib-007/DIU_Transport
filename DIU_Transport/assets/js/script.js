// DiuTransport - Main JavaScript File
// Daffodil International University Transport Management System

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all JavaScript functionality
    initFormValidation();
    initDynamicInteractions();
    initAjaxHandlers();
    initPrintFunctionality();
});

// Form Validation Functions
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const name = field.name;
    
    // Remove existing error messages
    removeFieldError(field);
    
    // Required field validation
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'This field is required.');
        return false;
    }
    
    // Email validation
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(field, 'Please enter a valid email address.');
            return false;
        }
    }
    
    // Phone validation
    if (name === 'phone' && value) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,15}$/;
        if (!phoneRegex.test(value)) {
            showFieldError(field, 'Please enter a valid phone number.');
            return false;
        }
    }
    
    // Date validation
    if (type === 'date' && value) {
        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            showFieldError(field, 'Please select a future date.');
            return false;
        }
    }
    
    // Number validation
    if (type === 'number' && value) {
        if (isNaN(value) || value < 0) {
            showFieldError(field, 'Please enter a valid positive number.');
            return false;
        }
    }
    
    return true;
}

function showFieldError(field, message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '5px';
    errorDiv.textContent = message;
    
    field.style.borderColor = '#dc3545';
    field.parentNode.appendChild(errorDiv);
}

function removeFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    field.style.borderColor = '#e1e5e9';
}

// Dynamic Interactions
function initDynamicInteractions() {
    // Real-time form validation
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.parentNode.querySelector('.field-error')) {
                validateField(this);
            }
        });
    });
    
    // Schedule filtering
    const filterInputs = document.querySelectorAll('.schedule-filter');
    filterInputs.forEach(input => {
        input.addEventListener('change', filterSchedules);
    });
    
    // Seat selection
    const seatButtons = document.querySelectorAll('.seat-btn');
    seatButtons.forEach(button => {
        button.addEventListener('click', function() {
            selectSeat(this);
        });
    });
    
    // Payment method selection
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            updatePaymentForm(this.value);
        });
    });
}

function filterSchedules() {
    const routeFilter = document.getElementById('route_filter')?.value || '';
    const dateFilter = document.getElementById('date_filter')?.value || '';
    const timeFilter = document.getElementById('time_filter')?.value || '';
    
    const scheduleRows = document.querySelectorAll('.schedule-row');
    
    scheduleRows.forEach(row => {
        let show = true;
        
        if (routeFilter && row.dataset.route !== routeFilter) {
            show = false;
        }
        
        if (dateFilter && row.dataset.date !== dateFilter) {
            show = false;
        }
        
        if (timeFilter && row.dataset.time !== timeFilter) {
            show = false;
        }
        
        row.style.display = show ? 'table-row' : 'none';
    });
}

function selectSeat(button) {
    const seatNumber = button.dataset.seat;
    const seatInput = document.getElementById('seat_number');
    
    // Remove previous selection
    document.querySelectorAll('.seat-btn.selected').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Select new seat
    button.classList.add('selected');
    if (seatInput) {
        seatInput.value = seatNumber;
    }
}

function updatePaymentForm(method) {
    const cardFields = document.getElementById('card_fields');
    const mobileFields = document.getElementById('mobile_fields');
    
    if (cardFields) cardFields.style.display = method === 'card' ? 'block' : 'none';
    if (mobileFields) mobileFields.style.display = method === 'mobile_banking' ? 'block' : 'none';
}

// AJAX Handlers
function initAjaxHandlers() {
    // Booking confirmation
    const bookingForms = document.querySelectorAll('.booking-form');
    bookingForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBooking(this);
        });
    });
    
    // Payment processing
    const paymentForms = document.querySelectorAll('.payment-form');
    paymentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            processPayment(this);
        });
    });
}

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

function processPayment(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
    submitBtn.disabled = true;
    
    fetch('process_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Payment successful! Transaction ID: ' + data.transaction_id, 'success');
            setTimeout(() => {
                window.location.href = data.redirect_url || 'payments.php';
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

// Utility Functions
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${getAlertIcon(type)}"></i>
        ${message}
    `;
    
    // Insert at the top of the content area
    const content = document.querySelector('.content') || document.querySelector('.container');
    content.insertBefore(alertDiv, content.firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function getAlertIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT'
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-BD', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatTime(timeString) {
    return new Date('2000-01-01T' + timeString).toLocaleTimeString('en-BD', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Print Functionality
function initPrintFunctionality() {
    const printButtons = document.querySelectorAll('.print-btn');
    printButtons.forEach(button => {
        button.addEventListener('click', function() {
            printPage();
        });
    });
}

function printPage() {
    window.print();
}

// Download functionality
function downloadTicket(bookingId) {
    window.open(`student/download_ticket.php?booking_id=${bookingId}`, '_blank');
}

// Search functionality
function searchTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const match = text.includes(searchTerm.toLowerCase());
        row.style.display = match ? 'table-row' : 'none';
    });
}

// Sort table functionality
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Try to sort as numbers if possible
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return aNum - bNum;
        }
        
        // Sort as strings
        return aValue.localeCompare(bValue);
    });
    
    // Reorder rows
    rows.forEach(row => tbody.appendChild(row));
}

// Export functionality
function exportTable(tableId, format = 'csv') {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => `"${col.textContent.trim()}"`).join(',');
        csv.push(rowData);
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `diutransport_${format}_${new Date().toISOString().split('T')[0]}.${format}`;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Modal functionality
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (e.target === modal) {
            closeModal(modal.id);
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + P for print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printPage();
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                closeModal(modal.id);
            }
        });
    }
});
