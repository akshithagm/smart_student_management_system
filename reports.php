<?php
session_start();
include "db_connect.php";

/* Check student login */
if (!isset($_SESSION["student_id"])) {
    header("Location: student_login.html");
    exit();
}

$student_id = $_SESSION["student_id"];

/* =========================================
   FETCH LOGGED-IN STUDENT
========================================= */

$student_sql = "
    SELECT
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

$student_stmt = mysqli_prepare($conn, $student_sql);

if (!$student_stmt) {
    die("Student query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $student_stmt,
    "s",
    $student_id
);

mysqli_stmt_execute($student_stmt);

$student_result = mysqli_stmt_get_result($student_stmt);

if (mysqli_num_rows($student_result) !== 1) {
    session_destroy();
    header("Location: student_login.html");
    exit();
}

$student = mysqli_fetch_assoc($student_result);

/* =========================================
   FETCH ATTENDANCE
========================================= */

$attendance = null;

$attendance_sql = "
    SELECT
        total_classes,
        attended_classes,
        attendance_percentage
    FROM attendance
    WHERE student_id = ?
    ORDER BY id DESC
    LIMIT 1
";

$attendance_stmt = mysqli_prepare($conn, $attendance_sql);

if (!$attendance_stmt) {
    die("Attendance query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $attendance_stmt,
    "s",
    $student_id
);

mysqli_stmt_execute($attendance_stmt);

$attendance_result = mysqli_stmt_get_result($attendance_stmt);

if (mysqli_num_rows($attendance_result) > 0) {
    $attendance = mysqli_fetch_assoc($attendance_result);
}

/* =========================================
   FETCH MARKS
========================================= */

$marks = [];

$marks_sql = "
    SELECT
        subject_name,
        internal_marks,
        maximum_marks
    FROM marks
    WHERE student_id = ?
    ORDER BY subject_name ASC
";

$marks_stmt = mysqli_prepare($conn, $marks_sql);

if (!$marks_stmt) {
    die("Marks query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $marks_stmt,
    "s",
    $student_id
);

mysqli_stmt_execute($marks_stmt);

$marks_result = mysqli_stmt_get_result($marks_stmt);

/* =========================================
   CALCULATE MARKS AND RESULT
========================================= */

$total_obtained = 0;
$total_maximum = 0;
$all_subjects_passed = true;

while ($mark = mysqli_fetch_assoc($marks_result)) {

    $internal_marks = (float) $mark["internal_marks"];
    $maximum_marks = (float) $mark["maximum_marks"];

    $subject_percentage = 0;

    if ($maximum_marks > 0) {
        $subject_percentage =
            ($internal_marks / $maximum_marks) * 100;
    }

    $mark["subject_percentage"] = $subject_percentage;

    $mark["subject_status"] =
        $subject_percentage >= 35
        ? "Pass"
        : "Fail";

    if ($subject_percentage < 35) {
        $all_subjects_passed = false;
    }

    $total_obtained += $internal_marks;
    $total_maximum += $maximum_marks;

    $marks[] = $mark;
}

$has_marks = count($marks) > 0;

$overall_percentage = 0;

if ($total_maximum > 0) {
    $overall_percentage =
        ($total_obtained / $total_maximum) * 100;
}

if (!$has_marks) {
    $overall_result = "Pending";
    $result_class = "pending";
} elseif (
    $overall_percentage >= 35 &&
    $all_subjects_passed
) {
    $overall_result = "Pass";
    $result_class = "pass";
} else {
    $overall_result = "Fail";
    $result_class = "fail";
}

/* Attendance status */

$attendance_status = "Pending";
$attendance_class = "pending";

if ($attendance) {

    $attendance_percentage =
        (float) $attendance["attendance_percentage"];

    if ($attendance_percentage >= 75) {
        $attendance_status = "Good";
        $attendance_class = "pass";
    } else {
        $attendance_status = "Low";
        $attendance_class = "fail";
    }
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

    <title>Student Reports</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body class="student-reports-page">

<div class="student-dashboard-layout">

    <!-- =========================================
         SIDEBAR
    ========================================== -->

    <aside class="student-sidebar">

        <div class="student-sidebar-logo">

            <i class="fa-solid fa-graduation-cap"></i>

            <div>
                <h2>SSMS</h2>
                <p>Student Portal</p>
            </div>

        </div>

        <nav class="student-sidebar-menu">

            <a href="student_dashboard.php">

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

            <a
                href="reports.php"
                class="active"
            >

                <i class="fa-solid fa-chart-column"></i>

                Reports

            </a>

            <a
                href="logout.php"
                class="logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                Logout

            </a>

        </nav>

    </aside>

    <!-- =========================================
         MAIN CONTENT
    ========================================== -->

    <main class="student-reports-main">

        <!-- HEADER -->

        <header class="student-reports-header">

            <div>

                <p class="dashboard-label">
                    Student Portal
                </p>

                <h1>Academic Reports</h1>

                <p>
                    View your attendance, subject marks
                    and overall academic performance.
                </p>

            </div>

            <div class="reports-student-profile">

                <div class="student-avatar">

                    <?php
                    echo strtoupper(
                        substr(
                            $student["full_name"],
                            0,
                            1
                        )
                    );
                    ?>

                </div>

                <div>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $student["full_name"]
                        );
                        ?>

                    </strong>

                    <span>

                        <?php
                        echo htmlspecialchars(
                            $student["student_id"]
                        );
                        ?>

                    </span>

                </div>

            </div>

        </header>

        <!-- =========================================
             SUMMARY CARDS
        ========================================== -->

        <section class="reports-summary-grid">

            <div class="reports-summary-card">

                <div class="reports-summary-icon">

                    <i class="fa-solid fa-id-card"></i>

                </div>

                <div>

                    <p>Student ID</p>

                    <h3>

                        <?php
                        echo htmlspecialchars(
                            $student["student_id"]
                        );
                        ?>

                    </h3>

                </div>

            </div>

            <div class="reports-summary-card">

                <div class="reports-summary-icon">

                    <i class="fa-solid fa-building-columns"></i>

                </div>

                <div>

                    <p>Department</p>

                    <h3>

                        <?php
                        echo htmlspecialchars(
                            $student["department"]
                        );
                        ?>

                    </h3>

                </div>

            </div>

            <div class="reports-summary-card">

                <div class="reports-summary-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>

                <div>

                    <p>Academic Year</p>

                    <h3>

                        <?php
                        echo htmlspecialchars(
                            $student["year"]
                        );
                        ?>

                    </h3>

                </div>

            </div>

            <div class="reports-summary-card">

                <div class="reports-summary-icon success-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>

                <div>

                    <p>Account Status</p>

                    <h3>Active</h3>

                </div>

            </div>

        </section>

        <!-- =========================================
             STUDENT INFORMATION + ACADEMIC UPDATES
        ========================================== -->

        <section class="reports-content-grid">

            <!-- STUDENT INFORMATION -->

            <div class="academic-report-card">

                <div class="reports-section-heading">

                    <div>

                        <p>Student Record</p>

                        <h2>Academic Information</h2>

                    </div>

                    <i class="fa-solid fa-file-lines"></i>

                </div>

                <div class="academic-details-grid">

                    <div class="academic-detail-item">

                        <span>Full Name</span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["full_name"]
                            );
                            ?>

                        </strong>

                    </div>

                    <div class="academic-detail-item">

                        <span>Email Address</span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["email"]
                            );
                            ?>

                        </strong>

                    </div>

                    <div class="academic-detail-item">

                        <span>Phone Number</span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["phone"]
                            );
                            ?>

                        </strong>

                    </div>

                    <div class="academic-detail-item">

                        <span>Department</span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["department"]
                            );
                            ?>

                        </strong>

                    </div>

                    <div class="academic-detail-item">

                        <span>Academic Year</span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $student["year"]
                            );
                            ?>

                        </strong>

                    </div>

                    <div class="academic-detail-item">

                        <span>Student Status</span>

                        <strong class="academic-active-text">
                            Active Student
                        </strong>

                    </div>

                </div>

            </div>

            <!-- ACADEMIC UPDATES -->

            <div class="report-status-card">

                <div class="reports-section-heading">

                    <div>

                        <p>Report Status</p>

                        <h2>Academic Updates</h2>

                    </div>

                    <i class="fa-solid fa-chart-line"></i>

                </div>

                <div class="report-status-list">

                    <!-- ATTENDANCE -->

                    <div class="report-status-item">

                        <div class="report-status-icon attendance-icon">

                            <i class="fa-solid fa-calendar-check"></i>

                        </div>

                        <div class="report-update-content">

                            <strong>Attendance Report</strong>

                            <?php if ($attendance): ?>

                                <h3>

                                    <?php
                                    echo number_format(
                                        $attendance[
                                            "attendance_percentage"
                                        ],
                                        2
                                    );
                                    ?>%

                                </h3>

                                <p>

                                    Attended

                                    <?php
                                    echo (int)
                                        $attendance[
                                            "attended_classes"
                                        ];
                                    ?>

                                    out of

                                    <?php
                                    echo (int)
                                        $attendance[
                                            "total_classes"
                                        ];
                                    ?>

                                    classes.

                                </p>

                                <span
                                    class="report-small-badge
                                    <?php echo $attendance_class; ?>"
                                >

                                    <?php
                                    echo $attendance_status;
                                    ?>

                                </span>

                            <?php else: ?>

                                <h3 class="result-text pending">
                                    Pending
                                </h3>

                                <p>
                                    Attendance has not been added
                                    by the admin.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- INTERNAL MARKS -->

                    <div class="report-status-item">

                        <div class="report-status-icon marks-icon">

                            <i class="fa-solid fa-book-open"></i>

                        </div>

                        <div class="report-update-content">

                            <strong>Internal Marks</strong>

                            <?php if ($has_marks): ?>

                                <h3>

                                    <?php
                                    echo count($marks);
                                    ?>

                                    Subjects

                                </h3>

                                <p>

                                    Total marks:

                                    <?php
                                    echo number_format(
                                        $total_obtained,
                                        2
                                    );
                                    ?>

                                    /

                                    <?php
                                    echo number_format(
                                        $total_maximum,
                                        2
                                    );
                                    ?>

                                </p>

                            <?php else: ?>

                                <h3 class="result-text pending">
                                    Pending
                                </h3>

                                <p>
                                    Internal marks have not been
                                    added by the admin.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- OVERALL RESULT -->

                    <div class="report-status-item">

                        <div
                            class="report-status-icon
                            <?php echo $result_class; ?>"
                        >

                            <i class="fa-solid fa-trophy"></i>

                        </div>

                        <div class="report-update-content">

                            <strong>Overall Result</strong>

                            <?php if ($has_marks): ?>

                                <h3
                                    class="result-text
                                    <?php echo $result_class; ?>"
                                >

                                    <?php
                                    echo $overall_result;
                                    ?>

                                </h3>

                                <p>

                                    Overall percentage:

                                    <?php
                                    echo number_format(
                                        $overall_percentage,
                                        2
                                    );
                                    ?>%

                                </p>

                            <?php else: ?>

                                <h3 class="result-text pending">
                                    Pending
                                </h3>

                                <p>
                                    The result will appear after
                                    marks are added.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </section>

        <!-- =========================================
             ATTENDANCE DETAILS
        ========================================== -->

        <section class="attendance-report-card">

            <div class="reports-section-heading">

                <div>

                    <p>Attendance Performance</p>

                    <h2>Attendance Details</h2>

                </div>

                <i class="fa-solid fa-user-check"></i>

            </div>

            <?php if ($attendance): ?>

                <div class="attendance-details-grid">

                    <div class="attendance-detail-box">

                        <span>Total Classes</span>

                        <strong>

                            <?php
                            echo (int)
                                $attendance["total_classes"];
                            ?>

                        </strong>

                    </div>

                    <div class="attendance-detail-box">

                        <span>Attended Classes</span>

                        <strong>

                            <?php
                            echo (int)
                                $attendance["attended_classes"];
                            ?>

                        </strong>

                    </div>

                    <div class="attendance-detail-box">

                        <span>Missed Classes</span>

                        <strong>

                            <?php
                            echo max(
                                0,
                                (int)
                                    $attendance["total_classes"]
                                -
                                (int)
                                    $attendance[
                                        "attended_classes"
                                    ]
                            );
                            ?>

                        </strong>

                    </div>

                    <div class="attendance-detail-box">

                        <span>Attendance Percentage</span>

                        <strong
                            class="attendance-percentage-text
                            <?php echo $attendance_class; ?>"
                        >

                            <?php
                            echo number_format(
                                $attendance[
                                    "attendance_percentage"
                                ],
                                2
                            );
                            ?>%

                        </strong>

                    </div>

                </div>

                <div class="attendance-progress-container">

                    <div class="attendance-progress-info">

                        <span>Attendance Progress</span>

                        <strong>

                            <?php
                            echo number_format(
                                $attendance[
                                    "attendance_percentage"
                                ],
                                2
                            );
                            ?>%

                        </strong>

                    </div>

                    <div class="attendance-progress-bar">

                        <div
                            class="attendance-progress-fill"
                            style="width:
                            <?php
                            echo min(
                                100,
                                max(
                                    0,
                                    (float)
                                    $attendance[
                                        "attendance_percentage"
                                    ]
                                )
                            );
                            ?>%;"
                        ></div>

                    </div>

                </div>

            <?php else: ?>

                <div class="marks-empty-state">

                    <i class="fa-solid fa-calendar-xmark"></i>

                    <h3>No Attendance Available</h3>

                    <p>
                        Attendance details will appear here after
                        the admin enters the record.
                    </p>

                </div>

            <?php endif; ?>

        </section>

        <!-- =========================================
             SUBJECT-WISE MARKS
        ========================================== -->

        <section class="student-marks-card">

            <div class="reports-section-heading">

                <div>

                    <p>Subject Performance</p>

                    <h2>Internal Marks</h2>

                </div>

                <i class="fa-solid fa-table-list"></i>

            </div>

            <?php if ($has_marks): ?>

                <div class="marks-table-wrapper">

                    <table class="student-marks-table">

                        <thead>

                            <tr>

                                <th>Subject</th>

                                <th>Obtained Marks</th>

                                <th>Maximum Marks</th>

                                <th>Percentage</th>

                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($marks as $mark): ?>

                                <?php
                                $subject_class =
                                    strtolower(
                                        $mark["subject_status"]
                                    );
                                ?>

                                <tr>

                                    <td>

                                        <div class="marks-subject-cell">

                                            <i class="fa-solid fa-book"></i>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $mark[
                                                        "subject_name"
                                                    ]
                                                );
                                                ?>

                                            </strong>

                                        </div>

                                    </td>

                                    <td>

                                        <?php
                                        echo number_format(
                                            $mark[
                                                "internal_marks"
                                            ],
                                            2
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo number_format(
                                            $mark[
                                                "maximum_marks"
                                            ],
                                            2
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo number_format(
                                            $mark[
                                                "subject_percentage"
                                            ],
                                            2
                                        );
                                        ?>%

                                    </td>

                                    <td>

                                        <span
                                            class="marks-status-badge
                                            <?php
                                            echo $subject_class;
                                            ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $mark[
                                                    "subject_status"
                                                ]
                                            );
                                            ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                        <tfoot>

                            <tr>

                                <th>Overall</th>

                                <th>

                                    <?php
                                    echo number_format(
                                        $total_obtained,
                                        2
                                    );
                                    ?>

                                </th>

                                <th>

                                    <?php
                                    echo number_format(
                                        $total_maximum,
                                        2
                                    );
                                    ?>

                                </th>

                                <th>

                                    <?php
                                    echo number_format(
                                        $overall_percentage,
                                        2
                                    );
                                    ?>%

                                </th>

                                <th>

                                    <span
                                        class="marks-status-badge
                                        <?php
                                        echo $result_class;
                                        ?>"
                                    >

                                        <?php
                                        echo $overall_result;
                                        ?>

                                    </span>

                                </th>

                            </tr>

                        </tfoot>

                    </table>

                </div>

            <?php else: ?>

                <div class="marks-empty-state">

                    <i class="fa-solid fa-file-circle-xmark"></i>

                    <h3>No Marks Available</h3>

                    <p>
                        Subject marks will appear here after
                        the admin enters them.
                    </p>

                </div>

            <?php endif; ?>

        </section>

        <!-- =========================================
             NOTE
        ========================================== -->

        <section class="report-note-card">

            <div class="report-note-icon">

                <i class="fa-solid fa-circle-info"></i>

            </div>

            <div>

                <h3>Academic Reports Module</h3>

                <p>
                    This page displays only the academic records
                    connected to your Student ID. Attendance and
                    marks are updated by the administrator.
                </p>

            </div>

        </section>

    </main>

</div>

</body>

</html>