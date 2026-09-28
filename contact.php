<?php

$page_title = "UTAB Online - Contact Us";

include "db.php";
include "header.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $subject = trim($_POST["subject"]);
    $user_message = trim($_POST["message"]);

    if ($name == "" || $email == "" || $subject == "" || $user_message == "") {

        $message = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO messages (name, email, subject, message)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssss",
            $name,
            $email,
            $subject,
            $user_message
        );

        if ($stmt->execute()) {

            $message = "Your message has been sent successfully.";

        } else {

            $message = "Something went wrong. Please try again.";

        }

        $stmt->close();
    }
}

?>

<section class="page-header">

    <h1>Contact Us</h1>

    <p>
        We would love to hear from you.
    </p>

</section>

<section class="contact-section">

    <div class="contact-info">

        <h2>Get In Touch</h2>

        <p>
            If you have questions about our courses or platform,
            contact us using the information below.
        </p>

        <div class="contact-item">
            <strong>📧 Email</strong>
            <p>info@utabonline.com</p>
        </div>

        <div class="contact-item">
            <strong>📞 Phone</strong>
            <p>+250 788 000 000</p>
        </div>

        <div class="contact-item">
            <strong>📍 Location</strong>
            <p>Rwanda</p>
        </div>

    </div>


    <div class="contact-form">

        <?php if ($message != ""): ?>

            <div class="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="contact.php">

            <label>Your Name</label>

            <input
                type="text"
                name="name"
                placeholder="Enter your name"
                required
            >

            <label>Email Address</label>

            <input
                type="email"
                name="email"
                placeholder="Enter your email"
                required
            >

            <label>Subject</label>

            <input
                type="text"
                name="subject"
                placeholder="Enter subject"
                required
            >

            <label>Message</label>

            <textarea
                name="message"
                rows="6"
                placeholder="Write your message..."
                required
            ></textarea>

            <button type="submit" class="primary-btn">
                Send Message
            </button>

        </form>

    </div>

</section>

<?php include "footer.php"; ?>