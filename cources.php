<?php
$page_title = "UTAB Online - Courses";
include "db.php";
$courses = $conn->query("SELECT id, title, category, description, image_path FROM courses ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
include "header.php";
?>

<section class="page-header">

    <h1>Our Courses</h1>

    <p>
        Explore courses available through UTAB Online.
    </p>

</section>

<section class="courses-section">

    <div class="course-container">

        <?php if ($courses): ?>
            <?php foreach ($courses as $course): ?>
                <div class="course-card">
                    <div class="course-image">
                        <?php if (!empty($course["image_path"])): ?>
                            <img src="<?php echo htmlspecialchars($course["image_path"], ENT_QUOTES, "UTF-8"); ?>" alt="">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars(strtoupper(substr($course["category"], 0, 1)), ENT_QUOTES, "UTF-8"); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="course-content">
                        <span class="course-category"><?php echo htmlspecialchars($course["category"], ENT_QUOTES, "UTF-8"); ?></span>
                        <h2><?php echo htmlspecialchars($course["title"], ENT_QUOTES, "UTF-8"); ?></h2>
                        <p><?php echo nl2br(htmlspecialchars($course["description"], ENT_QUOTES, "UTF-8")); ?></p>
                        <div class="course-actions">
                            <a href="signup.php" class="course-btn">Enroll Now</a>
                            <a href="course_details.php?id=<?php echo (int) $course["id"]; ?>" class="course-btn course-details-btn">Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="catalog-empty">New courses will be published here soon.</p>
        <?php endif; ?>

    </div>

</section>

<?php include "footer.php"; ?>