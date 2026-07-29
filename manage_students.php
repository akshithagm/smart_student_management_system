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
   SEARCH
================================================== */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $searchTerm = "%" . $search . "%";

    $sql = "
        SELECT *
        FROM students
        WHERE student_id LIKE ?
           OR full_name LIKE ?
           OR email LIKE ?
           OR department LIKE ?
        ORDER BY id DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        die("Query preparation failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

    mysqli_stmt_execute($stmt);
    $studentsResult = mysqli_stmt_get_result($stmt);

} else {

    $studentsResult = mysqli_query(
        $conn,
        "SELECT * FROM students ORDER BY id DESC"
    );

    if (!$studentsResult) {
        die("Unable to load students: " . mysqli_error($conn));
    }
}

/* ==================================================
   TOTAL STUDENTS
================================================== */

$totalResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM students"
);

$totalStudents = 0;

if ($totalResult) {
    $totalRow = mysqli_fetch_assoc($totalResult);
    $totalStudents = (int) $totalRow["total"];
}

$displayedStudents = mysqli_num_rows($studentsResult);

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Students | SSMS</title>

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

            <a href="admin_dashboard.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="manage_students.php" class="active">
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

        <header class="manage-students-header">

            <div>

                <p class="admin-header-label">
                    Student Management
                </p>

                <h1>Manage Students</h1>

                <span>
                    View, search, add, edit and remove student records.
                </span>

            </div>

            <a href="add_student.php" class="manage-add-student-btn">

                <i class="fa-solid fa-user-plus"></i>

                Add Student

            </a>

        </header>

        <section class="manage-student-statistics">

            <article class="manage-student-stat-card">

                <div class="manage-student-stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <p>Total Registered Students</p>
                    <h2><?php echo $totalStudents; ?></h2>
                </div>

            </article>

            <article class="manage-student-stat-card">

                <div class="manage-student-stat-icon search-result-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>

                <div>
                    <p>Displayed Records</p>
                    <h2><?php echo $displayedStudents; ?></h2>
                </div>

            </article>

        </section>

        <section class="manage-students-panel">

            <div class="manage-students-panel-header">

                <div>
                    <p>Student Records</p>
                    <h2>All Registered Students</h2>
                </div>

                <form
                    action="manage_students.php"
                    method="GET"
                    class="manage-students-search"
                >

                    <div class="manage-search-input">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search ID, name, email or department"
                        >

                    </div>

                    <button type="submit">

                        <i class="fa-solid fa-search"></i>

                        Search

                    </button>

                    <?php if ($search !== ""): ?>

                        <a href="manage_students.php">

                            <i class="fa-solid fa-xmark"></i>

                            Clear

                        </a>

                    <?php endif; ?>

                </form>

            </div>

            <?php if ($search !== ""): ?>

                <div class="manage-search-information">

                    <i class="fa-solid fa-circle-info"></i>

                    Showing results for:

                    <strong>
                        "<?php echo htmlspecialchars($search); ?>"
                    </strong>

                </div>

            <?php endif; ?>

            <?php if ($displayedStudents > 0): ?>

                <div class="manage-students-table-wrapper">

                    <table class="manage-students-table">

                        <thead>

                            <tr>
                                <th>No.</th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Academic Year</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php $serialNumber = 1; ?>

                        <?php while ($student = mysqli_fetch_assoc($studentsResult)): ?>

                            <?php
                            $studentDatabaseId =
                                $student["id"] ?? "";

                            $studentId =
                                $student["student_id"] ?? "-";

                            $studentName =
                                $student["full_name"] ??
                                $student["student_name"] ??
                                $student["name"] ??
                                "Not available";

                            $studentEmail =
                                $student["email"] ?? "Not available";

                            $studentDepartment =
                                $student["department"] ??
                                $student["course"] ??
                                "Not available";

                            $academicYear =
                                $student["academic_year"] ??
                                $student["year"] ??
                                "Not available";

                            $studentStatus =
                                $student["status"] ??
                                $student["account_status"] ??
                                "Active";

                            $statusClass =
                                strtolower($studentStatus) === "inactive"
                                ? "student-status-inactive"
                                : "student-status-active";

                            $firstLetter =
                                strtoupper(
                                    substr($studentName, 0, 1)
                                );
                            ?>

                            <tr>

                                <td>
                                    <?php echo $serialNumber++; ?>
                                </td>

                                <td>

                                    <div class="manage-student-identity">

                                        <div class="manage-student-avatar">

                                            <?php
                                            echo htmlspecialchars(
                                                $firstLetter
                                            );
                                            ?>

                                        </div>

                                        <div>

                                            <h3>
                                                <?php
                                                echo htmlspecialchars(
                                                    $studentName
                                                );
                                                ?>
                                            </h3>

                                            <p>
                                                Registered Student
                                            </p>

                                        </div>

                                    </div>

                                </td>

                                <td>

                                    <span class="manage-student-id">

                                        <?php
                                        echo htmlspecialchars(
                                            $studentId
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $studentEmail
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $studentDepartment
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $academicYear
                                    );
                                    ?>

                                </td>

                                <td>

                                    <span
                                        class="<?php echo $statusClass; ?>"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $studentStatus
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <div class="manage-student-actions">

                                        <a
                                            href="view_student.php?id=<?php
                                            echo urlencode(
                                                $studentDatabaseId
                                            );
                                            ?>"
                                            class="student-view-btn"
                                            title="View Student"
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                        </a>

                                        <a
                                            href="edit_student.php?id=<?php
                                            echo urlencode(
                                                $studentDatabaseId
                                            );
                                            ?>"
                                            class="student-edit-btn"
                                            title="Edit Student"
                                        >

                                            <i
                                                class="fa-solid fa-pen-to-square"
                                            ></i>

                                        </a>

                                        <a
                                            href="delete_student.php?id=<?php
                                            echo urlencode(
                                                $studentDatabaseId
                                            );
                                            ?>"
                                            class="student-delete-btn"
                                            title="Delete Student"
                                            onclick="
                                                return confirm(
                                                    'Are you sure you want to delete this student? This action cannot be undone.'
                                                );
                                            "
                                        >

                                            <i class="fa-solid fa-trash"></i>

                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="manage-students-empty">

                    <div class="manage-empty-icon">

                        <i class="fa-solid fa-user-graduate"></i>

                    </div>

                    <h2>No students found</h2>

                    <?php if ($search !== ""): ?>

                        <p>
                            No student records matched your search.
                        </p>

                        <a href="manage_students.php">

                            <i class="fa-solid fa-arrow-left"></i>

                            View All Students

                        </a>

                    <?php else: ?>

                        <p>
                            Student records will appear here after
                            registration.
                        </p>

                        <a href="add_student.php">

                            <i class="fa-solid fa-user-plus"></i>

                            Add First Student

                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>