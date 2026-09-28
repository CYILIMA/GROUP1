<?php
$page_title = "UTAB Online - Home";
include "db.php";
$feature_images = [];
$feature_result = $conn->query("SELECT feature_key, image_path FROM homepage_feature_images");
while ($feature_image = $feature_result->fetch_assoc()) {
    $feature_images[$feature_image["feature_key"]] = $feature_image["image_path"];
}
$hero_slides = $conn->query("SELECT image_path FROM hero_slides ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
include "header.php";
?>

<section class="hero">

    <?php if ($hero_slides): ?>
        <div class="hero-slideshow" aria-hidden="true">
            <?php foreach ($hero_slides as $index => $slide): ?>
                <div class="hero-slide<?php echo $index === 0 ? " active" : ""; ?>">
                    <img src="<?php echo htmlspecialchars($slide["image_path"], ENT_QUOTES, "UTF-8"); ?>" alt="">
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="hero-overlay"></div>

    <div class="hero-content">

        <h1>Learn Today.<br>Lead Tomorrow.</h1>

        <p>
            Welcome to UTAB Online, your platform for accessible,
            flexible and quality online education.
        </p>

        <div class="hero-buttons">
            <a href="cources.php" class="primary-btn">Explore Courses</a>
            <a href="signup.php" class="secondary-btn">Join Us</a>
        </div>

    </div>

</section>

<section class="features">

    <div class="section-title">
        <h2>Why Choose UTAB Online?</h2>
        <p>Everything you need to continue your education online.</p>
    </div>

    <div class="feature-container">

        <div class="feature-card">
            <div class="feature-image">
                <?php if (isset($feature_images["quality_courses"])): ?>
                    <img src="<?php echo htmlspecialchars($feature_images["quality_courses"], ENT_QUOTES, "UTF-8"); ?>" alt="Books and learning materials">
                <?php else: ?>
                    <span class="feature-image-placeholder">Image pending</span>
                <?php endif; ?>
            </div>
            <h3>Quality Courses</h3>
            <p>
                Access well-structured courses designed to help
                you develop valuable knowledge and skills.
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-image">
                <?php if (isset($feature_images["learn_online"])): ?>
                    <img src="<?php echo htmlspecialchars($feature_images["learn_online"], ENT_QUOTES, "UTF-8"); ?>" alt="Online learning on a computer">
                <?php else: ?>
                    <span class="feature-image-placeholder">Image pending</span>
                <?php endif; ?>
            </div>
            <h3>Learn Online</h3>
            <p>
                Study from anywhere using your computer,
                tablet or smartphone.
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-image">
                <?php if (isset($feature_images["build_future"])): ?>
                    <img src="<?php echo htmlspecialchars($feature_images["build_future"], ENT_QUOTES, "UTF-8"); ?>" alt="Graduation and career development">
                <?php else: ?>
                    <span class="feature-image-placeholder">Image pending</span>
                <?php endif; ?>
            </div>
            <h3>Build Your Future</h3>
            <p>
                Develop your academic and professional skills
                through flexible learning.
            </p>
        </div>

    </div>

</section>

<section class="cta">

    <h2>Start Your Learning Journey</h2>

    <p>
        Create your account and discover our online courses.
    </p>

    <a href="signup.php" class="primary-btn">Create Account</a>

</section>

<?php include "footer.php"; ?>