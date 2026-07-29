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

/* ==================================================
   HELPER: CHECK WHETHER TABLE EXISTS
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

/* ==================================================
   GET STUDENT DATABASE ID
================================================== */

$studentDatabaseId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$studentDatabaseId) {
    echo "
        <script>
            alert('Invalid student record.');
            window.location.href = 'manage_students.php';
        </script>
    ";
    exit();
}

/* ==================================================
   FETCH STUDENT DETAILS
================================================== */

$studentSql = "
    SELECT
        id,
        student_id,
        full_name
    FROM students
    WHERE id = ?
    LIMIT 1
";

$studentStmt = mysqli_prepare($conn, $studentSql);

if (!$studentStmt) {
    echo "
        <script>
            alert('Unable to verify the student.');
            window.location.href = 'manage_students.php';
        </script>
    ";
    exit();
}

mysqli_stmt_bind_param(
    $studentStmt,
    "i",
    $studentDatabaseId
);

mysqli_stmt_execute($studentStmt);

$studentResult = mysqli_stmt_get_result($studentStmt);

if (mysqli_num_rows($studentResult) !== 1) {
    mysqli_stmt_close($studentStmt);

    echo "
        <script>
            alert('Student record not found.');
            window.location.href = 'manage_students.php';
        </script>
    ";

    exit();
}

$student = mysqli_fetch_assoc($studentResult);

mysqli_stmt_close($studentStmt);

$studentId = $student["student_id"];
$studentName = $student["full_name"];

/* ==================================================
   DELETE STUDENT AND RELATED RECORDS
================================================== */

mysqli_begin_transaction($conn);

try {

    /* Delete attendance records */

    if (tableExists($conn, "attendance")) {

        $attendanceSql = "
            DELETE FROM attendance
            WHERE student_id = ?
        ";

        $attendanceStmt = mysqli_prepare(
            $conn,
            $attendanceSql
        );

        if (!$attendanceStmt) {
            throw new Exception(
                "Unable to prepare attendance deletion."
            );
        }

        mysqli_stmt_bind_param(
            $attendanceStmt,
            "s",
            $studentId
        );

        if (!mysqli_stmt_execute($attendanceStmt)) {
            throw new Exception(
                "Unable to delete attendance records."
            );
        }

        mysqli_stmt_close($attendanceStmt);
    }

    /* Delete marks records */

    if (tableExists($conn, "marks")) {

        $marksSql = "
            DELETE FROM marks
            WHERE student_id = ?
        ";

        $marksStmt = mysqli_prepare(
            $conn,
            $marksSql
        );

        if (!$marksStmt) {
            throw new Exception(
                "Unable to prepare marks deletion."
            );
        }

        mysqli_stmt_bind_param(
            $marksStmt,
            "s",
            $studentId
        );

        if (!mysqli_stmt_execute($marksStmt)) {
            throw new Exception(
                "Unable to delete marks records."
            );
        }

        mysqli_stmt_close($marksStmt);
    }

    /* Delete room-allocation records when table exists */

    if (tableExists($conn, "room_allocations")) {

        $allocationSql = "
            DELETE FROM room_allocations
            WHERE student_id = ?
        ";

        $allocationStmt = mysqli_prepare(
            $conn,
            $allocationSql
        );

        if (!$allocationStmt) {
            throw new Exception(
                "Unable to prepare room-allocation deletion."
            );
        }

        mysqli_stmt_bind_param(
            $allocationStmt,
            "s",
            $studentId
        );

        if (!mysqli_stmt_execute($allocationStmt)) {
            throw new Exception(
                "Unable to delete room-allocation records."
            );
        }

        mysqli_stmt_close($allocationStmt);
    }

    /* Delete student account */

    $deleteStudentSql = "
        DELETE FROM students
        WHERE id = ?
        LIMIT 1
    ";

    $deleteStudentStmt = mysqli_prepare(
        $conn,
        $deleteStudentSql
    );

    if (!$deleteStudentStmt) {
        throw new Exception(
            "Unable to prepare student deletion."
        );
    }

    mysqli_stmt_bind_param(
        $deleteStudentStmt,
        "i",
        $studentDatabaseId
    );

    if (!mysqli_stmt_execute($deleteStudentStmt)) {
        throw new Exception(
            "Unable to delete the student account."
        );
    }

    if (mysqli_stmt_affected_rows($deleteStudentStmt) !== 1) {
        throw new Exception(
            "Student record was not deleted."
        );
    }

    mysqli_stmt_close($deleteStudentStmt);

    /* Save all deletion operations */

    mysqli_commit($conn);

    $safeStudentName = json_encode($studentName);

    echo "
        <script>
            const studentName = $safeStudentName;

            alert(
                studentName +
                ' and all related records were deleted successfully.'
            );

            window.location.href = 'manage_students.php';
        </script>
    ";

    exit();

} catch (Exception $error) {

    /* Undo deletion if any operation fails */

    mysqli_rollback($conn);

    $safeErrorMessage = json_encode(
        "Student could not be deleted. " .
        $error->getMessage()
    );

    echo "
        <script>
            alert($safeErrorMessage);
            window.location.href = 'manage_students.php';
        </script>
    ";

    exit();
}
?>