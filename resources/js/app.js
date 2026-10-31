import './bootstrap';

const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
const header = document.querySelector('[data-header]');
const progress = document.querySelector('.page-progress span');
const update = () => { const root = document.documentElement; header?.classList.toggle('scrolled', root.scrollTop > 16); if (progress) progress.style.width = `${root.scrollHeight > root.clientHeight ? root.scrollTop / (root.scrollHeight - root.clientHeight) * 100 : 0}%`; };
addEventListener('scroll', update, { passive: true }); update();

const menu = document.querySelector('[data-menu-toggle]'); const nav = document.querySelector('[data-nav-links]');
menu?.addEventListener('click', () => { const open = nav.classList.toggle('open'); menu.classList.toggle('open', open); menu.setAttribute('aria-expanded', String(open)); });
nav?.addEventListener('click', (event) => { if (event.target.closest('a')) { nav.classList.remove('open'); menu?.classList.remove('open'); menu?.setAttribute('aria-expanded', 'false'); } });

const faNumber = (number) => new Intl.NumberFormat('fa-IR').format(number);
const count = (element) => { const target = Number(element.dataset.counter); const suffix = element.dataset.suffix || ''; const start = performance.now(); const draw = (now) => { const progressValue = reducedMotion ? 1 : Math.min((now - start) / 1250, 1); element.textContent = `${faNumber(Math.round(target * (1 - Math.pow(1 - progressValue, 4))))}${suffix}`; if (progressValue < 1) requestAnimationFrame(draw); }; requestAnimationFrame(draw); };
if ('IntersectionObserver' in window && !reducedMotion) { const reveal = new IntersectionObserver((items, observer) => items.forEach((item) => { if (item.isIntersecting) { setTimeout(() => item.target.classList.add('visible'), Number(item.target.dataset.revealDelay || 0)); observer.unobserve(item.target); } }), { threshold: .13 }); document.querySelectorAll('[data-reveal]').forEach((item) => reveal.observe(item)); const counters = new IntersectionObserver((items, observer) => items.forEach((item) => { if (item.isIntersecting) { count(item.target); observer.unobserve(item.target); } }), { threshold: .7 }); document.querySelectorAll('[data-counter]').forEach((item) => counters.observe(item)); } else { document.querySelectorAll('[data-reveal]').forEach((item) => item.classList.add('visible')); document.querySelectorAll('[data-counter]').forEach(count); }
