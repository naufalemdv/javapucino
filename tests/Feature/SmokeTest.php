<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_halaman_login_dapat_diakses(): void
    {
        $this->get('/login')->assertStatus(200);
    }
}
