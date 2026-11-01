document.addEventListener('DOMContentLoaded', () => {
    const toggles = [...document.querySelectorAll('#sidebar .menu-toggle')];
    const key = 'alamak-sidebar-open-menu';

    const setOpen = (toggle, open) => {
        const submenu = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!submenu) return;
        toggle.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        submenu.classList.toggle('show', open);
    };

    document.querySelectorAll('#sidebar .active-menu').forEach((link) => {
        let parent = link.closest('.submenu');
        while (parent) {
            const toggle = parent.previousElementSibling;
            if (toggle?.matches('.menu-toggle')) setOpen(toggle, true);
            parent = parent.parentElement?.closest('.submenu') ?? null;
        }
    });

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            const opening = toggle.getAttribute('aria-expanded') !== 'true';
            setOpen(toggle, opening);
            if (opening) localStorage.setItem(key, toggle.dataset.menu);
            else if (localStorage.getItem(key) === toggle.dataset.menu) localStorage.removeItem(key);
        });
    });

    const lastOpen = localStorage.getItem(key);
    const remembered = toggles.find((toggle) => toggle.dataset.menu === lastOpen);
    if (remembered) setOpen(remembered, true);
});
