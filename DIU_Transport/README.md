

# DiuTransport - University Transport Management System

A comprehensive web-based transport management system designed for Daffodil International University, Dhaka, Bangladesh. This system facilitates efficient management of university transport services including bus schedules, seat bookings, payments, and administrative oversight.

##  Features

###  User Management
- **Dual Role System**: Student and Admin roles with distinct dashboards
- **User Authentication**: Secure login/signup system with session management
- **Profile Management**: User profile updates and information management
- **Account Status**: Active, inactive, and suspended account states

###  Student Features
- **Schedule Viewing**: Browse available bus schedules with real-time seat availability
- **Smart Filtering**: Filter schedules by route, date, and time
- **Interactive Seat Booking**: Visual seat selection with real-time availability
- **Booking Management**: View, track, and manage all bookings
- **Payment Processing**: Multiple payment methods with dummy payment simulation
- **Ticket Management**: Download and view booking tickets
- **Booking History**: Complete history of all past and current bookings

###  Payment System
- **Multiple Payment Methods**:
  - Mobile Banking: bKash, Nagad, Rocket
  - Card Payments: Debit Card, 1 Card
  - Cash Payments: Office-based payments
- **Dummy Payment Simulation**: Test payment processing with realistic transaction IDs
- **Payment Tracking**: Monitor payment status and revenue
- **Receipt Generation**: Automated receipt generation for completed payments

###  Admin Features
- **Dashboard Analytics**: Comprehensive overview with statistics and charts
- **Schedule Management**: Add, edit, and delete bus schedules
- **Route Management**: Manage transport routes and fare structures
- **Bus Management**: Track buses, drivers, and vehicle information
- **Booking Oversight**: View all student bookings and manage status
- **Payment Administration**: Monitor all payments and generate reports
- **Student Management**: Manage student accounts and information
- **Report Generation**: Export data and generate comprehensive reports

###  Reporting & Analytics
- **Real-time Statistics**: Live dashboard with key metrics
- **Data Export**: CSV export functionality for all data tables
- **Payment Reports**: Detailed payment tracking and revenue analysis
- **Booking Analytics**: Booking patterns and seat utilization reports

##  Technology Stack

### Frontend
- **HTML5**: Semantic markup and structure
- **CSS3**: Modern styling with responsive design
- **JavaScript (ES6+)**: Dynamic interactions and AJAX functionality
- **Font Awesome**: Professional icons and UI elements

### Backend
- **PHP 7.4+**: Server-side logic and business rules
- **MySQL 5.7+**: Relational database management
- **Apache**: Web server and URL routing

### Development Environment
- **XAMPP**: Local development stack
- **phpMyAdmin**: Database management interface

##  Prerequisites

Before running this application, ensure you have:

- **XAMPP** (Apache + MySQL + PHP) installed
- **PHP 7.4** or higher
- **MySQL 5.7** or higher
- **Modern web browser** (Chrome, Firefox, Safari, Edge)

##  Installation

### Step 1: Clone/Download Project
```bash
# Clone the repository (if using Git)
git clone [repository-url]

# Or download and extract the ZIP file
# Place the project folder in: C:\xampp\htdocs\web project\
```

### Step 2: Start XAMPP Services
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL** services
3. Ensure both services show green status

### Step 3: Database Setup
1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Create a new database named `diutransport`
3. Import the database schema:
   - Go to **Import** tab
   - Select `database/diutransport.sql`
   - Click **Go** to execute

### Step 4: Configuration
1. Open `config/database.php`
2. Verify database connection settings:
   ```php
   $host = 'localhost';
   $username = 'root';
   $password = '';
   $database = 'diutransport';
   ```

### Step 5: Access Application
- Open your browser
- Navigate to: `http://localhost/web%20project/`
- The application should load successfully

##  Default Login Credentials

### Admin Access
- **Email**: admin@diu.edu.bd
- **Password**: 123456789

### Student Access
- **Email**: student@diu.edu.bd
- **Password**: 123456789

##  Usage Guide

### For Students

1. **Login/Signup**
   - Use existing credentials or create new account
   - Complete profile with student information

2. **Browse Schedules**
   - Navigate to "View Schedules"
   - Filter by route, date, or time
   - View real-time seat availability

3. **Book Seats**
   - Select desired schedule
   - Choose available seat
   - Confirm booking details
   - Complete payment process

4. **Manage Bookings**
   - View all bookings in "My Bookings"
   - Track payment status
   - Download tickets and receipts

### For Administrators

1. **Dashboard Overview**
   - Monitor system statistics
   - View recent activities
   - Access quick actions

2. **Schedule Management**
   - Add new bus schedules
   - Edit existing schedules
   - Manage route assignments

3. **User Management**
   - View all student accounts
   - Manage account status
   - Monitor user activities

4. **Reports & Analytics**
   - Generate payment reports
   - Export booking data
   - Analyze system usage

##  Database Schema

### Core Tables
- **users**: User accounts and authentication
- **buses**: Vehicle information and driver details
- **routes**: Transport routes and fare structures
- **schedules**: Bus schedules and availability
- **bookings**: Student seat reservations
- **payments**: Payment records and transactions

### Key Relationships
- Users → Bookings (one-to-many)
- Schedules → Bookings (one-to-many)
- Bookings → Payments (one-to-one)
- Buses → Schedules (one-to-many)
- Routes → Schedules (one-to-many)

##  Configuration

### Database Configuration
Edit `config/database.php`:
```php
$host = 'localhost';        // Database host
$username = 'root';         // Database username
$password = '';             // Database password
$database = 'diutransport'; // Database name
```

### Application Settings
- **Session Timeout**: Configured in PHP settings
- **File Upload Limits**: Set in php.ini
- **Error Reporting**: Development mode enabled

##  Testing

### Payment Testing
The system includes dummy payment functionality:
1. Select any payment method (bKash, Nagad, etc.)
2. Click "Process Dummy Payment"
3. System generates realistic transaction IDs
4. Complete payment process for testing

### Sample Data
The database includes comprehensive sample data:
- 4 sample users (1 admin, 3 students)
- 4 buses with driver information
- 5 transport routes
- 8 bus schedules
- Sample bookings and payments

##  Security Features

- **Session Management**: Secure user sessions
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Input sanitization
- **Role-based Access Control**: Admin/Student permissions
- **Form Validation**: Client and server-side validation

##  Responsive Design

The application is fully responsive and works on:
- Desktop computers
- Tablets
- Mobile phones
- All modern browsers

##  Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify XAMPP services are running
   - Check database credentials in `config/database.php`
   - Ensure database exists in phpMyAdmin

2. **Payment Processing Error**
   - Run the database update script: `database/update_payments_table.sql`
   - Check payment table structure in database

3. **Modal Not Working**
   - Clear browser cache
   - Check JavaScript console for errors
   - Ensure all CSS/JS files are loading

4. **File Upload Issues**
   - Check file permissions
   - Verify upload directory exists
   - Check PHP upload limits

### Error Logs
- Check Apache error logs in XAMPP
- Review PHP error logs
- Monitor browser console for JavaScript errors

##  Performance Optimization

- **Database Indexing**: Optimized queries with proper indexes
- **Caching**: Session-based caching for user data
- **Image Optimization**: Compressed images for faster loading
- **Code Minification**: Minified CSS and JavaScript files

##  Updates & Maintenance

### Database Updates
For existing installations, run update scripts:
```sql
-- Update payments table
ALTER TABLE payments 
ADD COLUMN payer_name VARCHAR(100) NULL,
ADD COLUMN payer_phone VARCHAR(15) NULL;
```

### Regular Maintenance
- Monitor database performance
- Clean up old session data
- Update security patches
- Backup database regularly

##  Support

For technical support or questions:
- Check the troubleshooting section
- Review error logs
- Contact system administrator

## 📄 License

This project is developed for educational purposes at Daffodil International University.

##  Contributing

This is an academic project. For improvements or suggestions:
1. Review the code structure
2. Test thoroughly
3. Document changes
4. Submit for review

---
