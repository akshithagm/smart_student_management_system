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

$adminName = $_SESSION["admin_name"] ?? "Administrator";
$adminId   = $_SESSION["admin_id"] ?? "Admin";

$search = trim($_GET["search"] ?? "");
$today  = date("Y-m-d");

/* ==================================================
   HELPER FUNCTION
================================================== */

function getCount($conn, $query)
{
    $result = mysqli_query($conn, $query);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int) ($row["total"] ?? 0);
}

/* ==================================================
   DASHBOARD STATISTICS
================================================== */

$totalStudents = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM students"
);

$markedToday = getCount(
    $conn,
    "
    SELECT COUNT(DISTINCT student_id) AS total
    FROM attendance_records
    WHERE attendance_date = CURDATE()
    "
);

$presentToday = getCount(
    $conn,
    "
    SELECT COUNT(DISTINCT student_id) AS total
    FROM attendance_records
    WHERE attendance_date = CURDATE()
      AND status = 'Present'
    "
);

$absentToday = getCount(
    $conn,
    "
    SELECT COUNT(DISTINCT student_id) AS total
    FROM attendance_records
    WHERE attendance_date = CURDATE()
      AND status = 'Absent'
    "
);

/* ==================================================
   FETCH STUDENTS WITH ATTENDANCE INFORMATION
================================================== */

$sql = "
    SELECT
        s.id,
        s.student_id,
        s.full_name,
        s.email,
        s.department,
        s.year,

        COALESCE(a.total_classes, 0) AS total_classes,
        COALESCE(a.attended_classes, 0) AS attended_classes,
        COALESCE(a.attendance_percentage, 0) AS attendance_percentage,

        ar.id AS attendance_record_id,
        ar.status AS today_status

    FROM students s

    LEFT JOIN attendance a
        ON a.student_id = s.student_id

    LEFT JOIN attendance_records ar
        ON ar.student_id = s.student_id
       AND ar.attendance_date = CURDATE()
";

if ($search !== "") {
    $sql .= "
        WHERE
            s.student_id LIKE ?
            OR s.full_name LIKE ?
            OR s.email LIKE ?
            OR s.department LIKE ?
    ";
}

$sql .= "
    ORDER BY s.full_name ASC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Unable to load attendance information: " . mysqli_error($conn));
}

if ($search !== "") {
    $searchValue = "%" . $search . "%";

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$displayedStudents = mysqli_num_rows($result);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Attendance | SSMS</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        /* ==================================================
           GENERAL
        ================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --background: #050b18;
            --sidebar: #071120;
            --card: rgba(13, 27, 48, 0.78);
            --card-light: rgba(18, 38, 66, 0.75);
            --border: rgba(127, 211, 255, 0.12);
            --primary: #00d4ff;
            --primary-dark: #009fc7;
            --text: #f5f9ff;
            --muted: #8192aa;
            --green: #30d98b;
            --red: #ff647c;
            --yellow: #ffc857;
        }

        body {
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(
                    circle at top right,
                    rgba(0, 212, 255, 0.1),
                    transparent 28%
                ),
                radial-gradient(
                    circle at bottom left,
                    rgba(76, 81, 255, 0.08),
                    transparent 30%
                ),
                var(--background);
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }

        /* ==================================================
           SIDEBAR
        ================================================== */

        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;

            display: flex;
            flex-direction: column;

            width: 255px;
            height: 100vh;
            padding: 24px 18px;

            background: rgba(6, 16, 31, 0.96);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 0 7px 23px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo {
            display: grid;
            place-items: center;

            width: 45px;
            height: 45px;

            color: #04101c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #8af0ff
            );
            border-radius: 13px;
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.2);
        }

        .sidebar-logo i {
            font-size: 20px;
        }

        .sidebar-brand h2 {
            font-size: 19px;
            letter-spacing: 1px;
        }

        .sidebar-brand span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .sidebar-profile {
            display: flex;
            align-items: center;
            gap: 11px;

            margin: 22px 0;
            padding: 14px;

            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 14px;
        }

        .profile-avatar {
            display: grid;
            flex-shrink: 0;
            place-items: center;

            width: 39px;
            height: 39px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.09);
            border-radius: 11px;
        }

        .sidebar-profile h3 {
            max-width: 145px;
            overflow: hidden;

            font-size: 13px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar-profile p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 10px;
        }

        .sidebar-menu {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-menu a,
        .sidebar-bottom a {
            display: flex;
            align-items: center;
            gap: 13px;

            padding: 12px 14px;

            color: #97a6ba;
            border: 1px solid transparent;
            border-radius: 11px;

            font-size: 12px;
            transition: 0.25s;
        }

        .sidebar-menu a i,
        .sidebar-bottom a i {
            width: 18px;
            text-align: center;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active,
        .sidebar-bottom a:hover {
            color: var(--text);
            background: rgba(0, 212, 255, 0.08);
            border-color: rgba(0, 212, 255, 0.12);
        }

        .sidebar-menu a.active {
            color: var(--primary);
        }

        .sidebar-bottom {
            display: flex;
            flex-direction: column;
            gap: 5px;

            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .sidebar-bottom .logout-link:hover {
            color: var(--red);
            background: rgba(255, 100, 124, 0.07);
            border-color: rgba(255, 100, 124, 0.12);
        }

        /* ==================================================
           MAIN CONTENT
        ================================================== */

        .admin-main {
            min-height: 100vh;
            margin-left: 255px;
            padding: 32px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            margin-bottom: 26px;
        }

        .header-label {
            margin-bottom: 7px;
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        .page-header h1 {
            margin-bottom: 7px;
            font-size: 26px;
        }

        .page-header span {
            color: var(--muted);
            font-size: 12px;
        }

        .mark-attendance-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 12px 18px;

            color: #03111c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #7defff
            );
            border-radius: 11px;
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.18);

            font-size: 12px;
            font-weight: 700;
            transition: 0.25s;
        }

        .mark-attendance-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(0, 212, 255, 0.25);
        }

        /* ==================================================
           STATISTICS
        ================================================== */

        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;

            margin-bottom: 22px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;

            padding: 19px;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            backdrop-filter: blur(18px);
        }

        .stat-card::after {
            position: absolute;
            right: -20px;
            bottom: -30px;

            width: 85px;
            height: 85px;

            background: rgba(0, 212, 255, 0.04);
            border-radius: 50%;

            content: "";
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 15px;
        }

        .stat-top p {
            color: var(--muted);
            font-size: 11px;
        }

        .stat-icon {
            display: grid;
            place-items: center;

            width: 37px;
            height: 37px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border-radius: 10px;
        }

        .stat-card.present .stat-icon {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
        }

        .stat-card.absent .stat-icon {
            color: var(--red);
            background: rgba(255, 100, 124, 0.08);
        }

        .stat-card.marked .stat-icon {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.08);
        }

        .stat-card h2 {
            font-size: 25px;
        }

        .stat-card small {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 9px;
        }

        /* ==================================================
           TABLE CARD
        ================================================== */

        .attendance-card {
            overflow: hidden;

            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            backdrop-filter: blur(18px);
        }

        .attendance-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;

            padding: 20px;
            border-bottom: 1px solid var(--border);
        }

        .toolbar-title h2 {
            margin-bottom: 5px;
            font-size: 15px;
        }

        .toolbar-title p {
            color: var(--muted);
            font-size: 10px;
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;

            width: 285px;
            padding: 0 13px;

            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .search-box i {
            color: var(--muted);
            font-size: 12px;
        }

        .search-box input {
            width: 100%;
            padding: 11px 0;

            color: var(--text);
            background: transparent;
            border: none;
            outline: none;

            font-size: 11px;
        }

        .search-box input::placeholder {
            color: #627187;
        }

        .search-button,
        .clear-button {
            display: grid;
            place-items: center;

            min-width: 42px;
            padding: 0 13px;

            color: #03111c;
            background: var(--primary);
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }

        .clear-button {
            color: var(--muted);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            min-width: 1000px;

            border-collapse: collapse;
        }

        .attendance-table th {
            padding: 14px 17px;

            color: #718198;
            background: rgba(255, 255, 255, 0.015);
            border-bottom: 1px solid var(--border);

            font-size: 9px;
            letter-spacing: 0.8px;
            text-align: left;
            text-transform: uppercase;
        }

        .attendance-table td {
            padding: 15px 17px;

            color: #dce7f4;
            border-bottom: 1px solid rgba(127, 211, 255, 0.07);

            font-size: 11px;
        }

        .attendance-table tbody tr {
            transition: 0.2s;
        }

        .attendance-table tbody tr:hover {
            background: rgba(0, 212, 255, 0.025);
        }

        .attendance-table tbody tr:last-child td {
            border-bottom: none;
        }

        .student-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar {
            display: grid;
            flex-shrink: 0;
            place-items: center;

            width: 35px;
            height: 35px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border-radius: 10px;

            font-size: 12px;
            font-weight: 700;
        }

        .student-cell h3 {
            margin-bottom: 4px;
            font-size: 11px;
        }

        .student-cell p {
            color: var(--muted);
            font-size: 9px;
        }

        .attendance-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 10px;
            border-radius: 20px;

            font-size: 9px;
            font-weight: 700;
        }

        .status-present {
            color: var(--green);
            background: rgba(48, 217, 139, 0.08);
            border: 1px solid rgba(48, 217, 139, 0.14);
        }

        .status-absent {
            color: var(--red);
            background: rgba(255, 100, 124, 0.08);
            border: 1px solid rgba(255, 100, 124, 0.14);
        }

        .status-pending {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.07);
            border: 1px solid rgba(255, 200, 87, 0.13);
        }

        .percentage-box {
            min-width: 105px;
        }

        .percentage-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 7px;
        }

        .percentage-label span {
            color: var(--muted);
            font-size: 8px;
        }

        .percentage-label strong {
            font-size: 9px;
        }

        .percentage-track {
            overflow: hidden;
            height: 5px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 20px;
        }

        .percentage-fill {
            height: 100%;
            background: linear-gradient(
                90deg,
                var(--primary-dark),
                var(--primary)
            );
            border-radius: 20px;
        }

        .table-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 7px 10px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border: 1px solid rgba(0, 212, 255, 0.13);
            border-radius: 8px;

            font-size: 9px;
            font-weight: 700;
            transition: 0.2s;
        }

        .table-action:hover {
            background: rgba(0, 212, 255, 0.13);
        }

        td .table-action {
            margin-right: 6px;
            margin-bottom: 4px;
        }

        .table-action.edit-action {
            color: var(--yellow);
            background: rgba(255, 200, 87, 0.07);
            border-color: rgba(255, 200, 87, 0.13);
        }

        /* ==================================================
           EMPTY STATE
        ================================================== */

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-state i {
            margin-bottom: 17px;
            color: var(--primary);
            font-size: 34px;
            opacity: 0.65;
        }

        .empty-state h3 {
            margin-bottom: 8px;
            font-size: 15px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 10px;
        }

        /* ==================================================
           RESPONSIVE DESIGN
        ================================================== */

        @media (max-width: 1150px) {

            .attendance-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 850px) {

            .admin-sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }

            .sidebar-bottom {
                flex-direction: row;
            }

            .admin-main {
                margin-left: 0;
                padding: 22px;
            }
        }

        @media (max-width: 650px) {

            .page-header,
            .attendance-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .mark-attendance-btn {
                width: 100%;
            }

            .attendance-stats {
                grid-template-columns: 1fr;
            }

            .search-form,
            .search-box {
                width: 100%;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

    <!-- ==================================================
         SIDEBAR
    ================================================== -->

    <aside class="admin-sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>

            <div>
                <h2>SSMS</h2>
                <span>Admin Portal</span>
            </div>

        </div>

        <div class="sidebar-profile">

            <div class="profile-avatar">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>

                <h3>
                    <?php echo htmlspecialchars($adminName); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($adminId); ?>
                </p>

            </div>

        </div>

        <nav class="sidebar-menu">

            <a href="admin_dashboard.php">

                <i class="fa-solid fa-chart-pie"></i>

                <span>Dashboard</span>

            </a>

            <a href="manage_students.php">

                <i class="fa-solid fa-user-graduate"></i>

                <span>Manage Students</span>

            </a>

            <a
                href="manage_attendance.php"
                class="active"
            >

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

        <div class="sidebar-bottom">

            <a href="index.php">

                <i class="fa-solid fa-house"></i>

                <span>Home Page</span>

            </a>

            <a
                href="logout.php"
                class="logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                <span>Logout</span>

            </a>

        </div>

    </aside>

    <!-- ==================================================
         MAIN CONTENT
    ================================================== -->

    <main class="admin-main">

        <header class="page-header">

            <div>

                <p class="header-label">
                    Attendance Management
                </p>

                <h1>Manage Attendance</h1>

                <span>
                    View and record student attendance for
                    <?php echo date("d F Y"); ?>.
                </span>

            </div>

            <a
                href="mark_attendance.php"
                class="mark-attendance-btn"
            >

                <i class="fa-solid fa-calendar-plus"></i>

                Mark Attendance

            </a>

        </header>

        <!-- ==================================================
             STATISTICS
        ================================================== -->

        <section class="attendance-stats">

            <article class="stat-card">

                <div class="stat-top">

                    <p>Total Students</p>

                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                </div>

                <h2>
                    <?php echo $totalStudents; ?>
                </h2>

                <small>Registered students</small>

            </article>

            <article class="stat-card marked">

                <div class="stat-top">

                    <p>Marked Today</p>

                    <div class="stat-icon">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>

                </div>

                <h2>
                    <?php echo $markedToday; ?>
                </h2>

                <small>Attendance records completed</small>

            </article>

            <article class="stat-card present">

                <div class="stat-top">

                    <p>Present Today</p>

                    <div class="stat-icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>

                </div>

                <h2>
                    <?php echo $presentToday; ?>
                </h2>

                <small>Students marked present</small>

            </article>

            <article class="stat-card absent">

                <div class="stat-top">

                    <p>Absent Today</p>

                    <div class="stat-icon">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>

                </div>

                <h2>
                    <?php echo $absentToday; ?>
                </h2>

                <small>Students marked absent</small>

            </article>

        </section>

        <!-- ==================================================
             ATTENDANCE TABLE
        ================================================== -->

        <section class="attendance-card">

            <div class="attendance-toolbar">

                <div class="toolbar-title">

                    <h2>Student Attendance</h2>

                    <p>
                        Showing <?php echo $displayedStudents; ?>
                        student record(s)
                    </p>

                </div>

                <form
                    action="manage_attendance.php"
                    method="GET"
                    class="search-form"
                >

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?php
                            echo htmlspecialchars($search);
                            ?>"
                            placeholder="Search ID, name or department"
                        >

                    </div>

                    <button
                        type="submit"
                        class="search-button"
                        title="Search"
                    >

                        <i class="fa-solid fa-search"></i>

                    </button>

                    <?php if ($search !== ""): ?>

                        <a
                            href="manage_attendance.php"
                            class="clear-button"
                            title="Clear search"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </a>

                    <?php endif; ?>

                </form>

            </div>

            <?php if ($displayedStudents > 0): ?>

                <div class="table-wrapper">

                    <table class="attendance-table">

                        <thead>

                            <tr>

                                <th>Student</th>

                                <th>Department</th>

                                <th>Academic Year</th>

                                <th>Total Classes</th>

                                <th>Attended</th>

                                <th>Percentage</th>

                                <th>Today's Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php while ($student = mysqli_fetch_assoc($result)): ?>

                                <?php

                                $fullName =
                                    $student["full_name"] ?? "";

                                $nameParts =
                                    preg_split(
                                        "/\s+/",
                                        trim($fullName)
                                    );

                                $initials = "";

                                foreach (
                                    array_slice($nameParts, 0, 2)
                                    as $namePart
                                ) {
                                    if ($namePart !== "") {
                                        $initials .= strtoupper(
                                            substr($namePart, 0, 1)
                                        );
                                    }
                                }

                                if ($initials === "") {
                                    $initials = "ST";
                                }

                                $percentage = (float)
                                    $student["attendance_percentage"];

                                $percentageWidth = max(
                                    0,
                                    min(100, $percentage)
                                );

                                $todayStatus =
                                    $student["today_status"];

                                ?>

                                <tr>

                                    <td>

                                        <div class="student-cell">

                                            <div class="student-avatar">

                                                <?php
                                                echo htmlspecialchars(
                                                    $initials
                                                );
                                                ?>

                                            </div>

                                            <div>

                                                <h3>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $fullName
                                                    );
                                                    ?>
                                                </h3>

                                                <p>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $student["student_id"]
                                                    );
                                                    ?>
                                                </p>

                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $student["department"]
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $student["year"]
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo (int)
                                            $student["total_classes"];
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo (int)
                                            $student["attended_classes"];
                                        ?>
                                    </td>

                                    <td>

                                        <div class="percentage-box">

                                            <div class="percentage-label">

                                                <span>Attendance</span>

                                                <strong>
                                                    <?php
                                                    echo number_format(
                                                        $percentage,
                                                        2
                                                    );
                                                    ?>%
                                                </strong>

                                            </div>

                                            <div class="percentage-track">

                                                <div
                                                    class="percentage-fill"
                                                    style="width: <?php
                                                    echo $percentageWidth;
                                                    ?>%;"
                                                ></div>

                                            </div>

                                        </div>

                                    </td>

                                    <td>

                                        <?php if ($todayStatus === "Present"): ?>

                                            <span
                                                class="attendance-status status-present"
                                            >

                                                <i
                                                    class="fa-solid fa-circle-check"
                                                ></i>

                                                Present

                                            </span>

                                        <?php elseif ($todayStatus === "Absent"): ?>

                                            <span
                                                class="attendance-status status-absent"
                                            >

                                                <i
                                                    class="fa-solid fa-circle-xmark"
                                                ></i>

                                                Absent

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="attendance-status status-pending"
                                            >

                                                <i
                                                    class="fa-solid fa-clock"
                                                ></i>

                                                Not Marked

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <a
                                            href="view_attendance.php?student_id=<?php
                                            echo urlencode(
                                                $student["student_id"]
                                            );
                                            ?>"
                                            class="table-action"
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                            View

                                        </a>

                                        <?php if ($todayStatus === null): ?>

                                            <a
                                                href="mark_attendance.php?student_id=<?php
                                                echo urlencode(
                                                    $student["student_id"]
                                                );
                                                ?>"
                                                class="table-action"
                                            >

                                                <i
                                                    class="fa-solid fa-calendar-plus"
                                                ></i>

                                                Mark

                                            </a>

                                        <?php else: ?>

                                            <a
                                                href="edit_attendance.php?id=<?php
                                                echo urlencode(
                                                    $student[
                                                        "attendance_record_id"
                                                    ]
                                                );
                                                ?>"
                                                class="table-action edit-action"
                                            >

                                                <i
                                                    class="fa-solid fa-pen"
                                                ></i>

                                                Edit

                                            </a>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-solid fa-user-clock"></i>

                    <h3>No students found</h3>

                    <p>
                        No student records match your search.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>

<?php

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>