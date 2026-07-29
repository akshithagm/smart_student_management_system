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

$errorMessage = "";

$markId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($markId <= 0) {
    header("Location: manage_marks.php");
    exit();
}

/* ==================================================
   FETCH EXISTING MARKS RECORD
================================================== */

$fetchStatement = mysqli_prepare(
    $conn,
    "
    SELECT
        m.id,
        m.student_id,
        m.subject_name,
        m.internal_marks,
        m.maximum_marks,
        s.full_name,
        s.department,
        s.year
    FROM marks m
    INNER JOIN students s
        ON s.student_id = m.student_id
    WHERE m.id = ?
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $fetchStatement,
    "i",
    $markId
);

mysqli_stmt_execute($fetchStatement);

$fetchResult = mysqli_stmt_get_result($fetchStatement);

if (mysqli_num_rows($fetchResult) === 0) {
    mysqli_stmt_close($fetchStatement);
    header("Location: manage_marks.php");
    exit();
}

$mark = mysqli_fetch_assoc($fetchResult);

mysqli_stmt_close($fetchStatement);

$studentId     = $mark["student_id"];
$studentName   = $mark["full_name"];
$department    = $mark["department"];
$year          = $mark["year"];
$subjectName   = $mark["subject_name"];
$internalMarks = $mark["internal_marks"];
$maximumMarks  = $mark["maximum_marks"];

/* ==================================================
   UPDATE MARKS
================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subjectName   = trim($_POST["subject_name"] ?? "");
    $internalMarks = trim($_POST["internal_marks"] ?? "");
    $maximumMarks  = trim($_POST["maximum_marks"] ?? "");

    if (
        $subjectName === "" ||
        $internalMarks === "" ||
        $maximumMarks === ""
    ) {
        $errorMessage = "Please fill in all fields.";
    } elseif (
        !is_numeric($internalMarks) ||
        !is_numeric($maximumMarks)
    ) {
        $errorMessage = "Marks must be valid numbers.";
    } elseif ((float) $maximumMarks <= 0) {
        $errorMessage = "Maximum marks must be greater than zero.";
    } elseif ((float) $internalMarks < 0) {
        $errorMessage = "Internal marks cannot be negative.";
    } elseif ((float) $internalMarks > (float) $maximumMarks) {
        $errorMessage =
            "Internal marks cannot be greater than maximum marks.";
    } else {

        /* Prevent duplicate subject for the same student */

        $duplicateStatement = mysqli_prepare(
            $conn,
            "
            SELECT id
            FROM marks
            WHERE student_id = ?
              AND LOWER(TRIM(subject_name)) =
                  LOWER(TRIM(?))
              AND id != ?
            LIMIT 1
            "
        );

        mysqli_stmt_bind_param(
            $duplicateStatement,
            "ssi",
            $studentId,
            $subjectName,
            $markId
        );

        mysqli_stmt_execute($duplicateStatement);

        $duplicateResult =
            mysqli_stmt_get_result($duplicateStatement);

        if (mysqli_num_rows($duplicateResult) > 0) {
            $errorMessage =
                "This student already has marks for that subject.";
        } else {

            $updateStatement = mysqli_prepare(
                $conn,
                "
                UPDATE marks
                SET
                    subject_name = ?,
                    internal_marks = ?,
                    maximum_marks = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                "
            );

            $internalMarksNumber = (float) $internalMarks;
            $maximumMarksNumber  = (float) $maximumMarks;

            mysqli_stmt_bind_param(
                $updateStatement,
                "sddi",
                $subjectName,
                $internalMarksNumber,
                $maximumMarksNumber,
                $markId
            );

            if (mysqli_stmt_execute($updateStatement)) {
                mysqli_stmt_close($updateStatement);
                mysqli_stmt_close($duplicateStatement);

                header(
                    "Location: manage_marks.php?status=updated"
                );
                exit();
            }

            $errorMessage =
                "Unable to update marks. Please try again.";

            mysqli_stmt_close($updateStatement);
        }

        mysqli_stmt_close($duplicateStatement);
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

    <title>Edit Marks | SSMS</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --background: #050b18;
            --card: rgba(13, 27, 48, 0.8);
            --border: rgba(127, 211, 255, 0.13);
            --primary: #00d4ff;
            --text: #f5f9ff;
            --muted: #8192aa;
            --green: #30d98b;
            --red: #ff647c;
            --yellow: #ffc857;
            --purple: #a78bfa;
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
                    rgba(167, 139, 250, 0.08),
                    transparent 30%
                ),
                var(--background);
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        input,
        button {
            font: inherit;
        }

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
            margin-bottom: 25px;
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

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 11px 16px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.07);
            border: 1px solid rgba(0, 212, 255, 0.14);
            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;
            transition: 0.25s;
        }

        .back-button:hover {
            background: rgba(0, 212, 255, 0.13);
            transform: translateY(-1px);
        }

        .content-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.6fr);
            gap: 20px;
            align-items: start;
        }

        .form-card,
        .student-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            backdrop-filter: blur(18px);
        }

        .card-header {
            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            margin-bottom: 6px;
            font-size: 16px;
        }

        .card-header p {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
        }

        .marks-form {
            padding: 23px;
        }

        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin-bottom: 20px;
            padding: 13px 14px;

            color: #ff9bac;
            background: rgba(255, 100, 124, 0.08);
            border: 1px solid rgba(255, 100, 124, 0.16);
            border-radius: 11px;

            font-size: 11px;
            line-height: 1.5;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #c7d3e2;
            font-size: 10px;
            font-weight: 700;
        }

        .required {
            color: var(--red);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            top: 50%;
            left: 14px;

            color: var(--muted);
            font-size: 12px;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .input-wrapper input {
            width: 100%;
            padding: 13px 14px 13px 41px;

            color: var(--text);
            background: rgba(255, 255, 255, 0.026);
            border: 1px solid var(--border);
            border-radius: 11px;
            outline: none;

            font-size: 11px;
            transition: 0.25s;
        }

        .input-wrapper input:focus {
            border-color: rgba(0, 212, 255, 0.45);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.06);
        }

        .field-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.5;
        }

        .live-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            grid-column: 1 / -1;

            padding: 14px 16px;

            background: rgba(0, 212, 255, 0.035);
            border: 1px solid rgba(0, 212, 255, 0.11);
            border-radius: 12px;
        }

        .preview-label {
            color: var(--muted);
            font-size: 10px;
        }

        .preview-result {
            font-size: 14px;
            font-weight: 700;
        }

        .preview-result span {
            color: var(--primary);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;

            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .cancel-button,
        .save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 12px 18px;
            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s;
        }

        .cancel-button {
            color: var(--muted);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
        }

        .save-button {
            color: #03111c;
            background: linear-gradient(
                135deg,
                var(--primary),
                #78efff
            );
            border: none;
            box-shadow: 0 9px 25px rgba(0, 212, 255, 0.16);
        }

        .cancel-button:hover,
        .save-button:hover {
            transform: translateY(-1px);
        }

        .student-body {
            padding: 22px;
        }

        .student-avatar-large {
            display: grid;
            place-items: center;

            width: 58px;
            height: 58px;
            margin-bottom: 16px;

            color: var(--primary);
            background: rgba(0, 212, 255, 0.08);
            border: 1px solid rgba(0, 212, 255, 0.13);
            border-radius: 16px;

            font-size: 20px;
            font-weight: 700;
        }

        .student-body h3 {
            margin-bottom: 5px;
            font-size: 17px;
        }

        .student-id {
            margin-bottom: 18px;
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
        }

        .detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            padding: 13px 0;
            border-bottom: 1px solid rgba(127, 211, 255, 0.08);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row span {
            color: var(--muted);
            font-size: 10px;
        }

        .detail-row strong {
            font-size: 10px;
            text-align: right;
        }

        @media (max-width: 1000px) {

            .content-layout {
                grid-template-columns: 1fr;
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

            .page-header {
                align-items: stretch;
                flex-direction: column;
            }

            .back-button {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width,
            .live-preview {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .cancel-button,
            .save-button {
                width: 100%;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

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

            <a href="manage_attendance.php">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Attendance</span>
            </a>

            <a
                href="manage_marks.php"
                class="active"
            >
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

    <main class="admin-main">

        <header class="page-header">

            <div>

                <p class="header-label">
                    Internal Marks Management
                </p>

                <h1>Edit Student Marks</h1>

                <span>
                    Update the selected student's subject marks.
                </span>

            </div>

            <a
                href="manage_marks.php"
                class="back-button"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Manage Marks
            </a>

        </header>

        <section class="content-layout">

            <article class="form-card">

                <div class="card-header">

                    <h2>Update Marks Information</h2>

                    <p>
                        Change the subject or marks and save your updates.
                    </p>

                </div>

                <form
                    action="edit_marks.php?id=<?php echo $markId; ?>"
                    method="POST"
                    class="marks-form"
                    id="marksForm"
                >

                    <?php if ($errorMessage !== ""): ?>

                        <div class="message">

                            <i class="fa-solid fa-circle-exclamation"></i>

                            <span>
                                <?php
                                echo htmlspecialchars($errorMessage);
                                ?>
                            </span>

                        </div>

                    <?php endif; ?>

                    <div class="form-grid">

                        <div class="form-group full-width">

                            <label for="subject_name">
                                Subject Name
                                <span class="required">*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-book-open"></i>

                                <input
                                    type="text"
                                    name="subject_name"
                                    id="subject_name"
                                    value="<?php
                                    echo htmlspecialchars($subjectName);
                                    ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <small class="field-help">
                                A duplicate subject is not allowed for the same student.
                            </small>

                        </div>

                        <div class="form-group">

                            <label for="internal_marks">
                                Internal Marks
                                <span class="required">*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-pen-to-square"></i>

                                <input
                                    type="number"
                                    name="internal_marks"
                                    id="internal_marks"
                                    value="<?php
                                    echo htmlspecialchars($internalMarks);
                                    ?>"
                                    min="0"
                                    step="0.01"
                                    required
                                >

                            </div>

                        </div>

                        <div class="form-group">

                            <label for="maximum_marks">
                                Maximum Marks
                                <span class="required">*</span>
                            </label>

                            <div class="input-wrapper">

                                <i class="fa-solid fa-bullseye"></i>

                                <input
                                    type="number"
                                    name="maximum_marks"
                                    id="maximum_marks"
                                    value="<?php
                                    echo htmlspecialchars($maximumMarks);
                                    ?>"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >

                            </div>

                        </div>

                        <div class="live-preview">

                            <span class="preview-label">
                                Updated percentage
                            </span>

                            <strong class="preview-result">
                                <span id="percentagePreview">0.00</span>%
                            </strong>

                        </div>

                    </div>

                    <div class="form-actions">

                        <a
                            href="manage_marks.php"
                            class="cancel-button"
                        >
                            <i class="fa-solid fa-xmark"></i>
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="save-button"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Update Marks
                        </button>

                    </div>

                </form>

            </article>

            <aside class="student-card">

                <div class="card-header">

                    <h2>Student Information</h2>

                    <p>
                        The marks record belongs to this student.
                    </p>

                </div>

                <div class="student-body">

                    <?php

                    $nameParts = preg_split(
                        "/\s+/",
                        trim($studentName)
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

                    ?>

                    <div class="student-avatar-large">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>

                    <h3>
                        <?php echo htmlspecialchars($studentName); ?>
                    </h3>

                    <p class="student-id">
                        <?php echo htmlspecialchars($studentId); ?>
                    </p>

                    <div class="detail-row">

                        <span>Department</span>

                        <strong>
                            <?php echo htmlspecialchars($department); ?>
                        </strong>

                    </div>

                    <div class="detail-row">

                        <span>Academic Year</span>

                        <strong>
                            <?php echo htmlspecialchars($year); ?>
                        </strong>

                    </div>

                    <div class="detail-row">

                        <span>Marks Record ID</span>

                        <strong>
                            #<?php echo $markId; ?>
                        </strong>

                    </div>

                </div>

            </aside>

        </section>

    </main>

    <script>

        const internalMarksInput =
            document.getElementById("internal_marks");

        const maximumMarksInput =
            document.getElementById("maximum_marks");

        const percentagePreview =
            document.getElementById("percentagePreview");

        function updatePercentagePreview() {

            const internalMarks =
                parseFloat(internalMarksInput.value);

            const maximumMarks =
                parseFloat(maximumMarksInput.value);

            if (
                Number.isFinite(internalMarks) &&
                Number.isFinite(maximumMarks) &&
                maximumMarks > 0
            ) {
                const percentage =
                    (internalMarks / maximumMarks) * 100;

                percentagePreview.textContent =
                    percentage.toFixed(2);
            } else {
                percentagePreview.textContent = "0.00";
            }
        }

        internalMarksInput.addEventListener(
            "input",
            updatePercentagePreview
        );

        maximumMarksInput.addEventListener(
            "input",
            updatePercentagePreview
        );

        updatePercentagePreview();

        document
            .getElementById("marksForm")
            .addEventListener("submit", function (event) {

                const internalMarks =
                    parseFloat(internalMarksInput.value);

                const maximumMarks =
                    parseFloat(maximumMarksInput.value);

                if (
                    Number.isFinite(internalMarks) &&
                    Number.isFinite(maximumMarks) &&
                    internalMarks > maximumMarks
                ) {
                    event.preventDefault();

                    alert(
                        "Internal marks cannot be greater than maximum marks."
                    );
                }
            });

    </script>

</body>
</html>

<?php

mysqli_close($conn);

?>