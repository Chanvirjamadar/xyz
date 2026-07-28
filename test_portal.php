<?php
// Set up mock session
session_start();

// Connection
include("db.php");

echo "=== STUDY PORTAL TEST SUITE ===\n\n";

// Helper to assert condition
function assertTrue($condition, $message)
{
    if ($condition) {
        echo "✅ PASS: $message\n";
    } else {
        echo "❌ FAIL: $message\n";
        exit(1);
    }
}

// Clean previous test data
$conn->query("DELETE FROM query_replies");
$conn->query("DELETE FROM queries");
$conn->query("DELETE FROM announcement_reads");
$conn->query("DELETE FROM announcements");
echo "Cleared old test data.\n\n";

// 1. TEST ANNOUNCEMENT SYSTEM (Staff -> Students)
echo "Testing Feature 1: Announcements...\n";
$_SESSION['staff'] = "S001";
$_SESSION['staff_name'] = "Admin Staff";

// Call announcements.php to create an announcement
$_POST['action'] = 'create';
$_POST['title'] = 'Final Exam Schedule';
$_POST['message'] = 'The final exams will start from August 15th.';
unset($_SESSION['student']); // Ensure only staff can create

ob_start();
include("api/announcements.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Staff can create announcement");

// Verify in DB
$dbAnn = $conn->query("SELECT * FROM announcements WHERE title = 'Final Exam Schedule'")->fetch_assoc();
assertTrue($dbAnn !== null, "Announcement saved in database");
$annId = $dbAnn['id'];

// Simulating Student session
$_SESSION['student'] = 123;
unset($_SESSION['staff']);

// Check unread count API
$_GET = ['action' => 'unread_count'];
$_POST = [];
ob_start();
include("api/announcements.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Student can get unread count");
assertTrue($res['unread_count'] === 1, "Unread announcements count is 1");

// Fetch announcements list
$_GET = ['action' => 'fetch'];
ob_start();
include("api/announcements.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Student can fetch announcements");
assertTrue(count($res['announcements']) === 1, "Fetched exactly 1 announcement");
assertTrue($res['announcements'][0]['is_read'] === 0, "Announcement is unread");

// Mark as read
$_GET = [];
$_POST = ['action' => 'mark_read', 'id' => $annId];
ob_start();
include("api/announcements.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Student can mark announcement as read");

// Re-check unread count
$_GET = ['action' => 'unread_count'];
$_POST = [];
ob_start();
include("api/announcements.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);
assertTrue($res['unread_count'] === 0, "Unread count drops to 0 after marking read");

echo "Feature 1 Tests Successful!\n\n";

// 2. TEST QUERY SYSTEM (Student -> Staff)
echo "Testing Feature 2: Student Query System...\n";
$_SESSION['student'] = 123;
$_POST = [
    'action' => 'raise',
    'category' => 'syllabus',
    'subject' => 'Math Part 2 Syllabus',
    'message' => 'Is Unit 5 included in the mid-term?'
];
$_GET = [];

ob_start();
include("api/queries.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Student can raise a query");

// Verify in DB
$dbQuery = $conn->query("SELECT * FROM queries WHERE student_id = 123")->fetch_assoc();
assertTrue($dbQuery !== null, "Query saved in database");
assertTrue($dbQuery['status'] === 'pending', "Query status is initially pending");
$queryId = $dbQuery['id'];

echo "Feature 2 Tests Successful!\n\n";

// 3. TEST STAFF QUERY MANAGEMENT (Staff view queries)
echo "Testing Feature 3: Staff Query Management...\n";
$_SESSION['staff'] = "S001";
unset($_SESSION['student']);
$_GET = ['action' => 'fetch_staff', 'status' => 'pending'];
$_POST = [];

ob_start();
include("api/queries.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Staff can fetch student queries");
assertTrue(count($res['queries']) === 1, "Staff sees the pending query");
assertTrue($res['queries'][0]['student_name'] === 'Yash Vinodbhai Jadhav', "Correct student name returned");

echo "Feature 3 Tests Successful!\n\n";

// 4. TEST REPLY SYSTEM (Staff replies to query)
echo "Testing Feature 4: Staff Reply System...\n";
$_POST = [
    'action' => 'reply',
    'query_id' => $queryId,
    'reply' => 'Yes, Unit 5 is fully included in mid-terms.'
];
$_GET = [];

ob_start();
include("api/queries.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Staff can reply to query");

// Check query status updated to answered
$dbQuery = $conn->query("SELECT * FROM queries WHERE id = $queryId")->fetch_assoc();
assertTrue($dbQuery['status'] === 'answered', "Query status updated to answered");

// Student check replies
$_SESSION['student'] = 123;
unset($_SESSION['staff']);
$_GET = ['action' => 'fetch_student'];
$_POST = [];

ob_start();
include("api/queries.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Student can fetch queries with replies");
assertTrue(count($res['queries'][0]['replies']) === 1, "Exactly 1 reply found on the query");
assertTrue($res['queries'][0]['replies'][0]['reply'] === 'Yes, Unit 5 is fully included in mid-terms.', "Correct reply message received");

echo "Feature 4 Tests Successful!\n\n";

// 5. TEST REAL-TIME POLLING UPDATE API
echo "Testing Feature 5: Real-time Polling...\n";
$_GET = ['action' => 'poll_replies_count', 'last_seen_id' => 0];
ob_start();
include("api/queries.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['success'] === true, "Student can poll replies count");
assertTrue($res['unread_replies'] === 1, "Unread replies count is 1 when last_seen_id is 0");

$_GET = ['action' => 'poll_replies_count', 'last_seen_id' => 9999];
ob_start();
include("api/queries.php");
$resJSON = ob_get_clean();
$res = json_decode($resJSON, true);

assertTrue($res['unread_replies'] === 0, "Unread replies count is 0 when last_seen_id is high");

echo "Feature 5 Tests Successful!\n\n";

// Clean up
$conn->query("DELETE FROM query_replies");
$conn->query("DELETE FROM queries");
$conn->query("DELETE FROM announcement_reads");
$conn->query("DELETE FROM announcements");
echo "Cleared test data after execution.\n\n";

echo "ALL TESTS COMPLETED SUCCESSFULLY!\n";
