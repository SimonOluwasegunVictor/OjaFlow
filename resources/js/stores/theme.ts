import { defineStore } from 'pinia';

export type ThemeMode = 'light' | 'dark' | 'system';

const storageKey = 'ojaflow_theme';
const mediaQuery = '(prefers-color-scheme: dark)';

function getStoredTheme(): ThemeMode {
    const value = localStorage.getItem(storageKey);
    return value === 'light' || value === 'dark' || value === 'system' ? value : 'system';
}

function resolveIsDark(mode: ThemeMode, systemPrefersDark: boolean) {
    return mode === 'dark' || (mode === 'system' && systemPrefersDark);
}

export const useThemeStore = defineStore('theme', {
    state: () => ({
        mode: getStoredTheme(),
        systemPrefersDark: window.matchMedia(mediaQuery).matches,
    }),

    getters: {
        isDark: (state) => resolveIsDark(state.mode, state.systemPrefersDark),
    },

    actions: {
        initialize() {
            const media = window.matchMedia(mediaQuery);
            this.systemPrefersDark = media.matches;
            this.applyTheme();

            media.addEventListener('change', (event) => {
                this.systemPrefersDark = event.matches;
                this.applyTheme();
            });
        },

        setMode(mode: ThemeMode) {
            this.mode = mode;
            localStorage.setItem(storageKey, mode);
            this.applyTheme(mode);
        },

        toggleDarkMode() {
            this.setMode(this.isDark ? 'light' : 'dark');
        },

        applyTheme(mode: ThemeMode = this.mode) {
            const isDark = resolveIsDark(mode, this.systemPrefersDark);
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
        },
    },
});
