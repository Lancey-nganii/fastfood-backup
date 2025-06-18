<?php
session_start();
include('includes/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch customer profile info with address components
$sql = "SELECT first_name, last_name, email, phone_number, street, city, postal_code, birthdate, registration_date 
        FROM customer 
        WHERE user_id = ?";
$stmt = $dbh->prepare($sql);
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Format dates for display
$registration_date = new DateTime($profile['registration_date']);
$profile['formatted_reg_date'] = $registration_date->format('F j, Y');

// Format birthdate if it exists
if (!empty($profile['birthdate'])) {
    $birthdate = new DateTime($profile['birthdate']);
    $profile['formatted_birthdate'] = $birthdate->format('F j, Y');
} else {
    $profile['formatted_birthdate'] = 'Not specified';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FastBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 220px;
            --primary: #cc5050;
            --primary-light: #e67e7e;
            --secondary: #d3c260;
            --bg-light: #fff8f0;
            --text-dark: #333;
            --text-muted: #666;
            --border-radius: 8px;
            --box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            display: flex;
            background-color: var(--bg-light);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(to bottom, var(--primary), var(--primary-light));
            color: white;
            padding: 2rem 0;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 2.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            padding: 0 1rem;
        }
        .sidebar ul {
            list-style: none;
        }
        .sidebar ul li {
            margin: 0.5rem 0;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: rgba(255,255,255,0.2);
            padding-left: 2rem;
        }
        .sidebar ul li a.active {
            border-left: 4px solid white;
            font-weight: 500;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            flex: 1;
            background-color: #f9f9f9;
        }
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        .profile-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .profile-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .profile-body {
            padding: 2.5rem;
        }
        .section-title {
            color: var(--primary);
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-card {
            background: #f9f9f9;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .info-card h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
        }
        .info-card h3 i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
        .info-item {
            margin-bottom: 1rem;
        }
        .info-item:last-child {
            margin-bottom: 0;
        }
        .info-item label {
            display: block;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
            font-weight: 500;
        }
        .info-item p {
            font-size: 1rem;
            color: var(--text-dark);
            word-break: break-word;
        }
        .address-card {
            grid-column: 1 / -1;
            background: #f9f9f9;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-top: 1rem;
        }
        .address-card h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
        }
        .address-card h3 i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
        .address-content {
            white-space: pre-line;
            line-height: 1.6;
        }
        .no-address {
            color: var(--text-muted);
            font-style: italic;
        }
        .edit-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .edit-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(15deg);
        }
        @media (max-width: 900px) {
            .sidebar {
                width: 80px;
                overflow: hidden;
            }
            .sidebar h2 {
                font-size: 1.2rem;
                margin-bottom: 2rem;
            }
            .sidebar ul li a {
                padding: 0.8rem;
                justify-content: center;
            }
            .sidebar ul li a i {
                margin: 0;
                font-size: 1.2rem;
            }
            .sidebar ul li a span {
                display: none;
            }
            .main-content {
                margin-left: 80px;
                padding: 1rem;
            }
            .profile-body {
                padding: 1.5rem;
            }
        }
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .profile-header h1 {
                font-size: 1.5rem;
            }
            .profile-body {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <nav class="sidebar">
        <h2>FastBite</h2>
        <ul>
            <li><a href="customer-dashboard.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
            <li><a href="profile.php" class="active"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            <li><a href="customer-orders.php"><i class="fas fa-clipboard-list"></i> <span>Orders</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </nav>

    <div class="main-content">
        <div class="profile-container">
            <div class="profile-header">
                <button class="edit-btn" title="Edit Profile">
                    <i class="fas fa-pen"></i>
                </button>
                <h1>My Profile</h1>
                <p>Manage your personal information and preferences</p>
            </div>

            <div class="profile-body">
                <h2 class="section-title">Personal Information</h2>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-id-card"></i> Basic Information</h3>
                        <div class="info-item">
                            <label>First Name</label>
                            <p><?php echo htmlspecialchars($profile['first_name'] ?? 'Not provided'); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Last Name</label>
                            <p><?php echo htmlspecialchars($profile['last_name'] ?? 'Not provided'); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Birthdate</label>
                            <p><?php echo $profile['formatted_birthdate']; ?></p>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-address-book"></i> Contact Details</h3>
                        <div class="info-item">
                            <label>Email Address</label>
                            <p><?php echo htmlspecialchars($profile['email'] ?? 'Not provided'); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Phone Number</label>
                            <p><?php echo !empty($profile['phone_number']) ? htmlspecialchars($profile['phone_number']) : 'Not provided'; ?></p>
                        </div>
                        <div class="info-item">
                            <label>Member Since</label>
                            <p><?php echo $profile['formatted_reg_date']; ?></p>
                        </div>
                    </div>

                    <div class="address-card">
                        <h3><i class="fas fa-map-marker-alt"></i> Delivery Address</h3>
                        <div class="address-content">
                            <?php if (!empty($profile['street'])): ?>
                                <div class="address-line"><?php echo htmlspecialchars($profile['street']); ?></div>
                                <div class="address-line">
                                    <?php 
                                    echo htmlspecialchars(trim($profile['city'] ?? ''));
                                    if (!empty($profile['city']) && !empty($profile['postal_code'])) {
                                        echo ', ';
                                    }
                                    echo htmlspecialchars(trim($profile['postal_code'] ?? ''));
                                    ?>
                                </div>
                            <?php else: ?>
                                <p class="no-address">No delivery address provided. Please update your profile.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add edit functionality
        document.querySelector('.edit-btn').addEventListener('click', function() {
            // In a real implementation, this would open an edit form/modal
            alert('Edit functionality will be implemented here');
        });
    </script>
</body>
</html>
