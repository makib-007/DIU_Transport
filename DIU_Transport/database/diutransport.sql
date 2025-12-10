-- DiuTransport Database Setup
-- For XAMPP MySQL

-- Create database
CREATE DATABASE IF NOT EXISTS diutransport;
USE diutransport;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    student_id VARCHAR(20) NULL,
    phone VARCHAR(15) NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Buses table
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number VARCHAR(20) UNIQUE NOT NULL,
    capacity INT NOT NULL,
    model VARCHAR(50) NULL,
    driver_name VARCHAR(100) NULL,
    driver_phone VARCHAR(15) NULL,
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Routes table
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(100) NOT NULL,
    start_location VARCHAR(100) NOT NULL,
    end_location VARCHAR(100) NOT NULL,
    distance_km DECIMAL(5,2) NULL,
    estimated_time_minutes INT NULL,
    fare DECIMAL(8,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Schedules table
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    route_id INT NOT NULL,
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    days_of_week VARCHAR(50) NOT NULL, -- Comma separated: Monday,Tuesday,Wednesday
    available_seats INT NOT NULL,
    status ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
);

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    booking_date DATE NOT NULL,
    seat_number INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    booking_code VARCHAR(10) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'bkash', 'nagad', 'rocket', 'debit_card', 'one_card') DEFAULT 'cash',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(50) NULL,
    payer_name VARCHAR(100) NULL,
    payer_phone VARCHAR(15) NULL,
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- Insert sample data

-- Sample users
INSERT INTO users (name, email, password, role, student_id, phone, status) VALUES
('Admin User', 'admin@diu.edu.bd', '123456789', 'admin', NULL, '+8801846182502', 'active'),
('Deluwar', 'student@diu.edu.bd', '123456789', 'student', '241-35-225', '+8801846182502', 'active'),
('Sisir', 'student2@diu.edu.bd', '123456789', 'student', '221-15-5679', '+8801912345678', 'active'),
('Talha', 'student3@diu.edu.bd', '123456789', 'student', '221-15-6041', '+8801612345678', 'active');

-- Sample buses
INSERT INTO buses (bus_number, capacity, model, driver_name, driver_phone) VALUES
('DIU-001', 45, 'Dolphin2', 'Md. Rahman', '+8801512345678'),
('DIU-002', 40, 'Dolphin3', 'Hamidur Rahman', '+8801522345678'),
('DIU-003', 35, 'Hanif34', 'Md. Salam', '+8801532345678'),
('DIU-004', 50, 'Surjomukhi12', 'Md. Aziz', '+8801542345678');

-- Sample routes
INSERT INTO routes (route_name, start_location, end_location, distance_km, estimated_time_minutes, fare) VALUES
('Route 1', 'Dhanmondi', 'DIU Main Campus', 12.5, 45, 40.00),
('Route 2', 'Gulshan', 'DIU Main Campus', 18.2, 60, 60.00),
('Route 3', 'Mirpur', 'DIU Main Campus', 15.8, 55, 30.00),
('Route 4', 'Uttara', 'DIU Main Campus', 22.0, 75, 40.00),
('Route 5', 'Narayanganj', 'DIU Main Campus', 16.5, 58, 80.00);

-- Sample schedules
INSERT INTO schedules (bus_id, route_id, departure_time, arrival_time, days_of_week, available_seats) VALUES
(1, 1, '07:00:00', '07:45:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 45),
(2, 2, '07:15:00', '08:15:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 40),
(3, 3, '07:30:00', '08:25:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 35),
(4, 4, '07:45:00', '09:00:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 50),
(1, 5, '08:00:00', '08:58:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 45),
(2, 1, '16:00:00', '16:45:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 40),
(3, 2, '16:15:00', '17:15:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 35),
(4, 3, '16:30:00', '17:25:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 50);

-- Sample bookings
INSERT INTO bookings (user_id, schedule_id, booking_date, seat_number, status, booking_code) VALUES
(2, 1, '2025-01-15', 15, 'confirmed', 'BK001'),
(2, 2, '2025-01-16', 22, 'confirmed', 'BK002'),
(3, 3, '2025-01-15', 8, 'confirmed', 'BK003'),
(4, 4, '2025-01-16', 33, 'pending', 'BK004');

-- Sample payments
INSERT INTO payments (booking_id, amount, payment_method, payment_status, transaction_id, payment_date) VALUES
(1, 50.00, 'cash', 'completed', 'TXN001', '2024-01-14 10:30:00'),
(2, 70.00, 'bkash', 'completed', 'TXN002', '2024-01-15 09:15:00'),
(3, 60.00, 'card', 'completed', 'TXN003', '2024-01-14 14:20:00'),
(4, 80.00, 'cash', 'pending', NULL, NULL);

-- Create indexes for better performance
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_booking_user ON bookings(user_id);
CREATE INDEX idx_booking_schedule ON bookings(schedule_id);
CREATE INDEX idx_schedule_bus ON schedules(bus_id);
CREATE INDEX idx_schedule_route ON schedules(route_id);
CREATE INDEX idx_payment_booking ON payments(booking_id);
