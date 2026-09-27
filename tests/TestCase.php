<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\EnsuresSafeTestingDatabase;

abstract class TestCase extends BaseTestCase
{
    use EnsuresSafeTestingDatabase;
    //
}
