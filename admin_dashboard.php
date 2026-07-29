<?php

session_start();
include "db_connect.php";

/* ==================================================
   ADMIN SESSION CHECK
================================================== */

if (
    !isset($_SESSION["admin_logged_in"]) ||
    $_SESSION["admin_logged_in"] !== true
) {
    header("Location: admin_login.html");
    exit();
}

$admin_name = $_SESSION["admin_name"] ?? "Administrator";
$admin_id   = $_SESSION["admin_id"] ?? "Admin";

/* ==================================================
   HELPER FUNCTIONS
================================================== */

function tableExists($conn, $tableName)
{
    $tableName = mysqli_real_escape_string($conn, $tableName);

    $result = mysqli_query(
        $conn,
        "SHOW TABLES LIKE '$tableName'"
    );

    return $result && mysqli_num_rows($result) > 0;
}

function findExistingTable($conn, $possibleTables)
{
    foreach ($possibleTables as $table) {
        if (tableExists($conn, $table)) {
            return $table;
        }
    }

    return null;
}

function getTableCount($conn, $tableName)
{
    if (!$tableName) {
        return 0;
    }

    $safeTable = "`" . str_replace("`", "``", $tableName) . "`";

    $result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM $safeTable"
    );

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return (int) $row["total"];
    }

    return 0;
}

/* ==================================================
   FIND TABLES
================================================== */

$studentsTable = findExistingTable(
    $conn,
    ["students", "Students"]
);

$roomsTable = findExistingTable(
    $conn,
    ["rooms", "Rooms", "classrooms", "Classrooms"]
);

$attendanceTable = findExistingTable(
    $conn,
    ["attendance", "Attendance"]
);

$marksTable = findExistingTable(
    $conn,
    ["marks", "Marks"]
);

$allocationsTable = findExistingTable(
    $conn,
    [
        "room_allocations",
        "Room_Allocations",
        "allocations",
        "Allocations"
    ]
);

/* ==================================================
   DASHBOARD COUNTS
================================================== */

$totalStudents   = getTableCount($conn, $studentsTable);
$totalRooms      = getTableCount($conn, $roomsTable);
$totalAttendance = getTableCount($conn, $attendanceTable);
$totalMarks      = getTableCount($conn, $marksTable);
$totalAllocations = getTableCount($conn, $allocationsTable);

/* ==================================================
   RECENT STUDENTS
================================================== */

$recentStudents = [];

if ($studentsTable) {
    $safeStudentsTable =
        "`" . str_replace("`", "``", $studentsTable) . "`";

    $recentQuery = mysqli_query(
        $conn,
        "SELECT * FROM $safeStudentsTable
         ORDER BY 1 DESC
         LIMIT 5"
    );

    if ($recentQuery) {
        while ($student = mysqli_fetch_assoc($recentQuery)) {
            $recentStudents[] = $student;
        }
    }
}

function getStudentValue($student, $possibleColumns, $default = "Not available")
{
    foreach ($possibleColumns as $column) {
        if (
            array_key_exists($column, $student) &&
            $student[$column] !== null &&
            $student[$column] !== ""
        ) {
            return htmlspecialchars($student[$column]);
        }
    }

    return $default;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard | SSMS</title>

    <link rel="stylesheet" href="style.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body class="admin-dashboard-page">

    <aside class="admin-sidebar">

        <div class="admin-sidebar-brand">

            <div class="admin-sidebar-logo">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>

            <div>
                <h2>SSMS</h2>
                <span>Admin Portal</span>
            </div>

        </div>

        <div class="admin-sidebar-profile">

            <div class="admin-profile-avatar">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>
                <h3>
                    <?php echo htmlspecialchars($admin_name); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($admin_id); ?>
                </p>
            </div>

        </div>

        <nav class="admin-sidebar-menu">

            <a href="admin_dashboard.php" class="active">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="manage_students.php">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Manage Students</span>
            </a>

            <a href="manage_attendance.php">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Attendance</span>
            </a>

            <a href="manage_marks.php">
                <i class="fa-solid fa-square-poll-vertical"></i>
                <span>Internal Marks</span>
            </a>

            <a href="manage_rooms.php">
                <i class="fa-solid fa-building"></i>
                <span>Manage Rooms</span>
            </a>

            <a href="manage_room_allocation.php">
                <i class="fa-solid fa-door-open"></i>
                <span>Room Allocation</span>
            </a>

        </nav>

        <div class="admin-sidebar-bottom">

            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                <span>Home Page</span>
            </a>

            <a href="logout.php" class="admin-logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </div>

    </aside>

    <main class="admin-main-content">

        <header class="admin-dashboard-header">

            <div>
                <p class="admin-header-label">
                    Administrator Dashboard
                </p>

                <h1>
                    Welcome back,
                    <?php echo htmlspecialchars($admin_name); ?>!
                </h1>

                <span>
                    Manage students, academic records and classrooms
                    from one place.
                </span>
            </div>

            <div class="admin-header-actions">

                <a href="index.php" class="admin-header-home-btn">
                    <i class="fa-solid fa-house"></i>
                    Home
                </a>

                <div class="admin-header-avatar">
                    <i class="fa-solid fa-user-shield"></i>
                </div>

            </div>

        </header>

        <section class="admin-statistics-grid">

            <article class="admin-stat-card">

                <div class="admin-stat-icon students-icon">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>

                <div>
                    <p>Total Students</p>
                    <h2><?php echo $totalStudents; ?></h2>
                    <span>Registered students</span>
                </div>

            </article>

            <article class="admin-stat-card">

                <div class="admin-stat-icon rooms-icon">
                    <i class="fa-solid fa-building"></i>
                </div>

                <div>
                    <p>Total Rooms</p>
                    <h2><?php echo $totalRooms; ?></h2>
                    <span>Classroom records</span>
                </div>

            </article>

            <article class="admin-stat-card">

                <div class="admin-stat-icon attendance-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>
                    <p>Attendance Records</p>
                    <h2><?php echo $totalAttendance; ?></h2>
                    <span>Student attendance entries</span>
                </div>

            </article>

            <article class="admin-stat-card">

                <div class="admin-stat-icon marks-icon">
                    <i class="fa-solid fa-square-poll-vertical"></i>
                </div>

                <div>
                    <p>Marks Records</p>
                    <h2><?php echo $totalMarks; ?></h2>
                    <span>Internal marks entries</span>
                </div>

            </article>

        </section>

        <section class="admin-dashboard-layout">

            <div class="admin-dashboard-left">

                <section class="admin-dashboard-panel">

                    <div class="admin-panel-heading">

                        <div>
                            <p>Student Records</p>
                            <h2>Recently Registered Students</h2>
                        </div>

                        <a href="manage_students.php">
                            View All
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>

                    <?php if (!empty($recentStudents)): ?>

                        <div class="admin-table-wrapper">

                            <table class="admin-dashboard-table">

                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>

                                <?php foreach ($recentStudents as $student): ?>

                                    <?php
                                    $studentId = getStudentValue(
                                        $student,
                                        ["student_id", "Student_ID", "id"],
                                        "-"
                                    );

                                    $studentName = getStudentValue(
                                        $student,
                                        [
                                            "full_name",
                                            "student_name",
                                            "name",
                                            "Full_Name"
                                        ]
                                    );

                                    $department = getStudentValue(
                                        $student,
                                        [
                                            "department",
                                            "course",
                                            "class",
                                            "Department"
                                        ]
                                    );

                                    $status = getStudentValue(
                                        $student,
                                        ["status", "account_status"],
                                        "Active"
                                    );
                                    ?>

                                    <tr>

                                        <td>
                                            <span class="admin-student-id">
                                                <?php echo $studentId; ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="admin-student-name">

                                                <div>
                                                    <i class="fa-solid fa-user"></i>
                                                </div>

                                                <span>
                                                    <?php echo $studentName; ?>
                                                </span>

                                            </div>
                                        </td>

                                        <td>
                                            <?php echo $department; ?>
                                        </td>

                                        <td>
                                            <span class="admin-status-active">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="admin-empty-state">

                            <i class="fa-solid fa-user-graduate"></i>

                            <h3>No student records found</h3>

                            <p>
                                Registered students will appear here.
                            </p>

                            <a href="manage_students.php">
                                Manage Students
                            </a>

                        </div>

                    <?php endif; ?>

                </section>

            </div>

            <div class="admin-dashboard-right">

                <section class="admin-dashboard-panel">

                    <div class="admin-panel-heading">

                        <div>
                            <p>Management</p>
                            <h2>Quick Actions</h2>
                        </div>

                    </div>

                    <div class="admin-quick-actions">

                        <a href="manage_students.php">

                            <div class="quick-action-icon">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>

                            <div>
                                <h3>Manage Students</h3>
                                <p>Add, edit or remove students</p>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                        <a href="manage_attendance.php">

                            <div class="quick-action-icon">
                                <i class="fa-solid fa-calendar-plus"></i>
                            </div>

                            <div>
                                <h3>Update Attendance</h3>
                                <p>Add student attendance records</p>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                        <a href="manage_marks.php">

                            <div class="quick-action-icon">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </div>

                            <div>
                                <h3>Update Marks</h3>
                                <p>Enter internal subject marks</p>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                        <a href="manage_room_allocation.php">

                            <div class="quick-action-icon">
                                <i class="fa-solid fa-door-open"></i>
                            </div>

                            <div>
                                <h3>Allocate Rooms</h3>
                                <p>Manage classroom schedules</p>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </div>

                </section>

                <section class="admin-dashboard-panel admin-system-summary">

                    <div class="admin-panel-heading">

                        <div>
                            <p>System Overview</p>
                            <h2>Current Records</h2>
                        </div>

                    </div>

                    <div class="admin-summary-row">
                        <span>Room Allocations</span>
                        <strong><?php echo $totalAllocations; ?></strong>
                    </div>

                    <div class="admin-summary-row">
                        <span>Attendance Entries</span>
                        <strong><?php echo $totalAttendance; ?></strong>
                    </div>

                    <div class="admin-summary-row">
                        <span>Marks Entries</span>
                        <strong><?php echo $totalMarks; ?></strong>
                    </div>

                </section>

            </div>

        </section>

    </main>

</body>
</html>