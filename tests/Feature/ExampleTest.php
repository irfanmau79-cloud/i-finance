<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman akar bukan halaman isi: aplikasi ini selalu masuk lewat
     * /login, jadi "/" hanya mengalihkan ke sana.
     */
    public function test_halaman_akar_mengalihkan_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
