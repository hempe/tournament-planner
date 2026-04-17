document.addEventListener('DOMContentLoaded', function () {
    const scrollTop = sessionStorage.getItem('scrollPosition');
    const currentUrl = window.location.href;
    const savedUrl = sessionStorage.getItem('currentUrl');

    if (scrollTop && savedUrl === currentUrl) {
        window.scrollTo(0, scrollTop);
        sessionStorage.removeItem('scrollPosition');
    }

    sessionStorage.setItem('currentUrl', currentUrl);
    document.addEventListener('scroll', function () {
        sessionStorage.setItem('scrollPosition', document.documentElement.scrollTop);
    });

    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);
});
