<?php
require "db.php";

$course_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$stmt = $conn->prepare("SELECT title, category, description, image_path, fee, fee_notes, duration, level, certificate_details, requirements, course_outline, course_notes FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();
$course_resources = [];
if ($course) {
    $resource_stmt = $conn->prepare("SELECT id, title, resource_type, file_path FROM course_resources WHERE course_id = ? ORDER BY created_at ASC, id ASC");
    $resource_stmt->bind_param("i", $course_id);
    $resource_stmt->execute();
    $course_resources = $resource_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $resource_stmt->close();
}
$course_images_by_id = [];
foreach ($course_resources as $resource) {
    if ($resource["resource_type"] === "image") {
        $course_images_by_id[(int) $resource["id"]] = $resource;
    }
}
$inline_image_ids = [];
preg_match_all("/\\[\\[image:(\\d+)\\]\\]/", (string) ($course["course_notes"] ?? ""), $inline_image_matches);
foreach ($inline_image_matches[1] as $inline_image_id) {
    $inline_image_ids[(int) $inline_image_id] = true;
}
$gallery_images = array_values(array_filter($course_resources, function ($resource) use ($inline_image_ids) {
    return $resource["resource_type"] === "image" && !isset($inline_image_ids[(int) $resource["id"]]);
}));

if (!$course) {
    http_response_code(404);
}

$page_title = $course ? $course["title"] . " - UTAB Online" : "Course Not Found - UTAB Online";
include "header.php";

function course_detail_lines($value)
{
    return array_values(array_filter(array_map("trim", preg_split("/\r\n|\r|\n/", (string) $value))));
}

function course_detail_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function course_detail_render_notes($notes, $course_images_by_id)
{
    if (strip_tags((string) $notes) !== (string) $notes) {
        $allowed_tags = ["p", "br", "h2", "h3", "blockquote", "a", "img", "ul", "ol", "li", "strong", "em", "b", "i"];
        $document = new DOMDocument("1.0", "UTF-8");
        libxml_use_internal_errors(true);
        $document->loadHTML("<div>" . $notes . "</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $clean_node = function ($node) use (&$clean_node, $allowed_tags) {
            if ($node->nodeType === XML_TEXT_NODE) return course_detail_escape($node->nodeValue);
            if ($node->nodeType !== XML_ELEMENT_NODE) return "";
            $tag = strtolower($node->nodeName);
            if (in_array($tag, ["script", "style", "iframe", "object"], true)) return "";
            $children = "";
            foreach ($node->childNodes as $child) $children .= $clean_node($child);
            if (!in_array($tag, $allowed_tags, true)) return $children;
            if ($tag === "br") return "<br>";
            if ($tag === "img") {
                $src = trim($node->getAttribute("src"));
                return preg_match("#^uploads/#i", $src) ? '<img src="' . course_detail_escape($src) . '" alt="' . course_detail_escape($node->getAttribute("alt")) . '">' : "";
            }
            if ($tag === "a") {
                $href = trim($node->getAttribute("href"));
                return preg_match("#^(https?://|mailto:|/)#i", $href) ? '<a href="' . course_detail_escape($href) . '" target="_blank" rel="noopener noreferrer">' . $children . "</a>" : $children;
            }
            return "<" . $tag . ">" . $children . "</" . $tag . ">";
        };
        $rendered_html = "";
        foreach ($document->documentElement->childNodes as $child) $rendered_html .= $clean_node($child);
        return $rendered_html;
    }

    $parts = preg_split('/(\[\[image:\d+\]\])/', (string) $notes, -1, PREG_SPLIT_DELIM_CAPTURE);
    $rendered_notes = "";

    foreach ($parts as $part) {
        if (preg_match("/^\\[\\[image:(\\d+)\\]\\]$/", $part, $match)) {
            $resource = $course_images_by_id[(int) $match[1]] ?? null;
            if ($resource) {
                $rendered_notes .= "<figure class=\"course-detail-inline-image\"><img src=\"" . course_detail_escape($resource["file_path"]) . "\" alt=\"" . course_detail_escape($resource["title"]) . "\"><figcaption>" . course_detail_escape($resource["title"]) . "</figcaption></figure>";
                continue;
            }
        }

        $rendered_notes .= nl2br(course_detail_escape($part));
    }

    return $rendered_notes;
}
?>

<?php if (!$course): ?>
    <section class="page-header">
        <h1>Course not found</h1>
        <p>This course may have been removed from the catalog.</p>
        <a href="cources.php" class="secondary-btn">Browse courses</a>
    </section>
<?php else: ?>
    <section class="course-detail-hero">
        <?php if (!empty($course["image_path"])): ?>
            <img src="<?php echo course_detail_escape($course["image_path"]); ?>" alt="">
        <?php endif; ?>
        <div class="course-detail-hero-content">
            <span class="course-detail-category"><?php echo course_detail_escape($course["category"]); ?></span>
            <h1><?php echo course_detail_escape($course["title"]); ?></h1>
            <p><?php echo nl2br(course_detail_escape($course["description"])); ?></p>
            <div class="course-detail-actions">
                <a href="signup.php" class="primary-btn">Enroll Now</a>
                <a href="cources.php" class="course-back-link">Back to courses</a>
            </div>
        </div>
    </section>

    <main class="course-detail-layout">
        <div class="course-detail-main">
            <section class="course-detail-section">
                <p class="course-detail-eyebrow">PROGRAMME OVERVIEW</p>
                <h2>What you will learn</h2>
                <?php if (course_detail_lines($course["course_outline"])): ?>
                    <ul class="course-detail-list">
                        <?php foreach (course_detail_lines($course["course_outline"]) as $topic): ?>
                            <li><?php echo course_detail_escape($topic); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>The course outline will be announced soon.</p>
                <?php endif; ?>
            </section>

            <section class="course-detail-section">
                <p class="course-detail-eyebrow">ADMISSION</p>
                <h2>Entry requirements</h2>
                <?php if (course_detail_lines($course["requirements"])): ?>
                    <ul class="course-detail-list">
                        <?php foreach (course_detail_lines($course["requirements"]) as $requirement): ?>
                            <li><?php echo course_detail_escape($requirement); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Entry requirements will be announced soon.</p>
                <?php endif; ?>
            </section>

            <section class="course-detail-section">
                <p class="course-detail-eyebrow">STUDY NOTES</p>
                <h2>Full course notes</h2>
                <?php if (!empty(trim($course["course_notes"] ?? ""))): ?>
                    <div class="course-notes"><?php echo course_detail_render_notes($course["course_notes"], $course_images_by_id); ?></div>
                <?php else: ?>
                    <p>Full course notes will be added soon.</p>
                <?php endif; ?>
            </section>

            <?php if ($gallery_images): ?>
                <section class="course-detail-section">
                    <p class="course-detail-eyebrow">VISUAL MATERIALS</p>
                    <h2>Course images</h2>
                    <div class="course-material-gallery">
                        <?php foreach ($gallery_images as $resource): ?>
                            <figure>
                                <img src="<?php echo course_detail_escape($resource["file_path"]); ?>" alt="<?php echo course_detail_escape($resource["title"]); ?>">
                                <figcaption><?php echo course_detail_escape($resource["title"]); ?></figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($course["fee_notes"])): ?>
                <section class="course-detail-section">
                    <p class="course-detail-eyebrow">PAYMENT</p>
                    <h2>Fees and payment</h2>
                    <p><?php echo nl2br(course_detail_escape($course["fee_notes"])); ?></p>
                </section>
            <?php endif; ?>

            <?php $pdf_resources = array_values(array_filter($course_resources, function ($resource) { return $resource["resource_type"] === "pdf"; })); ?>
            <?php if ($pdf_resources): ?>
                <section class="course-detail-section">
                    <p class="course-detail-eyebrow">LEARNING LIBRARY</p>
                    <h2>Study resources</h2>
                    <div class="course-resource-list">
                        <?php foreach ($pdf_resources as $resource): ?>
                            <a class="course-resource-link" href="<?php echo course_detail_escape($resource["file_path"]); ?>" download>
                                <span class="course-resource-pdf-mark" aria-hidden="true">PDF</span>
                                <span class="course-resource-title"><?php echo course_detail_escape($resource["title"]); ?><small>PDF study material</small></span>
                                <span class="course-resource-download">Download</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="course-facts">
            <p class="course-detail-eyebrow">COURSE DETAILS</p>
            <dl>
                <div><dt>Tuition fee</dt><dd><?php echo $course["fee"] === null ? "To be confirmed" : ((float) $course["fee"] === 0.0 ? "Free" : "RWF " . number_format((float) $course["fee"], ((float) $course["fee"] === floor((float) $course["fee"])) ? 0 : 2)); ?></dd></div>
                <div><dt>Duration</dt><dd><?php echo course_detail_escape($course["duration"] ?: "To be announced"); ?></dd></div>
                <div><dt>Level</dt><dd><?php echo course_detail_escape($course["level"] ?: "All levels"); ?></dd></div>
                <div><dt>Certificate</dt><dd><?php echo nl2br(course_detail_escape($course["certificate_details"] ?: "To be announced")); ?></dd></div>
            </dl>
            <a href="signup.php" class="primary-btn">Enroll Now</a>
        </aside>
    </main>
<?php endif; ?>

<?php include "footer.php"; ?>
