<?php
session_start();
include "db_connect.php";

/* -------------------------------------------------------
   ADMIN SESSION CHECK
------------------------------------------------------- */

if (
    !isset($_SESSION["admin_logged_in"]) ||
    $_SESSION["admin_logged_in"] !== true
) {
    header("Location: admin_login.html");
    exit();
}

/* -------------------------------------------------------
   GET STUDENT ID
------------------------------------------------------- */

$studentId = trim($_GET["student_id"] ?? "");

if ($studentId === "") {
    header("Location: manage_attendance.php");
    exit();
}

/* -------------------------------------------------------
   LOAD STUDENT DETAILS
------------------------------------------------------- */

$studentQuery = "
    SELECT
        id,
        student_id,
        full_name,
        email,
        phone,
        department,
        year
    FROM students
    WHERE student_id = ?
    LIMIT 1
";

$studentStatement = mysqli_prepare($conn, $studentQuery);

if (!$studentStatement) {
    die("Unable to load student information.");
}

mysqli_stmt_bind_param($studentStatement, "s", $studentId);
mysqli_stmt_execute($studentStatement);

$studentResult = mysqli_stmt_get_result($studentStatement);
$student = mysqli_fetch_assoc($studentResult);

mysqli_stmt_close($studentStatement);

if (!$student) {
    header("Location: manage_attendance.php");
    exit();
}

/* -------------------------------------------------------
   LOAD ATTENDANCE SUMMARY
------------------------------------------------------- */

$summaryQuery = "
    SELECT
        total_classes,
        attended_classes,
        attendance_percentage
    FROM attendance
    WHERE student_id = ?
    LIMIT 1
";

$summaryStatement = mysqli_prepare($conn, $summaryQuery);

if (!$summaryStatement) {
    die("Unable to load attendance summary.");
}

mysqli_stmt_bind_param($summaryStatement, "s", $studentId);
mysqli_stmt_execute($summaryStatement);

$summaryResult = mysqli_stmt_get_result($summaryStatement);
$summary = mysqli_fetch_assoc($summaryResult);

mysqli_stmt_close($summaryStatement);

$totalClasses = (int)($summary["total_classes"] ?? 0);
$attendedClasses = (int)($summary["attended_classes"] ?? 0);
$attendancePercentage = (float)($summary["attendance_percentage"] ?? 0);

$absentClasses = max(0, $totalClasses - $attendedClasses);

/* -------------------------------------------------------
   DATE FILTERS
------------------------------------------------------- */

$fromDate = trim($_GET["from_date"] ?? "");
$toDate = trim($_GET["to_date"] ?? "");
$filterError = "";

if ($fromDate !== "" && $toDate !== "" && $fromDate > $toDate) {
    $filterError = "From date cannot be later than To date.";
}

/* -------------------------------------------------------
   LOAD ATTENDANCE HISTORY
------------------------------------------------------- */

$history = [];

if ($filterError === "") {
    if ($fromDate !== "" && $toDate !== "") {
        $historyQuery = "
            SELECT
                id,
                student_id,
                attendance_date,
                status,
                created_at
            FROM attendance_records
            WHERE
                student_id = ?
                AND attendance_date BETWEEN ? AND ?
            ORDER BY attendance_date DESC, id DESC
        ";

        $historyStatement = mysqli_prepare($conn, $historyQuery);

        if (!$historyStatement) {
            die("Unable to load attendance history.");
        }

        mysqli_stmt_bind_param(
            $historyStatement,
            "sss",
            $studentId,
            $fromDate,
            $toDate
        );
    } elseif ($fromDate !== "") {
        $historyQuery = "
            SELECT
                id,
                student_id,
                attendance_date,
                status,
                created_at
            FROM attendance_records
            WHERE
                student_id = ?
                AND attendance_date >= ?
            ORDER BY attendance_date DESC, id DESC
        ";

        $historyStatement = mysqli_prepare($conn, $historyQuery);

        if (!$historyStatement) {
            die("Unable to load attendance history.");
        }

        mysqli_stmt_bind_param(
            $historyStatement,
            "ss",
            $studentId,
            $fromDate
        );
    } elseif ($toDate !== "") {
        $historyQuery = "
            SELECT
                id,
                student_id,
                attendance_date,
                status,
                created_at
            FROM attendance_records
            WHERE
                student_id = ?
                AND attendance_date <= ?
            ORDER BY attendance_date DESC, id DESC
        ";

        $historyStatement = mysqli_prepare($conn, $historyQuery);

        if (!$historyStatement) {
            die("Unable to load attendance history.");
        }

        mysqli_stmt_bind_param(
            $historyStatement,
            "ss",
            $studentId,
            $toDate
        );
    } else {
        $historyQuery = "
            SELECT
                id,
                student_id,
                attendance_date,
                status,
                created_at
            FROM attendance_records
            WHERE student_id = ?
            ORDER BY attendance_date DESC, id DESC
        ";

        $historyStatement = mysqli_prepare($conn, $historyQuery);

        if (!$historyStatement) {
            die("Unable to load attendance history.");
        }

        mysqli_stmt_bind_param(
            $historyStatement,
            "s",
            $studentId
        );
    }

    mysqli_stmt_execute($historyStatement);

    $historyResult = mysqli_stmt_get_result($historyStatement);

    while ($record = mysqli_fetch_assoc($historyResult)) {
        $history[] = $record;
    }

    mysqli_stmt_close($historyStatement);
}

/* -------------------------------------------------------
   ATTENDANCE MESSAGE
------------------------------------------------------- */

if ($totalClasses === 0) {
    $attendanceMessage = "No Attendance Available";
    $attendanceMessageClass = "neutral";
    $attendanceIcon = "—";
} elseif ($attendancePercentage >= 90) {
    $attendanceMessage = "Excellent Attendance";
    $attendanceMessageClass = "excellent";
    $attendanceIcon = "★";
} elseif ($attendancePercentage >= 75) {
    $attendanceMessage = "Good Attendance";
    $attendanceMessageClass = "good";
    $attendanceIcon = "✓";
} else {
    $attendanceMessage = "Needs Improvement";
    $attendanceMessageClass = "warning";
    $attendanceIcon = "!";
}

$adminName = $_SESSION["admin_name"] ?? "Administrator";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        View Attendance | Smart Student Management System
    </title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            color: #ffffff;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(99, 102, 241, 0.28),
                    transparent 34%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(14, 165, 233, 0.20),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #07111f,
                    #10182b,
                    #07111f
                );
        }

        .page-layout {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 260px;
            padding: 28px 20px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            overflow-y: auto;
            background: rgba(10, 18, 34, 0.84);
            border-right: 1px solid rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(18px);
        }

        .brand {
            margin-bottom: 34px;
        }

        .brand h2 {
            font-size: 23px;
            line-height: 1.3;
        }

        .brand p {
            margin-top: 8px;
            color: #9ca3af;
            font-size: 13px;
        }

        .admin-profile {
            padding: 16px;
            margin-bottom: 28px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .admin-profile span {
            color: #9ca3af;
            font-size: 12px;
        }

        .admin-profile strong {
            display: block;
            margin-top: 5px;
            font-size: 15px;
        }

        .navigation a {
            display: block;
            padding: 13px 15px;
            margin-bottom: 9px;
            color: #d1d5db;
            text-decoration: none;
            border-radius: 12px;
            transition: 0.25s;
        }

        .navigation a:hover,
        .navigation a.active {
            color: #ffffff;
            transform: translateX(3px);
            background: linear-gradient(
                135deg,
                rgba(99, 102, 241, 0.76),
                rgba(14, 165, 233, 0.56)
            );
        }

        .main-content {
            width: calc(100% - 260px);
            margin-left: 260px;
            padding: 35px;
        }

        .top-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .top-section h1 {
            margin-bottom: 7px;
            font-size: 31px;
        }

        .top-section p {
            color: #aeb8c8;
        }

        .back-button {
            display: inline-block;
            padding: 11px 18px;
            color: #ffffff;
            text-decoration: none;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.13);
            transition: 0.25s;
        }

        .back-button:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.15);
        }

        .glass-card {
            padding: 25px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.11);
            backdrop-filter: blur(18px);
            box-shadow: 0 20px 55px rgba(0, 0, 0, 0.22);
        }

        .student-section {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            margin-bottom: 25px;
        }

        .student-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 24px;
        }

        .student-avatar {
            width: 75px;
            height: 75px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 29px;
            font-weight: 700;
            background: linear-gradient(
                135deg,
                #6366f1,
                #0ea5e9
            );
        }

        .student-header h2 {
            margin-bottom: 6px;
            font-size: 23px;
        }

        .student-header p {
            color: #93c5fd;
        }

        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .detail-box {
            padding: 15px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .detail-box span {
            display: block;
            margin-bottom: 6px;
            color: #9ca3af;
            font-size: 12px;
        }

        .detail-box strong {
            font-size: 14px;
            word-break: break-word;
        }

        .attendance-result {
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .result-icon {
            width: 74px;
            height: 74px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            border-radius: 50%;
            font-size: 30px;
            font-weight: 700;
        }

        .attendance-result h2 {
            margin-bottom: 8px;
        }

        .attendance-result p {
            color: #aeb8c8;
            line-height: 1.6;
        }

        .excellent .result-icon {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.18);
            border: 1px solid rgba(34, 197, 94, 0.42);
        }

        .good .result-icon {
            color: #bfdbfe;
            background: rgba(59, 130, 246, 0.18);
            border: 1px solid rgba(59, 130, 246, 0.42);
        }

        .warning .result-icon {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.18);
            border: 1px solid rgba(245, 158, 11, 0.42);
        }

        .neutral .result-icon {
            color: #d1d5db;
            background: rgba(156, 163, 175, 0.15);
            border: 1px solid rgba(156, 163, 175, 0.30);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            padding: 22px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(16px);
        }

        .summary-card span {
            display: block;
            margin-bottom: 10px;
            color: #9ca3af;
            font-size: 13px;
        }

        .summary-card strong {
            font-size: 29px;
        }

        .summary-card small {
            display: block;
            margin-top: 7px;
            color: #aeb8c8;
        }

        .history-card {
            overflow: hidden;
        }

        .history-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 22px;
        }

        .history-heading h2 {
            margin-bottom: 6px;
            font-size: 22px;
        }

        .history-heading p {
            color: #9ca3af;
            font-size: 14px;
        }

        .record-count {
            padding: 9px 13px;
            color: #bfdbfe;
            white-space: nowrap;
            border-radius: 11px;
            background: rgba(59, 130, 246, 0.14);
            border: 1px solid rgba(59, 130, 246, 0.28);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 13px;
            align-items: end;
            padding: 18px;
            margin-bottom: 22px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #dbeafe;
            font-size: 13px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 13px;
            color: #ffffff;
            outline: none;
            border-radius: 11px;
            background: rgba(4, 12, 25, 0.72);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .form-control:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.14);
        }

        .filter-button,
        .reset-button {
            padding: 12px 17px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            border-radius: 11px;
            font-weight: 700;
            transition: 0.25s;
        }

        .filter-button {
            color: #ffffff;
            background: linear-gradient(
                135deg,
                #6366f1,
                #0ea5e9
            );
        }

        .reset-button {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.13);
        }

        .filter-button:hover,
        .reset-button:hover {
            transform: translateY(-2px);
        }

        .error-message {
            padding: 13px 15px;
            margin-bottom: 20px;
            color: #fecaca;
            border-radius: 12px;
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.30);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
        }

        thead {
            background: rgba(255, 255, 255, 0.06);
        }

        th,
        td {
            padding: 15px 14px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        th {
            color: #cbd5e1;
            font-size: 13px;
            letter-spacing: 0.3px;
        }

        td {
            color: #e5e7eb;
            font-size: 14px;
        }

        tbody tr {
            transition: 0.2s;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .record-number {
            color: #93c5fd;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            min-width: 88px;
            padding: 7px 11px;
            text-align: center;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-present {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.16);
            border: 1px solid rgba(34, 197, 94, 0.35);
        }

        .status-absent {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.16);
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        .edit-button {
            display: inline-block;
            padding: 8px 13px;
            color: #ffffff;
            text-decoration: none;
            border-radius: 9px;
            background: rgba(99, 102, 241, 0.22);
            border: 1px solid rgba(99, 102, 241, 0.40);
            transition: 0.2s;
        }

        .edit-button:hover {
            transform: translateY(-1px);
            background: rgba(99, 102, 241, 0.38);
        }

        .empty-state {
            padding: 45px 20px;
            text-align: center;
            color: #9ca3af;
        }

        .empty-state h3 {
            margin-bottom: 9px;
            color: #ffffff;
        }

        @media (max-width: 1100px) {
            .student-section {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 800px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                width: calc(100% - 220px);
                margin-left: 220px;
                padding: 25px;
            }

            .student-details {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .page-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.10);
            }

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 20px;
            }

            .top-section,
            .history-heading {
                flex-direction: column;
                align-items: flex-start;
            }

            .summary-grid,
            .filter-form {
                grid-template-columns: 1fr;
            }

            .student-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="page-layout">

    <aside class="sidebar">

        <div class="brand">
            <h2>Smart Student Management</h2>
            <p>Administrator Control Panel</p>
        </div>

        <div class="admin-profile">
            <span>Logged in as</span>

            <strong>
                <?php echo htmlspecialchars($adminName); ?>
            </strong>
        </div>

        <nav class="navigation">

            <a href="admin_dashboard.php">
                Dashboard
            </a>

            <a href="manage_students.php">
                Manage Students
            </a>

            <a href="manage_attendance.php" class="active">
                Manage Attendance
            </a>

            <a href="manage_marks.php">
                Internal Marks
            </a>

            <a href="manage_rooms.php">
                Manage Rooms
            </a>

            <a href="admin_room_allocation.php">
                Room Allocation
            </a>

            <a href="logout.php">
                Logout
            </a>

        </nav>

    </aside>

    <main class="main-content">

        <section class="top-section">

            <div>
                <h1>View Attendance</h1>

                <p>
                    View the student’s attendance summary and daily history.
                </p>
            </div>

            <a href="manage_attendance.php" class="back-button">
                ← Back to Attendance
            </a>

        </section>

        <section class="student-section">

            <div class="glass-card">

                <div class="student-header">

                    <div class="student-avatar">
                        <?php
                        echo strtoupper(
                            substr($student["full_name"], 0, 1)
                        );
                        ?>
                    </div>

                    <div>
                        <h2>
                            <?php
                            echo htmlspecialchars(
                                $student["full_name"]
                            );
                            ?>
                        </h2>

                        <p>
                            <?php
                            echo htmlspecialchars(
                                $student["student_id"]
                            );
                            ?>
                        </p>
                    </div>

                </div>

                <div class="student-details">

                    <div class="detail-box">
                        <span>Email Address</span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["email"]
                            );
                            ?>
                        </strong>
                    </div>

                    <div class="detail-box">
                        <span>Phone Number</span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["phone"] ?: "Not provided"
                            );
                            ?>
                        </strong>
                    </div>

                    <div class="detail-box">
                        <span>Department</span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["department"]
                            );
                            ?>
                        </strong>
                    </div>

                    <div class="detail-box">
                        <span>Academic Year</span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["year"]
                            );
                            ?>
                        </strong>
                    </div>

                </div>

            </div>

            <div class="glass-card attendance-result <?php
                echo $attendanceMessageClass;
            ?>">

                <div class="result-icon">
                    <?php echo $attendanceIcon; ?>
                </div>

                <h2>
                    <?php echo htmlspecialchars($attendanceMessage); ?>
                </h2>

                <p>
                    Current attendance percentage:
                    <strong>
                        <?php
                        echo number_format(
                            $attendancePercentage,
                            2
                        );
                        ?>%
                    </strong>
                </p>

            </div>

        </section>

        <section class="summary-grid">

            <div class="summary-card">
                <span>Total Classes</span>

                <strong>
                    <?php echo $totalClasses; ?>
                </strong>

                <small>Attendance records marked</small>
            </div>

            <div class="summary-card">
                <span>Present</span>

                <strong>
                    <?php echo $attendedClasses; ?>
                </strong>

                <small>Classes attended</small>
            </div>

            <div class="summary-card">
                <span>Absent</span>

                <strong>
                    <?php echo $absentClasses; ?>
                </strong>

                <small>Classes missed</small>
            </div>

            <div class="summary-card">
                <span>Attendance Percentage</span>

                <strong>
                    <?php
                    echo number_format(
                        $attendancePercentage,
                        2
                    );
                    ?>%
                </strong>

                <small>Overall performance</small>
            </div>

        </section>

        <section class="glass-card history-card">

            <div class="history-heading">

                <div>
                    <h2>Attendance History</h2>

                    <p>
                        Daily attendance records are shown newest first.
                    </p>
                </div>

                <div class="record-count">
                    <?php echo count($history); ?>
                    record<?php echo count($history) === 1 ? "" : "s"; ?>
                </div>

            </div>

            <form method="GET" class="filter-form">

                <input
                    type="hidden"
                    name="student_id"
                    value="<?php
                    echo htmlspecialchars($studentId);
                    ?>"
                >

                <div class="form-group">

                    <label for="from_date">
                        From Date
                    </label>

                    <input
                        type="date"
                        id="from_date"
                        name="from_date"
                        class="form-control"
                        value="<?php
                        echo htmlspecialchars($fromDate);
                        ?>"
                        max="<?php echo date("Y-m-d"); ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="to_date">
                        To Date
                    </label>

                    <input
                        type="date"
                        id="to_date"
                        name="to_date"
                        class="form-control"
                        value="<?php
                        echo htmlspecialchars($toDate);
                        ?>"
                        max="<?php echo date("Y-m-d"); ?>"
                    >

                </div>

                <button type="submit" class="filter-button">
                    Apply Filter
                </button>

                <a
                    href="view_attendance.php?student_id=<?php
                    echo urlencode($studentId);
                    ?>"
                    class="reset-button"
                >
                    Reset
                </a>

            </form>

            <?php if ($filterError !== ""): ?>

                <div class="error-message">
                    <?php echo htmlspecialchars($filterError); ?>
                </div>

            <?php endif; ?>

            <?php if (count($history) > 0): ?>

                <div class="table-container">

                    <table>

                        <thead>
                            <tr>
                                <th>Record</th>
                                <th>Attendance Date</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Recorded On</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($history as $record): ?>

                            <tr>

                                <td class="record-number">
                                    #<?php echo (int)$record["id"]; ?>
                                </td>

                                <td>
                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $record["attendance_date"]
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo date(
                                        "l",
                                        strtotime(
                                            $record["attendance_date"]
                                        )
                                    );
                                    ?>
                                </td>

                                <td>

                                    <?php
                                    $statusClass =
                                        $record["status"] === "Present"
                                        ? "status-present"
                                        : "status-absent";
                                    ?>

                                    <span class="status-badge <?php
                                        echo $statusClass;
                                    ?>">
                                        <?php
                                        echo htmlspecialchars(
                                            $record["status"]
                                        );
                                        ?>
                                    </span>

                                </td>

                                <td>
                                    <?php
                                    echo date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $record["created_at"]
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <a
                                        href="edit_attendance.php?id=<?php
                                        echo (int)$record["id"];
                                        ?>"
                                        class="edit-button"
                                    >
                                        Edit
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <h3>No Attendance Records Found</h3>

                    <p>
                        No attendance records match the selected dates.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>