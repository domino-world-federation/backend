import { ref, readonly } from 'vue'

export type Theme = 'light' | 'dark'

const STORAGE_KEY = 'dwf.theme'

/**
 * Nilai awal dibaca dari atribut yang sudah dipasang skrip inline di
 * `app.blade.php`, bukan dihitung ulang. Menghitung ulang di sini berarti dua
 * tempat memutuskan hal yang sama, dan yang kedua selalu telat satu frame.
 */
function current(): Theme {
    return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light'
}

const theme = ref<Theme>(typeof document === 'undefined' ? 'light' : current())

export function useTheme() {
    function set(next: Theme): void {
        theme.value = next
        document.documentElement.dataset.theme = next

        try {
            localStorage.setItem(STORAGE_KEY, next)
        } catch {
            // Safari mode privat melempar saat menulis. Tema tetap berganti
            // untuk sesi ini; hanya pilihannya yang tidak bertahan.
        }
    }

    function toggle(): void {
        set(theme.value === 'dark' ? 'light' : 'dark')
    }

    return { theme: readonly(theme), set, toggle }
}
