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
   HELPER FUNCTION: UPDATE ATTENDANCE SUMMARY
------------------------------------------------------- */

function updateAttendanceSummary($conn, $studentId)
{
    $summaryQuery = "
        SELECT
            COUNT(*) AS total_classes,
            SUM(
                CASE
                    WHEN status = 'Present' THEN 1
                    ELSE 0
                END
            ) AS attended_classes
        FROM attendance_records
        WHERE student_id = ?
    ";

    $summaryStatement = mysqli_prepare($conn, $summaryQuery);

    if (!$summaryStatement) {
        throw new Exception("Unable to prepare attendance summary query.");
    }

    mysqli_stmt_bind_param($summaryStatement, "s", $studentId);
    mysqli_stmt_execute($summaryStatement);

    $summaryResult = mysqli_stmt_get_result($summaryStatement);
    $summaryData = mysqli_fetch_assoc($summaryResult);

    mysqli_stmt_close($summaryStatement);

    $totalClasses = (int)($summaryData["total_classes"] ?? 0);
    $attendedClasses = (int)($summaryData["attended_classes"] ?? 0);

    if ($totalClasses > 0) {
        $attendancePercentage = ($attendedClasses / $totalClasses) * 100;
    } else {
        $attendancePercentage = 0;
    }

    $attendancePercentage = round($attendancePercentage, 2);

    $checkQuery = "
        SELECT id
        FROM attendance
        WHERE student_id = ?
        LIMIT 1
    ";

    $checkStatement = mysqli_prepare($conn, $checkQuery);

    if (!$checkStatement) {
        throw new Exception("Unable to check attendance summary.");
    }

    mysqli_stmt_bind_param($checkStatement, "s", $studentId);
    mysqli_stmt_execute($checkStatement);

    $checkResult = mysqli_stmt_get_result($checkStatement);
    $summaryExists = mysqli_num_rows($checkResult) > 0;

    mysqli_stmt_close($checkStatement);

    if ($summaryExists) {
        $updateQuery = "
            UPDATE attendance
            SET
                total_classes = ?,
                attended_classes = ?,
                attendance_percentage = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE student_id = ?
        ";

        $updateStatement = mysqli_prepare($conn, $updateQuery);

        if (!$updateStatement) {
            throw new Exception("Unable to prepare attendance summary update.");
        }

        mysqli_stmt_bind_param(
            $updateStatement,
            "iids",
            $totalClasses,
            $attendedClasses,
            $attendancePercentage,
            $studentId
        );

        if (!mysqli_stmt_execute($updateStatement)) {
            throw new Exception("Unable to update attendance summary.");
        }

        mysqli_stmt_close($updateStatement);
    } else {
        $insertQuery = "
            INSERT INTO attendance
            (
                student_id,
                total_classes,
                attended_classes,
                attendance_percentage
            )
            VALUES (?, ?, ?, ?)
        ";

        $insertStatement = mysqli_prepare($conn, $insertQuery);

        if (!$insertStatement) {
            throw new Exception("Unable to prepare attendance summary insert.");
        }

        mysqli_stmt_bind_param(
            $insertStatement,
            "siid",
            $studentId,
            $totalClasses,
            $attendedClasses,
            $attendancePercentage
        );

        if (!mysqli_stmt_execute($insertStatement)) {
            throw new Exception("Unable to insert attendance summary.");
        }

        mysqli_stmt_close($insertStatement);
    }
}

/* -------------------------------------------------------
   GET ATTENDANCE RECORD ID
------------------------------------------------------- */

$attendanceId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($attendanceId <= 0) {
    header("Location: manage_attendance.php");
    exit();
}

/* -------------------------------------------------------
   LOAD CURRENT ATTENDANCE RECORD
------------------------------------------------------- */

$recordQuery = "
    SELECT
        ar.id,
        ar.student_id,
        ar.attendance_date,
        ar.status,
        s.full_name,
        s.email,
        s.department,
        s.year
    FROM attendance_records ar
    INNER JOIN students s
        ON ar.student_id = s.student_id
    WHERE ar.id = ?
    LIMIT 1
";

$recordStatement = mysqli_prepare($conn, $recordQuery);

if (!$recordStatement) {
    die("Unable to load attendance record.");
}

mysqli_stmt_bind_param($recordStatement, "i", $attendanceId);
mysqli_stmt_execute($recordStatement);

$recordResult = mysqli_stmt_get_result($recordStatement);
$attendanceRecord = mysqli_fetch_assoc($recordResult);

mysqli_stmt_close($recordStatement);

if (!$attendanceRecord) {
    header("Location: manage_attendance.php");
    exit();
}

/* -------------------------------------------------------
   DEFAULT VALUES
------------------------------------------------------- */

$attendanceDate = $attendanceRecord["attendance_date"];
$attendanceStatus = $attendanceRecord["status"];

$errorMessage = "";
$successMessage = "";

/* -------------------------------------------------------
   UPDATE ATTENDANCE
------------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $attendanceDate = trim($_POST["attendance_date"] ?? "");
    $attendanceStatus = trim($_POST["status"] ?? "");

    $allowedStatuses = ["Present", "Absent"];
    $today = date("Y-m-d");

    if ($attendanceDate === "") {
        $errorMessage = "Please select the attendance date.";
    } elseif ($attendanceDate > $today) {
        $errorMessage = "Future attendance dates are not allowed.";
    } elseif (!in_array($attendanceStatus, $allowedStatuses, true)) {
        $errorMessage = "Please select a valid attendance status.";
    } else {
        $duplicateQuery = "
            SELECT id
            FROM attendance_records
            WHERE
                student_id = ?
                AND attendance_date = ?
                AND id != ?
            LIMIT 1
        ";

        $duplicateStatement = mysqli_prepare($conn, $duplicateQuery);

        if (!$duplicateStatement) {
            $errorMessage = "Unable to validate the attendance record.";
        } else {
            mysqli_stmt_bind_param(
                $duplicateStatement,
                "ssi",
                $attendanceRecord["student_id"],
                $attendanceDate,
                $attendanceId
            );

            mysqli_stmt_execute($duplicateStatement);
            $duplicateResult = mysqli_stmt_get_result($duplicateStatement);

            if (mysqli_num_rows($duplicateResult) > 0) {
                $errorMessage =
                    "Attendance has already been marked for this student on the selected date.";
            }

            mysqli_stmt_close($duplicateStatement);
        }
    }

    if ($errorMessage === "") {
        mysqli_begin_transaction($conn);

        try {
            $updateQuery = "
                UPDATE attendance_records
                SET
                    attendance_date = ?,
                    status = ?
                WHERE id = ?
            ";

            $updateStatement = mysqli_prepare($conn, $updateQuery);

            if (!$updateStatement) {
                throw new Exception("Unable to prepare attendance update.");
            }

            mysqli_stmt_bind_param(
                $updateStatement,
                "ssi",
                $attendanceDate,
                $attendanceStatus,
                $attendanceId
            );

            if (!mysqli_stmt_execute($updateStatement)) {
                throw new Exception("Unable to update attendance.");
            }

            mysqli_stmt_close($updateStatement);

            updateAttendanceSummary(
                $conn,
                $attendanceRecord["student_id"]
            );

            mysqli_commit($conn);

            $_SESSION["attendance_success"] =
                "Attendance updated successfully.";

            header("Location: manage_attendance.php");
            exit();
        } catch (Exception $exception) {
            mysqli_rollback($conn);

            $errorMessage =
                "Attendance could not be updated. Please try again.";
        }
    }
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
        Edit Attendance | Smart Student Management System
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
                    rgba(80, 70, 229, 0.30),
                    transparent 35%
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
            background: rgba(10, 18, 34, 0.82);
            border-right: 1px solid rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(18px);
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            overflow-y: auto;
        }

        .brand {
            margin-bottom: 35px;
        }

        .brand h2 {
            font-size: 23px;
            line-height: 1.3;
        }

        .brand p {
            color: #9ca3af;
            font-size: 13px;
            margin-top: 8px;
        }

        .admin-profile {
            padding: 16px;
            margin-bottom: 28px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
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
            text-decoration: none;
            color: #d1d5db;
            padding: 13px 15px;
            margin-bottom: 9px;
            border-radius: 12px;
            transition: 0.25s;
        }

        .navigation a:hover,
        .navigation a.active {
            color: #ffffff;
            background: linear-gradient(
                135deg,
                rgba(99, 102, 241, 0.75),
                rgba(14, 165, 233, 0.55)
            );
            transform: translateX(3px);
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
            font-size: 31px;
            margin-bottom: 7px;
        }

        .top-section p {
            color: #aeb8c8;
        }

        .back-button {
            display: inline-block;
            text-decoration: none;
            color: #ffffff;
            padding: 11px 18px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.13);
            transition: 0.25s;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            gap: 25px;
        }

        .glass-card {
            padding: 27px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.11);
            backdrop-filter: blur(18px);
            box-shadow: 0 20px 55px rgba(0, 0, 0, 0.22);
        }

        .card-heading {
            margin-bottom: 23px;
        }

        .card-heading h2 {
            font-size: 21px;
            margin-bottom: 6px;
        }

        .card-heading p {
            color: #9ca3af;
            font-size: 14px;
        }

        .student-avatar {
            width: 72px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
            border-radius: 50%;
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(
                135deg,
                #6366f1,
                #0ea5e9
            );
        }

        .student-name {
            font-size: 22px;
            margin-bottom: 7px;
        }

        .student-id {
            color: #93c5fd;
            margin-bottom: 24px;
        }

        .detail-row {
            padding: 13px 0;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #9ca3af;
        }

        .detail-value {
            text-align: right;
            font-weight: 600;
        }

        .message {
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-size: 14px;
        }

        .error-message {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.30);
        }

        .success-message {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.14);
            border: 1px solid rgba(34, 197, 94, 0.30);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: #e5e7eb;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 13px 14px;
            color: #ffffff;
            outline: none;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(4, 12, 25, 0.70);
            transition: 0.25s;
        }

        .form-control:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.14);
        }

        select.form-control option {
            color: #ffffff;
            background: #101827;
        }

        .status-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .status-option input {
            display: none;
        }

        .status-option label {
            display: block;
            padding: 17px;
            margin: 0;
            text-align: center;
            cursor: pointer;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.13);
            background: rgba(255, 255, 255, 0.05);
            transition: 0.25s;
        }

        .present-option input:checked + label {
            color: #bbf7d0;
            border-color: rgba(34, 197, 94, 0.70);
            background: rgba(34, 197, 94, 0.17);
        }

        .absent-option input:checked + label {
            color: #fecaca;
            border-color: rgba(239, 68, 68, 0.70);
            background: rgba(239, 68, 68, 0.17);
        }

        .form-note {
            color: #9ca3af;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 18px;
            padding: 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.04);
        }

        .button-row {
            display: flex;
            gap: 13px;
            margin-top: 25px;
        }

        .submit-button,
        .cancel-button {
            border: none;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            padding: 13px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            transition: 0.25s;
        }

        .submit-button {
            flex: 1;
            color: #ffffff;
            background: linear-gradient(
                135deg,
                #6366f1,
                #0ea5e9
            );
        }

        .cancel-button {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.13);
        }

        .submit-button:hover,
        .cancel-button:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 1000px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                width: calc(100% - 220px);
                margin-left: 220px;
                padding: 25px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
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

            .top-section {
                align-items: flex-start;
                flex-direction: column;
            }

            .status-options {
                grid-template-columns: 1fr;
            }

            .button-row {
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
                <h1>Edit Attendance</h1>

                <p>
                    Correct the attendance date or status for this student.
                </p>
            </div>

            <a href="manage_attendance.php" class="back-button">
                ← Back to Attendance
            </a>

        </section>

        <section class="content-grid">

            <div class="glass-card">

                <div class="card-heading">
                    <h2>Student Information</h2>
                    <p>Attendance belongs to the following student.</p>
                </div>

                <div class="student-avatar">
                    <?php
                    echo strtoupper(
                        substr(
                            $attendanceRecord["full_name"],
                            0,
                            1
                        )
                    );
                    ?>
                </div>

                <h3 class="student-name">
                    <?php
                    echo htmlspecialchars(
                        $attendanceRecord["full_name"]
                    );
                    ?>
                </h3>

                <div class="student-id">
                    <?php
                    echo htmlspecialchars(
                        $attendanceRecord["student_id"]
                    );
                    ?>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Email</span>

                    <span class="detail-value">
                        <?php
                        echo htmlspecialchars(
                            $attendanceRecord["email"]
                        );
                        ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Department</span>

                    <span class="detail-value">
                        <?php
                        echo htmlspecialchars(
                            $attendanceRecord["department"]
                        );
                        ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Academic Year</span>

                    <span class="detail-value">
                        <?php
                        echo htmlspecialchars(
                            $attendanceRecord["year"]
                        );
                        ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Record Number</span>

                    <span class="detail-value">
                        #<?php echo $attendanceId; ?>
                    </span>
                </div>

            </div>

            <div class="glass-card">

                <div class="card-heading">
                    <h2>Update Attendance Record</h2>

                    <p>
                        Select the correct date and attendance status.
                    </p>
                </div>

                <?php if ($errorMessage !== ""): ?>

                    <div class="message error-message">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>

                <?php endif; ?>

                <?php if ($successMessage !== ""): ?>

                    <div class="message success-message">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>

                <?php endif; ?>

                <form method="POST" action="">

                    <div class="form-group">

                        <label for="attendance_date">
                            Attendance Date
                        </label>

                        <input
                            type="date"
                            id="attendance_date"
                            name="attendance_date"
                            class="form-control"
                            value="<?php
                            echo htmlspecialchars($attendanceDate);
                            ?>"
                            max="<?php echo date("Y-m-d"); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Attendance Status</label>

                        <div class="status-options">

                            <div class="status-option present-option">

                                <input
                                    type="radio"
                                    id="present"
                                    name="status"
                                    value="Present"
                                    <?php
                                    echo $attendanceStatus === "Present"
                                        ? "checked"
                                        : "";
                                    ?>
                                    required
                                >

                                <label for="present">
                                    ✓ Present
                                </label>

                            </div>

                            <div class="status-option absent-option">

                                <input
                                    type="radio"
                                    id="absent"
                                    name="status"
                                    value="Absent"
                                    <?php
                                    echo $attendanceStatus === "Absent"
                                        ? "checked"
                                        : "";
                                    ?>
                                    required
                                >

                                <label for="absent">
                                    ✕ Absent
                                </label>

                            </div>

                        </div>

                    </div>

                    <div class="form-note">
                        After updating this record, the student’s total
                        classes, attended classes and attendance percentage
                        will be recalculated automatically.
                    </div>

                    <div class="button-row">

                        <button
                            type="submit"
                            class="submit-button"
                        >
                            Save Attendance Changes
                        </button>

                        <a
                            href="manage_attendance.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

</body>
</html>