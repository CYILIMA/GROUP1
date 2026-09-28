<?php
require "db.php";
require "admin_auth.php";
admin_require_login();

$feature_slots = [
    "quality_courses" => "Quality Courses",
    "learn_online" => "Learn Online",
    "build_future" => "Build Your Future"
];

function admin_sanitize_course_notes($html)
{
    $html = trim((string) $html);
    if ($html === "") return "";
    if (strip_tags($html) === $html) return nl2br(admin_escape($html));

    $allowed_tags = ["p", "br", "h2", "h3", "blockquote", "a", "img", "ul", "ol", "li", "strong", "em", "b", "i"];
    $document = new DOMDocument("1.0", "UTF-8");
    libxml_use_internal_errors(true);
    $document->loadHTML("<div>" . $html . "</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $clean_node = function ($node) use (&$clean_node, $allowed_tags) {
        if ($node->nodeType === XML_TEXT_NODE) return admin_escape($node->nodeValue);
        if ($node->nodeType !== XML_ELEMENT_NODE) return "";
        $tag = strtolower($node->nodeName);
        if (in_array($tag, ["script", "style", "iframe", "object"], true)) return "";
        $children = "";
        foreach ($node->childNodes as $child) $children .= $clean_node($child);
        if (!in_array($tag, $allowed_tags, true)) return $children;
        if ($tag === "br") return "<br>";
        if ($tag === "img") {
            $src = trim($node->getAttribute("src"));
            return preg_match("#^uploads/#i", $src) ? '<img src="' . admin_escape($src) . '" alt="' . admin_escape($node->getAttribute("alt")) . '">' : "";
        }
        if ($tag === "a") {
            $href = trim($node->getAttribute("href"));
            return preg_match("#^(https?://|mailto:|/)#i", $href) ? '<a href="' . admin_escape($href) . '" target="_blank" rel="noopener noreferrer">' . $children . "</a>" : $children;
        }
        return "<" . $tag . ">" . $children . "</" . $tag . ">";
    };
    $result = "";
    foreach ($document->documentElement->childNodes as $child) $result .= $clean_node($child);
    return $result;
}

$view = $_GET["view"] ?? "overview";
if (!in_array($view, ["overview", "courses", "students", "messages", "team", "account", "homepage"], true)) {
    $view = "overview";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    admin_verify_csrf();
    $action = $_POST["action"] ?? "";

    if ($action === "upload_note_image") {
        header("Content-Type: application/json");
        $upload = $_FILES["note_image"] ?? null;
        $image_types = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
        if (!$upload || $upload["error"] !== UPLOAD_ERR_OK || $upload["size"] > 8 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(["error" => "Choose an image smaller than 8 MB."]);
            exit();
        }
        $extension = $image_types[(new finfo(FILEINFO_MIME_TYPE))->file($upload["tmp_name"])] ?? null;
        if (!$extension) {
            http_response_code(422);
            echo json_encode(["error" => "Only JPG, PNG, and WebP images are allowed."]);
            exit();
        }
        $directory = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "course-notes";
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            http_response_code(500);
            echo json_encode(["error" => "The image upload folder could not be created."]);
            exit();
        }
        $filename = bin2hex(random_bytes(16)) . "." . $extension;
        if (!move_uploaded_file($upload["tmp_name"], $directory . DIRECTORY_SEPARATOR . $filename)) {
            http_response_code(500);
            echo json_encode(["error" => "The image could not be uploaded."]);
            exit();
        }
        echo json_encode(["path" => "uploads/course-notes/" . $filename]);
        exit();
    }

    if ($action === "upload_feature_image") {
        $feature_key = $_POST["feature_key"] ?? "";
        $upload = $_FILES["feature_image"] ?? null;
        $error = "";

        if (!isset($feature_slots[$feature_key])) {
            $error = "Choose a valid homepage feature.";
        } elseif (!$upload || $upload["error"] !== UPLOAD_ERR_OK || $upload["size"] > 5 * 1024 * 1024) {
            $error = "Choose an image smaller than 5 MB.";
        } else {
            $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($upload["tmp_name"]);
            $image_types = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

            if (!isset($image_types[$mime_type])) {
                $error = "Upload a JPG, PNG, or WebP image.";
            } else {
                $upload_directory = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "homepage";
                if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
                    $error = "The homepage image folder could not be created.";
                } else {
                    $filename = bin2hex(random_bytes(16)) . "." . $image_types[$mime_type];
                    $image_path = "uploads/homepage/" . $filename;
                    if (!move_uploaded_file($upload["tmp_name"], $upload_directory . DIRECTORY_SEPARATOR . $filename)) {
                        $error = "The image could not be saved.";
                    } else {
                        $stmt = $conn->prepare("SELECT image_path FROM homepage_feature_images WHERE feature_key = ?");
                        $stmt->bind_param("s", $feature_key);
                        $stmt->execute();
                        $previous_image = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        $stmt = $conn->prepare("INSERT INTO homepage_feature_images (feature_key, image_path) VALUES (?, ?) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)");
                        $stmt->bind_param("ss", $feature_key, $image_path);
                        if ($stmt->execute()) {
                            $stmt->close();
                            if ($previous_image && strpos($previous_image["image_path"], "uploads/homepage/") === 0) {
                                $previous_file = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $previous_image["image_path"]);
                                if (is_file($previous_file)) {
                                    unlink($previous_file);
                                }
                            }
                            header("Location: admin_dashboard.php?view=homepage&notice=feature_image_updated");
                            exit();
                        }
                        $stmt->close();
                        unlink($upload_directory . DIRECTORY_SEPARATOR . $filename);
                        $error = "The homepage image could not be attached to this feature.";
                    }
                }
            }
        }

        $_SESSION["admin_error"] = $error;
        header("Location: admin_dashboard.php?view=homepage");
        exit();
    }

    if ($action === "add_hero_slide") {
        $upload = $_FILES["hero_image"] ?? null;
        $error = "";

        if (!$upload || $upload["error"] !== UPLOAD_ERR_OK || $upload["size"] > 5 * 1024 * 1024) {
            $error = "Choose an image smaller than 5 MB.";
        } else {
            $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($upload["tmp_name"]);
            $image_types = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

            if (!isset($image_types[$mime_type])) {
                $error = "Upload a JPG, PNG, or WebP image.";
            } else {
                $upload_directory = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "hero";
                if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
                    $error = "The hero image folder could not be created.";
                } else {
                    $filename = bin2hex(random_bytes(16)) . "." . $image_types[$mime_type];
                    $image_path = "uploads/hero/" . $filename;
                    if (!move_uploaded_file($upload["tmp_name"], $upload_directory . DIRECTORY_SEPARATOR . $filename)) {
                        $error = "The image could not be saved.";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO hero_slides (image_path) VALUES (?)");
                        $stmt->bind_param("s", $image_path);
                        if ($stmt->execute()) {
                            $stmt->close();
                            header("Location: admin_dashboard.php?view=homepage&notice=hero_slide_added");
                            exit();
                        }
                        $stmt->close();
                        unlink($upload_directory . DIRECTORY_SEPARATOR . $filename);
                        $error = "The slide could not be saved.";
                    }
                }
            }
        }

        $_SESSION["admin_error"] = $error;
        header("Location: admin_dashboard.php?view=homepage");
        exit();
    }

    if ($action === "delete_hero_slide") {
        $slide_id = (int) ($_POST["slide_id"] ?? 0);
        $stmt = $conn->prepare("SELECT image_path FROM hero_slides WHERE id = ?");
        $stmt->bind_param("i", $slide_id);
        $stmt->execute();
        $slide = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM hero_slides WHERE id = ?");
        $stmt->bind_param("i", $slide_id);
        $stmt->execute();
        $stmt->close();

        if ($slide && strpos($slide["image_path"], "uploads/hero/") === 0) {
            $image_file = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $slide["image_path"]);
            if (is_file($image_file)) {
                unlink($image_file);
            }
        }

        header("Location: admin_dashboard.php?view=homepage&notice=hero_slide_deleted");
        exit();
    }

    if ($action === "update_account") {
        $name = trim($_POST["name"] ?? "");
        $current_password = $_POST["current_password"] ?? "";
        $new_password = $_POST["new_password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";
        $error = "";

        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->bind_param("i", $_SESSION["admin_id"]);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($name === "") {
            $error = "Enter your name.";
        } elseif (!$admin || !password_verify($current_password, $admin["password"])) {
            $error = "Your current password is incorrect.";
        } elseif (($new_password !== "" || $confirm_password !== "") && $new_password !== $confirm_password) {
            $error = "The new passwords do not match.";
        } elseif ($new_password !== "" && strlen($new_password) < 10) {
            $error = "The new password must contain at least 10 characters.";
        }

        if ($error === "") {
            $password_hash = $new_password === "" ? $admin["password"] : password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET name = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $password_hash, $_SESSION["admin_id"]);
            $stmt->execute();
            $stmt->close();
            $_SESSION["admin_name"] = $name;
            session_regenerate_id(true);
            header("Location: admin_dashboard.php?view=account&notice=account_updated");
            exit();
        }

        $_SESSION["admin_error"] = $error;
        header("Location: admin_dashboard.php?view=account");
        exit();
    }

    if ($action === "create_admin") {
        $name = trim($_POST["name"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = $_POST["password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";
        $error = "";

        if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Enter a name and valid email address.";
        } elseif (strlen($password) < 10) {
            $error = "The password must contain at least 10 characters.";
        } elseif ($password !== $confirm_password) {
            $error = "The passwords do not match.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            $email_exists = $stmt->num_rows > 0;
            $stmt->close();

            if ($email_exists) {
                $error = "An admin with that email address already exists.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $password_hash);
                $stmt->execute();
                $stmt->close();
                header("Location: admin_dashboard.php?view=team&notice=admin_added");
                exit();
            }
        }

        $_SESSION["admin_error"] = $error;
        header("Location: admin_dashboard.php?view=team");
        exit();
    }

    if ($action === "add_course") {
        $title = trim($_POST["title"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $fee_input = trim($_POST["fee"] ?? "");
        $fee = is_numeric($fee_input) ? (float) $fee_input : -1;
        $fee_notes = trim($_POST["fee_notes"] ?? "");
        $duration = trim($_POST["duration"] ?? "");
        $level = trim($_POST["level"] ?? "");
        $certificate_details = trim($_POST["certificate_details"] ?? "");
        $requirements = trim($_POST["requirements"] ?? "");
        $course_outline = trim($_POST["course_outline"] ?? "");
        $course_notes = admin_sanitize_course_notes($_POST["course_notes"] ?? "");
        $image_path = null;
        $error = "";

        if ($title === "" || $category === "" || $description === "") {
            $error = "Complete all course details before publishing.";
        } elseif ($fee < 0 || $fee > 9999999999.99) {
            $error = "Enter a valid course fee in RWF.";
        } elseif ($duration === "" || !in_array($level, ["Beginner", "Intermediate", "Advanced", "All levels"], true)) {
            $error = "Enter the course duration and select a valid level.";
        } elseif ($certificate_details === "" || $requirements === "") {
            $error = "Add certificate details and entry requirements before publishing.";
        } elseif (!empty($_FILES["cover"]["name"])) {
            $cover = $_FILES["cover"];
            if ($cover["error"] !== UPLOAD_ERR_OK || $cover["size"] > 5 * 1024 * 1024) {
                $error = "The cover image must be smaller than 5 MB.";
            } else {
                $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($cover["tmp_name"]);
                $image_types = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

                if (!isset($image_types[$mime_type])) {
                    $error = "Upload a JPG, PNG, or WebP cover image.";
                } else {
                    $upload_directory = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "courses";
                    if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
                        $error = "The course image folder could not be created.";
                    } else {
                        $filename = bin2hex(random_bytes(16)) . "." . $image_types[$mime_type];
                        if (move_uploaded_file($cover["tmp_name"], $upload_directory . DIRECTORY_SEPARATOR . $filename)) {
                            $image_path = "uploads/courses/" . $filename;
                        } else {
                            $error = "The cover image could not be saved.";
                        }
                    }
                }
            }
        }

        if ($error === "") {
            $stmt = $conn->prepare("INSERT INTO courses (title, category, description, image_path, fee, fee_notes, duration, level, certificate_details, requirements, course_outline, course_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdsssssss", $title, $category, $description, $image_path, $fee, $fee_notes, $duration, $level, $certificate_details, $requirements, $course_outline, $course_notes);
            if ($stmt->execute()) {
                header("Location: admin_dashboard.php?view=courses&notice=course_added");
                exit();
            }
            $error = "The course could not be published. Please try again.";
            $stmt->close();
        }

        if ($error !== "") {
            $_SESSION["admin_error"] = $error;
            header("Location: admin_dashboard.php?view=courses");
            exit();
        }
    }

    if ($action === "update_course") {
        $course_id = (int) ($_POST["course_id"] ?? 0);
        $title = trim($_POST["title"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $fee_input = trim($_POST["fee"] ?? "");
        $fee = is_numeric($fee_input) ? (float) $fee_input : -1;
        $fee_notes = trim($_POST["fee_notes"] ?? "");
        $duration = trim($_POST["duration"] ?? "");
        $level = trim($_POST["level"] ?? "");
        $certificate_details = trim($_POST["certificate_details"] ?? "");
        $requirements = trim($_POST["requirements"] ?? "");
        $course_outline = trim($_POST["course_outline"] ?? "");
        $course_notes = admin_sanitize_course_notes($_POST["course_notes"] ?? "");
        $error = "";

        if ($course_id < 1 || $title === "" || $category === "" || $description === "") {
            $error = "Complete all required course details.";
        } elseif ($fee < 0 || $fee > 9999999999.99) {
            $error = "Enter a valid course fee in RWF.";
        } elseif ($duration === "" || !in_array($level, ["Beginner", "Intermediate", "Advanced", "All levels"], true)) {
            $error = "Enter the course duration and select a valid level.";
        } elseif ($certificate_details === "" || $requirements === "") {
            $error = "Add certificate details and entry requirements before saving.";
        } else {
            $stmt = $conn->prepare("UPDATE courses SET title = ?, category = ?, description = ?, fee = ?, fee_notes = ?, duration = ?, level = ?, certificate_details = ?, requirements = ?, course_outline = ?, course_notes = ? WHERE id = ?");
            $stmt->bind_param("sssdsssssssi", $title, $category, $description, $fee, $fee_notes, $duration, $level, $certificate_details, $requirements, $course_outline, $course_notes, $course_id);
            if ($stmt->execute() && $stmt->affected_rows >= 0) {
                header("Location: admin_dashboard.php?view=courses&notice=course_updated");
                exit();
            }
            $error = "The course details could not be saved.";
            $stmt->close();
        }

        $_SESSION["admin_error"] = $error;
        header("Location: admin_dashboard.php?view=courses&edit=" . $course_id);
        exit();
    }

    if ($action === "add_course_resource") {
        $course_id = (int) ($_POST["course_id"] ?? 0);
        $title = trim($_POST["resource_title"] ?? "");
        $resource_type = $_POST["resource_type"] ?? "";
        $upload = $_FILES["resource_file"] ?? null;
        $error = "";

        if ($course_id < 1 || $title === "" || !in_array($resource_type, ["image", "pdf"], true)) {
            $error = "Enter a title and choose an image or PDF resource.";
        } elseif (!$upload || $upload["error"] !== UPLOAD_ERR_OK) {
            $error = "Choose a valid file to upload.";
        } else {
            $maximum_size = $resource_type === "image" ? 8 * 1024 * 1024 : 20 * 1024 * 1024;
            if ($upload["size"] > $maximum_size) {
                $error = $resource_type === "image" ? "Course images must be 8 MB or smaller." : "PDF resources must be 20 MB or smaller.";
            } else {
                $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($upload["tmp_name"]);
                $allowed_types = $resource_type === "image"
                    ? ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"]
                    : ["application/pdf" => "pdf"];

                if (!isset($allowed_types[$mime_type])) {
                    $error = $resource_type === "image" ? "Upload a JPG, PNG, or WebP image." : "Upload a valid PDF document.";
                } else {
                    $course_check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
                    $course_check->bind_param("i", $course_id);
                    $course_check->execute();
                    $course_exists = $course_check->get_result()->num_rows === 1;
                    $course_check->close();

                    if (!$course_exists) {
                        $error = "The selected course could not be found.";
                    } else {
                        $resource_folder = $resource_type === "image" ? "images" : "pdfs";
                        $upload_directory = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "course-resources" . DIRECTORY_SEPARATOR . $resource_folder;
                        if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
                            $error = "The course resource folder could not be created.";
                        } else {
                            $filename = bin2hex(random_bytes(16)) . "." . $allowed_types[$mime_type];
                            $file_path = "uploads/course-resources/" . $resource_folder . "/" . $filename;
                            if (!move_uploaded_file($upload["tmp_name"], $upload_directory . DIRECTORY_SEPARATOR . $filename)) {
                                $error = "The resource file could not be saved.";
                            } else {
                                $stmt = $conn->prepare("INSERT INTO course_resources (course_id, title, resource_type, file_path) VALUES (?, ?, ?, ?)");
                                $stmt->bind_param("isss", $course_id, $title, $resource_type, $file_path);
                                if ($stmt->execute()) {
                                    $stmt->close();
                                    header("Location: admin_dashboard.php?view=courses&edit=" . $course_id . "&notice=resource_added");
                                    exit();
                                }
                                $stmt->close();
                                unlink($upload_directory . DIRECTORY_SEPARATOR . $filename);
                                $error = "The resource could not be attached to the course.";
                            }
                        }
                    }
                }
            }
        }

        $_SESSION["admin_error"] = $error;
        header("Location: admin_dashboard.php?view=courses&edit=" . $course_id);
        exit();
    }

    if ($action === "delete_course_resource") {
        $course_id = (int) ($_POST["course_id"] ?? 0);
        $resource_id = (int) ($_POST["resource_id"] ?? 0);
        $stmt = $conn->prepare("SELECT file_path FROM course_resources WHERE id = ? AND course_id = ?");
        $stmt->bind_param("ii", $resource_id, $course_id);
        $stmt->execute();
        $resource = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM course_resources WHERE id = ? AND course_id = ?");
        $stmt->bind_param("ii", $resource_id, $course_id);
        $stmt->execute();
        $stmt->close();

        if ($resource && strpos($resource["file_path"], "uploads/course-resources/") === 0) {
            $resource_file = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $resource["file_path"]);
            if (is_file($resource_file)) {
                unlink($resource_file);
            }
        }

        header("Location: admin_dashboard.php?view=courses&edit=" . $course_id . "&notice=resource_deleted");
        exit();
    }

    if ($action === "delete_course") {
        $course_id = (int) ($_POST["course_id"] ?? 0);
        $stmt = $conn->prepare("SELECT image_path FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $course = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $resource_stmt = $conn->prepare("SELECT file_path FROM course_resources WHERE course_id = ?");
        $resource_stmt->bind_param("i", $course_id);
        $resource_stmt->execute();
        $course_resources = $resource_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $resource_stmt->close();

        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $stmt->close();

        if ($course && !empty($course["image_path"]) && strpos($course["image_path"], "uploads/courses/") === 0) {
            $image_file = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $course["image_path"]);
            if (is_file($image_file)) {
                unlink($image_file);
            }
        }

        foreach ($course_resources as $resource) {
            if (strpos($resource["file_path"], "uploads/course-resources/") === 0) {
                $resource_file = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $resource["file_path"]);
                if (is_file($resource_file)) {
                    unlink($resource_file);
                }
            }
        }

        header("Location: admin_dashboard.php?view=courses&notice=course_deleted");
        exit();
    }

    if ($action === "delete_message") {
        $message_id = (int) ($_POST["message_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_dashboard.php?view=messages&notice=message_deleted");
        exit();
    }
}

$counts = [
    "courses" => $conn->query("SELECT COUNT(*) AS total FROM courses")->fetch_assoc()["total"],
    "students" => $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()["total"],
    "messages" => $conn->query("SELECT COUNT(*) AS total FROM messages")->fetch_assoc()["total"]
];
$courses = $conn->query("SELECT id, title, category, description, image_path, fee, duration, level, certificate_details, requirements, course_outline, course_notes, fee_notes, created_at FROM courses ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$edit_course_id = (int) ($_GET["edit"] ?? 0);
$edit_course = null;
foreach ($courses as $course) {
    if ((int) $course["id"] === $edit_course_id) {
        $edit_course = $course;
        break;
    }
}
$course_resources = [];
if ($edit_course) {
    $resource_stmt = $conn->prepare("SELECT id, title, resource_type, file_path, created_at FROM course_resources WHERE course_id = ? ORDER BY created_at DESC");
    $resource_stmt->bind_param("i", $edit_course_id);
    $resource_stmt->execute();
    $course_resources = $resource_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $resource_stmt->close();
}
$students = $conn->query("SELECT id, name, email, created_at FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$messages = $conn->query("SELECT id, name, email, subject, message, created_at FROM messages ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$admins = $conn->query("SELECT id, name, email, created_at FROM admins ORDER BY created_at ASC")->fetch_all(MYSQLI_ASSOC);
$feature_images = [];
$feature_image_result = $conn->query("SELECT feature_key, image_path FROM homepage_feature_images");
while ($feature_image = $feature_image_result->fetch_assoc()) {
    $feature_images[$feature_image["feature_key"]] = $feature_image["image_path"];
}
$hero_slides = $conn->query("SELECT id, image_path, created_at FROM hero_slides ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$latest_students = array_slice($students, 0, 5);
$notice = $_GET["notice"] ?? "";
$notices = [
    "course_added" => "Course published to the public catalog.",
    "course_updated" => "Course details updated.",
    "resource_added" => "Study material added to the course.",
    "resource_deleted" => "Study material removed from the course.",
    "course_deleted" => "Course removed from the catalog.",
    "message_deleted" => "Message deleted.",
    "account_updated" => "Your account details were updated.",
    "admin_added" => "Admin account created. They can now sign in.",
    "feature_image_updated" => "Homepage feature image updated.",
    "hero_slide_added" => "Hero slideshow image added.",
    "hero_slide_deleted" => "Hero slideshow image removed."
];
$error = $_SESSION["admin_error"] ?? "";
unset($_SESSION["admin_error"]);
$view_titles = [
    "overview" => "Overview",
    "courses" => "Course library",
    "students" => "Student registrations",
    "messages" => "Contact messages",
    "team" => "Admin team",
    "account" => "My account",
    "homepage" => "Homepage images"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_escape($view_titles[$view]); ?> | UTAB Admin</title>
    <link rel="stylesheet" href="css/admin.css?v=rich-notes-v3">
    <script src="css/js/script.js?v=rich-notes-v3" defer></script>
</head>
<body class="dashboard-page">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="admin_dashboard.php">UTAB <span>ONLINE</span></a>
        <button class="admin-menu-toggle" id="adminMenuBtn" type="button" aria-label="Open admin menu" aria-expanded="false" aria-controls="adminNav">
            <svg class="admin-menu-icon admin-menu-open" aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
            <svg class="admin-menu-icon admin-menu-close" aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"></path></svg>
        </button>
        <p class="sidebar-label">WORKSPACE</p>
        <nav class="admin-nav" id="adminNav" aria-label="Admin dashboard">
            <a class="<?php echo $view === "overview" ? "active" : ""; ?>" href="admin_dashboard.php">Overview</a>
            <a class="<?php echo $view === "courses" ? "active" : ""; ?>" href="admin_dashboard.php?view=courses">Courses <span><?php echo (int) $counts["courses"]; ?></span></a>
            <a class="<?php echo $view === "students" ? "active" : ""; ?>" href="admin_dashboard.php?view=students">Students <span><?php echo (int) $counts["students"]; ?></span></a>
            <a class="<?php echo $view === "messages" ? "active" : ""; ?>" href="admin_dashboard.php?view=messages">Messages <span><?php echo (int) $counts["messages"]; ?></span></a>
            <a class="<?php echo $view === "homepage" ? "active" : ""; ?>" href="admin_dashboard.php?view=homepage">Homepage images</a>
            <a class="<?php echo $view === "team" ? "active" : ""; ?>" href="admin_dashboard.php?view=team">Admin team</a>
            <a class="<?php echo $view === "account" ? "active" : ""; ?>" href="admin_dashboard.php?view=account">My account</a>
        </nav>
        <div class="sidebar-bottom">
            <a href="index.php">View public website</a>
            <form method="post" action="admin_logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                <button type="submit">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="admin-eyebrow">UTAB ONLINE / ADMIN</p>
                <h1><?php echo admin_escape($view_titles[$view]); ?></h1>
            </div>
            <div class="admin-user"><span class="admin-avatar"><?php echo admin_escape(strtoupper(substr($_SESSION["admin_name"], 0, 1))); ?></span><span><?php echo admin_escape($_SESSION["admin_name"]); ?></span></div>
        </header>

        <?php if (isset($notices[$notice])): ?>
            <div class="admin-alert admin-alert-success"><?php echo admin_escape($notices[$notice]); ?></div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="admin-alert admin-alert-error"><?php echo admin_escape($error); ?></div>
        <?php endif; ?>

        <?php if ($view === "overview"): ?>
            <section class="metric-grid" aria-label="Site totals">
                <article class="metric-panel"><span>Published courses</span><strong><?php echo (int) $counts["courses"]; ?></strong><a href="admin_dashboard.php?view=courses">Manage courses</a></article>
                <article class="metric-panel"><span>Student accounts</span><strong><?php echo (int) $counts["students"]; ?></strong><a href="admin_dashboard.php?view=students">View registrations</a></article>
                <article class="metric-panel"><span>Contact messages</span><strong><?php echo (int) $counts["messages"]; ?></strong><a href="admin_dashboard.php?view=messages">Review inbox</a></article>
            </section>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">LATEST ACTIVITY</p><h2>Recent student registrations</h2></div><a class="admin-text-link" href="admin_dashboard.php?view=students">All students</a></div>
                <?php if (!$latest_students): ?>
                    <p class="empty-state">No student accounts have been created yet.</p>
                <?php else: ?>
                    <div class="table-scroll"><table><thead><tr><th>Student</th><th>Email address</th><th>Registered</th></tr></thead><tbody>
                    <?php foreach ($latest_students as $student): ?>
                        <tr><td class="table-primary"><?php echo admin_escape($student["name"]); ?></td><td><?php echo admin_escape($student["email"]); ?></td><td><?php echo admin_escape(date("M j, Y", strtotime($student["created_at"]))); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php elseif ($view === "courses"): ?>
            <section class="admin-panel course-editor">
                <div class="panel-heading"><div><p class="admin-eyebrow"><?php echo $edit_course ? "UPDATE CONTENT" : "PUBLISH CONTENT"; ?></p><h2><?php echo $edit_course ? "Edit course" : "Add a course"; ?></h2></div><?php if ($edit_course): ?><a class="admin-text-link" href="admin_dashboard.php?view=courses">Cancel</a><?php endif; ?></div>
                <?php if (!$edit_course): ?>
                <form method="post" enctype="multipart/form-data" class="admin-form course-form">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                    <input type="hidden" name="action" value="add_course">
                    <div class="form-row"><div><label for="title">Course title</label><input id="title" name="title" type="text" maxlength="180" required></div><div><label for="category">Category</label><input id="category" name="category" type="text" maxlength="100" required></div></div>
                    <label for="description">Course description</label><textarea id="description" name="description" rows="4" required></textarea>
                    <div class="form-row"><div><label for="fee">Fee in RWF</label><input id="fee" name="fee" type="number" min="0" max="9999999999.99" step="0.01" value="0" required></div><div><label for="duration">Duration</label><input id="duration" name="duration" type="text" maxlength="120" placeholder="e.g. 12 weeks" required></div></div>
                    <div class="form-row"><div><label for="level">Level</label><select id="level" name="level" required><option value="All levels">All levels</option><option value="Beginner">Beginner</option><option value="Intermediate">Intermediate</option><option value="Advanced">Advanced</option></select></div><div><label for="fee_notes">Fee details <span class="field-hint">Optional</span></label><input id="fee_notes" name="fee_notes" type="text" maxlength="255" placeholder="Payment plans or included costs"></div></div>
                    <label for="certificate_details">Certificate</label><textarea id="certificate_details" name="certificate_details" rows="3" placeholder="Certificate awarded and completion criteria" required></textarea>
                    <label for="requirements">Entry requirements</label><textarea id="requirements" name="requirements" rows="3" placeholder="Add each requirement on a new line" required></textarea>
                    <label for="course_outline">Course outline <span class="field-hint">Optional, one topic per line</span></label><textarea id="course_outline" name="course_outline" rows="4" placeholder="Topics or modules covered"></textarea>
                    <label for="course_notes">Full course notes <span class="field-hint">Use + to add headings, text, quotes, links, or images</span></label>
                    <div class="rich-notes-editor" data-rich-notes-editor><div class="rich-notes-toolbar"><button class="rich-notes-add" type="button" aria-label="Add a note block" aria-expanded="false">+</button><div class="rich-notes-menu" hidden><input class="rich-notes-filter" type="search" placeholder="Filter blocks" aria-label="Filter note blocks"><button type="button" data-rich-command="paragraph">Text paragraph</button><button type="button" data-rich-command="heading">Heading</button><button type="button" data-rich-command="quote">Quote</button><button type="button" data-rich-command="link">Link</button><button type="button" data-rich-command="image">Image from computer</button></div></div><p class="rich-notes-empty">Click <strong>+</strong> to add your first text, heading, image, quote, or link.</p><div class="rich-notes-content" contenteditable="true" role="textbox" aria-multiline="true" data-rich-notes-content></div><input class="rich-notes-image-picker" type="file" accept="image/jpeg,image/png,image/webp" hidden><textarea id="course_notes" name="course_notes" class="rich-notes-input" hidden></textarea></div>
                    <label for="cover">Cover image <span class="field-hint">Optional, JPG / PNG / WebP, up to 5 MB</span></label><input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
                    <button class="admin-button admin-button-primary" type="submit">Publish course</button>
                </form>
                <?php else: ?>
                <form method="post" class="admin-form course-form">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_course">
                    <input type="hidden" name="course_id" value="<?php echo (int) $edit_course["id"]; ?>">
                    <div class="form-row"><div><label for="edit_title">Course title</label><input id="edit_title" name="title" type="text" maxlength="180" value="<?php echo admin_escape($edit_course["title"]); ?>" required></div><div><label for="edit_category">Category</label><input id="edit_category" name="category" type="text" maxlength="100" value="<?php echo admin_escape($edit_course["category"]); ?>" required></div></div>
                    <label for="edit_description">Course description</label><textarea id="edit_description" name="description" rows="4" required><?php echo admin_escape($edit_course["description"]); ?></textarea>
                    <div class="form-row"><div><label for="edit_fee">Fee in RWF</label><input id="edit_fee" name="fee" type="number" min="0" max="9999999999.99" step="0.01" value="<?php echo admin_escape($edit_course["fee"]); ?>" required></div><div><label for="edit_duration">Duration</label><input id="edit_duration" name="duration" type="text" maxlength="120" value="<?php echo admin_escape($edit_course["duration"]); ?>" required></div></div>
                    <div class="form-row"><div><label for="edit_level">Level</label><select id="edit_level" name="level" required><?php foreach (["All levels", "Beginner", "Intermediate", "Advanced"] as $level_option): ?><option value="<?php echo admin_escape($level_option); ?>" <?php echo $edit_course["level"] === $level_option ? "selected" : ""; ?>><?php echo admin_escape($level_option); ?></option><?php endforeach; ?></select></div><div><label for="edit_fee_notes">Fee details <span class="field-hint">Optional</span></label><input id="edit_fee_notes" name="fee_notes" type="text" value="<?php echo admin_escape($edit_course["fee_notes"]); ?>"></div></div>
                    <label for="edit_certificate">Certificate</label><textarea id="edit_certificate" name="certificate_details" rows="3" required><?php echo admin_escape($edit_course["certificate_details"]); ?></textarea>
                    <label for="edit_requirements">Entry requirements</label><textarea id="edit_requirements" name="requirements" rows="3" required><?php echo admin_escape($edit_course["requirements"]); ?></textarea>
                    <label for="edit_outline">Course outline <span class="field-hint">Optional, one topic per line</span></label><textarea id="edit_outline" name="course_outline" rows="4"><?php echo admin_escape($edit_course["course_outline"]); ?></textarea>
                    <label for="edit_course_notes">Full course notes <span class="field-hint">Use + to add headings, text, quotes, links, or images</span></label>
                    <div class="rich-notes-editor" data-rich-notes-editor><div class="rich-notes-toolbar"><button class="rich-notes-add" type="button" aria-label="Add a note block" aria-expanded="false">+</button><div class="rich-notes-menu" hidden><input class="rich-notes-filter" type="search" placeholder="Filter blocks" aria-label="Filter note blocks"><button type="button" data-rich-command="paragraph">Text paragraph</button><button type="button" data-rich-command="heading">Heading</button><button type="button" data-rich-command="quote">Quote</button><button type="button" data-rich-command="link">Link</button><button type="button" data-rich-command="image">Image from computer</button></div></div><p class="rich-notes-empty">Click <strong>+</strong> to add your first text, heading, image, quote, or link.</p><div class="rich-notes-content" contenteditable="true" role="textbox" aria-multiline="true" data-rich-notes-content><?php echo admin_sanitize_course_notes($edit_course["course_notes"]); ?></div><input class="rich-notes-image-picker" type="file" accept="image/jpeg,image/png,image/webp" hidden><textarea id="edit_course_notes" name="course_notes" class="rich-notes-input" hidden></textarea></div>
                    <button class="admin-button admin-button-primary" type="submit">Save course details</button>
                </form>
                <?php endif; ?>
            </section>
            <?php if ($edit_course): ?>
                <section class="admin-panel">
                    <div class="panel-heading"><div><p class="admin-eyebrow">COURSE MATERIALS</p><h2>Study images and PDF resources</h2></div><span class="result-count"><?php echo count($course_resources); ?> files</span></div>
                    <p class="empty-state">Add diagrams, lesson images, worksheets, reading materials, or other study PDFs. Images may be up to 8 MB; PDFs up to 20 MB.</p>
                    <form method="post" enctype="multipart/form-data" class="admin-form admin-form-panel course-resource-form">
                        <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_course_resource">
                        <input type="hidden" name="course_id" value="<?php echo (int) $edit_course["id"]; ?>">
                        <div class="form-row"><div><label for="resource_title">Resource title</label><input id="resource_title" name="resource_title" type="text" maxlength="180" placeholder="e.g. Week 1: Introduction" required></div><div><label for="resource_type">Resource type</label><select id="resource_type" name="resource_type" required><option value="image">Study image</option><option value="pdf">PDF document</option></select></div></div>
                        <label for="resource_file">Choose file</label><input id="resource_file" name="resource_file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                        <button class="admin-button admin-button-primary" type="submit">Add study material</button>
                    </form>
                    <?php if ($course_resources): ?>
                        <div class="course-resource-grid">
                            <?php foreach ($course_resources as $resource): ?>
                                <article class="course-resource-card">
                                    <?php if ($resource["resource_type"] === "image"): ?>
                                        <img src="<?php echo admin_escape($resource["file_path"]); ?>" alt="<?php echo admin_escape($resource["title"]); ?>">
                                    <?php else: ?>
                                        <div class="course-resource-pdf" aria-hidden="true">PDF</div>
                                    <?php endif; ?>
                                    <div class="course-resource-info">
                                        <strong><?php echo admin_escape($resource["title"]); ?></strong>
                                        <?php if ($resource["resource_type"] === "image"): ?><button class="admin-button admin-button-insert-image" type="button" data-course-image-token="[[image:<?php echo (int) $resource["id"]; ?>]]" data-course-image-path="<?php echo admin_escape($resource["file_path"]); ?>" data-course-image-title="<?php echo admin_escape($resource["title"]); ?>">Insert in notes</button><?php endif; ?>
                                        <?php if ($resource["resource_type"] === "pdf"): ?><a href="<?php echo admin_escape($resource["file_path"]); ?>" target="_blank" rel="noopener">Open PDF</a><?php endif; ?>
                                        <form method="post" onsubmit="return confirm('Remove this study material?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete_course_resource">
                                            <input type="hidden" name="course_id" value="<?php echo (int) $edit_course["id"]; ?>">
                                            <input type="hidden" name="resource_id" value="<?php echo (int) $resource["id"]; ?>">
                                            <button class="admin-button admin-button-quiet" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">No study materials uploaded for this course yet.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">PUBLIC CATALOG</p><h2>Published courses</h2></div><span class="result-count"><?php echo count($courses); ?> total</span></div>
                <?php if (!$courses): ?>
                    <p class="empty-state">No courses published yet. Add one above to make it visible on the Courses page.</p>
                <?php else: ?>
                    <div class="table-scroll"><table><thead><tr><th>Course</th><th>Category</th><th>Published</th><th></th></tr></thead><tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr><td class="table-primary"><?php echo admin_escape($course["title"]); ?><small><?php echo admin_escape($course["description"]); ?></small></td><td><?php echo admin_escape($course["category"]); ?></td><td><?php echo admin_escape(date("M j, Y", strtotime($course["created_at"]))); ?></td><td><div class="table-actions"><a class="admin-text-link" href="admin_dashboard.php?view=courses&amp;edit=<?php echo (int) $course["id"]; ?>">Edit</a><form method="post" onsubmit="return confirm('Remove this course from the catalog?');"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><input type="hidden" name="action" value="delete_course"><input type="hidden" name="course_id" value="<?php echo (int) $course["id"]; ?>"><button class="admin-button admin-button-quiet" type="submit">Remove</button></form></div></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php elseif ($view === "students"): ?>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">LEARNER DIRECTORY</p><h2>Registered accounts</h2></div><span class="result-count"><?php echo count($students); ?> total</span></div>
                <?php if (!$students): ?>
                    <p class="empty-state">Student signups will appear here.</p>
                <?php else: ?>
                    <div class="table-scroll"><table><thead><tr><th>Student</th><th>Email address</th><th>Joined</th></tr></thead><tbody>
                    <?php foreach ($students as $student): ?>
                        <tr><td class="table-primary"><?php echo admin_escape($student["name"]); ?></td><td><?php echo admin_escape($student["email"]); ?></td><td><?php echo admin_escape(date("M j, Y · g:i a", strtotime($student["created_at"]))); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php elseif ($view === "messages"): ?>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">INBOX</p><h2>Website enquiries</h2></div><span class="result-count"><?php echo count($messages); ?> total</span></div>
                <?php if (!$messages): ?>
                    <p class="empty-state">Contact form submissions will appear here.</p>
                <?php else: ?>
                    <div class="table-scroll"><table><thead><tr><th>Sender</th><th>Subject and message</th><th>Received</th><th></th></tr></thead><tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr><td class="table-primary"><?php echo admin_escape($message["name"]); ?><small><?php echo admin_escape($message["email"]); ?></small></td><td class="message-cell"><strong><?php echo admin_escape($message["subject"]); ?></strong><small><?php echo nl2br(admin_escape($message["message"])); ?></small></td><td><?php echo admin_escape(date("M j, Y", strtotime($message["created_at"]))); ?></td><td><form method="post" onsubmit="return confirm('Delete this message?');"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><input type="hidden" name="action" value="delete_message"><input type="hidden" name="message_id" value="<?php echo (int) $message["id"]; ?>"><button class="admin-button admin-button-quiet" type="submit">Delete</button></form></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php elseif ($view === "team"): ?>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">ACCESS CONTROL</p><h2>Create admin account</h2></div></div>
                <form method="post" class="admin-form admin-form-panel">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                    <input type="hidden" name="action" value="create_admin">
                    <label for="new_admin_name">Full name</label>
                    <input id="new_admin_name" name="name" type="text" maxlength="100" required>
                    <label for="new_admin_email">Email address</label>
                    <input id="new_admin_email" name="email" type="email" maxlength="150" required>
                    <label for="new_admin_password">Temporary password</label>
                    <input id="new_admin_password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                    <label for="confirm_admin_password">Confirm password</label>
                    <input id="confirm_admin_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required>
                    <button class="admin-button admin-button-primary" type="submit">Create admin</button>
                </form>
            </section>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">AUTHORIZED USERS</p><h2>Admin accounts</h2></div><span class="result-count"><?php echo count($admins); ?> total</span></div>
                <div class="table-scroll"><table><thead><tr><th>Name</th><th>Email address</th><th>Added</th></tr></thead><tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr><td class="table-primary"><?php echo admin_escape($admin["name"]); ?><?php if ((int) $admin["id"] === (int) $_SESSION["admin_id"]): ?><small>Signed-in account</small><?php endif; ?></td><td><?php echo admin_escape($admin["email"]); ?></td><td><?php echo admin_escape(date("M j, Y", strtotime($admin["created_at"]))); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            </section>
        <?php elseif ($view === "homepage"): ?>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">HERO BACKGROUND</p><h2>Rotating background photos</h2></div><span class="result-count"><?php echo count($hero_slides); ?> slides</span></div>
                <p class="empty-state">Add multiple photos to the homepage hero slideshow. Images rotate every 3 seconds with a translucent overlay behind the headline.</p>
                <form method="post" enctype="multipart/form-data" class="admin-form admin-form-panel">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                    <input type="hidden" name="action" value="add_hero_slide">
                    <label for="hero_image">Background photo <span class="field-hint">JPG / PNG / WebP, up to 5 MB</span></label>
                    <input id="hero_image" name="hero_image" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <button class="admin-button admin-button-primary" type="submit">Add slideshow photo</button>
                </form>
                <?php if ($hero_slides): ?>
                    <div class="hero-slide-grid">
                        <?php foreach ($hero_slides as $slide): ?>
                            <article class="hero-slide-admin-card">
                                <img src="<?php echo admin_escape($slide["image_path"]); ?>" alt="Hero slideshow photo">
                                <div><span>Slide <?php echo (int) $slide["id"]; ?></span><form method="post" onsubmit="return confirm('Remove this hero photo?');"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><input type="hidden" name="action" value="delete_hero_slide"><input type="hidden" name="slide_id" value="<?php echo (int) $slide["id"]; ?>"><button class="admin-button admin-button-quiet" type="submit">Remove</button></form></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">HOME PAGE</p><h2>Feature card images</h2></div></div>
                <p class="empty-state">Upload a photo for each card. Images are shown on the public home page and can be replaced at any time.</p>
                <?php foreach ($feature_slots as $feature_key => $feature_label): ?>
                    <div class="feature-upload-row">
                        <div class="feature-upload-preview">
                            <?php if (isset($feature_images[$feature_key])): ?>
                                <img src="<?php echo admin_escape($feature_images[$feature_key]); ?>" alt="<?php echo admin_escape($feature_label); ?>">
                            <?php else: ?>
                                <span>Image not uploaded</span>
                            <?php endif; ?>
                        </div>
                        <div class="feature-upload-details">
                            <h3><?php echo admin_escape($feature_label); ?></h3>
                            <form method="post" enctype="multipart/form-data" class="admin-form">
                                <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                                <input type="hidden" name="action" value="upload_feature_image">
                                <input type="hidden" name="feature_key" value="<?php echo admin_escape($feature_key); ?>">
                                <label for="image_<?php echo admin_escape($feature_key); ?>">Image <span class="field-hint">JPG / PNG / WebP, up to 5 MB</span></label>
                                <input id="image_<?php echo admin_escape($feature_key); ?>" name="feature_image" type="file" accept="image/jpeg,image/png,image/webp" required>
                                <button class="admin-button admin-button-primary" type="submit"><?php echo isset($feature_images[$feature_key]) ? "Replace image" : "Upload image"; ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="admin-panel">
                <div class="panel-heading"><div><p class="admin-eyebrow">PROFILE</p><h2>Update your details</h2></div></div>
                <form method="post" class="admin-form admin-form-panel">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_account">
                    <label for="account_name">Display name</label>
                    <input id="account_name" name="name" type="text" maxlength="100" value="<?php echo admin_escape($_SESSION["admin_name"]); ?>" required>
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    <label for="new_password">New password <span class="field-hint">Leave blank to keep the current password</span></label>
                    <input id="new_password" name="new_password" type="password" minlength="10" autocomplete="new-password">
                    <label for="confirm_new_password">Confirm new password</label>
                    <input id="confirm_new_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password">
                    <button class="admin-button admin-button-primary" type="submit">Save changes</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
