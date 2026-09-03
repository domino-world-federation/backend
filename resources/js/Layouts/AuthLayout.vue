<script setup lang="ts">
/**
 * Kerangka halaman di luar sesi (sekarang: login saja).
 *
 * Tidak ada desain Figma untuk layar ini — kanvas Backoffice mulai dari
 * halaman yang sudah masuk. Jadi dibuat sesederhana mungkin dengan token yang
 * sama, bukan dikarang jadi sesuatu yang nanti harus dibongkar saat desainnya
 * benar-benar datang.
 */
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-shell px-4 py-12">
        <div
            class="pointer-events-none fixed inset-x-0 top-20 bottom-0"
            style="background: linear-gradient(180deg, #101010 0%, #636363 100%)"
            aria-hidden="true"
        />

        <div class="relative z-10 flex w-full max-w-[420px] flex-col gap-8">
            <div class="flex items-center gap-3">
                <!-- `alt` kosong: mereknya sudah tertulis di sebelahnya, jadi
                     gambar ini dekoratif. -->
                <img
                    src="/assets/images/navbar-logo.svg"
                    alt=""
                    width="37"
                    height="40"
                    class="h-12 w-auto shrink-0"
                />
                <span class="text-nav-l-semibold text-white">DWF Backoffice</span>
            </div>

            <slot />
        </div>
    </div>
</template>
