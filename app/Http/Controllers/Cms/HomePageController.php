<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Layar Home Page lama — sejak 2026-09-29 naskah beranda disunting di
 * Editor Halaman (`/pages/home`), bersama pratinjaunya.
 *
 * Tinggal pengalihan, supaya markah dan tautan lama tidak berujung 404.
 * Datanya disalin ke kunci Editor Halaman oleh migrasi
 * `2026_09_29_120000_move_home_copy_to_page_editor`.
 */
class HomePageController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect('/pages/home');
    }
}
