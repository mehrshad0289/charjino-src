<?php

namespace Tests;

use App\Modules\Catalog\StorefrontHomeCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        StorefrontHomeCache::reset();
        Cache::flush();
    }
}
