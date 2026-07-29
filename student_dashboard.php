<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: student_login.html");
    exit();
}

$student_id = mysqli_real_escape_string(
    $conn,
    $_SESSION["student_id"]
);

$sql = "SELECT student_id, full_name, email, phone, department, year
        FROM students
        WHERE student_id = '$student_id'
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) !== 1) {
    session_destroy();
    die("Student profile could not be loaded.");
}

$student = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="student-dashboard-page">

<div class="student-dashboard-layout">

    <aside class="student-sidebar">

        <div class="student-sidebar-logo">

            <i class="fa-solid fa-graduation-cap"></i>

            <div>
                <h2>SSMS</h2>
                <p>Student Portal</p>
            </div>

        </div>

        <nav class="student-sidebar-menu">

            <a href="student_dashboard.php" class="active">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="profile.php">
                <i class="fa-solid fa-user"></i>
                My Profile
            </a>

            <a href="room_allocation.php">
                <i class="fa-solid fa-building"></i>
                Room Details
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-column"></i>
                Reports
            </a>

            <a href="logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </nav>

    </aside>

    <main class="student-dashboard-main">

        <header class="student-dashboard-header">

            <div>
                <p class="dashboard-label">Student Dashboard</p>

                <h1>
                    Welcome,
                    <?php echo htmlspecialchars($student["full_name"]); ?>
                    👋
                </h1>

                <p>
                    Manage your profile and academic information
                    from one place.
                </p>
            </div>

            <div class="student-header-profile">

                <div class="student-avatar">
                    <?php
                    echo strtoupper(
                        substr($student["full_name"], 0, 1)
                    );
                    ?>
                </div>

                <div>
                    <strong>
                        <?php echo htmlspecialchars($student["full_name"]); ?>
                    </strong>

                    <span>
                        <?php echo htmlspecialchars($student["student_id"]); ?>
                    </span>
                </div>

            </div>

        </header>

        <section class="student-summary-grid">

            <div class="student-summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-id-card"></i>
                </div>

                <div>
                    <p>Student ID</p>

                    <h3>
                        <?php echo htmlspecialchars($student["student_id"]); ?>
                    </h3>
                </div>

            </div>

            <div class="student-summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-building-columns"></i>
                </div>

                <div>
                    <p>Department</p>

                    <h3>
                        <?php echo htmlspecialchars($student["department"]); ?>
                    </h3>
                </div>

            </div>

            <div class="student-summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-calendar"></i>
                </div>

                <div>
                    <p>Academic Year</p>

                    <h3>
                        <?php echo htmlspecialchars($student["year"]); ?>
                    </h3>
                </div>

            </div>

            <div class="student-summary-card">

                <div class="summary-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>

                <div>
                    <p>Account Status</p>
                    <h3>Active</h3>
                </div>

            </div>

        </section>

        <section class="student-dashboard-content">

            <div class="student-profile-card">

                <div class="student-section-heading">

                    <div>
                        <p>Personal Details</p>
                        <h2>Student Information</h2>
                    </div>

                    <a href="profile.php">
                        <i class="fa-solid fa-pen"></i>
                        Update Profile
                    </a>

                </div>

                <div class="student-details-grid">

                    <div class="student-detail-item">
                        <span>Full Name</span>

                        <strong>
                            <?php echo htmlspecialchars($student["full_name"]); ?>
                        </strong>
                    </div>

                    <div class="student-detail-item">
                        <span>Email Address</span>

                        <strong>
                            <?php echo htmlspecialchars($student["email"]); ?>
                        </strong>
                    </div>

                    <div class="student-detail-item">
                        <span>Phone Number</span>

                        <strong>
                            <?php echo htmlspecialchars($student["phone"]); ?>
                        </strong>
                    </div>

                    <div class="student-detail-item">
                        <span>Department</span>

                        <strong>
                            <?php echo htmlspecialchars($student["department"]); ?>
                        </strong>
                    </div>

                    <div class="student-detail-item">
                        <span>Year</span>

                        <strong>
                            <?php echo htmlspecialchars($student["year"]); ?>
                        </strong>
                    </div>

                    <div class="student-detail-item">
                        <span>Student ID</span>

                        <strong>
                            <?php echo htmlspecialchars($student["student_id"]); ?>
                        </strong>
                    </div>

                </div>

            </div>

            <div class="student-side-column">

                <div class="student-notification-card">

                    <div class="student-section-heading compact">
                        <div>
                            <p>Updates</p>
                            <h2>Notifications</h2>
                        </div>
                    </div>

                    <div class="notification-item">
                        <i class="fa-solid fa-bell"></i>

                        <div>
                            <strong>Profile Ready</strong>
                            <p>Your student profile is active.</p>
                        </div>
                    </div>

                    <div class="notification-item">
                        <i class="fa-solid fa-building"></i>

                        <div>
                            <strong>Room Information</strong>
                            <p>Check room allocation from the menu.</p>
                        </div>
                    </div>

                    <div class="notification-item">
                        <i class="fa-solid fa-chart-column"></i>

                        <div>
                            <strong>Academic Reports</strong>
                            <p>Your reports will appear here later.</p>
                        </div>
                    </div>

                </div>

                <div class="student-quick-actions">

                    <h2>Quick Actions</h2>

                    <a href="profile.php">
                        <i class="fa-solid fa-user-pen"></i>
                        Update Profile
                    </a>

                    <a href="room_allocation.php">
                        <i class="fa-solid fa-building"></i>
                        View Rooms
                    </a>

                    <a href="logout.php">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        Logout
                    </a>

                </div>

            </div>

        </section>

    </main>

</div>

<script src="script.js"></script>

</body>
</html>