<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicStorageServeTest extends TestCase
{
    public function test_banner_file_is_served_without_a_signed_url(): void
    {
        $dir = storage_path('app/public/banners');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $name = 'local-home-slider.jpg';
        $full = $dir.DIRECTORY_SEPARATOR.$name;
        file_put_contents($full, "\xFF\xD8\xFF\xD9");

        try {
            $this->get('/storage/banners/'.$name.'?v=123')
                ->assertOk();
        } finally {
            @unlink($full);
        }
    }

    public function test_missing_storage_file_is_not_found(): void
    {
        $this->get('/storage/banners/does-not-exist-'.uniqid('', true).'.jpg')
            ->assertNotFound();
    }
}
