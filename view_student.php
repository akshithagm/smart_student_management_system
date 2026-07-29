<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION["admin_logged_in"])) {
    header("Location: admin_login.html");
    exit();
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: manage_students.php");
    exit();
}

$id = (int) $_GET["id"];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, student_id, full_name, email, phone, department, year, created_at
     FROM students
     WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    header("Location: manage_students.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: #ffffff;
            background:
                radial-gradient(circle at top right, rgba(0, 212, 255, 0.12), transparent 30%),
                #08111f;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px;
        }

        .card {
            width: 700px;
            max-width: 100%;
            background: rgba(16, 33, 59, 0.94);
            border: 1px solid rgba(0, 212, 255, 0.16);
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .student-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #00d4ff;
            background: rgba(0, 212, 255, 0.1);
            font-size: 28px;
        }

        h2 {
            margin: 0 0 8px;
        }

        .subtitle {
            margin: 0;
            color: #91a3ba;
            font-size: 13px;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .item {
            padding: 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.035);
            border: 1px solid rgba(127, 211, 255, 0.12);
        }

        .item.full {
            grid-column: 1 / -1;
        }

        .label {
            display: block;
            margin-bottom: 7px;
            color: #7f91a8;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .value {
            font-size: 15px;
            color: #ffffff;
            word-break: break-word;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 13px;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 14px;
        }

        .back {
            color: #ffffff;
            background: #3b4656;
        }

        .edit {
            color: #00111a;
            background: linear-gradient(135deg, #00d4ff, #7beeff);
        }

        @media (max-width: 600px) {
            .details {
                grid-template-columns: 1fr;
            }

            .item.full {
                grid-column: auto;
            }

            .buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="card">

    <div class="header">
        <div class="student-icon">
            <i class="fa-solid fa-user-graduate"></i>
        </div>

        <h2><?php echo htmlspecialchars($student["full_name"]); ?></h2>

        <p class="subtitle">
            Student ID:
            <?php echo htmlspecialchars($student["student_id"] ?? "Not assigned"); ?>
        </p>
    </div>

    <div class="details">

        <div class="item">
            <span class="label">Database ID</span>
            <span class="value"><?php echo (int) $student["id"]; ?></span>
        </div>

        <div class="item">
            <span class="label">Student ID</span>
            <span class="value">
                <?php echo htmlspecialchars($student["student_id"] ?? "Not assigned"); ?>
            </span>
        </div>

        <div class="item full">
            <span class="label">Full Name</span>
            <span class="value">
                <?php echo htmlspecialchars($student["full_name"]); ?>
            </span>
        </div>

        <div class="item">
            <span class="label">Email</span>
            <span class="value">
                <?php echo htmlspecialchars($student["email"]); ?>
            </span>
        </div>

        <div class="item">
            <span class="label">Phone</span>
            <span class="value">
                <?php echo htmlspecialchars($student["phone"]); ?>
            </span>
        </div>

        <div class="item">
            <span class="label">Department</span>
            <span class="value">
                <?php echo htmlspecialchars($student["department"]); ?>
            </span>
        </div>

        <div class="item">
            <span class="label">Year</span>
            <span class="value">
                <?php echo htmlspecialchars($student["year"]); ?>
            </span>
        </div>

        <div class="item full">
            <span class="label">Created At</span>
            <span class="value">
                <?php echo htmlspecialchars($student["created_at"]); ?>
            </span>
        </div>

    </div>

    <div class="buttons">
        <a href="manage_students.php" class="btn back">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>

        <a href="edit_student.php?id=<?php echo (int) $student["id"]; ?>" class="btn edit">
            <i class="fa-solid fa-pen"></i> Edit Student
        </a>
    </div>

</div>

</body>
</html>
