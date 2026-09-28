document.addEventListener("DOMContentLoaded", function () {

    const heroSlides = document.querySelectorAll(".hero-slide");
    if (heroSlides.length > 1) {
        let activeHeroSlide = 0;
        window.setInterval(function () {
            heroSlides[activeHeroSlide].classList.remove("active");
            activeHeroSlide = (activeHeroSlide + 1) % heroSlides.length;
            heroSlides[activeHeroSlide].classList.add("active");
        }, 3000);
    }

    const eyeIcon = '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffIcon = '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.8 15.8 0 0 1-3.1 3.8"></path><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.8 10.8 0 0 0 4.1-.8"></path><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path><path d="m3 3 18 18"></path></svg>';

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        const wrapper = document.createElement("div");
        wrapper.className = "password-field";
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "password-toggle";
        toggle.setAttribute("aria-label", "Show password");
        toggle.setAttribute("aria-pressed", "false");
        toggle.title = "Show password";
        toggle.innerHTML = eyeIcon;
        wrapper.appendChild(toggle);

        toggle.addEventListener("click", function () {
            const showPassword = input.type === "password";
            input.type = showPassword ? "text" : "password";
            toggle.setAttribute("aria-label", showPassword ? "Hide password" : "Show password");
            toggle.setAttribute("aria-pressed", showPassword ? "true" : "false");
            toggle.title = showPassword ? "Hide password" : "Show password";
            toggle.innerHTML = showPassword ? eyeOffIcon : eyeIcon;
        });
    });

    const menuBtn = document.getElementById("menuBtn");
    const navbar = document.getElementById("navbar");

    if (menuBtn && navbar) {

        menuBtn.addEventListener("click", function () {

            navbar.classList.toggle("active");

        });

    }

    const adminMenuBtn = document.getElementById("adminMenuBtn");
    const adminSidebar = document.querySelector(".admin-sidebar");

    if (adminMenuBtn && adminSidebar) {
        adminMenuBtn.addEventListener("click", function () {
            const isOpen = adminSidebar.classList.toggle("is-open");
            adminMenuBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
            adminMenuBtn.setAttribute("aria-label", isOpen ? "Close admin menu" : "Open admin menu");
        });
    }

    const richEditors = document.querySelectorAll("[data-rich-notes-editor]");

    function insertRichHtml(editor, html) {
        editor.focus();
        document.execCommand("insertHTML", false, html);
        editor.dispatchEvent(new Event("input"));
    }

    richEditors.forEach(function (editorShell) {
        const editor = editorShell.querySelector("[data-rich-notes-content]");
        const input = editorShell.querySelector(".rich-notes-input");
        const addButton = editorShell.querySelector(".rich-notes-add");
        const menu = editorShell.querySelector(".rich-notes-menu");
        const filter = editorShell.querySelector(".rich-notes-filter");
        const imagePicker = editorShell.querySelector(".rich-notes-image-picker");

        function syncNotes() {
            input.value = editor.innerHTML.trim();
            editorShell.classList.toggle("has-content", editor.textContent.trim() !== "" || editor.querySelector("img") !== null);
        }

        editor.addEventListener("input", syncNotes);
        editor.closest("form").addEventListener("submit", syncNotes);
        syncNotes();

        addButton.addEventListener("click", function () {
            const isOpen = !menu.hidden;
            menu.hidden = isOpen;
            addButton.setAttribute("aria-expanded", String(!isOpen));
            if (!isOpen) filter.focus();
        });

        filter.addEventListener("input", function () {
            const term = filter.value.toLowerCase();
            menu.querySelectorAll("button").forEach(function (button) {
                button.hidden = !button.textContent.toLowerCase().includes(term);
            });
        });

        imagePicker.addEventListener("change", async function () {
            const image = imagePicker.files[0];
            if (!image) return;
            const upload = new FormData();
            upload.append("action", "upload_note_image");
            upload.append("csrf_token", editor.closest("form").querySelector('input[name="csrf_token"]').value);
            upload.append("note_image", image);
            try {
                const response = await fetch("admin_dashboard.php", { method: "POST", body: upload });
                const result = await response.json();
                if (!response.ok || !result.path) throw new Error(result.error || "The image could not be uploaded.");
                insertRichHtml(editor, '<img src="' + result.path + '" alt="' + image.name.replace(/"/g, "&quot;") + '"><p><br></p>');
            } catch (error) {
                window.alert(error.message || "The image could not be uploaded.");
            }
            imagePicker.value = "";
        });

        menu.querySelectorAll("[data-rich-command]").forEach(function (button) {
            button.addEventListener("click", function () {
                const command = button.getAttribute("data-rich-command");
                editor.focus();
                if (command === "paragraph") insertRichHtml(editor, "<p>Start writing your paragraph here.</p><p><br></p>");
                if (command === "heading") insertRichHtml(editor, "<h2>Write your heading here</h2><p><br></p>");
                if (command === "quote") insertRichHtml(editor, "<blockquote>Write a quote</blockquote><p><br></p>");
                if (command === "link") {
                    const url = window.prompt("Paste the link URL (https://...)");
                    if (url) {
                        const label = window.prompt("What text should this link show?", "Open link");
                        insertRichHtml(editor, '<p><a href="' + url.replace(/"/g, "&quot;") + '">' + (label || "Open link").replace(/</g, "&lt;") + "</a></p>");
                    }
                }
                if (command === "image") {
                    imagePicker.click();
                }
                menu.hidden = true;
                addButton.setAttribute("aria-expanded", "false");
                syncNotes();
            });
        });
    });

    document.querySelectorAll("[data-course-image-token]").forEach(function (button) {
        button.addEventListener("click", function () {
            const editor = document.querySelector("[data-rich-notes-content]");
            if (editor) {
                const imagePath = button.getAttribute("data-course-image-path");
                const imageTitle = button.getAttribute("data-course-image-title") || "Course image";
                insertRichHtml(editor, '<img src="' + imagePath.replace(/"/g, "&quot;") + '" alt="' + imageTitle.replace(/"/g, "&quot;") + '">');
            }
        });
    });


    // Password confirmation

    const signupForm = document.querySelector(
        'form[action="signup.php"]'
    );

    if (signupForm) {

        signupForm.addEventListener("submit", function (event) {

            const password =
                document.querySelector('input[name="password"]').value;

            const confirmPassword =
                document.querySelector(
                    'input[name="confirm_password"]'
                ).value;

            if (password !== confirmPassword) {

                event.preventDefault();

                alert("Passwords do not match.");

            }

        });

    }

});
