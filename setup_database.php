<?php
/*
 * Database setup / migration script.
 *
 * Day 6 note: this script should be run from the EC2 terminal before creating
 * the AMI, then removed from /var/www/html. It is blocked from browser access
 * so it will not be exposed through the Load Balancer or Auto Scaling instances.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. Run this setup file from the EC2 terminal only: php setup_database.php";
    exit;
}

require_once __DIR__ . '/config.php';

function run_query($sql) {
    global $conn;
    if (!$conn->query($sql)) {
        throw new Exception($conn->error . "\nSQL: " . $sql);
    }
}

function table_exists($table) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
}

function column_exists($table, $column) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
}

function add_column_if_missing($table, $column, $definition) {
    if (!column_exists($table, $column)) {
        run_query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "Added $table.$column\n";
    }
}

try {
    run_query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NULL,
        phone VARCHAR(40) NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'customer',
        auth_provider VARCHAR(30) NOT NULL DEFAULT 'local',
        google_id VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS customer_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        default_vehicle_model VARCHAR(120) NULL,
        default_vehicle_registration VARCHAR(60) NULL,
        address TEXT NULL,
        preferred_service_centre INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS service_centres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        centre_name VARCHAR(120) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(80) NOT NULL,
        phone VARCHAR(40) NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        opening_hours VARCHAR(120) DEFAULT 'Mon-Sat, 8.00 AM - 6.00 PM',
        is_active TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!table_exists('bookings')) {
        run_query("CREATE TABLE bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(100) NOT NULL,
            email VARCHAR(120) NOT NULL,
            phone VARCHAR(30),
            vehicle_model VARCHAR(100),
            vehicle_registration VARCHAR(50),
            service_type VARCHAR(100),
            package_type VARCHAR(100),
            urgency_level VARCHAR(50),
            preferred_location VARCHAR(100),
            preferred_date DATE,
            preferred_time TIME,
            notes TEXT,
            status VARCHAR(30) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    add_column_if_missing('bookings', 'booking_code', "VARCHAR(30) NULL UNIQUE AFTER id");
    add_column_if_missing('bookings', 'user_id', "INT NULL AFTER booking_code");
    add_column_if_missing('bookings', 'mileage', "INT NULL AFTER vehicle_registration");
    add_column_if_missing('bookings', 'fuel_type', "VARCHAR(40) NULL AFTER mileage");
    add_column_if_missing('bookings', 'service_mode', "VARCHAR(40) DEFAULT 'service_centre' AFTER urgency_level");
    add_column_if_missing('bookings', 'customer_address', "TEXT NULL AFTER service_mode");
    add_column_if_missing('bookings', 'customer_lat', "DECIMAL(10,7) NULL AFTER customer_address");
    add_column_if_missing('bookings', 'customer_lng', "DECIMAL(10,7) NULL AFTER customer_lat");
    add_column_if_missing('bookings', 'service_centre_id', "INT NULL AFTER customer_lng");
    add_column_if_missing('bookings', 'assigned_staff_id', "INT NULL AFTER service_centre_id");
    add_column_if_missing('bookings', 'eta_minutes', "INT NULL AFTER assigned_staff_id");
    add_column_if_missing('bookings', 'estimated_cost', "DECIMAL(10,2) NULL AFTER eta_minutes");
    add_column_if_missing('bookings', 'pickup_required', "VARCHAR(20) DEFAULT 'No' AFTER estimated_cost");

    run_query("CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NULL,
        phone VARCHAR(40) NULL,
        job_title VARCHAR(100) DEFAULT 'Service Technician',
        notes TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS booking_employees (
        booking_id INT NOT NULL,
        employee_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (booking_id, employee_id),
        INDEX idx_booking_employee_employee (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS staff_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        staff_id INT NOT NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        eta_minutes INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_booking_staff (booking_id, staff_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS booking_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        sender_role VARCHAR(30) NOT NULL,
        sender_user_id INT NULL,
        sender_name VARCHAR(120) NOT NULL,
        sender_email VARCHAR(160) NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_booking_messages_booking (booking_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NOT NULL,
        phone VARCHAR(40) NULL,
        subject VARCHAR(160) NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_column_if_missing('contact_messages', 'status', "VARCHAR(30) DEFAULT 'New'");
    add_column_if_missing('contact_messages', 'handled_at', "DATETIME NULL");

    run_query("CREATE TABLE IF NOT EXISTS service_catalog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(120) NOT NULL,
        description TEXT NOT NULL,
        duration VARCHAR(80) NULL,
        best_for VARCHAR(120) NULL,
        price_label VARCHAR(80) NULL,
        includes_text TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS package_catalog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(120) NOT NULL,
        badge VARCHAR(80) NULL,
        price_label VARCHAR(80) NULL,
        description TEXT NOT NULL,
        features_text TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    run_query("CREATE TABLE IF NOT EXISTS car_tips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        icon VARCHAR(20) DEFAULT '🔧',
        title VARCHAR(120) NOT NULL,
        summary TEXT NOT NULL,
        category VARCHAR(80) NULL,
        image_path VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_column_if_missing('car_tips', 'image_path', "VARCHAR(255) NULL AFTER category");

    $centres = [
        ['Colombo Central PitCrew Centre', 'No. 25, Galle Road, Colombo 03', 'Colombo', '0112345678', 6.927079, 79.861244],
        ['Nugegoda Service Hub', 'High Level Road, Nugegoda', 'Nugegoda', '0112457788', 6.864908, 79.899678],
        ['Maharagama Express Centre', 'Avissawella Road, Maharagama', 'Maharagama', '0112894455', 6.848000, 79.926500],
        ['Kandy City PitCrew Centre', 'Peradeniya Road, Kandy', 'Kandy', '0812233445', 7.290572, 80.633728]
    ];
    foreach ($centres as $c) {
        $stmt = $conn->prepare("SELECT id FROM service_centres WHERE centre_name = ? LIMIT 1");
        $stmt->bind_param('s', $c[0]);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $insert = $conn->prepare("INSERT INTO service_centres (centre_name, address, city, phone, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param('ssssdd', $c[0], $c[1], $c[2], $c[3], $c[4], $c[5]);
            $insert->execute();
        }
    }

    $seedUsers = [
        ['PitCrew Staff', 'staff@pitcrewconnect.com', 'Staff@12345', 'staff', '0770000002'],
        ['PitCrew Supervisor', 'supervisor@pitcrewconnect.com', 'Staff@12345', 'staff', '0770000003']
    ];
    foreach ($seedUsers as $u) {
        $existing = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $existing->bind_param('s', $u[1]);
        $existing->execute();
        if (!$existing->get_result()->fetch_assoc()) {
            $hash = password_hash($u[2], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $u[0], $u[1], $hash, $u[4], $u[3]);
            $stmt->execute();
            echo "Created staff user: {$u[1]} / {$u[2]}\n";
        }
    }
    run_query("UPDATE users SET role = 'staff' WHERE role = 'admin'");

    $employees = [
        ['Nimal Fernando', 'nimal@pitcrewconnect.example', '0771000001', 'Senior Technician'],
        ['Ashani Perera', 'ashani@pitcrewconnect.example', '0771000002', 'Mobile Service Technician'],
        ['Ruwan Silva', 'ruwan@pitcrewconnect.example', '0771000003', 'Brake & Tyre Specialist'],
        ['Kavindu Jayasinghe', 'kavindu@pitcrewconnect.example', '0771000004', 'Service Advisor']
    ];
    foreach ($employees as $e) {
        $stmt = $conn->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $e[1]);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $insert = $conn->prepare("INSERT INTO employees (full_name, email, phone, job_title) VALUES (?, ?, ?, ?)");
            $insert->bind_param('ssss', $e[0], $e[1], $e[2], $e[3]);
            $insert->execute();
        }
    }

    $serviceSeeds = [
        ['Oil Change', 'Fresh engine oil support for smoother daily driving.', '30-45 min', 'Daily drivers', 'Rs. 6,500+', "Oil level review\nFilter check\nBasic fluid top-up review", 1],
        ['Tyre Check', 'Tyre pressure, tread depth, and visible damage checks.', '20-30 min', 'Long trips', 'Rs. 3,500+', "Pressure check\nTread check\nRotation advice", 2],
        ['Brake Inspection', 'Brake safety review for noise, vibration, or reduced stopping response.', '30-45 min', 'Safety concerns', 'Rs. 5,500+', "Brake pad inspection\nFluid check\nSafety recommendation", 3],
        ['Battery Check', 'Battery health and starting system check before breakdowns happen.', '20 min', 'Weak starting', 'Rs. 4,000+', "Voltage check\nTerminal inspection\nReplacement advice", 4],
        ['General Maintenance', 'Balanced maintenance request for regular vehicle care.', '60-90 min', 'Routine care', 'Rs. 12,500+', "Oil service option\nTyre and brake review\nBattery check", 5],
        ['Pre-Trip Safety Check', 'Quick safety review before long-distance travel.', '45 min', 'Family trips', 'Rs. 7,500+', "Lights\nTyres\nBrakes\nFluids", 6],
        ['Emergency Vehicle Check', 'Priority review for urgent vehicle issues.', 'As soon as available', 'Urgent issues', 'Rs. 9,500+', "Priority queue\nFault description review\nMobile support option", 7],
        ['Fleet Maintenance', 'Multi-vehicle scheduling for small business teams.', 'By schedule', 'Business vehicles', 'Custom', "Fleet booking\nGrouped service history\nPriority planning", 8]
    ];
    foreach ($serviceSeeds as $s) {
        $stmt = $conn->prepare("SELECT id FROM service_catalog WHERE title = ? LIMIT 1");
        $stmt->bind_param('s', $s[0]);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $insert = $conn->prepare("INSERT INTO service_catalog (title, description, duration, best_for, price_label, includes_text, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param('ssssssi', $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6]);
            $insert->execute();
        }
    }

    $packageSeeds = [
        ['Basic Pit Check', 'Entry', 'Rs. 3,500+', 'For quick safety checks before routine driving.', "Tyre pressure check\nBattery visual check\nLights and fluid review", 1],
        ['Standard Service', 'Most popular', 'Rs. 12,500+', 'A balanced service request for everyday car owners.', "Oil change booking\nBrake inspection\nTyre and battery check", 2],
        ['Full PitCrew Service', 'Complete', 'Rs. 22,000+', 'A full vehicle care request inspired by pit-stop coordination.', "Complete maintenance check\nPriority queue option\nService notes and report", 3],
        ['Emergency Pit Stop', 'Priority', 'Rs. 15,000+', 'For urgent vehicle concerns that need faster review.', "Priority staff review\nMobile service option\nUrgent slot request", 4],
        ['Fleet Care Plan', 'Business', 'Custom', 'Planning support for small business and fleet vehicles.', "Grouped scheduling\nRecurring service notes\nPriority planning", 5]
    ];
    foreach ($packageSeeds as $p) {
        $stmt = $conn->prepare("SELECT id FROM package_catalog WHERE title = ? LIMIT 1");
        $stmt->bind_param('s', $p[0]);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $insert = $conn->prepare("INSERT INTO package_catalog (title, badge, price_label, description, features_text, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param('sssssi', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
            $insert->execute();
        }
    }

    $tipSeeds = [
        ['🛢️', 'Oil change timing', 'Regular oil service helps protect engine parts during daily stop-start driving.', 'Engine', 1],
        ['🛞', 'Tyre safety', 'Check pressure and tread before long trips or wet-weather driving.', 'Tyres', 2],
        ['🛑', 'Brake warning signs', 'Noises, vibration, or longer stopping distance should be inspected quickly.', 'Brakes', 3],
        ['🔋', 'Weak battery signs', 'Slow starting or dim lights can point to battery or charging issues.', 'Battery', 4]
    ];
    foreach ($tipSeeds as $t) {
        $stmt = $conn->prepare("SELECT id FROM car_tips WHERE title = ? LIMIT 1");
        $stmt->bind_param('s', $t[1]);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $insert = $conn->prepare("INSERT INTO car_tips (icon, title, summary, category, sort_order) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param('ssssi', $t[0], $t[1], $t[2], $t[3], $t[4]);
            $insert->execute();
        }
    }

    $defaultTipImages = [
        'Oil change timing' => 'assets/images/tips/oil-change.jpg',
        'Tyre safety' => 'assets/images/tips/tyre-safety.jpg',
        'Brake warning signs' => 'assets/images/tips/brake-warning.jpg',
        'Weak battery signs' => 'assets/images/tips/battery-health.jpg'
    ];
    foreach ($defaultTipImages as $title => $imagePath) {
        $stmt = $conn->prepare("UPDATE car_tips SET image_path = ? WHERE title = ? AND (image_path IS NULL OR image_path = '')");
        $stmt->bind_param('ss', $imagePath, $title);
        $stmt->execute();
    }

    $conn->query("UPDATE bookings SET booking_code = CONCAT('PC-', id) WHERE booking_code IS NULL OR booking_code = ''");

    echo "\nPitCrew database setup completed successfully.\n";
    echo "Unified staff area enabled.\n";
    echo "Default staff: staff@pitcrewconnect.com / Staff@12345\n";
    echo "IMPORTANT: Delete setup_database.php from /var/www/html after running it.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "Setup failed: " . $e->getMessage() . "\n";
}
?>