document.addEventListener("DOMContentLoaded", function () {

    const toggles = document.querySelectorAll(".menu-toggle");

    // اگر لینک فعال داخل زیرمنو باشد، والد را باز کن
    document.querySelectorAll(".active-menu").forEach(activeLink => {

        const submenu = activeLink.closest(".submenu");

        if (submenu) {

            submenu.classList.add("show");

            const toggle = submenu.previousElementSibling;

            if (toggle)
                toggle.classList.add("open");

        }

    });

    toggles.forEach(toggle => {

        toggle.addEventListener("click", function (e) {

            e.preventDefault();

            const submenu = this.nextElementSibling;

            const opened = submenu.classList.contains("show");

            // بستن همه منوها
            document.querySelectorAll(".submenu").forEach(item => {

                item.classList.remove("show");

            });

            document.querySelectorAll(".menu-toggle").forEach(item => {

                item.classList.remove("open");

            });

            // اگر بسته بود بازش کن
            if (!opened) {

                submenu.classList.add("show");

                this.classList.add("open");

                localStorage.setItem("menu", this.dataset.menu);

            } else {

                localStorage.removeItem("menu");

            }

        });

    });

    // آخرین منوی باز
    const last = localStorage.getItem("menu");

    if(last){

        const submenu=document.getElementById("submenu"+last);

        const toggle=document.querySelector(
            '.menu-toggle[data-menu="'+last+'"]'
        );

        if(submenu && toggle){

            submenu.classList.add("show");

            toggle.classList.add("open");

        }

    }

});