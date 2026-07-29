<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: student_login.html");
    exit();
}

$student_id = $_SESSION["student_id"];

$student_sql = "SELECT full_name, student_id
                FROM students
                WHERE student_id = ?
                LIMIT 1";

$student_stmt = mysqli_prepare($conn, $student_sql);

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

/*
    room_allocations and rooms are connected
    using the room_number column.
*/

$allocation_sql = "
    SELECT
        ra.id,
        ra.room_number,
        ra.class_name,
        ra.teacher_name,
        ra.time_slot,
        ra.status AS allocation_status,
        r.room_status
    FROM room_allocations ra
    LEFT JOIN rooms r
        ON ra.room_number = r.room_number
    ORDER BY ra.room_number ASC
";

$allocation_result = mysqli_query($conn, $allocation_sql);

if (!$allocation_result) {
    die("Unable to load room allocations: " . mysqli_error($conn));
}

$total_allocations = mysqli_num_rows($allocation_result);

$available_rooms = 0;
$occupied_rooms = 0;
$maintenance_rooms = 0;

$room_count_sql = "
    SELECT room_status, COUNT(*) AS total
    FROM rooms
    GROUP BY room_status
";

$room_count_result = mysqli_query($conn, $room_count_sql);

if ($room_count_result) {
    while ($room_count = mysqli_fetch_assoc($room_count_result)) {

        if ($room_count["room_status"] === "Available") {
            $available_rooms = $room_count["total"];
        }

        if ($room_count["room_status"] === "Occupied") {
            $occupied_rooms = $room_count["total"];
        }

        if ($room_count["room_status"] === "Maintenance") {
            $maintenance_rooms = $room_count["total"];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Room Allocation</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="room-allocation-page">

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

            <a href="student_dashboard.php">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="profile.php">
                <i class="fa-solid fa-user"></i>
                My Profile
            </a>

            <a href="room_allocation.php" class="active">
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

    <main class="room-allocation-main">

        <header class="room-allocation-header">

            <div>
                <p class="dashboard-label">Student Portal</p>

                <h1>Room Allocation</h1>

                <p>
                    View classroom schedules, teachers,
                    time slots and room availability.
                </p>
            </div>

            <div class="room-student-profile">

                <div class="student-avatar">
                    <?php
                    echo strtoupper(
                        substr($student["full_name"], 0, 1)
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

        <section class="room-summary-grid">

            <div class="room-summary-card">

                <div class="room-summary-icon">
                    <i class="fa-solid fa-list-check"></i>
                </div>

                <div>
                    <p>Total Allocations</p>
                    <h3><?php echo $total_allocations; ?></h3>
                </div>

            </div>

            <div class="room-summary-card available-card">

                <div class="room-summary-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <p>Available Rooms</p>
                    <h3><?php echo $available_rooms; ?></h3>
                </div>

            </div>

            <div class="room-summary-card occupied-card">

                <div class="room-summary-icon">
                    <i class="fa-solid fa-door-closed"></i>
                </div>

                <div>
                    <p>Occupied Rooms</p>
                    <h3><?php echo $occupied_rooms; ?></h3>
                </div>

            </div>

            <div class="room-summary-card maintenance-card">

                <div class="room-summary-icon">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>

                <div>
                    <p>Under Maintenance</p>
                    <h3><?php echo $maintenance_rooms; ?></h3>
                </div>

            </div>

        </section>

        <section class="room-allocation-card">

            <div class="room-allocation-heading">

                <div>
                    <p>Classroom Information</p>
                    <h2>Current Room Schedule</h2>
                </div>

                <div class="room-status-legend">

                    <span class="legend-available">
                        <i class="fa-solid fa-circle"></i>
                        Available
                    </span>

                    <span class="legend-occupied">
                        <i class="fa-solid fa-circle"></i>
                        Occupied
                    </span>

                    <span class="legend-maintenance">
                        <i class="fa-solid fa-circle"></i>
                        Maintenance
                    </span>

                </div>

            </div>

            <div class="room-table-wrapper">

                <table class="room-allocation-table">

                    <thead>
                        <tr>
                            <th>Room Number</th>
                            <th>Class Name</th>
                            <th>Teacher</th>
                            <th>Time Slot</th>
                            <th>Allocation</th>
                            <th>Room Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($total_allocations > 0): ?>

                        <?php
                        mysqli_data_seek($allocation_result, 0);

                        while (
                            $allocation =
                            mysqli_fetch_assoc($allocation_result)
                        ):
                        ?>

                            <?php
                            $room_status =
                                $allocation["room_status"]
                                ?? "Unknown";

                            $room_status_class =
                                strtolower(
                                    str_replace(
                                        " ",
                                        "-",
                                        $room_status
                                    )
                                );

                            $allocation_status_class =
                                strtolower(
                                    $allocation["allocation_status"]
                                );
                            ?>

                            <tr>

                                <td>
                                    <div class="room-number-cell">

                                        <i class="fa-solid fa-door-open"></i>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $allocation["room_number"]
                                            );
                                            ?>
                                        </strong>

                                    </div>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $allocation["class_name"]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <div class="teacher-cell">

                                        <i class="fa-solid fa-chalkboard-user"></i>

                                        <span>
                                            <?php
                                            echo htmlspecialchars(
                                                $allocation["teacher_name"]
                                            );
                                            ?>
                                        </span>

                                    </div>
                                </td>

                                <td>
                                    <div class="time-slot-cell">

                                        <i class="fa-regular fa-clock"></i>

                                        <span>
                                            <?php
                                            echo htmlspecialchars(
                                                $allocation["time_slot"]
                                            );
                                            ?>
                                        </span>

                                    </div>
                                </td>

                                <td>
                                    <span class="allocation-badge
                                        <?php
                                        echo htmlspecialchars(
                                            $allocation_status_class
                                        );
                                        ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $allocation["allocation_status"]
                                        );
                                        ?>

                                    </span>
                                </td>

                                <td>
                                    <span class="room-status-badge
                                        <?php
                                        echo htmlspecialchars(
                                            $room_status_class
                                        );
                                        ?>">

                                        <i class="fa-solid fa-circle"></i>

                                        <?php
                                        echo htmlspecialchars(
                                            $room_status
                                        );
                                        ?>

                                    </span>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6"
                                class="room-empty-message">

                                <i class="fa-solid fa-building-circle-xmark"></i>

                                <h3>No Room Allocations Found</h3>

                                <p>
                                    Room allocation information will
                                    appear here once it is added.
                                </p>

                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>