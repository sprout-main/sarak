const KEY = 'atlas-theme';

export function getTheme() {
    return localStorage.getItem(KEY) === 'light' ? 'light' : 'dark';
}

export function applyTheme(value) {
    const theme = value === 'light' ? 'light' : 'dark';
    localStorage.setItem(KEY, theme);
    document.documentElement.dataset.theme = theme;
    window.dispatchEvent(new CustomEvent('atlas-theme', { detail: { theme } }));
    return theme;
}

export function themeToggle() {
    return {
        theme: getTheme(),
        init() {
            window.addEventListener('atlas-theme', (e) => {
                if (e.detail && e.detail.theme && e.detail.theme !== this.theme) {
                    this.theme = e.detail.theme;
                }
            });
        },
        toggle() {
            this.theme = applyTheme(this.theme === 'light' ? 'dark' : 'light');
        },
    };
}